<?php
require_once('header.php');
require_once('inc/employee_functions.php');

employee_ensure_tables($pdo);

if (!function_exists('telegram_ensure_tables')) {
    require_once('inc/telegram_bot.php');
}
telegram_ensure_tables($pdo);

$error_message = '';
$success_message = '';

$search = trim($_GET['search'] ?? '');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$employee_id = (int) ($_POST['employee_id'] ?? ($_GET['id'] ?? 0));

if ($action === 'auto_assign') {
    $count = employee_auto_assign_unassigned($pdo);
    $success_message = "تم توزيع $count طلب على الموظفين.";
} elseif ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
        $is_active = !empty($_POST['is_active']) ? 1 : 0;

        if ($full_name === '') throw new Exception('الاسم الكامل مطلوب.');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('البريد الإلكتروني غير صالح.');
        if ($password === '') throw new Exception('كلمة المرور مطلوبة.');
        if (strlen($password) < 6) throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل.');

        if (employee_find_by_email($pdo, $email)) {
            throw new Exception('البريد الإلكتروني مستخدم بالفعل.');
        }

        employee_create($pdo, [
            'full_name' => $full_name,
            'email' => $email,
            'password' => $password,
            'telegram_chat_id' => $telegram_chat_id,
            'is_active' => $is_active
        ]);

        $success_message = 'تم إضافة الموظف بنجاح.';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
} elseif ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $telegram_chat_id = trim($_POST['telegram_chat_id'] ?? '');
        $is_active = !empty($_POST['is_active']) ? 1 : 0;

        if ($employee_id <= 0) throw new Exception('معرّف الموظف غير صالح.');
        if ($full_name === '') throw new Exception('الاسم الكامل مطلوب.');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('البريد الإلكتروني غير صالح.');

        $existing = employee_find_by_email($pdo, $email, $employee_id);
        if ($existing) throw new Exception('البريد الإلكتروني مستخدم بالفعل.');

        if ($password !== '' && strlen($password) < 6) {
            throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل.');
        }

        employee_update($pdo, $employee_id, [
            'full_name' => $full_name,
            'email' => $email,
            'password' => $password !== '' ? $password : '',
            'telegram_chat_id' => $telegram_chat_id,
            'is_active' => $is_active
        ]);

        $success_message = 'تم تحديث بيانات الموظف بنجاح.';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
} elseif ($action === 'test_telegram' && $employee_id > 0) {
    $test_result = telegram_send_test($pdo, $employee_id);
    if ($test_result['success']) {
        $success_message = $test_result['message'];
    } else {
        $error_message = $test_result['message'];
    }
} elseif ($action === 'delete' && $employee_id > 0) {
    try {
        $assign_check = $pdo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE employee_id = ?");
        $assign_check->execute([$employee_id]);
        if ((int) $assign_check->fetchColumn() > 0) {
            $pdo->prepare("UPDATE tbl_employee SET is_active = 0 WHERE id = ?")->execute([$employee_id]);
            $success_message = 'لا يمكن حذف موظف لديه طلبات. تم تعطيل الحساب بدلاً من ذلك.';
        } else {
            employee_delete($pdo, $employee_id);
            $success_message = 'تم حذف الموظف بنجاح.';
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

if ($search !== '') {
    $employees = employee_search($pdo, $search);
} else {
    $employees = employee_get_all($pdo);
}

$all_stats = employee_get_all_stats($pdo);
$unassigned_count = employee_get_unassigned_orders_count($pdo);
?>

<style>
.emp-wrap { direction: rtl; text-align: right; font-family: 'Cairo', sans-serif; }
.emp-hero { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.emp-hero h3 { margin: 0; font-weight: 800; color: #0f172a; }
.emp-hero p { margin: 6px 0 0; color: #64748b; }
.emp-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.emp-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 8px 24px rgba(15,23,42,.06); padding: 18px; margin-bottom: 18px; }
.emp-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 18px; }
.emp-stat { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; text-align: center; }
.emp-stat strong { display: block; font-size: 24px; font-weight: 800; color: #0f172a; }
.emp-stat small { color: #64748b; font-size: 12px; }
.emp-stat.is-primary { border-color: #0f766e; background: linear-gradient(135deg, #f0fdfa, #fff); }
.emp-stat.is-warning { border-color: #f59e0b; background: linear-gradient(135deg, #fffbeb, #fff); }
.emp-table th { background: #f8fafc; font-weight: 700; white-space: nowrap; }
.emp-table td { vertical-align: middle; }
.emp-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.emp-badge.is-active { background: #dcfce7; color: #15803d; }
.emp-badge.is-inactive { background: #fef2f2; color: #b91c1c; }
.emp-modal-lg { max-width: 600px; }
.emp-search-form { display: flex; gap: 8px; align-items: center; }
.emp-search-form input { min-width: 220px; }
.emp-stat-table td { padding: 4px 8px; font-size: 13px; }
.emp-stat-table td:first-child { font-weight: 700; }
</style>

<section class="content emp-wrap">
    <div class="emp-hero">
        <div>
            <h3><i class="fa fa-users"></i> إدارة الموظفين</h3>
            <p>إضافة وتعديل وتعطيل الموظفين وعرض إحصائيات التوزيع.</p>
        </div>
        <div class="emp-actions">
            <form class="emp-search-form" method="get">
                <input type="text" name="search" class="form-control input-sm" placeholder="بحث بالاسم أو البريد..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-search"></i></button>
                <?php if ($search !== ''): ?>
                    <a href="employees.php" class="btn btn-default btn-sm"><i class="fa fa-times"></i></a>
                <?php endif; ?>
            </form>
            <a href="employees.php?action=auto_assign" class="btn btn-primary btn-sm" onclick="return confirm('توزيع الطلبات غير الموزعة على الموظفين؟');"><i class="fa fa-random"></i> توزيع تلقائي</a>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addEmployeeModal"><i class="fa fa-plus"></i> إضافة موظف</button>
        </div>
    </div>

    <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($success_message !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="emp-stats">
        <div class="emp-stat is-primary">
            <strong><?php echo count($all_stats); ?></strong>
            <small>الموظفون النشطون</small>
        </div>
        <div class="emp-stat is-primary">
            <strong><?php echo array_sum(array_column($all_stats, 'total_assigned')); ?></strong>
            <small>إجمالي الطلبات الموزعة</small>
        </div>
        <div class="emp-stat is-warning">
            <strong><?php echo $unassigned_count; ?></strong>
            <small>طلبات غير موزعة</small>
        </div>
        <?php
        $all_pending = array_sum(array_column($all_stats, 'pending'));
        $all_completed = array_sum(array_column($all_stats, 'completed'));
        $all_cancelled = array_sum(array_column($all_stats, 'cancelled'));
        ?>
        <div class="emp-stat"><strong><?php echo $all_pending; ?></strong><small>قيد الانتظار</small></div>
        <div class="emp-stat"><strong><?php echo $all_completed; ?></strong><small>مكتملة</small></div>
        <div class="emp-stat"><strong><?php echo $all_cancelled; ?></strong><small>ملغاة</small></div>
    </div>

    <div class="emp-card">
        <div class="table-responsive">
            <table class="table table-bordered table-hover emp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>الاسم الكامل</th>
                        <th>البريد الإلكتروني</th>
                        <th>حالة التيليجرام</th>
                        <th>الحالة</th>
                        <th>تاريخ الإضافة</th>
                        <th>الإحصائيات</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="8" class="text-center text-muted">لا يوجد موظفون بعد.</td></tr>
                    <?php else: $i = 0; foreach ($employees as $emp): $i++;
                        $stats = employee_get_stats($pdo, (int) $emp['id']);
                    ?>
                        <tr>
                            <td><?php echo $i; ?></td>
                            <td><strong><?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                            <td><?php echo htmlspecialchars($emp['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo telegram_get_status_html($emp); ?></td>
                            <td>
                                <span class="emp-badge <?php echo $emp['is_active'] ? 'is-active' : 'is-inactive'; ?>">
                                    <?php echo $emp['is_active'] ? 'نشط' : 'معطل'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($emp['created_at'])); ?></td>
                            <td>
                                <table class="emp-stat-table" style="width:100%">
                                    <tr><td>الكل</td><td><?php echo $stats['total_assigned']; ?></td><td>معلق</td><td><?php echo $stats['pending']; ?></td></tr>
                                    <tr><td>مؤكد</td><td><?php echo $stats['confirmed']; ?></td><td>مكتمل</td><td><?php echo $stats['completed']; ?></td></tr>
                                    <tr><td>ملغي</td><td><?php echo $stats['cancelled']; ?></td><td>مرتجع</td><td><?php echo $stats['returned']; ?></td></tr>
                                </table>
                            </td>
                            <td>
                                <a href="employees.php?action=test_telegram&id=<?php echo (int) $emp['id']; ?>" class="btn btn-default btn-xs" title="إرسال رسالة اختبار"><i class="fa fa-paper-plane"></i></a>
                                <button class="btn btn-primary btn-xs" onclick="editEmployee(<?php echo (int) $emp['id']; ?>)"><i class="fa fa-edit"></i></button>
                                <a href="employees.php?action=delete&id=<?php echo (int) $emp['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد؟');"><i class="fa fa-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="modal fade" id="addEmployeeModal" tabindex="-1">
    <div class="modal-dialog emp-modal-lg">
        <div class="modal-content">
            <form method="post" action="employees.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة موظف جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">معرّف تيليجرام</label>
                            <input type="text" name="telegram_chat_id" class="form-control" placeholder="اختياري">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="addIsActive" checked>
                            <label class="form-check-label" for="addIsActive">نشط</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editEmployeeModal" tabindex="-1">
    <div class="modal-dialog emp-modal-lg">
        <div class="modal-content">
            <form method="post" action="employees.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="employee_id" id="editEmployeeId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title">تعديل بيانات الموظف</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="editFullName" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="editEmail" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">كلمة المرور (اترك فارغاً إن لم ترد التغيير)</label>
                            <input type="password" name="password" class="form-control" minlength="6">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">معرّف تيليجرام</label>
                            <input type="text" name="telegram_chat_id" id="editTelegram" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" class="form-check-input" value="1" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">نشط</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var employeesData = <?php echo json_encode($employees, JSON_UNESCAPED_UNICODE); ?>;

function editEmployee(id) {
    var emp = employeesData.find(function(e) { return parseInt(e.id) === id; });
    if (!emp) return;
    document.getElementById('editEmployeeId').value = emp.id;
    document.getElementById('editFullName').value = emp.full_name;
    document.getElementById('editEmail').value = emp.email;
    document.getElementById('editTelegram').value = emp.telegram_chat_id || '';
    document.getElementById('editIsActive').checked = parseInt(emp.is_active) === 1;
    var modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
    modal.show();
}
</script>

<?php require_once('footer.php'); ?>
