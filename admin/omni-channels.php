<?php
require_once('inc/config.php');
require_once('inc/functions.php');

// ====== بدء الجلسة لعرض الرسائل بعد Redirect ======
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ====== معالجة POST قبل أي HTML (PRG Pattern) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- إضافة قناة جديدة ---
    if (isset($_POST['add_channel'])) {
        $provider      = trim($_POST['provider']      ?? '');
        $channelType   = trim($_POST['channel_type']  ?? '');
        $accountName   = trim($_POST['account_name']  ?? '');
        $accountId     = trim($_POST['account_id']    ?? '');
        $accessToken   = trim($_POST['access_token']  ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');

        if ($provider && $accountName && $accountId) {
            $stmt = $dbRepo->prepare("
                INSERT INTO tbl_omni_channels
                    (channel_type, provider, account_name, account_id, access_token, webhook_secret, status, tenant_id)
                VALUES (?, ?, ?, ?, ?, ?, 'ACTIVE', ?)
            ");
            $stmt->execute([
                $channelType ?: $provider,
                $provider,
                $accountName,
                $accountId,
                $accessToken    ?: null,
                $webhookSecret  ?: null,
                function_exists('get_current_tenant_id') ? get_current_tenant_id() : 1,
            ]);
            $_SESSION['omni_success'] = '✅ تمت إضافة القناة بنجاح!';
        } else {
            $_SESSION['omni_error'] = '❌ يرجى ملء جميع الحقول المطلوبة (المزود، الاسم، المعرف).';
        }
        header('Location: omni-channels.php');
        exit;
    }

    // --- تغيير حالة القناة ---
    if (isset($_POST['toggle_status'])) {
        $cid       = (int) ($_POST['channel_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? 'INACTIVE';
        if ($cid > 0 && in_array($newStatus, ['ACTIVE', 'INACTIVE'])) {
            $tenant_id = function_exists('get_current_tenant_id') ? get_current_tenant_id() : 1;
            $dbRepo->prepare("UPDATE tbl_omni_channels SET status=?, updated_at=NOW() WHERE id=? AND tenant_id=?")
                ->execute([$newStatus, $cid, $tenant_id]);
            $_SESSION['omni_success'] = ($newStatus === 'ACTIVE')
                ? '✅ تم تفعيل القناة.'
                : '⏸️ تم إيقاف القناة.';
        }
        header('Location: omni-channels.php');
        exit;
    }

    // --- حذف قناة ---
    if (isset($_POST['delete_channel'])) {
        $cid = (int) ($_POST['channel_id'] ?? 0);
        if ($cid > 0) {
            $dbRepo->prepare("DELETE FROM tbl_omni_channels WHERE id=?")->execute([$cid]);
            $_SESSION['omni_success'] = '🗑️ تم حذف القناة بنجاح.';
        }
        header('Location: omni-channels.php');
        exit;
    }

    // --- تعديل قناة (تحديث Access Token) ---
    if (isset($_POST['edit_channel'])) {
        $cid           = (int) ($_POST['channel_id'] ?? 0);
        $accountName   = trim($_POST['account_name']  ?? '');
        $accountId     = trim($_POST['account_id']    ?? '');
        $accessToken   = trim($_POST['access_token']  ?? '');
        $webhookSecret = trim($_POST['webhook_secret'] ?? '');

        if ($cid > 0) {
            // بناء الاستعلام ديناميكيًا
            $fields = [];
            $params = [];
            if ($accountName)   { $fields[] = 'account_name=?';   $params[] = $accountName; }
            if ($accountId)     { $fields[] = 'account_id=?';     $params[] = $accountId; }
            if ($accessToken)   { $fields[] = 'access_token=?';   $params[] = $accessToken; }
            if ($webhookSecret) { $fields[] = 'webhook_secret=?'; $params[] = $webhookSecret; }
            $fields[] = 'updated_at=NOW()';
            $params[] = $cid;

            $dbRepo->prepare("UPDATE tbl_omni_channels SET " . implode(', ', $fields) . " WHERE id=?")
                ->execute($params);
            $_SESSION['omni_success'] = '✅ تم تحديث بيانات القناة بنجاح!';
        }
        header('Location: omni-channels.php');
        exit;
    }
}

// ====== استرجاع رسائل الجلسة ======
$success = $_SESSION['omni_success'] ?? '';
$error   = $_SESSION['omni_error']   ?? '';
unset($_SESSION['omni_success'], $_SESSION['omni_error']);

// ====== جلب القنوات ======
$channels = $dbRepo->query("SELECT * FROM tbl_omni_channels ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

require_once('header.php');
?>

<script>
window.__pageActions = [
    {
        id: 'action-settings',
        href: 'omni-settings.php',
        label: 'إعدادات Webhook',
        variant: 'default'
    },
    {
        id: 'action-add-channel',
        onClick: "$('#addChannelModal').modal('show');",
        label: 'إضافة قناة جديدة',
        variant: 'filled'
    }
];
</script>

<!-- رأس الصفحة -->
<section class="content-header">
    <div class="content-header-left">
        <h1><i class="fa fa-comments-o"></i> إدارة قنوات التواصل</h1>
        <small class="text-muted">ربط Facebook / Instagram / WhatsApp بصندوق الوارد الموحد</small>
    </div>
    <div class="content-header-right">
        <a href="omni-settings.php" class="btn btn-default">
            <i class="fa fa-cog"></i> إعدادات Webhook
        </a>
        &nbsp;
        <button type="button" class="btn btn-primary" onclick="$('#addChannelBox').slideDown(); $('html, body').animate({scrollTop: $('#addChannelBox').offset().top - 50}, 500);">
            <i class="fa fa-plus"></i> إضافة قناة جديدة
        </button>
    </div>
</section>

<section class="content">

<style>
/* Modern SaaS Card Styles to override CSS resets */
.modern-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    margin-top: 24px;
    margin-bottom: 24px;
    overflow: hidden;
    font-family: inherit;
    direction: rtl;
    text-align: right;
}
.modern-card-header {
    background: #f9fafb;
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.modern-card-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modern-card-body {
    padding: 32px 24px;
}
.modern-card-footer {
    background: #f9fafb;
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 16px;
}
.modern-form-group {
    margin-bottom: 24px;
}
.modern-label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 10px;
}
.modern-input, .modern-select, .modern-textarea {
    display: block;
    width: 100%;
    padding: 12px 16px;
    font-size: 0.95rem;
    line-height: 1.5;
    color: #111827;
    background-color: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: all 0.2s ease-in-out;
    box-sizing: border-box;
}
.modern-input:focus, .modern-select:focus, .modern-textarea:focus {
    border-color: #3b82f6;
    outline: none;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
}
.modern-row {
    display: flex;
    flex-wrap: wrap;
    margin-left: -12px;
    margin-right: -12px;
}
.modern-col {
    flex: 1 1 50%;
    padding: 0 12px;
    box-sizing: border-box;
}
@media (max-width: 768px) {
    .modern-col { flex: 1 1 100%; }
}
.modern-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    font-size: 0.95rem;
    font-weight: 600;
    border-radius: 8px;
    cursor: pointer;
    border: 1px solid transparent;
    transition: all 0.2s;
    gap: 8px;
}
.modern-btn-primary {
    background-color: #4f46e5;
    color: #ffffff;
    box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
}
.modern-btn-primary:hover {
    background-color: #4338ca;
}
.modern-btn-default {
    background-color: #ffffff;
    color: #374151;
    border-color: #d1d5db;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
.modern-btn-default:hover {
    background-color: #f3f4f6;
}
.modern-callout {
    background-color: #eff6ff;
    border-right: 4px solid #3b82f6;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
}
.modern-callout h4 { margin-top: 0; color: #1e40af; font-size: 1.05rem; font-weight: 700; margin-bottom: 12px;}
.modern-callout p, .modern-callout ol { margin: 0; color: #1e3a8a; font-size: 0.95rem; padding-right: 20px; line-height: 1.6;}
.modern-close {
    background: transparent; border: none; color: #9ca3af; cursor: pointer; font-size: 1.25rem;
}
.modern-close:hover { color: #4b5563; }
.modern-hint { display: block; font-size: 0.8rem; color: #6b7280; margin-top: 6px; }
</style>


    <!-- شريط الإجراءات الثابت لضمان ظهوره دائماً -->
    <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border-top: 3px solid #3c8dbc;">
        <div>
            <h4 style="margin: 0; font-weight: bold; color: #333;"><i class="fa fa-plug"></i> إجراءات القنوات</h4>
            <small class="text-muted">استخدم هذه الأزرار لإضافة قناة أو ضبط الإعدادات</small>
        </div>
        <div>
            <a href="omni-settings.php" class="btn btn-default btn-lg">
                <i class="fa fa-cog"></i> إعدادات Webhook
            </a>
            &nbsp;
            <button type="button" class="btn btn-primary btn-lg" onclick="$('#addChannelBox').slideDown(); $('html, body').animate({scrollTop: $('#addChannelBox').offset().top - 50}, 500);">
                <i class="fa fa-plus"></i> إضافة قناة جديدة
            </button>
        </div>
    </div>

    

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- جدول القنوات -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-list"></i>
                        القنوات المتصلة
                        <span class="badge bg-blue"><?= count($channels) ?></span>
                    </h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-bordered table-striped table-hover" style="margin-bottom:0;">
                        <thead>
                            <tr style="background:#ecf0f1;">
                                <th style="width:50px;">#</th>
                                <th>المزود</th>
                                <th>نوع القناة</th>
                                <th>اسم الحساب</th>
                                <th>معرف الحساب (ID)</th>
                                <th>Access Token</th>
                                <th style="width:80px; text-align:center;">الحالة</th>
                                <th style="width:200px; text-align:center;">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php if (count($channels) === 0): ?>
                            <tr>
                                <td colspan="8" class="text-center" style="padding: 50px 20px;">
                                    <i class="fa fa-plug fa-4x text-muted"></i>
                                    <h4 class="text-muted" style="margin-top:15px;">لا توجد قنوات مضافة بعد</h4>
                                    <p class="text-muted">اضغط الزر أعلاه <strong>"إضافة قناة جديدة"</strong> للبدء</p>
                                    <button type="button" class="btn btn-primary btn-lg"
                                        onclick="$('#addChannelBox').slideDown(); $('html, body').animate({scrollTop: $('#addChannelBox').offset().top - 50}, 500);">
                                        <i class="fa fa-plus"></i> إضافة قناة جديدة
                                    </button>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($channels as $ch): ?>
                            <?php
                                $providerIcons = [
                                    'meta'      => 'fa-facebook-official',
                                    'whatsapp'  => 'fa-whatsapp',
                                    'telegram'  => 'fa-telegram',
                                    'website'   => 'fa-globe',
                                ];
                                $providerColors = [
                                    'meta'      => '#1877f2',
                                    'whatsapp'  => '#25d366',
                                    'telegram'  => '#0088cc',
                                    'website'   => '#e67e22',
                                ];
                                $prov     = strtolower($ch['provider']);
                                $icon     = $providerIcons[$prov]  ?? 'fa-plug';
                                $color    = $providerColors[$prov] ?? '#888';
                                $isActive = $ch['status'] === 'ACTIVE';
                                $hasToken = !empty($ch['access_token']);
                            ?>
                            <tr>
                                <td><?= (int)$ch['id'] ?></td>

                                <td>
                                    <i class="fa <?= $icon ?>" style="color:<?= $color ?>; font-size:18px; margin-left:5px;"></i>
                                    <strong><?= strtoupper(htmlspecialchars($ch['provider'])) ?></strong>
                                </td>

                                <td>
                                    <span class="label label-default"><?= htmlspecialchars($ch['channel_type']) ?></span>
                                </td>

                                <td><?= htmlspecialchars($ch['account_name']) ?></td>

                                <td>
                                    <code style="font-size:12px;"><?= htmlspecialchars($ch['account_id']) ?></code>
                                </td>

                                <td class="text-center">
                                    <?php if ($hasToken): ?>
                                        <span class="label label-success">
                                            <i class="fa fa-check"></i> محفوظ
                                        </span>
                                    <?php else: ?>
                                        <span class="label label-danger">
                                            <i class="fa fa-times"></i> مفقود
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <?php if ($isActive): ?>
                                        <span class="label label-success">
                                            <i class="fa fa-circle"></i> نشط
                                        </span>
                                    <?php else: ?>
                                        <span class="label label-danger">
                                            <i class="fa fa-circle-o"></i> معطل
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center">
                                    <div style="display:flex; gap:4px; justify-content:center; align-items:center; flex-wrap:wrap;">

                                    <!-- زر تعديل الـ Token -->
                                    <button type="button" class="btn btn-sm btn-info"
                                        title="تعديل Access Token"
                                        onclick="openEditModal(<?= (int)$ch['id'] ?>, '<?= addslashes(htmlspecialchars($ch['account_name'])) ?>')">
                                        <i class="fa fa-edit"></i> تعديل
                                    </button>

                                    <!-- زر تغيير الحالة -->
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="channel_id" value="<?= (int)$ch['id'] ?>">
                                        <input type="hidden" name="toggle_status" value="1">
                                        <?php if ($isActive): ?>
                                            <input type="hidden" name="new_status" value="INACTIVE">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <i class="fa fa-pause"></i> إيقاف
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="ACTIVE">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fa fa-play"></i> تفعيل
                                            </button>
                                        <?php endif; ?>
                                    </form>

                                    <!-- زر الحذف -->
                                    <form method="POST" style="margin:0;"
                                        onsubmit="return confirm('⚠️ هل أنت متأكد من حذف قناة «<?= addslashes(htmlspecialchars($ch['account_name'])) ?>»؟\n\nلا يمكن التراجع عن هذا الإجراء.');">
                                        <input type="hidden" name="channel_id" value="<?= (int)$ch['id'] ?>">
                                        <input type="hidden" name="delete_channel" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fa fa-trash-o"></i> حذف
                                        </button>
                                    </form>

                                    </div><!-- /flex -->
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div><!-- /.box-body -->
            </div><!-- /.box -->
        </div>
    </div>

    <!-- بطاقة معلومات الـ Webhook -->
    <div class="row">
        <div class="col-md-12">
            <div class="callout callout-info">
                <h4><i class="fa fa-link"></i> رابط الـ Webhook الخاص بموقعك</h4>
                <div class="input-group" style="max-width:600px;">
                    <input type="text" class="form-control" id="webhookUrl"
                        value="<?= 'https://' . $_SERVER['HTTP_HOST'] . '/ecom/api/meta_webhook.php' ?>" readonly>
                    <span class="input-group-btn">
                        <button class="btn btn-info" type="button" id="copyWebhookBtn"
                            onclick="
                                navigator.clipboard.writeText(document.getElementById('webhookUrl').value);
                                this.innerHTML = '<i class=\'fa fa-check\'></i> تم النسخ!';
                                setTimeout(() => this.innerHTML = '<i class=\'fa fa-copy\'></i> نسخ', 2000);
                            ">
                            <i class="fa fa-copy"></i> نسخ
                        </button>
                    </span>
                </div>
                <small class="text-muted">
                    أدخل هذا الرابط في حقل <strong>Callback URL</strong> عند إعداد Webhook في Meta Developer Portal.
                </small>
            </div>
        </div>
    </div>

</section>

<!-- ============================================================ -->
<!-- Modal: إضافة قناة جديدة                                       -->
<!-- ============================================================ -->

<!-- ============================================================ -->

<!-- ============================================================ -->
<!-- صندوق: إضافة قناة جديدة (مدمج بدلاً من Modal)                  -->
<!-- ============================================================ -->
<div class="modern-card" id="addChannelBox" style="display:none;">
    <div class="modern-card-header">
        <h3 class="modern-card-title">
            <i class="fa fa-plus-circle" style="color: #4f46e5;"></i> إضافة قناة تواصل جديدة
        </h3>
        <button type="button" class="modern-close" onclick="$('#addChannelBox').slideUp();"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" id="addChannelForm">
        <input type="hidden" name="add_channel" value="1">
        <div class="modern-card-body">
            <div class="modern-row">
                <div class="modern-col">
                    <div class="modern-form-group">
                        <label class="modern-label"><i class="fa fa-plug"></i> مزود الخدمة <span style="color:#ef4444;">*</span></label>
                        <select name="provider" id="providerSelect" class="modern-select" onchange="onProviderChange(this.value)">
                            <option value="meta">📘 Meta (Facebook / Instagram)</option>
                            <option value="whatsapp">💬 WhatsApp Cloud API</option>
                            <option value="telegram">✈️ Telegram Bot</option>
                            <option value="website">🌐 Website Live Chat</option>
                        </select>
                    </div>
                </div>
                <div class="modern-col">
                    <div class="modern-form-group">
                        <label class="modern-label"><i class="fa fa-tag"></i> نوع القناة</label>
                        <input type="text" name="channel_type" id="channelTypeInput" class="modern-input" value="facebook_page">
                        <span class="modern-hint">يُملأ تلقائياً عند اختيار المزود</span>
                    </div>
                </div>
            </div>

            <div class="modern-row">
                <div class="modern-col">
                    <div class="modern-form-group">
                        <label class="modern-label"><i class="fa fa-user"></i> اسم الحساب (للعرض فقط) <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="account_name" class="modern-input" placeholder="مثال: صفحة متجري الرسمية" required>
                    </div>
                </div>
                <div class="modern-col">
                    <div class="modern-form-group">
                        <label class="modern-label"><i class="fa fa-hashtag"></i> <span id="accountIdLabel">معرف الصفحة (Page ID)</span> <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="account_id" class="modern-input" id="accountIdInput" placeholder="مثال: 123456789012345" required>
                        <span class="modern-hint" id="accountIdHelp">من إعدادات صفحة فيسبوك → نبذة عنا → Page ID</span>
                    </div>
                </div>
            </div>

            <div class="modern-form-group">
                <label class="modern-label"><i class="fa fa-key"></i> <span id="tokenLabel">Page Access Token</span> <span style="color:#ef4444;">*</span></label>
                <textarea name="access_token" id="accessTokenInput" class="modern-textarea" rows="3" placeholder="الصق رمز الوصول الطويل هنا..." required></textarea>
                <span class="modern-hint" id="tokenHelp">من Meta Developer → تطبيقك → Messenger API Settings → Generate Token</span>
            </div>

            <div class="modern-form-group">
                <label class="modern-label"><i class="fa fa-shield"></i> Webhook Secret <span style="color:#6b7280; font-weight:normal;">(اختياري)</span></label>
                <input type="text" name="webhook_secret" class="modern-input" placeholder="رمز سري إضافي للتحقق من الطلبات القادمة">
            </div>

            <div class="modern-callout" id="providerHint">
                <h4><i class="fa fa-info-circle"></i> كيفية الحصول على البيانات (Meta)</h4>
                <ol>
                    <li>اذهب إلى <a href="https://developers.facebook.com/apps" target="_blank">developers.facebook.com/apps</a></li>
                    <li>اختر تطبيقك ← <strong>Messenger</strong> ← <strong>Messenger API Settings</strong></li>
                    <li>اضغط <strong>Add or Remove Pages</strong> وأضف صفحتك</li>
                    <li>اضغط <strong>Generate Token</strong> واقبل الأذونات</li>
                    <li>انسخ الـ <strong>Page ID</strong> من إعدادات صفحة فيسبوك</li>
                </ol>
            </div>
        </div>
        <div class="modern-card-footer">
            <button type="button" class="modern-btn modern-btn-default" onclick="$('#addChannelBox').slideUp();">
                <i class="fa fa-times"></i> إلغاء
            </button>
            <button type="submit" class="modern-btn modern-btn-primary">
                <i class="fa fa-save"></i> حفظ وتفعيل القناة
            </button>
        </div>
    </form>
</div>



<script>
var providerData = {
    meta: {
        channelType: 'facebook_page',
        idLabel:     'معرف الصفحة (Page ID)',
        idHelp:      'من إعدادات صفحة فيسبوك → نبذة عنا → Page ID',
        idPlaceholder: 'مثال: 123456789012345',
        tokenLabel:  'Page Access Token',
        tokenHelp:   'من Meta Developer → تطبيقك → Messenger API Settings → Generate Token',
        tokenPlaceholder: 'EAABsbCS...',
        hint: '<h4><i class="fa fa-info-circle"></i> كيفية الحصول على البيانات (Meta)</h4><ol style="margin:0;padding-right:20px;line-height:1.8;"><li>اذهب إلى <a href="https://developers.facebook.com/apps" target="_blank">developers.facebook.com</a></li><li>اختر تطبيقك ← <strong>Messenger</strong> ← <strong>Messenger API Settings</strong></li><li>أضف صفحتك واضغط <strong>Generate Token</strong></li><li>انسخ الـ Page ID من إعدادات الصفحة</li></ol>'
    },
    whatsapp: {
        channelType: 'whatsapp_cloud',
        idLabel:     'Phone Number ID',
        idHelp:      'من Meta Developer → تطبيقك → WhatsApp → API Setup',
        idPlaceholder: 'مثال: 123456789012345',
        tokenLabel:  'Temporary / Permanent Access Token',
        tokenHelp:   'من Meta Developer → تطبيقك → WhatsApp → API Setup → Access Token',
        tokenPlaceholder: 'EAABsbCS...',
        hint: '<h4><i class="fa fa-info-circle"></i> كيفية الحصول على البيانات (WhatsApp)</h4><ol style="margin:0;padding-right:20px;line-height:1.8;"><li>اذهب إلى <a href="https://developers.facebook.com/apps" target="_blank">developers.facebook.com</a></li><li>اختر تطبيقك ← <strong>WhatsApp</strong> ← <strong>API Setup</strong></li><li>انسخ <strong>Phone Number ID</strong> و <strong>Access Token</strong></li></ol>'
    },
    telegram: {
        channelType: 'telegram_bot',
        idLabel:     'Bot Username',
        idHelp:      'اسم البوت بدون @ (مثال: mystore_bot)',
        idPlaceholder: 'مثال: mystore_bot',
        tokenLabel:  'Bot Token',
        tokenHelp:   'من @BotFather على تيليغرام بعد إنشاء البوت',
        tokenPlaceholder: '1234567890:ABCdef...',
        hint: '<h4><i class="fa fa-info-circle"></i> كيفية إنشاء بوت تيليغرام</h4><ol style="margin:0;padding-right:20px;line-height:1.8;"><li>افتح تيليغرام وابحث عن <a href="https://t.me/BotFather" target="_blank">@BotFather</a></li><li>أرسل <code>/newbot</code> واتبع التعليمات</li><li>اختر اسماً واسم مستخدم للبوت</li><li>ستحصل على Bot Token - انسخه هنا</li></ol>'
    },
    website: {
        channelType: 'website_chat',
        idLabel:     'اسم النطاق',
        idHelp:      'النطاق الكامل لموقعك (مثال: example.com)',
        idPlaceholder: 'مثال: example.com',
        tokenLabel:  'API Key (اختياري)',
        tokenHelp:   'مفتاح API لربط الدردشة المباشرة (إن وجد)',
        tokenPlaceholder: 'اتركه فارغاً إذا لم يكن مطلوباً',
        hint: '<h4><i class="fa fa-info-circle"></i> دردشة الموقع المباشرة</h4><p>بعد الإضافة، سيتم إنشاء كود JavaScript تضعه في موقعك لتفعيل الدردشة المباشرة.</p>'
    }
};

function onProviderChange(val) { global $dbRepo;
    global $dbRepo;

    var d = providerData[val];
    if (!d) return;
    document.getElementById('channelTypeInput').value      = d.channelType;
    document.getElementById('accountIdLabel').textContent  = d.idLabel;
    document.getElementById('accountIdHelp').textContent   = d.idHelp;
    document.getElementById('accountIdInput').placeholder  = d.idPlaceholder;
    document.getElementById('tokenLabel').textContent      = d.tokenLabel;
    document.getElementById('tokenHelp').textContent       = d.tokenHelp;
    document.getElementById('accessTokenInput').placeholder = d.tokenPlaceholder;
    document.getElementById('providerHint').innerHTML      = d.hint;
}

function openEditModal(channelId, channelName) { global $dbRepo;
    global $dbRepo;

    document.getElementById('editChannelId').value   = channelId;
    document.getElementById('editChannelName').textContent = channelName;
    // تصفير حقل التوكن لحماية التوكن الحالي
    document.getElementById('editAccessToken').value = '';
    document.getElementById('editAccessToken').placeholder = 'اتركه فارغًا إذا لم ترد تغييره • سيظل التوكن الحالي كما هو';
    $('#editChannelBox').slideDown(); $('html, body').animate({scrollTop: $('#editChannelBox').offset().top - 50}, 500);
}
</script>

<!-- ============================================================ -->
<!-- Modal: تعديل قناة موجودة                                     -->
<!-- ============================================================ -->

<!-- ============================================================ -->

<!-- ============================================================ -->
<!-- صندوق: تعديل قناة موجودة                                     -->
<!-- ============================================================ -->
<div class="modern-card" id="editChannelBox" style="display:none; border-top: 4px solid #10b981;">
    <div class="modern-card-header">
        <h3 class="modern-card-title">
            <i class="fa fa-edit" style="color: #10b981;"></i>
            تعديل قناة: <span id="editChannelName" style="color:#059669;"></span>
        </h3>
        <button type="button" class="modern-close" onclick="$('#editChannelBox').slideUp();"><i class="fa fa-times"></i></button>
    </div>
    <form method="POST" id="editChannelForm">
        <input type="hidden" name="edit_channel" value="1">
        <input type="hidden" name="channel_id" id="editChannelId" value="">
        <div class="modern-card-body">
            
            <div class="modern-callout" style="background-color: #fef3c7; border-right-color: #f59e0b;">
                <h4 style="color: #b45309;"><i class="fa fa-info-circle"></i> تنبيه</h4>
                <p style="color: #92400e;">اترك أي حقل فارغًا إذا لم ترد تغييره. سيتم تحديث الحقول التي تملؤها فقط.</p>
            </div>

            <div class="modern-row">
                <div class="modern-col">
                    <div class="modern-form-group">
                        <label class="modern-label"><i class="fa fa-user"></i> اسم الحساب</label>
                        <input type="text" name="account_name" id="editAccountName" class="modern-input" placeholder="اتركه فارغًا لعدم التغيير">
                    </div>
                </div>
                <div class="modern-col">
                    <div class="modern-form-group">
                        <label class="modern-label"><i class="fa fa-hashtag"></i> معرف الحساب (ID)</label>
                        <input type="text" name="account_id" id="editAccountId" class="modern-input" placeholder="اتركه فارغًا لعدم التغيير">
                    </div>
                </div>
            </div>

            <div class="modern-form-group">
                <label class="modern-label"><i class="fa fa-key"></i> Access Token الجديد</label>
                <textarea name="access_token" id="editAccessToken" class="modern-textarea" rows="4" placeholder="الصق التوكن الجديد هنا... (اتركه فارغًا إذا لم ترد تغييره)"></textarea>
                <span class="modern-hint"><i class="fa fa-lock"></i> سيتم حفظه مباشرة في قاعدة البيانات ولن يظهر للعامة.</span>
            </div>

            <div class="modern-form-group">
                <label class="modern-label"><i class="fa fa-shield"></i> Webhook Secret (اختياري)</label>
                <input type="text" name="webhook_secret" id="editWebhookSecret" class="modern-input" placeholder="اتركه فارغًا لعدم التغيير">
            </div>
        </div>
        <div class="modern-card-footer">
            <button type="button" class="modern-btn modern-btn-default" onclick="$('#editChannelBox').slideUp();">
                <i class="fa fa-times"></i> إلغاء
            </button>
            <button type="submit" class="modern-btn modern-btn-primary" style="background-color: #10b981;">
                <i class="fa fa-save"></i> حفظ التعديلات
            </button>
        </div>
    </form>
</div>



<?php require_once('footer.php'); ?>
