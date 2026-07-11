<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $graphApiVer = $_POST['graph_api_version'] ?? 'v19.0';
    $metaAppId   = $_POST['meta_app_id'] ?? '';
    $apiEnabled  = isset($_POST['marketing_api_enabled']) ? 1 : 0;
    $syncInt     = (int)($_POST['marketing_sync_interval'] ?? 3600);
    $insightsD   = (int)($_POST['marketing_insights_days'] ?? 30);
    $pixelId     = trim($_POST['facebook_pixel_id'] ?? '');

    // Add these columns to tbl_settings if they do not exist
    try {
        $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS meta_app_id VARCHAR(100) DEFAULT NULL");
        $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS marketing_sync_interval INT DEFAULT 3600");
        $pdo->exec("ALTER TABLE tbl_settings ADD COLUMN IF NOT EXISTS marketing_insights_days INT DEFAULT 30");
    } catch(PDOException $e) {
        // columns might exist, continue
    }

    $stmt = $pdo->prepare("UPDATE tbl_settings SET 
        graph_api_version = ?, 
        marketing_api_enabled = ?, 
        meta_app_id = ?, 
        marketing_sync_interval = ?, 
        marketing_insights_days = ?,
        facebook_pixel_id = ?
        WHERE id = 1");
    $stmt->execute([$graphApiVer, $apiEnabled, $metaAppId, $syncInt, $insightsD, $pixelId]);
    
    $msg = "تم حفظ الإعدادات بنجاح.";
}

$stmt = $pdo->query("SELECT * FROM tbl_settings WHERE id = 1 LIMIT 1");
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #475569 0%, #334155 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }

.mc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media (max-width: 900px) { .mc-grid { grid-template-columns: 1fr; } }

.mc-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 24px; }
.mc-card-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px; }
.mc-card-body { padding: 24px; }

.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; }
.form-control:focus { border-color: #1877f2; outline: none; }

.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
input:checked + .slider { background-color: #16a34a; }
input:checked + .slider:before { transform: translateX(20px); }

.alert-msg { padding: 12px 16px; border-radius: 8px; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; margin-bottom: 20px; font-weight: 600; }

.btn-mc-primary { background: #1877f2; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.btn-mc-primary:hover { background: #1467d9; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-cog"></i> إعدادات Marketing Center</h1>
            <p>إدارة تكوينات الربط مع Meta وواجهات برمجة التطبيقات.</p>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert-msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php csrf_field(); ?>
        
        <div class="mc-grid">
            <!-- Section 1: Meta API Settings -->
            <div class="mc-card">
                <div class="mc-card-header">
                    <i class="fa fa-facebook-square" style="color: #1877f2; font-size: 1.2rem;"></i> إعدادات Meta API
                </div>
                <div class="mc-card-body">
                    <div class="form-group" style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <label class="form-label" style="margin-bottom: 0;">تفعيل Marketing API</label>
                            <span style="font-size: 0.8rem; color: #64748b;">تفعيل أو إيقاف كافة ميزات Marketing Center</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="marketing_api_enabled" <?= ($settings['marketing_api_enabled'] ?? 1) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label">إصدار Graph API</label>
                        <select name="graph_api_version" class="form-control">
                            <?php $currVer = $settings['graph_api_version'] ?? 'v19.0'; ?>
                            <option value="v20.0" <?= $currVer === 'v20.0' ? 'selected' : '' ?>>v20.0 (الأحدث)</option>
                            <option value="v19.0" <?= $currVer === 'v19.0' ? 'selected' : '' ?>>v19.0 (مستقر)</option>
                            <option value="v18.0" <?= $currVer === 'v18.0' ? 'selected' : '' ?>>v18.0</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Meta App ID (اختياري)</label>
                        <input type="text" name="meta_app_id" class="form-control" value="<?= htmlspecialchars($settings['meta_app_id'] ?? '') ?>" placeholder="123456789012345">
                    </div>

                    <div class="form-group">
                        <label class="form-label">الفاصل الزمني للمزامنة (ثواني)</label>
                        <input type="number" name="marketing_sync_interval" class="form-control" value="<?= htmlspecialchars($settings['marketing_sync_interval'] ?? 3600) ?>" min="300">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">أيام جلب Insights</label>
                        <input type="number" name="marketing_insights_days" class="form-control" value="<?= htmlspecialchars($settings['marketing_insights_days'] ?? 30) ?>" min="1" max="90">
                    </div>
                </div>
            </div>

            <!-- Section 2: Pixel & Tracking -->
            <div class="mc-card">
                <div class="mc-card-header">
                    <i class="fa fa-crosshairs" style="color: #ef4444; font-size: 1.2rem;"></i> بكسل والتتبع (Pixel & CAPI)
                </div>
                <div class="mc-card-body">
                    <div class="form-group">
                        <label class="form-label">Facebook Pixel ID (الافتراضي)</label>
                        <input type="text" name="facebook_pixel_id" class="form-control" value="<?= htmlspecialchars($settings['facebook_pixel_id'] ?? '') ?>" placeholder="مثال: 1029384756">
                    </div>

                    <?php
                    $pixels = $pdo->query("SELECT * FROM tbl_pixel ORDER BY pixel_network ASC, pixel_name ASC")->fetchAll(PDO::FETCH_ASSOC);
                    $networkColors = ['Facebook' => '#1877f2', 'TikTok' => '#000000', 'Snapchat' => '#FFFC00', 'Google' => '#4285f4'];
                    $networkIcons = ['Facebook' => 'fa-facebook', 'TikTok' => 'fa-music', 'Snapchat' => 'fa-ghost', 'Google' => 'fa-google'];
                    ?>
                    <div style="margin-top: 20px;">
                        <label class="form-label" style="margin-bottom: 12px;">
                            <i class="fa fa-list"></i> البكسلات المُضافة (<?= count($pixels) ?>)
                            <a href="pixel-add.php" style="float:left;font-size:0.8rem;color:#1877f2;text-decoration:none;"><i class="fa fa-plus"></i> إضافة بكسل</a>
                        </label>
                        <?php if (empty($pixels)): ?>
                            <div style="text-align:center;padding:24px;color:#94a3b8;background:#f8fafc;border-radius:10px;border:1px dashed #e2e8f0;">
                                <i class="fa fa-crosshairs" style="font-size:2rem;opacity:0.3;display:block;margin-bottom:8px;"></i>
                                لا توجد بكسلات مضافة بعد.
                                <br><a href="pixel-add.php" style="color:#1877f2;">أضف أول بكسل</a> أو اربط حساب إعلاني من <a href="marketing-accounts.php" style="color:#1877f2;">صفحة الحسابات</a>.
                            </div>
                        <?php else: ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <?php foreach ($pixels as $px): ?>
                                <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">
                                    <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:0.9rem;flex-shrink:0;background:<?= $networkColors[$px['pixel_network']] ?? '#64748b' ?>;">
                                        <i class="fa <?= $networkIcons[$px['pixel_network']] ?? 'fa-tag' ?>"></i>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-weight:700;color:#0f172a;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($px['pixel_name']) ?></div>
                                        <div style="font-size:0.75rem;color:#94a3b8;font-family:monospace;"><?= htmlspecialchars($px['pixel_id']) ?></div>
                                    </div>
                                    <div style="flex-shrink:0;">
                                        <a href="pixel-edit.php?id=<?= $px['id'] ?>" title="تعديل" style="color:#1877f2;font-size:0.85rem;margin-left:6px;"><i class="fa fa-pencil"></i></a>
                                        <a href="pixel-delete.php?id=<?= $px['id'] ?>" title="حذف" style="color:#ef4444;font-size:0.85rem;" onclick="return confirm('حذف هذا البكسل؟')"><i class="fa fa-trash"></i></a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; margin-top: 24px;">
                        <div>
                            <label class="form-label" style="margin-bottom: 0;">Conversions API (CAPI)</label>
                            <span style="font-size: 0.8rem; color: #64748b;">إرسال الأحداث عبر السيرفر</span>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked disabled title="مفعل تلقائياً مع Token">
                            <span class="slider" style="opacity:0.5;"></span>
                        </label>
                    </div>
                    <div style="font-size: 0.8rem; color: #f59e0b; margin-top: 8px;">
                        <i class="fa fa-info-circle"></i> يتم تفعيل CAPI تلقائياً بمجرد ربط حساب الإعلانات (Ad Account) واستخدام التوكن الخاص به.
                    </div>
                </div>
            </div>
        </div>
        
        <div style="text-align: left;">
            <button type="submit" name="save_settings" class="btn-mc-primary">
                <i class="fa fa-save"></i> حفظ الإعدادات
            </button>
        </div>
    </form>
</div>

<?php require_once('footer.php'); ?>
