<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId    = \SaaS\TenantContext::getTenantId();
$secretMgr   = new \Security\SecretManager($pdo);
$accountRepo = new \Marketing\Repositories\AdAccountRepository($pdo);

$success = '';
$error   = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_account'])) {
        $accountId   = trim($_POST['account_id'] ?? '');
        $accountName = trim($_POST['account_name'] ?? '');
        $token       = trim($_POST['access_token'] ?? '');
        $currency    = trim($_POST['currency'] ?? 'DZD');
        $apiVer      = trim($_POST['graph_api_version'] ?? 'v19.0');

        if (!$accountId || !$token) {
            $error = 'معرف الحساب وتوكن الوصول مطلوبان.';
        } else {
            // Validate token by calling Meta API
                try {
                // Temporarily store token to validate
                $secretName = "meta_marketing_{$accountId}_{$tenantId}";
                $secretMgr->setSecret($secretName, 'meta_marketing', $token);

                // Try to fetch account info to validate
                $apiClient = new \Marketing\MarketingApiClient($pdo, $accountId, $tenantId);
                $info      = $apiClient->getAdAccount($accountId);

                // Check for existing record (including soft-deleted)
                $existing = $accountRepo->findByMetaAccountId($accountId);
                if (!$existing) {
                    $stmtChk = $pdo->prepare("SELECT id FROM tbl_meta_ad_accounts WHERE account_id = ? AND tenant_id = ? LIMIT 1");
                    $stmtChk->execute([$accountId, $tenantId]);
                    $existing = $stmtChk->fetch(PDO::FETCH_ASSOC);
                }

                // Save to DB
                $data = [
                    'account_id'    => $accountId,
                    'account_name'  => $info['name'] ?? $accountName,
                    'currency'      => $info['currency'] ?? $currency,
                    'timezone'      => $info['timezone_name'] ?? 'Africa/Algiers',
                    'status'        => 'ACTIVE',
                    'graph_api_ver' => $apiVer,
                    'last_synced_at'=> date('Y-m-d H:i:s'),
                    'is_deleted'    => 0,
                ];
                if ($existing) {
                    $accountRepo->update($existing['id'], $data);
                } else {
                    $data['tenant_id'] = $tenantId;
                    $accountRepo->create($data);
                }

                // Update settings
                $pdo->prepare("UPDATE tbl_settings SET graph_api_version = ?, marketing_api_enabled = 1 WHERE id = 1")->execute([$apiVer]);

                // Create default automation rules
                (new \Marketing\AutomationEngine($pdo))->createDefaultRules($tenantId);

                // Auto-sync pixels from Meta
                $syncedPixels = 0;
                try {
                    $metaPixels = $apiClient->getAdAccountPixels($accountId);
                    if (!empty($metaPixels)) {
                        $chkStmt = $pdo->prepare("SELECT id FROM tbl_pixel WHERE pixel_id = ? AND pixel_network = 'Facebook' LIMIT 1");
                        $insStmt = $pdo->prepare("INSERT INTO tbl_pixel (pixel_name, pixel_network, pixel_id, pixel_script) VALUES (?, 'Facebook', ?, '')");
                        foreach ($metaPixels as $px) {
                            $pid = $px['id'] ?? '';
                            $pname = $px['name'] ?? '';
                            if (!$pid) continue;
                            $chkStmt->execute([$pid]);
                            if (!$chkStmt->fetch()) {
                                $insStmt->execute([$pname, $pid]);
                                $syncedPixels++;
                            }
                        }
                    }
                } catch (Exception $pxEx) {
                    // Pixel sync is non-critical, continue
                }

                $success = "✅ تم ربط حساب '{$info['name']}' بنجاح! العملة: {$info['currency']}.";
                if ($syncedPixels > 0) {
                    $success .= " تم ربط {$syncedPixels} بكسل تلقائياً.";
                }

            } catch (Exception $e) {
                // Remove invalid secret
                $secretMgr->removeSecret("meta_marketing_{$accountId}_{$tenantId}");
                $error = 'فشل التحقق من الحساب: ' . $e->getMessage();
            }
        }
    }

    if (isset($_POST['delete_account'])) {
        $id = (int)$_POST['account_id_del'];
        $accountRepo->update($id, ['is_deleted' => 1, 'status' => 'DISABLED']);
        $success = 'تم حذف الحساب.';
    }
}

$accounts = $accountRepo->getActive();
require_once('header.php');
?>
<style>
.accounts-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.page-title { font-size: 1.6rem; font-weight: 700; color: #0f172a; margin-bottom: 6px; }
.page-subtitle { color: #64748b; margin-bottom: 28px; font-size: 0.92rem; }
.accounts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
@media(max-width:900px) { .accounts-grid { grid-template-columns: 1fr; } }
.panel { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
.panel-header { padding: 18px 24px; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #0f172a; display: flex; gap: 10px; align-items: center; }
.panel-body { padding: 24px; }
.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; box-sizing: border-box; transition: border 0.2s; }
.form-control:focus { outline: none; border-color: #1877f2; box-shadow: 0 0 0 3px rgba(24,119,242,0.1); }
.btn-save { background: #1877f2; color: white; border: none; padding: 12px 28px; border-radius: 10px; font-weight: 700; font-size: 0.95rem; cursor: pointer; width: 100%; transition: background 0.2s; }
.btn-save:hover { background: #1467d9; }
.alert { padding: 12px 16px; border-radius: 10px; font-size: 0.9rem; margin-bottom: 16px; }
.alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.account-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px; display: flex; align-items: center; gap: 14px; }
.account-logo { width: 44px; height: 44px; background: #1877f2; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; flex-shrink: 0; }
.account-name { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
.account-id   { color: #94a3b8; font-size: 0.8rem; }
.account-status { background: #dcfce7; color: #16a34a; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.hint-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 16px; margin-bottom: 18px; font-size: 0.85rem; color: #1e40af; line-height: 1.7; }
.hint-box ol { margin: 8px 0 0 16px; padding: 0; }
</style>

<div class="accounts-wrapper">
    <div class="page-title"><i class="fa fa-plug" style="color:#1877f2"></i> ربط Ad Accounts</div>
    <div class="page-subtitle">قم بربط حساباتك الإعلانية على Meta للبدء في إدارة الحملات.</div>

    <div class="accounts-grid">

        <!-- Add Account Form -->
        <div class="panel">
            <div class="panel-header"><i class="fa fa-plus-circle" style="color:#1877f2"></i> إضافة حساب إعلاني</div>
            <div class="panel-body">
                <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

                <div class="hint-box">
                    <strong>كيفية الحصول على البيانات:</strong>
                    <ol>
                        <li>اذهب إلى <a href="https://developers.facebook.com" target="_blank">Meta for Developers</a></li>
                        <li>اختر تطبيقك ← Tools ← Graph API Explorer</li>
                        <li>انسخ الـ Access Token مع صلاحيات <code>ads_management</code> و <code>business_management</code></li>
                        <li>معرف الحساب: من Meta Business Manager ← حسابات الإعلانات</li>
                    </ol>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">معرف الحساب (Account ID)</label>
                        <input type="text" name="account_id" class="form-control" placeholder="123456789" required>
                        <small style="color:#94a3b8">بدون act_ — الأرقام فقط</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">اسم الحساب (اختياري)</label>
                        <input type="text" name="account_name" class="form-control" placeholder="اسم الحساب التجاري">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Access Token (سيُشفَّر تلقائياً)</label>
                        <textarea name="access_token" class="form-control" rows="3" placeholder="EAAxxxxxxxx..." required style="font-family:monospace;font-size:0.8rem;resize:vertical"></textarea>
                        <small style="color:#94a3b8">يُخزَّن مشفراً بـ AES-256-GCM — آمن تماماً</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">إصدار Graph API</label>
                        <select name="graph_api_version" class="form-control">
                            <option value="v19.0">v19.0 (مستحسن)</option>
                            <option value="v20.0">v20.0</option>
                            <option value="v18.0">v18.0</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">العملة</label>
                        <select name="currency" class="form-control">
                            <option value="DZD">DZD - دينار جزائري</option>
                            <option value="USD">USD - دولار</option>
                            <option value="EUR">EUR - يورو</option>
                            <option value="MAD">MAD - درهم مغربي</option>
                        </select>
                    </div>
                    <button type="submit" name="save_account" class="btn-save">
                        <i class="fa fa-link"></i> ربط الحساب والتحقق
                    </button>
                </form>
            </div>
        </div>

        <!-- Linked Accounts -->
        <div class="panel">
            <div class="panel-header"><i class="fa fa-check-circle" style="color:#00c875"></i> الحسابات المرتبطة</div>
            <div class="panel-body">
                <?php if (empty($accounts)): ?>
                <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
                    <div style="font-size:3rem;opacity:0.3">📡</div>
                    <p>لا يوجد حسابات مرتبطة بعد.<br>أضف أول حساب إعلاني.</p>
                </div>
                <?php else: ?>
                <?php foreach ($accounts as $acc): ?>
                <div class="account-card">
                    <div class="account-logo"><i class="fa fa-facebook-official"></i></div>
                    <div style="flex:1">
                        <div class="account-name"><?= htmlspecialchars($acc['account_name']) ?></div>
                        <div class="account-id">act_<?= $acc['account_id'] ?> · <?= $acc['currency'] ?> · <?= $acc['graph_api_ver'] ?></div>
                        <?php if ($acc['last_synced_at']): ?>
                        <div style="font-size:0.78rem;color:#94a3b8;margin-top:3px">آخر مزامنة: <?= date('H:i d/m/Y', strtotime($acc['last_synced_at'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <span class="account-status">متصل</span>
                        <form method="POST" style="margin-top:6px" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            <input type="hidden" name="account_id_del" value="<?= $acc['id'] ?>">
                            <button type="submit" name="delete_account" style="background:none;border:none;color:#ef4444;cursor:pointer;font-size:0.8rem;">
                                <i class="fa fa-trash"></i> حذف
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once('footer.php'); ?>
