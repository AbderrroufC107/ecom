<?php require_once('header.php'); ?>
<?php require_once __DIR__ . '/../inc/meta-platform.php'; ?>
<?php
meta_platform_ensure_settings_columns($pdo);
meta_platform_ensure_webhook_log_table($pdo);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meta_platform'])) {
    $accessFields = [
        'meta_app_secret',
        'instagram_access_token',
        'messenger_page_access_token',
    ];
    $data = [
        'meta_app_id' => trim((string) ($_POST['meta_app_id'] ?? '')),
        'meta_webhook_verify_token' => trim((string) ($_POST['meta_webhook_verify_token'] ?? '')),
        'meta_platform_graph_version' => meta_platform_graph_version($_POST['meta_platform_graph_version'] ?? 'v25.0'),
        'instagram_platform_enabled' => isset($_POST['instagram_platform_enabled']) ? 1 : 0,
        'instagram_business_account_id' => trim((string) ($_POST['instagram_business_account_id'] ?? '')),
        'messenger_platform_enabled' => isset($_POST['messenger_platform_enabled']) ? 1 : 0,
        'messenger_page_id' => trim((string) ($_POST['messenger_page_id'] ?? '')),
        'messenger_order_admin_enabled' => isset($_POST['messenger_order_admin_enabled']) ? 1 : 0,
        'messenger_admin_psid' => trim((string) ($_POST['messenger_admin_psid'] ?? '')),
    ];

    foreach ($accessFields as $field) {
        $value = trim((string) ($_POST[$field] ?? ''));
        if ($value !== '') {
            $data[$field] = $value;
        }
    }

    $sets = [];
    $params = [];
    foreach ($data as $key => $value) {
        $sets[] = $key . '=?';
        $params[] = $value;
    }
    $params[] = 1;
    $stmt = $pdo->prepare("UPDATE tbl_settings SET " . implode(',', $sets) . " WHERE id=?");
    $stmt->execute($params);
    $success = 'Meta Platform settings saved.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_messenger_test'])) {
    $settingsForTest = meta_platform_settings($pdo);
    $psid = trim((string) ($_POST['test_messenger_psid'] ?? $settingsForTest['messenger_admin_psid'] ?? ''));
    $message = trim((string) ($_POST['test_messenger_message'] ?? 'Test message from ecom admin.'));
    $result = meta_platform_messenger_send_text($pdo, $psid, $message);
    if (!empty($result['sent'])) {
        $success = 'Messenger test message sent.';
    } else {
        $error = 'Messenger test failed: ' . htmlspecialchars((string) ($result['error'] ?? 'Unknown error'), ENT_QUOTES, 'UTF-8');
    }
}

$settings = meta_platform_settings($pdo);
$instagramProfile = !empty($settings['instagram_platform_enabled']) ? meta_platform_instagram_profile($settings) : null;
$instagramMedia = !empty($settings['instagram_platform_enabled']) ? meta_platform_instagram_media($settings, 8) : null;

$logs = [];
try {
    $logs = $pdo->query("SELECT * FROM tbl_meta_webhook_log ORDER BY id DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $logs = [];
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/meta-platform.php'), '/\\');
$webhookUrl = $scheme . '://' . $host . $base . '/meta-webhook.php';
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>Meta Platform</h1>
    </div>
</section>

<section class="content">
    <?php if ($error): ?><div class="callout callout-danger"><p><?php echo $error; ?></p></div><?php endif; ?>
    <?php if ($success): ?><div class="callout callout-success"><p><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p></div><?php endif; ?>

    <div class="box box-info">
        <div class="box-header with-border"><h3 class="box-title">Settings</h3></div>
        <form class="form-horizontal" method="post">
            <div class="box-body">
                <div class="alert alert-info">
                    Graph Webhook Callback URL: <code><?php echo htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8'); ?></code>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Graph API Version</label>
                    <div class="col-sm-3"><input class="form-control" name="meta_platform_graph_version" value="<?php echo htmlspecialchars(meta_platform_graph_version($settings['meta_platform_graph_version'] ?? 'v25.0'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">App ID</label>
                    <div class="col-sm-8"><input class="form-control" name="meta_app_id" value="<?php echo htmlspecialchars((string) ($settings['meta_app_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">App Secret</label>
                    <div class="col-sm-8"><input type="password" class="form-control" name="meta_app_secret" value="" placeholder="<?php echo !empty($settings['meta_app_secret']) ? 'Saved - leave empty to keep it' : 'Paste App Secret'; ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Webhook Verify Token</label>
                    <div class="col-sm-8"><input class="form-control" name="meta_webhook_verify_token" value="<?php echo htmlspecialchars((string) ($settings['meta_webhook_verify_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <hr>
                <h4>Instagram Platform</h4>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Enable Instagram</label>
                    <div class="col-sm-8"><label><input type="checkbox" name="instagram_platform_enabled" value="1" <?php echo !empty($settings['instagram_platform_enabled']) ? 'checked' : ''; ?>> Instagram Graph API</label></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Instagram Access Token</label>
                    <div class="col-sm-8"><input type="password" class="form-control" name="instagram_access_token" value="" placeholder="<?php echo !empty($settings['instagram_access_token']) ? 'Saved - leave empty to keep it' : 'Paste Instagram token'; ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Instagram Business Account ID</label>
                    <div class="col-sm-8"><input class="form-control" name="instagram_business_account_id" value="<?php echo htmlspecialchars((string) ($settings['instagram_business_account_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <hr>
                <h4>Messenger Platform</h4>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Enable Messenger</label>
                    <div class="col-sm-8"><label><input type="checkbox" name="messenger_platform_enabled" value="1" <?php echo !empty($settings['messenger_platform_enabled']) ? 'checked' : ''; ?>> Messenger Send API and webhooks</label></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Page ID</label>
                    <div class="col-sm-8"><input class="form-control" name="messenger_page_id" value="<?php echo htmlspecialchars((string) ($settings['messenger_page_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Page Access Token</label>
                    <div class="col-sm-8"><input type="password" class="form-control" name="messenger_page_access_token" value="" placeholder="<?php echo !empty($settings['messenger_page_access_token']) ? 'Saved - leave empty to keep it' : 'Paste Page Access Token'; ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Admin PSID</label>
                    <div class="col-sm-8"><input class="form-control" name="messenger_admin_psid" value="<?php echo htmlspecialchars((string) ($settings['messenger_admin_psid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <div class="form-group">
                    <label class="col-sm-3 control-label">Order Alerts</label>
                    <div class="col-sm-8"><label><input type="checkbox" name="messenger_order_admin_enabled" value="1" <?php echo !empty($settings['messenger_order_admin_enabled']) ? 'checked' : ''; ?>> Send new orders to admin PSID</label></div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-8"><button class="btn btn-primary" name="save_meta_platform" type="submit"><i class="fa fa-save"></i> Save Meta Platform</button></div>
                </div>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Instagram Status</h3></div>
                <div class="box-body">
                    <?php if ($instagramProfile && !empty($instagramProfile['ok'])): $profile = $instagramProfile['data']; ?>
                        <p><strong>@<?php echo htmlspecialchars((string) ($profile['username'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong></p>
                        <p>Account: <?php echo htmlspecialchars((string) ($profile['account_type'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p>Media: <?php echo (int) ($profile['media_count'] ?? 0); ?> | Followers: <?php echo (int) ($profile['followers_count'] ?? 0); ?></p>
                    <?php elseif ($instagramProfile): ?>
                        <p class="text-danger"><?php echo htmlspecialchars((string) ($instagramProfile['error'] ?? 'Instagram unavailable'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php else: ?>
                        <p class="text-muted">Instagram is disabled.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Messenger Test</h3></div>
                <form method="post">
                    <div class="box-body">
                        <input class="form-control" name="test_messenger_psid" placeholder="Recipient PSID" value="<?php echo htmlspecialchars((string) ($settings['messenger_admin_psid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <br>
                        <textarea class="form-control" name="test_messenger_message" rows="3">Test message from ecom admin.</textarea>
                    </div>
                    <div class="box-footer"><button class="btn btn-default" name="send_messenger_test" type="submit">Send Test</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header with-border"><h3 class="box-title">Recent Graph Webhooks</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Time</th><th>Object</th><th>Field</th><th>Sender</th><th>Recipient</th></tr></thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-muted text-center">No Graph webhook events logged yet.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) $log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $log['object_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $log['event_field'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $log['sender_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) $log['recipient_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
