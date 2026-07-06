<?php
require_once 'inc/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$entity_id = (int)($_POST['entity_id'] ?? 0);
$entity_type = $_POST['entity_type'] ?? 'product';
$task_type = $_POST['task_type'] ?? 'product_generation';
$language = $_POST['language'] ?? 'ar';
$priority = $_POST['priority'] ?? 'NORMAL';

// Tasks requested array (e.g., ['keywords', 'faqs', 'objections'])
$tasks_requested = isset($_POST['tasks']) && is_array($_POST['tasks']) ? $_POST['tasks'] : [];

if (!$entity_id || empty($tasks_requested)) {
    echo json_encode(['status' => 'error', 'message' => 'بيانات غير مكتملة']);
    exit;
}

try {
    // Collect the necessary payload for the AI based on entity_type
    $payload = [];
    if ($entity_type === 'product') {
        $stmt = $dbRepo->prepare("SELECT p_name, p_description, p_current_price, p_short_description FROM tbl_product WHERE p_id = ?");
        $stmt->execute([$entity_id]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($prod) {
            $payload = [
                'name' => $prod['p_name'],
                'description' => strip_tags($prod['p_description']),
                'short_description' => strip_tags($prod['p_short_description']),
                'price' => $prod['p_current_price'],
                'tasks_requested' => $tasks_requested
            ];
        }
    }

    $stmt = $dbRepo->prepare("
        INSERT INTO tbl_ai_tasks 
        (task_type, entity_type, entity_id, language, priority, status, payload, created_by)
        VALUES (?, ?, ?, ?, ?, 'PENDING', ?, ?)
    ");
    
    // Assume user ID is 1 for now if no session
    $user_id = $_SESSION['user']['id'] ?? 1;

    $stmt->execute([
        $task_type,
        $entity_type,
        $entity_id,
        $language,
        $priority,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
        $user_id
    ]);

    $task_id = $dbRepo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'task_id' => $task_id,
        'message' => 'تمت إضافة المهمة إلى طابور المعالجة.'
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
