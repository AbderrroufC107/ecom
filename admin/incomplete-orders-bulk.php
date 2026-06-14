<?php require_once('header.php'); ?>
<?php
require_once __DIR__ . '/inc/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? $_POST['ids'] : [];
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if (!empty($ids) && $action === 'delete') {
        $ids = array_map('intval', $ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM incomplete_orders WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
}

header('Location: incomplete-orders.php');
exit;
?>


