<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();
$status = $_GET['status'] ?? 'ALL';

$sql = "SELECT c.*, a.account_name FROM tbl_meta_campaigns c LEFT JOIN tbl_meta_ad_accounts a ON a.id = c.ad_account_id WHERE c.tenant_id = ? AND c.is_deleted = 0";
$params = [$tenantId];
if ($status !== 'ALL') {
    $sql .= " AND c.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY c.updated_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #1877f2 0%, #0d5dbf 50%, #7c3aed 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }
.btn-mc { padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s; }
.btn-mc-white { background: white; color: #1877f2; }
.btn-mc-white:hover { background: #f0f4ff; color: #1877f2; }
.mc-quick-nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.mc-nav-item { padding: 8px 16px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.85rem; font-weight: 600; color: #475569; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
.mc-nav-item:hover, .mc-nav-item.active { background: #1877f2; color: white; border-color: #1877f2; }
.filter-bar { background: white; padding: 16px 20px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; gap: 16px; align-items: center; }
.filter-bar select, .filter-bar input { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; outline: none; }
.filter-bar select:focus, .filter-bar input:focus { border-color: #1877f2; }
.mc-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-size: 0.9rem; }
.mc-table th { background: #f8fafc; padding: 14px 16px; text-align: right; color: #475569; font-weight: 600; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }
.mc-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.mc-table tr:hover td { background: #f8fafc; }
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
.status-ACTIVE { background: #dcfce7; color: #16a34a; }
.status-PAUSED { background: #fef3c7; color: #d97706; }
.status-DELETED { background: #fee2e2; color: #dc2626; }
.action-btn { background: none; border: none; color: #94a3b8; cursor: pointer; transition: color 0.2s; font-size: 1.1rem; }
.action-btn:hover { color: #1877f2; }
.empty-state { text-align: center; padding: 40px 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; }
.empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; }
.empty-state p { color: #64748b; margin-bottom: 20px; font-size: 1rem; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-bullhorn"></i> الحملات (Campaigns)</h1>
            <p>إدارة حملاتك الإعلانية ومتابعة أدائها</p>
        </div>
        <a href="marketing-campaign-wizard.php" class="btn-mc btn-mc-white">
            <i class="fa fa-plus"></i> حملة جديدة
        </a>
    </div>

    <!-- Quick Nav -->
    <div class="mc-quick-nav">
        <a href="marketing-center.php" class="mc-nav-item">
            <i class="fa fa-dashboard"></i> Dashboard
        </a>
        <a href="marketing-campaigns.php" class="mc-nav-item active">
            <i class="fa fa-bullhorn"></i> الحملات
        </a>
        <a href="marketing-adsets.php" class="mc-nav-item">
            <i class="fa fa-object-group"></i> Ad Sets
        </a>
        <a href="marketing-ads.php" class="mc-nav-item">
            <i class="fa fa-image"></i> الإعلانات
        </a>
    </div>

    <div class="filter-bar">
        <strong>فلترة:</strong>
        <form method="GET" style="display: flex; gap: 10px; align-items: center; margin: 0;">
            <select name="status" onchange="this.form.submit()">
                <option value="ALL" <?= $status === 'ALL' ? 'selected' : '' ?>>الكل (All)</option>
                <option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>نشط (Active)</option>
                <option value="PAUSED" <?= $status === 'PAUSED' ? 'selected' : '' ?>>متوقف (Paused)</option>
            </select>
            <button type="button" class="btn-mc btn-mc-white" style="border: 1px solid #cbd5e1; padding: 7px 12px; color: #475569;" onclick="syncCampaigns()">
                <i class="fa fa-refresh"></i> مزامنة الآن
            </button>
        </form>
        <span id="sync-msg" style="color: #16a34a; font-weight: 600; display: none; margin-right: auto; font-size: 0.85rem;">جاري المزامنة...</span>
    </div>

    <?php if (empty($campaigns)): ?>
        <div class="empty-state">
            <i class="fa fa-bullhorn"></i>
            <p>لا توجد حملات إعلانية تطابق بحثك أو لم يتم إنشاء أي حملة بعد.</p>
            <a href="marketing-campaign-wizard.php" class="btn-mc btn-mc-white" style="background: #1877f2; color: white;">
                إنشاء حملة الآن
            </a>
        </div>
    <?php else: ?>
        <table class="mc-table">
            <thead>
                <tr>
                    <th>الحملة</th>
                    <th>الهدف</th>
                    <th>الحالة</th>
                    <th>الميزانية (يومية)</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($campaigns as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['name']) ?></strong><br>
                            <small style="color: #94a3b8;"><?= $c['meta_campaign_id'] ?> | <?= htmlspecialchars($c['account_name'] ?? '') ?></small>
                        </td>
                        <td><?= htmlspecialchars($c['objective'] ?? '-') ?></td>
                        <td>
                            <span class="status-badge status-<?= $c['status'] ?>" id="status-<?= $c['id'] ?>">
                                <?= $c['status'] ?>
                            </span>
                        </td>
                        <td><?= $c['budget_daily'] ? number_format($c['budget_daily'], 2) . ' دج' : '-' ?></td>
                        <td>
                            <button class="action-btn" title="تغيير الحالة" onclick="toggleStatus('campaign', '<?= $c['meta_campaign_id'] ?>', '<?= $c['status'] === 'ACTIVE' ? 'PAUSED' : 'ACTIVE' ?>', <?= $c['id'] ?>)">
                                <i class="fa fa-<?= $c['status'] === 'ACTIVE' ? 'pause-circle' : 'play-circle' ?>" style="color: <?= $c['status'] === 'ACTIVE' ? '#d97706' : '#16a34a' ?>"></i>
                            </button>
                            <a href="marketing-adsets.php?campaign_id=<?= $c['id'] ?>" class="action-btn" title="عرض Ad Sets" style="margin-right: 8px;">
                                <i class="fa fa-folder-open"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function toggleStatus(type, metaId, newStatus, rowId) {
    if (!confirm('هل تريد تغيير حالة هذه الحملة إلى ' + newStatus + '؟')) return;
    
    const formData = new URLSearchParams();
    formData.append('action', 'toggle');
    formData.append('entity_type', type);
    formData.append('meta_id', metaId);
    formData.append('status', newStatus);

    fetch('api/marketing-sync.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('خطأ: ' + data.error);
        }
    })
    .catch(e => alert('خطأ في الاتصال.'));
}

function syncCampaigns() {
    const msg = document.getElementById('sync-msg');
    msg.style.display = 'block';
    
    fetch('api/marketing-sync.php?action=campaigns')
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            msg.innerText = 'تمت المزامنة بنجاح!';
            setTimeout(() => location.reload(), 1000);
        } else {
            msg.innerText = 'خطأ: ' + data.error;
            msg.style.color = '#dc2626';
        }
    })
    .catch(e => {
        msg.innerText = 'خطأ في الاتصال.';
        msg.style.color = '#dc2626';
    });
}
</script>
<?php require_once('footer.php'); ?>
