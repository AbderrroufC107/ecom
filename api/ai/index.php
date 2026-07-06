<?php
// API Router for AI Endpoints
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Ensure the config path is correct
$config_path = __DIR__ . '/../../admin/inc/config.php';
if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'System configuration not found']);
    exit;
}
require_once $config_path;

// --- Authentication ---
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
$api_key = '';

if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $api_key = $matches[1];
}

if (empty($api_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Missing API Key']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tbl_api_key WHERE api_key = ? AND is_active = 1");
$stmt->execute([$api_key]);
$api_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$api_user) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Invalid API Key']);
    exit;
}

// Update last used
$pdo->prepare("UPDATE tbl_api_key SET last_used_at = NOW() WHERE id = ?")->execute([$api_user['id']]);

// --- Routing ---
$request = isset($_GET['request']) ? trim($_GET['request'], '/') : '';
$method = $_SERVER['REQUEST_METHOD'];
$parts = explode('/', $request);


if ($parts[0] === 'knowledge') {
    require_once __DIR__ . '/knowledge.php';
    exit;
}

if (empty($parts[0]) || $parts[0] !== 'products') {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    exit;
}

// Helper: Fetch Full AI Product Object
function getFullProduct($pdo, $p_id) {
    $stmt = $pdo->prepare("
        SELECT p.p_id, p.p_name, p.p_sku, p.p_current_price, p.p_qty,
               a.ai_version, a.selling_title, a.short_pitch, a.long_pitch, a.cta, 
               a.negotiable, a.lowest_price, a.max_discount_pct, a.discount_conditions,
               a.created_at, a.updated_at
        FROM tbl_product p
        LEFT JOIN tbl_ai_product a ON p.p_id = a.p_id
        WHERE p.p_id = ?
    ");
    $stmt->execute([$p_id]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prod) return null;

    // Fetch Keywords
    $stmt = $pdo->prepare("SELECT keyword, is_synonym FROM tbl_ai_keyword WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $prod['keywords'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch FAQs
    $stmt = $pdo->prepare("SELECT question, answer FROM tbl_ai_faq WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $prod['faqs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Objections
    $stmt = $pdo->prepare("SELECT objection, best_reply, priority FROM tbl_ai_objection WHERE p_id = ? ORDER BY priority DESC");
    $stmt->execute([$p_id]);
    $prod['objections'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Training
    $stmt = $pdo->prepare("SELECT topic, training_reply FROM tbl_ai_training WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $prod['training'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Campaigns
    $stmt = $pdo->prepare("SELECT platform, campaign_id, ad_id, post_id, story_id, reel_id FROM tbl_ai_campaign WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $prod['campaigns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Media
    $stmt = $pdo->prepare("SELECT media_type, media_url FROM tbl_ai_media WHERE p_id = ?");
    $stmt->execute([$p_id]);
    $prod['media'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $prod;
}

try {
    if ($method === 'GET') {
        if (count($parts) === 1) {
            // GET /api/ai/products (List all AI enabled products)
            $stmt = $pdo->query("SELECT p_id FROM tbl_ai_product LIMIT 100"); // Add pagination in prod
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = getFullProduct($pdo, $row['p_id']);
            }
            echo json_encode(['data' => $results]);
        } elseif (count($parts) === 2 && is_numeric($parts[1])) {
            // GET /api/ai/products/{id}
            $product = getFullProduct($pdo, (int)$parts[1]);
            if ($product) {
                echo json_encode(['data' => $product]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            }
        } elseif (count($parts) === 3 && $parts[1] === 'by-campaign') {
            // GET /api/ai/products/by-campaign/{campaignId}
            $stmt = $pdo->prepare("SELECT p_id FROM tbl_ai_campaign WHERE campaign_id = ? LIMIT 50");
            $stmt->execute([$parts[2]]);
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = getFullProduct($pdo, $row['p_id']);
            }
            echo json_encode(['data' => $results]);
        } elseif (count($parts) === 3 && $parts[1] === 'by-post') {
            // GET /api/ai/products/by-post/{postId}
            $stmt = $pdo->prepare("SELECT p_id FROM tbl_ai_campaign WHERE post_id = ? LIMIT 50");
            $stmt->execute([$parts[2]]);
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = getFullProduct($pdo, $row['p_id']);
            }
            echo json_encode(['data' => $results]);
        } elseif (count($parts) === 3 && $parts[1] === 'by-keyword') {
            // GET /api/ai/products/by-keyword/{keyword}
            $stmt = $pdo->prepare("SELECT DISTINCT p_id FROM tbl_ai_keyword WHERE keyword LIKE ? LIMIT 50");
            $stmt->execute(['%' . $parts[2] . '%']);
            $results = [];
            while ($row = $stmt->fetch()) {
                $results[] = getFullProduct($pdo, $row['p_id']);
            }
            echo json_encode(['data' => $results]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid GET route']);
        }
    } elseif ($method === 'PUT') {
        if (count($parts) === 2 && is_numeric($parts[1])) {
            // PUT /api/ai/products/{id} (Update from n8n)
            $p_id = (int)$parts[1];
            $body = json_decode(file_get_contents('php://input'), true);
            
            if (!$body) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON payload']);
                exit;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id FROM tbl_ai_product WHERE p_id = ?");
            $stmt->execute([$p_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE tbl_ai_product SET 
                    ai_version = ai_version + 1,
                    selling_title = COALESCE(?, selling_title),
                    short_pitch = COALESCE(?, short_pitch),
                    long_pitch = COALESCE(?, long_pitch),
                    cta = COALESCE(?, cta)
                    WHERE p_id = ?");
                $stmt->execute([
                    $body['selling_title'] ?? null,
                    $body['short_pitch'] ?? null,
                    $body['long_pitch'] ?? null,
                    $body['cta'] ?? null,
                    $p_id
                ]);
            } else {
                // Initial Insert if it doesn't exist
                $stmt = $pdo->prepare("INSERT INTO tbl_ai_product (p_id, selling_title, short_pitch, long_pitch, cta) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $p_id,
                    $body['selling_title'] ?? '',
                    $body['short_pitch'] ?? '',
                    $body['long_pitch'] ?? '',
                    $body['cta'] ?? ''
                ]);
            }

            // Sync Keywords if provided
            if (isset($body['keywords']) && is_array($body['keywords'])) {
                $pdo->prepare("DELETE FROM tbl_ai_keyword WHERE p_id = ?")->execute([$p_id]);
                $stmt = $pdo->prepare("INSERT INTO tbl_ai_keyword (p_id, keyword, is_synonym) VALUES (?, ?, ?)");
                foreach ($body['keywords'] as $kw) {
                    $stmt->execute([$p_id, $kw['keyword'] ?? '', $kw['is_synonym'] ?? 0]);
                }
            }

            // Sync FAQs if provided
            if (isset($body['faqs']) && is_array($body['faqs'])) {
                $pdo->prepare("DELETE FROM tbl_ai_faq WHERE p_id = ?")->execute([$p_id]);
                $stmt = $pdo->prepare("INSERT INTO tbl_ai_faq (p_id, question, answer) VALUES (?, ?, ?)");
                foreach ($body['faqs'] as $faq) {
                    $stmt->execute([$p_id, $faq['question'] ?? '', $faq['answer'] ?? '']);
                }
            }

            $pdo->commit();
            echo json_encode(['status' => 'success', 'data' => getFullProduct($pdo, $p_id)]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid PUT route']);
        }
    } elseif ($method === 'POST') {
        if (count($parts) === 2 && $parts[1] === 'sync') {
            // POST /api/ai/products/sync (Batch sync)
            echo json_encode(['status' => 'success', 'message' => 'Sync endpoint active (to be implemented fully)']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid POST route']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
}
