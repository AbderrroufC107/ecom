<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();

$stmt = $pdo->prepare("SELECT facebook_pixel_id FROM tbl_settings WHERE id = 1");
$stmt->execute();
$pixelId = $stmt->fetchColumn();

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }

.pixel-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 24px; margin-bottom: 24px; }
.pixel-status { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 8px 16px; border-radius: 8px; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; margin-bottom: 20px; }
.pixel-status.inactive { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

.event-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.event-table th { padding: 12px; text-align: right; background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; }
.event-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; color: #334155; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-crosshairs"></i> مركز البكسل (Pixel Center)</h1>
            <p>مراقبة أحداث التتبع (Events) وإرسالها إلى Meta</p>
        </div>
        <a href="marketing-settings.php" class="btn btn-default" style="color:#ef4444;border-color:transparent"><i class="fa fa-cog"></i> إعدادات البكسل</a>
    </div>

    <div class="pixel-card">
        <?php if ($pixelId): ?>
            <div class="pixel-status"><i class="fa fa-check-circle"></i> البكسل نشط (<?= htmlspecialchars($pixelId) ?>)</div>
            <p style="color: #64748b; margin-bottom: 24px;">يتم إرسال الأحداث التالية تلقائياً من خلال متجرك.</p>
            
            <table class="event-table">
                <thead>
                    <tr>
                        <th>الحدث (Event)</th>
                        <th>النوع</th>
                        <th>الوصف</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>PageView</strong></td>
                        <td><span style="color: #1877f2">قياسي</span></td>
                        <td>يُرسل عند زيارة أي صفحة في المتجر.</td>
                    </tr>
                    <tr>
                        <td><strong>ViewContent</strong></td>
                        <td><span style="color: #1877f2">قياسي</span></td>
                        <td>يُرسل عند مشاهدة صفحة منتج معين (مع بيانات المنتج).</td>
                    </tr>
                    <tr>
                        <td><strong>AddToCart</strong></td>
                        <td><span style="color: #1877f2">قياسي</span></td>
                        <td>يُرسل عند إضافة منتج إلى سلة التسوق.</td>
                    </tr>
                    <tr>
                        <td><strong>InitiateCheckout</strong></td>
                        <td><span style="color: #1877f2">قياسي</span></td>
                        <td>يُرسل عند بدء عملية الدفع.</td>
                    </tr>
                    <tr>
                        <td><strong>Purchase</strong></td>
                        <td><span style="color: #16a34a">CAPI & Browser</span></td>
                        <td>يُرسل عند إتمام الطلب (مع قيمة الطلب والعملة).</td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <div class="pixel-status inactive"><i class="fa fa-times-circle"></i> البكسل غير مفعل</div>
            <p style="color: #64748b;">لم يتم تعيين Facebook Pixel ID. يرجى الذهاب إلى الإعدادات لإضافته.</p>
            <a href="marketing-settings.php" class="btn btn-primary">الذهاب إلى الإعدادات</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once('footer.php'); ?>
