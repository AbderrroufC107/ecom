<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();
$engine = new \Marketing\AutomationEngine($pdo);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['run_automation'])) {
        $triggered = $engine->evaluate($tenantId);
        $count = count($triggered);
        $msg = "تم تشغيل القواعد بنجاح. قواعد تفاعلت: $count";
        if ($count > 0) {
            $msg .= ' (' . implode(', ', $triggered) . ')';
        }
    } elseif (isset($_POST['delete_rule'])) {
        $ruleId = (int)$_POST['rule_id'];
        $stmt = $pdo->prepare("DELETE FROM tbl_meta_automation_rules WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$ruleId, $tenantId]);
        $msg = "تم حذف القاعدة بنجاح.";
    } elseif (isset($_POST['toggle_rule'])) {
        $ruleId = (int)$_POST['rule_id'];
        $isActive = (int)$_POST['is_active'];
        $newActive = $isActive ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE tbl_meta_automation_rules SET is_active = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$newActive, $ruleId, $tenantId]);
        $msg = "تم تغيير حالة القاعدة بنجاح.";
    } elseif (isset($_POST['add_rule'])) {
        $name = trim($_POST['name']);
        $scope = $_POST['scope'];
        $metric = $_POST['metric'];
        $operator = $_POST['operator'];
        $threshold = (float)$_POST['threshold'];
        $period = $_POST['period'];
        $channels = $_POST['channels'] ?? ['dashboard'];
        $message = trim($_POST['message']);

        $condition = json_encode([
            'metric' => $metric,
            'operator' => $operator,
            'threshold' => $threshold,
            'period' => $period
        ]);
        $action = json_encode([
            'message' => $message,
            'severity' => 'WARNING'
        ]);
        $alert_channels = json_encode($channels);

        $stmt = $pdo->prepare("INSERT INTO tbl_meta_automation_rules (tenant_id, name, is_active, scope, condition_json, action_json, alert_channels, created_at) VALUES (?, ?, 1, ?, ?, ?, ?, NOW())");
        $stmt->execute([$tenantId, $name, $scope, $condition, $action, $alert_channels]);
        $msg = "تم إضافة القاعدة بنجاح.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM tbl_meta_automation_rules WHERE tenant_id = ? ORDER BY created_at DESC");
$stmt->execute([$tenantId]);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #f59e0b 0%, #ea580c 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }
.btn-mc { padding: 8px 18px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s; }
.btn-mc-white { background: white; color: #ea580c; }
.btn-mc-white:hover { background: #fff7ed; color: #ea580c; }
.btn-mc-primary { background: #1877f2; color: white; }
.btn-mc-primary:hover { background: #1467d9; }

.mc-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
@media (max-width: 900px) { .mc-grid { grid-template-columns: 1fr; } }

.mc-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 24px; }
.mc-card-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0f172a; display: flex; justify-content: space-between; align-items: center; }
.mc-card-body { padding: 20px; }

.mc-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
.mc-table th { padding: 12px 16px; text-align: right; color: #475569; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
.mc-table td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.9rem; box-sizing: border-box; }
.form-control:focus { border-color: #1877f2; outline: none; }
.checkbox-list { display: flex; gap: 16px; margin-top: 6px; }
.checkbox-item { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #475569; }

.alert-msg { padding: 12px 16px; border-radius: 8px; background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; margin-bottom: 20px; font-weight: 600; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-bolt"></i> الأتمتة (Automation)</h1>
            <p>قواعد تلقائية لمراقبة الحملات وتنبيهك عند تجاوز الحدود</p>
        </div>
        <form method="POST" style="margin: 0;">
            <?php csrf_field(); ?>
            <button type="submit" name="run_automation" class="btn-mc btn-mc-white">
                <i class="fa fa-play"></i> تشغيل القواعد الآن
            </button>
        </form>
    </div>

    <?php if ($msg): ?>
        <div class="alert-msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="mc-grid">
        <!-- Rules List -->
        <div class="mc-card">
            <div class="mc-card-header">
                <span><i class="fa fa-list"></i> القواعد الحالية</span>
            </div>
            <div class="mc-card-body" style="padding: 0;">
                <?php if (empty($rules)): ?>
                    <div style="padding: 30px; text-align: center; color: #94a3b8;">لا توجد قواعد أتمتة حالياً.</div>
                <?php else: ?>
                    <table class="mc-table">
                        <thead>
                            <tr>
                                <th>اسم القاعدة</th>
                                <th>الشرط</th>
                                <th>التنبيه عبر</th>
                                <th>الحالة</th>
                                <th>تفعيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $r): ?>
                                <?php
                                $cond = json_decode($r['condition_json'], true);
                                $chan = json_decode($r['alert_channels'], true);
                                $metric = $cond['metric'] ?? '';
                                $op = $cond['operator'] ?? '';
                                $val = $cond['threshold'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($r['name']) ?></strong><br>
                                        <small style="color:#64748b"><?= $r['scope'] ?></small>
                                    </td>
                                    <td dir="ltr" style="text-align:right">
                                        <?= $metric ?> <?= $op ?> <?= $val ?>
                                    </td>
                                    <td>
                                        <?php foreach($chan as $c): ?>
                                            <span style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:0.75rem;margin-left:4px;"><?= $c ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['is_active']): ?>
                                            <span style="color:#16a34a;font-weight:600;"><i class="fa fa-check-circle"></i> نشط</span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;font-weight:600;"><i class="fa fa-pause-circle"></i> متوقف</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline-block;">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $r['is_active'] ?>">
                                            <button type="submit" name="toggle_rule" style="background:none;border:none;color:#1877f2;cursor:pointer;margin-left:8px;" title="تفعيل/تعطيل">
                                                <i class="fa fa-exchange"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('تأكيد الحذف؟')">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="rule_id" value="<?= $r['id'] ?>">
                                            <button type="submit" name="delete_rule" style="background:none;border:none;color:#ef4444;cursor:pointer;" title="حذف">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Rule Form -->
        <div class="mc-card">
            <div class="mc-card-header">
                <span><i class="fa fa-plus-circle"></i> إضافة قاعدة جديدة</span>
            </div>
            <div class="mc-card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="form-group">
                        <label class="form-label">اسم القاعدة</label>
                        <input type="text" name="name" class="form-control" required placeholder="مثال: تنبيه ROAS منخفض">
                    </div>
                    <div class="form-group">
                        <label class="form-label">النطاق (Scope)</label>
                        <select name="scope" class="form-control">
                            <option value="ACCOUNT">كامل الحساب (Account)</option>
                            <option value="CAMPAIGN">على مستوى الحملة</option>
                        </select>
                    </div>
                    
                    <div style="display:flex;gap:10px;margin-bottom:16px;">
                        <div style="flex:1;">
                            <label class="form-label">المقياس</label>
                            <select name="metric" class="form-control">
                                <option value="roas">ROAS</option>
                                <option value="cpc">تكلفة النقر (CPC)</option>
                                <option value="spend">الإنفاق</option>
                                <option value="leads">Leads</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label class="form-label">الشرط</label>
                            <select name="operator" class="form-control">
                                <option value="<">أقل من (<)</option>
                                <option value=">">أكبر من (>)</option>
                                <option value="=">يساوي (=)</option>
                            </select>
                        </div>
                        <div style="flex:1;">
                            <label class="form-label">القيمة</label>
                            <input type="number" step="0.01" name="threshold" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">الفترة (البيانات التي سيتم حسابها)</label>
                        <select name="period" class="form-control">
                            <option value="today">اليوم (Today)</option>
                            <option value="yesterday">أمس (Yesterday)</option>
                            <option value="last_7d">آخر 7 أيام</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">رسالة التنبيه</label>
                        <textarea name="message" class="form-control" rows="2" required placeholder="نص التنبيه..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">قنوات التنبيه</label>
                        <div class="checkbox-list">
                            <label class="checkbox-item"><input type="checkbox" name="channels[]" value="dashboard" checked> Dashboard</label>
                            <label class="checkbox-item"><input type="checkbox" name="channels[]" value="telegram"> Telegram</label>
                        </div>
                    </div>

                    <button type="submit" name="add_rule" class="btn-mc btn-mc-primary" style="width: 100%; justify-content: center;">
                        <i class="fa fa-save"></i> إضافة القاعدة
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
