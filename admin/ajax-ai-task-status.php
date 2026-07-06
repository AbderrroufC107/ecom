<?php
require_once 'inc/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$task_id = (int)($_GET['task_id'] ?? 0);

if (!$task_id) {
    echo json_encode(['status' => 'error', 'message' => 'رقم المهمة غير صالح']);
    exit;
}

try {
    $stmt = $dbRepo->prepare("SELECT status, result, error_message, retries FROM tbl_ai_tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        echo json_encode(['status' => 'error', 'message' => 'المهمة غير موجودة']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'task_status' => $task['status'],
        'result' => $task['result'] ? json_decode($task['result'], true) : null,
        'error_message' => $task['error_message'],
        'retries' => $task['retries']
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
