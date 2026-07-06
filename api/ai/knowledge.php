<?php
require_once __DIR__ . '/../../admin/inc/config.php';

// Auth checks should be done in index.php before requiring this file
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$pathParts = explode('/', isset($_GET['request']) ? trim($_GET['request'], '/') : '');
// Expected format: knowledge, knowledge/123, knowledge/search

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($method === 'GET') {
        if (count($pathParts) === 2 && $pathParts[1] === 'search') {
            // GET /api/ai/knowledge/search?q=foo&category=bar&tags=baz&page=1
            $q = $_GET['q'] ?? '';
            $category = $_GET['category'] ?? '';
            $limit = (int)($_GET['limit'] ?? 50);
            $page = (int)($_GET['page'] ?? 1);
            $offset = ($page - 1) * $limit;

            $sql = "SELECT k.*, c.name as category_name 
                    FROM tbl_ai_knowledge k 
                    LEFT JOIN tbl_ai_knowledge_categories c ON k.category_id = c.id 
                    WHERE k.is_active = 1";
            $params = [];

            if ($q !== '') {
                // Using FULLTEXT search if possible, else fallback to LIKE
                $sql .= " AND (MATCH(k.title, k.content) AGAINST(? IN BOOLEAN MODE) OR k.title LIKE ?)";
                $params[] = $q . '*';
                $params[] = "%{$q}%";
            }

            if ($category !== '') {
                $sql .= " AND c.slug = ?";
                $params[] = $category;
            }

            $sql .= " ORDER BY k.priority DESC LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            respond(['data' => $results, 'page' => $page]);

        } elseif (count($pathParts) === 2 && is_numeric($pathParts[1])) {
            // GET /api/ai/knowledge/{id}
            $id = (int)$pathParts[1];
            $stmt = $pdo->prepare("SELECT * FROM tbl_ai_knowledge WHERE id = ?");
            $stmt->execute([$id]);
            $knowledge = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($knowledge) {
                // Fetch tags
                $stmtT = $pdo->prepare("SELECT t.name, t.slug FROM tbl_ai_tags t JOIN tbl_ai_knowledge_tags kt ON t.id = kt.tag_id WHERE kt.knowledge_id = ?");
                $stmtT->execute([$id]);
                $knowledge['tags'] = $stmtT->fetchAll(PDO::FETCH_ASSOC);
                respond(['data' => $knowledge]);
            } else {
                respond(['error' => 'Not found'], 404);
            }
        } else {
            // GET /api/ai/knowledge
            $limit = (int)($_GET['limit'] ?? 50);
            $page = (int)($_GET['page'] ?? 1);
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("SELECT * FROM tbl_ai_knowledge ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute();
            respond(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'page' => $page]);
        }
    } elseif ($method === 'POST') {
        // Create new knowledge item
        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body || empty($body['title']) || empty($body['content'])) {
            respond(['error' => 'Invalid payload. Title and content required.'], 400);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO tbl_ai_knowledge (title, category_id, knowledge_type, language, content, priority, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $body['title'],
            $body['category_id'] ?? null,
            $body['knowledge_type'] ?? 'Policy',
            $body['language'] ?? 'ar',
            $body['content'],
            $body['priority'] ?? 0,
            $body['is_active'] ?? 1
        ]);
        $id = $pdo->lastInsertId();

        // History
        $stmtH = $pdo->prepare("INSERT INTO tbl_ai_knowledge_history (knowledge_id, content, version, created_by) VALUES (?, ?, 1, ?)");
        $stmtH->execute([$id, $body['content'], $_SESSION['user']['id'] ?? null]);

        $pdo->commit();
        respond(['status' => 'success', 'id' => $id], 201);

    } elseif ($method === 'PUT') {
        if (count($pathParts) !== 2 || !is_numeric($pathParts[1])) {
            respond(['error' => 'Invalid ID'], 400);
        }
        $id = (int)$pathParts[1];
        $body = json_decode(file_get_contents('php://input'), true);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT version FROM tbl_ai_knowledge WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            $pdo->rollBack();
            respond(['error' => 'Not found'], 404);
        }

        $newVersion = $current['version'] + 1;
        $stmt = $pdo->prepare("
            UPDATE tbl_ai_knowledge SET 
                title = COALESCE(?, title),
                content = COALESCE(?, content),
                version = ?,
                priority = COALESCE(?, priority),
                is_active = COALESCE(?, is_active)
            WHERE id = ?
        ");
        $stmt->execute([
            $body['title'] ?? null,
            $body['content'] ?? null,
            $newVersion,
            $body['priority'] ?? null,
            $body['is_active'] ?? null,
            $id
        ]);

        if (isset($body['content'])) {
            $stmtH = $pdo->prepare("INSERT INTO tbl_ai_knowledge_history (knowledge_id, content, version, created_by) VALUES (?, ?, ?, ?)");
            $stmtH->execute([$id, $body['content'], $newVersion, $_SESSION['user']['id'] ?? null]);
        }

        $pdo->commit();
        respond(['status' => 'success', 'version' => $newVersion]);

    } elseif ($method === 'DELETE') {
        if (count($pathParts) !== 2 || !is_numeric($pathParts[1])) {
            respond(['error' => 'Invalid ID'], 400);
        }
        $id = (int)$pathParts[1];
        $stmt = $pdo->prepare("DELETE FROM tbl_ai_knowledge WHERE id = ?");
        $stmt->execute([$id]);
        respond(['status' => 'success']);
    } else {
        respond(['error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    respond(['error' => 'Internal server error', 'message' => $e->getMessage()], 500);
}
