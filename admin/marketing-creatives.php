<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();

$stmt = $pdo->prepare("
    SELECT c.*, a.account_name 
    FROM tbl_meta_creatives c 
    LEFT JOIN tbl_meta_ad_accounts a ON a.id = c.ad_account_id 
    WHERE c.tenant_id = ? 
    ORDER BY c.created_at DESC LIMIT 50
");
$stmt->execute([$tenantId]);
$creatives = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }
.btn-mc { padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s; }
.btn-mc-white { background: white; color: #059669; }
.btn-mc-white:hover { background: #ecfdf5; color: #059669; }
.btn-mc-ai { background: linear-gradient(90deg, #7c3aed, #ec4899); color: white; border: none; }
.btn-mc-ai:hover { opacity: 0.9; color: white; }

.creatives-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.creative-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; }
.creative-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
.creative-img { height: 160px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
.creative-img img { width: 100%; height: 100%; object-fit: cover; }
.creative-img i { font-size: 3rem; color: #cbd5e1; }
.creative-body { padding: 16px; flex: 1; display: flex; flex-direction: column; }
.creative-title { font-weight: 700; color: #0f172a; font-size: 0.95rem; margin-bottom: 6px; }
.creative-type { display: inline-block; padding: 3px 8px; background: #e2e8f0; color: #475569; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-bottom: 12px; }
.creative-text { font-size: 0.85rem; color: #475569; margin-bottom: auto; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.creative-footer { border-top: 1px solid #f1f5f9; padding-top: 12px; margin-top: 16px; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; color: #94a3b8; }

</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-paint-brush"></i> استوديو التصميم (Creative Studio)</h1>
            <p>إدارة الصور والنصوص الإعلانية بمساعدة الذكاء الاصطناعي</p>
        </div>
        <div style="display: flex; gap: 12px;">
            <button class="btn-mc btn-mc-ai" onclick="alert('جاري التطوير: سيتم توليد نصوص الإعلانات باستخدام AI.')">
                <i class="fa fa-magic"></i> AI Copywriter
            </button>
            <button class="btn-mc btn-mc-white">
                <i class="fa fa-upload"></i> رفع تصميم
            </button>
        </div>
    </div>

    <?php if (empty($creatives)): ?>
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; border: 1px solid #e2e8f0;">
            <i class="fa fa-image" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 16px;"></i>
            <h3 style="margin-bottom: 8px; font-weight: 700;">لا توجد تصاميم حتى الآن</h3>
            <p style="color: #64748b; margin-bottom: 24px;">قم برفع الصور ومقاطع الفيديو، أو استخدم الذكاء الاصطناعي لكتابة الإعلانات.</p>
        </div>
    <?php else: ?>
        <div class="creatives-grid">
            <?php foreach ($creatives as $c): ?>
                <?php 
                $data = json_decode($c['meta_json'] ?? '{}', true); 
                $imgUrl = $data['image_url'] ?? $data['thumbnail_url'] ?? '';
                $bodyText = $data['object_story_spec']['link_data']['message'] ?? $data['body'] ?? 'بدون نص إعلاني';
                ?>
                <div class="creative-card">
                    <div class="creative-img">
                        <?php if ($imgUrl): ?>
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Creative">
                        <?php else: ?>
                            <i class="fa fa-image"></i>
                        <?php endif; ?>
                    </div>
                    <div class="creative-body">
                        <div class="creative-title"><?= htmlspecialchars($c['name'] ?? 'Creative') ?></div>
                        <div><span class="creative-type"><?= $c['status'] ?></span></div>
                        <div class="creative-text"><?= htmlspecialchars($bodyText) ?></div>
                        <div class="creative-footer">
                            <span><?= date('d/m/Y', strtotime($c['created_at'])) ?></span>
                            <span title="<?= htmlspecialchars($c['meta_creative_id']) ?>"><i class="fa fa-barcode"></i> معرف الإعلان</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
