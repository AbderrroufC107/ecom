<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();

$stmt = $pdo->prepare("SELECT * FROM tbl_meta_ab_tests WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$tenantId]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }

.mc-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-size: 0.9rem; }
.mc-table th { background: #f8fafc; padding: 14px 16px; text-align: right; color: #475569; font-weight: 600; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }
.mc-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.mc-table tr:hover td { background: #f8fafc; }
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
.status-ACTIVE { background: #dbeafe; color: #1e3a8a; }
.status-COMPLETED { background: #dcfce7; color: #16a34a; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-flask"></i> اختبارات A/B (A/B Testing)</h1>
            <p>مقارنة أداء الإعلانات واختيار الأفضل</p>
        </div>
        <button class="btn btn-default" onclick="alert('جاري التطوير: سيتم إطلاق ميزة الـ A/B Testing قريباً.')"><i class="fa fa-plus"></i> اختبار جديد</button>
    </div>

    <?php if (empty($tests)): ?>
        <div style="text-align: center; padding: 40px; background: white; border-radius: 12px; border: 1px solid #e2e8f0; color: #64748b;">
            <i class="fa fa-flask" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 16px;"></i><br>
            لا توجد اختبارات A/B نشطة حالياً.
        </div>
    <?php else: ?>
        <table class="mc-table">
            <thead>
                <tr>
                    <th>اسم الاختبار</th>
                    <th>الحالة</th>
                    <th>الفائز</th>
                    <th>تاريخ الإنشاء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tests as $t): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($t['name']) ?></strong><br>
                            <small style="color: #94a3b8;"><?= $t['meta_test_id'] ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $t['status'] ?>">
                                <?= $t['status'] ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($t['winner_entity_id'] ?? 'في الانتظار') ?></td>
                        <td><?= date('Y-m-d', strtotime($t['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
