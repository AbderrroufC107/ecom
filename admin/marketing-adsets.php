<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();
$campaignId = (int)($_GET['campaign_id'] ?? 0);

$sql = "SELECT a.*, c.name as campaign_name FROM tbl_meta_ad_sets a JOIN tbl_meta_campaigns c ON c.id = a.campaign_id WHERE a.tenant_id = ?";
$params = [$tenantId];

if ($campaignId) {
    $sql .= " AND a.campaign_id = ?";
    $params[] = $campaignId;
}
$sql .= " ORDER BY a.updated_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$adsets = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #1877f2 0%, #0d5dbf 50%, #7c3aed 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }

.mc-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-size: 0.9rem; }
.mc-table th { background: #f8fafc; padding: 14px 16px; text-align: right; color: #475569; font-weight: 600; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }
.mc-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.mc-table tr:hover td { background: #f8fafc; }
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
.status-ACTIVE { background: #dcfce7; color: #16a34a; }
.status-PAUSED { background: #fef3c7; color: #d97706; }
.action-btn { background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 1.1rem; }
.action-btn:hover { color: #1877f2; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-object-group"></i> المجموعات الإعلانية (Ad Sets)</h1>
            <p>إدارة الميزانية والاستهداف والجداول الزمنية</p>
        </div>
        <?php if ($campaignId): ?>
            <a href="marketing-campaigns.php" style="color:white; text-decoration:underline;">العودة للحملات</a>
        <?php endif; ?>
    </div>

    <?php if (empty($adsets)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; color: #64748b;">
            لا توجد مجموعات إعلانية.
        </div>
    <?php else: ?>
        <table class="mc-table">
            <thead>
                <tr>
                    <th>المجموعة الإعلانية</th>
                    <th>الحملة</th>
                    <th>الحالة</th>
                    <th>الميزانية (يومية)</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($adsets as $a): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($a['name']) ?></strong><br>
                            <small style="color: #94a3b8;"><?= $a['meta_adset_id'] ?></small>
                        </td>
                        <td><?= htmlspecialchars($a['campaign_name']) ?></td>
                        <td>
                            <span class="status-badge status-<?= $a['status'] ?>">
                                <?= $a['status'] ?>
                            </span>
                        </td>
                        <td><?= $a['budget_daily'] ? number_format($a['budget_daily'], 2) . ' دج' : '-' ?></td>
                        <td>
                            <button class="action-btn" onclick="toggleStatus('adset', '<?= $a['meta_adset_id'] ?>', '<?= $a['status'] === 'ACTIVE' ? 'PAUSED' : 'ACTIVE' ?>')">
                                <i class="fa fa-<?= $a['status'] === 'ACTIVE' ? 'pause-circle' : 'play-circle' ?>" style="color: <?= $a['status'] === 'ACTIVE' ? '#d97706' : '#16a34a' ?>"></i>
                            </button>
                            <a href="marketing-ads.php?adset_id=<?= $a['id'] ?>" class="action-btn" title="الإعلانات" style="margin-right: 8px;"><i class="fa fa-image"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<script>
function toggleStatus(type, metaId, newStatus) {
    if (!confirm('تأكيد تغيير الحالة؟')) return;
    const fd = new URLSearchParams(); fd.append('action', 'toggle'); fd.append('entity_type', type); fd.append('meta_id', metaId); fd.append('status', newStatus);
    fetch('api/marketing-sync.php', { method: 'POST', body: fd.toString(), headers: {'Content-Type': 'application/x-www-form-urlencoded'} })
    .then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.error); });
}
</script>
<?php require_once('footer.php'); ?>
