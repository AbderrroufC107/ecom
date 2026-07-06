<?php
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'inc/Security/SecretManager.php';

use Security\SecretManager;

$sm = new SecretManager($pdo);
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'save_meta') {
        $verifyToken = trim($_POST['meta_verify_token']);
        $appSecret = trim($_POST['meta_app_secret']);

        if ($verifyToken) {
            $sm->setSecret('meta_verify_token', 'meta', $verifyToken);
        }
        if ($appSecret && $appSecret !== '••••••••••••••••') {
            $sm->setSecret('meta_app_secret', 'meta', $appSecret);
        }
        $success = 'تم حفظ إعدادات Meta بنجاح.';
    }
}

$hasVerifyToken = $sm->getSecret('meta_verify_token') !== null;
$hasAppSecret = $sm->getSecret('meta_app_secret') !== null;
$webhookUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/ecom/api/meta_webhook.php';

require_once 'header.php';
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>⚙️ إعدادات OmniChannel</h1>
    </div>
</section>

<section class="content">
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fa fa-check"></i> <?= $success ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-facebook-official"></i> إعدادات Meta (Facebook/Instagram)</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="save_meta">
                    <div class="box-body">
                        <div class="form-group">
                            <label>رابط الـ Webhook الخاص بك (Webhook URL)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?= $webhookUrl ?>" readonly id="metaWebhookUrl">
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="navigator.clipboard.writeText(document.getElementById('metaWebhookUrl').value); alert('تم النسخ');"><i class="fa fa-copy"></i> نسخ</button>
                                </span>
                            </div>
                            <small class="text-muted">قم بإدخال هذا الرابط في صفحة إعدادات Webhooks في Meta Developer Portal.</small>
                        </div>

                        <div class="form-group">
                            <label>Verify Token (رمز التحقق)</label>
                            <input type="text" name="meta_verify_token" class="form-control" placeholder="أدخل رمز تحقق من اختيارك" value="<?= $hasVerifyToken ? htmlspecialchars($sm->getSecret('meta_verify_token')) : '' ?>">
                            <small class="text-muted">يستخدم للتحقق من ملكيتك للرابط عند إضافته في Meta.</small>
                        </div>

                        <div class="form-group">
                            <label>App Secret (السر الخاص بالتطبيق)</label>
                            <input type="password" name="meta_app_secret" class="form-control" placeholder="App Secret من إعدادات Meta" value="<?= $hasAppSecret ? '••••••••••••••••' : '' ?>">
                            <small class="text-muted">يُستخدم للتحقق من صحة توقيع الرسائل (Signature Validation). سيتم تشفيره في قاعدة البيانات.</small>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> حفظ إعدادات Meta</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-check-circle"></i> Production Readiness Checklist</h3>
                </div>
                <div class="box-body">
                    <ul class="list-group">
                        <li class="list-group-item">
                            <?php if($hasVerifyToken): ?>
                                <i class="fa fa-check text-success"></i> Verify Token محفوظ
                            <?php else: ?>
                                <i class="fa fa-times text-danger"></i> Verify Token مفقود
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item">
                            <?php if($hasAppSecret): ?>
                                <i class="fa fa-check text-success"></i> App Secret محفوظ ومشفّر (AES-256)
                            <?php else: ?>
                                <i class="fa fa-times text-danger"></i> App Secret مفقود
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item">
                            <?php if(file_exists(__DIR__ . '/../api/meta_webhook.php')): ?>
                                <i class="fa fa-check text-success"></i> نقطة اتصال Webhook (meta_webhook.php) جاهزة وتدعم X-Hub-Signature-256
                            <?php else: ?>
                                <i class="fa fa-times text-danger"></i> نقطة اتصال Webhook مفقودة
                            <?php endif; ?>
                        </li>
                    </ul>
                    <p class="mt-3 text-muted" style="font-size:12px;">النظام الآن جاهز للربط في بيئة الإنتاج واستقبال رسائل (نصوص، صور، ملفات، تعليقات) وتوجيهها للـ AI والموظفين بأمان.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
