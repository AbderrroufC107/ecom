<?php
/**
 * marketing-sync.php - AJAX endpoint for bidirectional Meta sync
 * Called by marketing-center.php and other pages.
 */
require_once('../inc/config.php');
require_once('../inc/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$tenantId = \SaaS\TenantContext::getTenantId();
$action   = $_GET['action'] ?? $_POST['action'] ?? 'full';

try {
    // Get the primary active ad account
    $stmt = $pdo->prepare(
        "SELECT id, account_id FROM tbl_meta_ad_accounts WHERE tenant_id = ? AND status = 'ACTIVE' AND is_deleted = 0 LIMIT 1"
    );
    $stmt->execute([$tenantId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo json_encode(['success' => false, 'error' => 'لا يوجد حساب إعلاني مرتبط. يرجى الإعداد أولاً.']);
        exit;
    }

    $engine = new \Marketing\SyncEngine($pdo, $account['account_id'], $tenantId);
    $result = [];

    switch ($action) {
        case 'full':
            $result = $engine->fullSync($account['id']);
            break;

        case 'campaigns':
            $result = $engine->importCampaigns($account['id']);
            break;

        case 'insights':
            // Sync insights for all campaigns
            $stmt2 = $pdo->prepare(
                "SELECT id, meta_campaign_id FROM tbl_meta_campaigns WHERE tenant_id = ? AND is_deleted = 0 AND status = 'ACTIVE' LIMIT 20"
            );
            $stmt2->execute([$tenantId]);
            $campaigns = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $total = 0;
            foreach ($campaigns as $c) {
                $r = $engine->importInsights($c['meta_campaign_id'], $c['id'], 30);
                $total += $r['synced'];
            }
            $result = ['synced' => $total];
            break;

        case 'leads':
            // Lead sync is handled via webhook - return status
            $result = ['message' => 'Leads يصلون عبر Webhook تلقائياً عند إرسال النماذج.'];
            break;

        case 'toggle':
            $entityType = $_POST['entity_type'] ?? 'campaign';
            $metaId     = $_POST['meta_id'] ?? '';
            $newStatus  = $_POST['status'] ?? 'PAUSED';
            $result     = $engine->toggleStatus($entityType, $metaId, $newStatus);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
            exit;
    }

    echo json_encode(['success' => true, 'result' => $result]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
