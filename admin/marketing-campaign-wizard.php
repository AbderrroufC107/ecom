<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();

// Ensure an account is linked
$stmt = $pdo->prepare("SELECT * FROM tbl_meta_ad_accounts WHERE tenant_id = ? AND status = 'ACTIVE' LIMIT 1");
$stmt->execute([$tenantId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    header("Location: marketing-accounts.php");
    exit;
}

$msg = '';
$step = (int)($_GET['step'] ?? 1);

// Step 1: Objective & Name
// Step 2: Budget & Schedule
// Step 3: Audience (Targeting)
// Step 4: Creative & Placement
// Step 5: Review & Publish

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish'])) {
    $engine = new \Marketing\SyncEngine($pdo, $account['account_id'], $tenantId);
    try {
        // Here we would collect session data and send it to Meta.
        // For demonstration, we'll simulate the success if validation passes.
        $campaignData = [
            'name' => $_POST['campaign_name'],
            'objective' => $_POST['objective'],
            'status' => 'PAUSED', // create as paused by default
            'special_ad_categories' => [],
            'adsets' => [
                [
                    'name' => $_POST['campaign_name'] . ' - AdSet',
                    'daily_budget' => ((int)$_POST['daily_budget']) * 100, // in cents
                    'billing_event' => 'IMPRESSIONS',
                    'optimization_goal' => 'REACH',
                    'targeting' => ['geo_locations' => ['countries' => ['DZ']]],
                ]
            ]
        ];
        // Mock successful creation
        // $engine->publishCampaign($campaignData);
        $msg = "تم إنشاء الحملة بنجاح، وهي الآن قيد المراجعة.";
        $step = 6; // Success step
    } catch (Exception $e) {
        $msg = "فشل إنشاء الحملة: " . $e->getMessage();
    }
}

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #1877f2 0%, #0d5dbf 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }

.wizard-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; display: flex; min-height: 600px; }
.wizard-sidebar { width: 280px; background: #f8fafc; border-left: 1px solid #e2e8f0; padding: 24px 0; }
.wizard-step-link { display: block; padding: 16px 24px; text-decoration: none; color: #64748b; font-weight: 600; border-right: 4px solid transparent; transition: all 0.2s; }
.wizard-step-link.active { color: #1877f2; border-right-color: #1877f2; background: white; }
.wizard-step-link .step-num { display: inline-flex; width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%; align-items: center; justify-content: center; font-size: 0.8rem; margin-left: 10px; color: #475569; }
.wizard-step-link.active .step-num { background: #1877f2; color: white; }

.wizard-content { flex: 1; padding: 32px 40px; position: relative; }
.wizard-title { font-size: 1.4rem; font-weight: 700; color: #0f172a; margin-bottom: 24px; }

.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 0.9rem; font-weight: 600; color: #334155; margin-bottom: 8px; }
.form-control { width: 100%; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; }
.form-control:focus { border-color: #1877f2; outline: none; }

.objective-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.objective-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s; }
.objective-card:hover { border-color: #93c5fd; background: #eff6ff; }
.objective-card.selected { border-color: #1877f2; background: #eff6ff; box-shadow: 0 4px 12px rgba(24,119,242,0.15); }
.objective-icon { font-size: 2rem; color: #1877f2; margin-bottom: 12px; }
.objective-title { font-weight: 700; color: #0f172a; margin-bottom: 6px; }
.objective-desc { font-size: 0.8rem; color: #64748b; }

.wizard-footer { position: absolute; bottom: 0; left: 0; right: 0; padding: 20px 40px; border-top: 1px solid #e2e8f0; background: white; display: flex; justify-content: space-between; align-items: center; }
.btn-mc { padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; border: none; text-decoration: none; }
.btn-next { background: #1877f2; color: white; }
.btn-next:hover { background: #1467d9; }
.btn-prev { background: white; color: #475569; border: 1px solid #cbd5e1; }
.btn-prev:hover { background: #f8fafc; }

.alert-success { padding: 20px; border-radius: 12px; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; text-align: center; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-magic"></i> معالج إنشاء الحملات (Campaign Wizard)</h1>
            <p>حساب الإعلانات النشط: <?= htmlspecialchars($account['account_name'] ?? $account['account_id']) ?></p>
        </div>
        <a href="marketing-campaigns.php" style="color:white; text-decoration:underline;">إلغاء والعودة</a>
    </div>

    <div class="wizard-container">
        <!-- Sidebar -->
        <div class="wizard-sidebar">
            <div class="wizard-step-link <?= $step === 1 ? 'active' : '' ?>"><span class="step-num">1</span> الهدف والاسم</div>
            <div class="wizard-step-link <?= $step === 2 ? 'active' : '' ?>"><span class="step-num">2</span> الميزانية والجدول</div>
            <div class="wizard-step-link <?= $step === 3 ? 'active' : '' ?>"><span class="step-num">3</span> الجمهور المستهدف</div>
            <div class="wizard-step-link <?= $step === 4 ? 'active' : '' ?>"><span class="step-num">4</span> التصميم والإعلان</div>
            <div class="wizard-step-link <?= $step === 5 ? 'active' : '' ?>"><span class="step-num">5</span> المراجعة والنشر</div>
        </div>

        <!-- Content -->
        <div class="wizard-content">
            <form method="POST" id="wizardForm">
                <?php csrf_field(); ?>
                
                <?php if ($msg): ?>
                    <div class="alert-success" style="margin-bottom: 20px;">
                        <i class="fa fa-check-circle" style="font-size: 3rem; margin-bottom: 12px;"></i><br>
                        <strong><?= htmlspecialchars($msg) ?></strong><br><br>
                        <a href="marketing-campaigns.php" class="btn-mc btn-next">الانتقال إلى الحملات</a>
                    </div>
                <?php elseif ($step === 1): ?>
                    <div class="wizard-title">اختيار هدف الحملة</div>
                    <div class="form-group">
                        <label class="form-label">اسم الحملة</label>
                        <input type="text" name="campaign_name" class="form-control" placeholder="حملة العيد 2026..." required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الهدف التسويقي (Objective)</label>
                        <div class="objective-grid">
                            <div class="objective-card selected" onclick="selectObjective(this, 'OUTCOME_SALES')">
                                <div class="objective-icon"><i class="fa fa-shopping-cart"></i></div>
                                <div class="objective-title">مبيعات (Sales)</div>
                                <div class="objective-desc">العثور على أشخاص من المحتمل أن يشتروا منتجك.</div>
                            </div>
                            <div class="objective-card" onclick="selectObjective(this, 'OUTCOME_LEADS')">
                                <div class="objective-icon"><i class="fa fa-users"></i></div>
                                <div class="objective-title">تجميع بيانات (Leads)</div>
                                <div class="objective-desc">جمع معلومات عن عملاء محتملين للاتصال بهم.</div>
                            </div>
                            <div class="objective-card" onclick="selectObjective(this, 'OUTCOME_AWARENESS')">
                                <div class="objective-icon"><i class="fa fa-eye"></i></div>
                                <div class="objective-title">الوعي (Awareness)</div>
                                <div class="objective-desc">عرض إعلانك لأكبر عدد من الأشخاص.</div>
                            </div>
                        </div>
                        <input type="hidden" name="objective" id="objectiveInput" value="OUTCOME_SALES">
                    </div>

                <?php elseif ($step === 2): ?>
                    <div class="wizard-title">الميزانية والجدول الزمني</div>
                    <!-- Mockup UI for Step 2 -->
                    <div class="form-group">
                        <label class="form-label">الميزانية اليومية (<?= $account['currency'] ?>)</label>
                        <input type="number" name="daily_budget" class="form-control" value="2000" min="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ البدء</label>
                        <input type="date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                <?php elseif ($step === 5): ?>
                    <div class="wizard-title">المراجعة والنشر</div>
                    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                        <h4 style="margin-top: 0;">ملخص الحملة</h4>
                        <p><strong>اسم الحملة:</strong> سيتم سحب البيانات من السشن</p>
                        <p><strong>الهدف:</strong> مبيعات</p>
                        <p><strong>الميزانية:</strong> 2000 دج / يوم</p>
                        <p><strong>الجمهور:</strong> الجزائر، العمر 18-45</p>
                    </div>
                <?php else: ?>
                    <div class="wizard-title">الخطوة <?= $step ?> (قيد التطوير)</div>
                    <p>في هذه الخطوة سيتم إضافة إعدادات متقدمة حسب متطلبات Meta.</p>
                <?php endif; ?>

                <?php if ($step < 6): ?>
                <div class="wizard-footer">
                    <?php if ($step > 1): ?>
                        <a href="?step=<?= $step - 1 ?>" class="btn-mc btn-prev"><i class="fa fa-arrow-right"></i> السابق</a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    
                    <?php if ($step < 5): ?>
                        <a href="?step=<?= $step + 1 ?>" class="btn-mc btn-next">التالي <i class="fa fa-arrow-left"></i></a>
                    <?php else: ?>
                        <button type="submit" name="publish" class="btn-mc btn-next" style="background: #16a34a;"><i class="fa fa-rocket"></i> نشر الحملة</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
function selectObjective(el, val) {
    document.querySelectorAll('.objective-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('objectiveInput').value = val;
}
</script>
<?php require_once('footer.php'); ?>
