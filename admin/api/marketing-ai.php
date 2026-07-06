<?php
require_once '../inc/config.php';
require_once '../inc/MarketingAI/Enums/RecommendationStatus.php';

use MarketingAI\Enums\RecommendationStatus;

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$tenantId = $_SESSION['tenant_id'] ?? 1;

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM tbl_marketing_ai_recommendations WHERE tenant_id = ? ORDER BY created_at DESC");
    $stmt->execute([$tenantId]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'approve') {
    $id = $_POST['id'] ?? 0;
    // Update state to APPROVED
    $stmt = $pdo->prepare("UPDATE tbl_marketing_ai_recommendations SET status = ? WHERE id = ? AND tenant_id = ? AND status = ?");
    $stmt->execute([RecommendationStatus::APPROVED, $id, $tenantId, RecommendationStatus::PENDING]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'reject') {
    $id = $_POST['id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE tbl_marketing_ai_recommendations SET status = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([RecommendationStatus::REJECTED, $id, $tenantId]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
