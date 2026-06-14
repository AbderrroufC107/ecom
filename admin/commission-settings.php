<?php require_once('header.php'); ?>
<?php
require_once('inc/employee_functions.php');
require_once('inc/performance_functions.php');

performance_ensure_tables($pdo);

$error_message = '';
$success_message = '';

$employees = employee_get_all($pdo, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_commission'])) {
            $fields = [
                'commission_enabled', 'commission_mode', 'commission_fixed_amount',
                'commission_percentage', 'commission_min_order_amount',
            ];
            foreach ($fields as $key) {
                $val = trim((string) ($_POST[$key] ?? ''));
                performance_set_setting($pdo, $key, $val);
            }
            $success_message = 'تم حفظ إعدادات العمولة.';
        }

        if (isset($_POST['mark_paid'])) {
            $employee_id = (int) ($_POST['employee_id'] ?? 0);
            $amount = (float) ($_POST['amount'] ?? 0);
            $notes = trim((string) ($_POST['notes'] ?? ''));
            $paid_by = $_SESSION['user']['full_name'] ?? 'admin';

            if ($employee_id > 0 && $amount > 0) {
                $stmt = $pdo->prepare("INSERT INTO tbl_commission_payment (employee_id, amount, paid_by, notes) VALUES (?, ?, ?, ?)");
                $stmt->execute([$employee_id, $amount, $paid_by, $notes]);
                $payment_id = (int) $pdo->lastInsertId();
                if ($payment_id > 0 && function_exists('audit_log_commission')) {
                    audit_log_commission($pdo, $payment_id, 'commission_paid', null, json_encode(['employee_id' => $employee_id, 'amount' => $amount, 'paid_by' => $paid_by], JSON_UNESCAPED_UNICODE), 'admin_panel', (int) ($_SESSION['user']['id'] ?? 0));
                }
                $success_message = 'تم تسجيل الدفعة بنجاح.';
            } else {
                $error_message = 'يرجى اختيار الموظف وإدخال المبلغ.';
            }
        }
    } catch (PDOException $e) {
        $error_message = 'خطأ: ' . $e->getMessage();
    }
}

$commission_enabled = performance_get_setting_bool($pdo, 'commission_enabled');
$commission_mode = performance_get_setting($pdo, 'commission_mode', 'percentage');
$commission_fixed = performance_get_setting($pdo, 'commission_fixed_amount', '0');
$commission_pct = performance_get_setting($pdo, 'commission_percentage', '5');
$commission_min = performance_get_setting($pdo, 'commission_min_order_amount', '0');

$stmt = $pdo->query("
    SELECT ec.*, e.full_name, o.customer_name, o.product_name
    FROM tbl_employee_commission ec
    LEFT JOIN tbl_employee e ON e.id = ec.employee_id
    LEFT JOIN tbl_order o ON o.id = ec.order_id
    ORDER BY ec.created_at DESC
    LIMIT 100
");
$commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT cp.*, e.full_name
    FROM tbl_commission_payment cp
    LEFT JOIN tbl_employee e ON e.id = cp.employee_id
    ORDER BY cp.paid_at DESC
    LIMIT 50
");
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$employee_summaries = [];
foreach ($employees as $emp) {
    $cs = performance_get_commission_summary($pdo, (int) $emp['id']);
    $employee_summaries[] = [
        'id' => (int) $emp['id'],
        'full_name' => $emp['full_name'],
        'total_unpaid' => $cs['total_unpaid'],
        'total_paid' => $cs['total_paid'],
        'this_month' => $cs['this_month'],
    ];
}
?>
<section class="content-header">
    <h1>إعدادات العمولة</h1>
</section>

<section class="content">
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">إعدادات نظام العمولة</h3>
                </div>
                <form method="post">
                    <div class="box-body">
                        <div class="form-group">
                            <label>تفعيل نظام العمولة</label>
                            <select name="commission_enabled" class="form-control">
                                <option value="1" <?php echo $commission_enabled ? 'selected' : ''; ?>>مفعل</option>
                                <option value="0" <?php echo !$commission_enabled ? 'selected' : ''; ?>>معطل</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>نظام العمولة</label>
                            <select name="commission_mode" class="form-control" id="commission_mode">
                                <option value="percentage" <?php echo $commission_mode === 'percentage' ? 'selected' : ''; ?>>نسبة مئوية</option>
                                <option value="fixed" <?php echo $commission_mode === 'fixed' ? 'selected' : ''; ?>>مبلغ ثابت</option>
                                <option value="hybrid" <?php echo $commission_mode === 'hybrid' ? 'selected' : ''; ?>>نظام هجين (ثابت + نسبة)</option>
                            </select>
                        </div>
                        <div class="form-group" id="fixed_group">
                            <label>المبلغ الثابت لكل توصيلة (دج)</label>
                            <input type="number" step="0.01" name="commission_fixed_amount" class="form-control"
                                value="<?php echo htmlspecialchars($commission_fixed, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group" id="percentage_group">
                            <label>نسبة العمولة (%)</label>
                            <input type="number" step="0.1" name="commission_percentage" class="form-control"
                                value="<?php echo htmlspecialchars($commission_pct, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-group">
                            <label>الحد الأدنى لقيمة الطلب لاحتساب العمولة (دج)</label>
                            <input type="number" step="0.01" name="commission_min_order_amount" class="form-control"
                                value="<?php echo htmlspecialchars($commission_min, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="save_commission" class="btn btn-primary">حفظ</button>
                    </div>
                </form>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">ملخص العمولات لكل موظف</h3>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>الموظف</th>
                                <th>هذا الشهر</th>
                                <th>غير مدفوعة</th>
                                <th>مدفوعة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employee_summaries as $es): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($es['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo number_format($es['this_month'], 2); ?> دج</td>
                                    <td><?php echo number_format($es['total_unpaid'], 2); ?> دج</td>
                                    <td><?php echo number_format($es['total_paid'], 2); ?> دج</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">تسجيل دفعة</h3>
                </div>
                <form method="post">
                    <div class="box-body">
                        <div class="form-group">
                            <label>الموظف</label>
                            <select name="employee_id" class="form-control" required>
                                <option value="">اختر الموظف</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo (int) $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>المبلغ (دج)</label>
                            <input type="number" step="0.01" name="amount" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="mark_paid" class="btn btn-success">تسجيل الدفعة</button>
                    </div>
                </form>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">سجل العمولات</h3>
                </div>
                <div class="box-body table-responsive no-padding" style="max-height:400px;overflow-y:auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الموظف</th>
                                <th>الطلب</th>
                                <th>النوع</th>
                                <th>المبلغ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($commissions)): ?>
                                <tr><td colspan="5" class="text-center">لا توجد عمولات بعد</td></tr>
                            <?php else: ?>
                                <?php foreach ($commissions as $c): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($c['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>#<?php echo (int) $c['order_id']; ?></td>
                                        <td><?php echo htmlspecialchars($c['commission_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $c['commission_amount'], 2); ?> دج</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">سجل المدفوعات</h3>
                </div>
                <div class="box-body table-responsive no-padding" style="max-height:300px;overflow-y:auto;">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الموظف</th>
                                <th>المبلغ</th>
                                <th>مدفوع من قبل</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr><td colspan="5" class="text-center">لا توجد مدفوعات بعد</td></tr>
                            <?php else: ?>
                                <?php foreach ($payments as $p): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($p['paid_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($p['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo number_format((float) $p['amount'], 2); ?> دج</td>
                                        <td><?php echo htmlspecialchars($p['paid_by'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($p['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    var sel = document.getElementById('commission_mode');
    function toggleFields() {
        var v = sel.value;
        document.getElementById('fixed_group').style.display = (v === 'fixed' || v === 'hybrid') ? 'block' : 'none';
        document.getElementById('percentage_group').style.display = (v === 'percentage' || v === 'hybrid') ? 'block' : 'none';
    }
    sel.addEventListener('change', toggleFields);
    toggleFields();
})();
</script>
<?php require_once('footer.php'); ?>
