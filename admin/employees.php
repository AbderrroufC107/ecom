<?php
require_once('header.php');
require_once('inc/employee_functions.php');

employee_ensure_tables($pdo);

if (!function_exists('telegram_ensure_tables')) {
    if (file_exists('inc/telegram_bot.php')) {
        require_once('inc/telegram_bot.php');
    }
}
telegram_ensure_tables($pdo);

$error_message = '';
$success_message = '';

$current_manager_id = (int)($_SESSION['user']['id'] ?? 0);
$is_super_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin');
$effective_manager_id = $is_super_admin ? null : $current_manager_id;

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

        $new_emp_id = employee_create($pdo, [
            'full_name' => $full_name,
            'email' => $email,
            'password' => $password,
            'telegram_chat_id' => $telegram_chat_id,
            'is_active' => $is_active,
            'manager_id' => $effective_manager_id
        ]);

        if (isset($_POST['products_tab_present'])) {
            $products_array = isset($_POST['allowed_products']) && is_array($_POST['allowed_products']) ? $_POST['allowed_products'] : [];
            employee_update_products($pdo, $new_emp_id, $products_array);
        }

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

        if (isset($_POST['products_tab_present'])) {
            $products_array = isset($_POST['allowed_products']) && is_array($_POST['allowed_products']) ? $_POST['allowed_products'] : [];
            employee_update_products($pdo, $employee_id, $products_array);
        }

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
        $assign_check = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE employee_id = ?");
        $assign_check->execute([$employee_id]);
        if ((int) $assign_check->fetchColumn() > 0) {
            $dbRepo->prepare("UPDATE tbl_employee SET is_active = 0 WHERE id = ?")->execute([$employee_id]);
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
    $employees = employee_get_all($pdo, false, $effective_manager_id);
}

foreach ($employees as &$emp_row) {
    $emp_row['allowed_products'] = employee_get_allowed_products($pdo, (int)$emp_row['id']);
}
unset($emp_row);

$all_active_products = $dbRepo->query("SELECT p_id, p_name FROM tbl_product WHERE p_is_active = 1 ORDER BY p_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$prod_names_map = array_column($all_active_products, 'p_name', 'p_id');

$all_stats = employee_get_all_stats($pdo, $effective_manager_id);
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
            <button class="btn btn-success btn-sm js-open-add-employee"><i class="fa fa-plus"></i> إضافة موظف</button>
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
                        <th>صلاحيات المنتجات</th>
                        <th>تاريخ الإضافة</th>
                        <th>الإحصائيات</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees)): ?>
                        <tr><td colspan="9" class="text-center text-muted">لا يوجد موظفون بعد.</td></tr>
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
                            <td>
                                <?php
                                $allowed_cnt = count($emp['allowed_products'] ?? []);
                                if ($allowed_cnt === 0) {
                                    echo '<span class="label label-info" style="background:#0284c7;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;display:inline-block;">جميع المنتجات</span>';
                                } else {
                                    $p_titles = [];
                                    foreach ($emp['allowed_products'] as $pid) {
                                        $p_titles[] = $prod_names_map[$pid] ?? ("منتج #" . $pid);
                                    }
                                    echo '<span class="label label-warning" style="background:#d97706;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;display:inline-block;cursor:help;" title="المنتجات: ' . htmlspecialchars(implode('، ', $p_titles), ENT_QUOTES, 'UTF-8') . '">محدد بـ ' . $allowed_cnt . ' منتجات</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($emp['created_at'])); ?></td>
                            <td>
                                <table class="emp-stat-table" style="width:100%">
                                    <tr><td>الكل</td><td><?php echo $stats['total_assigned']; ?></td><td>معلق</td><td><?php echo $stats['pending']; ?></td></tr>
                                    <tr><td>مؤكد</td><td><?php echo $stats['confirmed']; ?></td><td>مكتمل</td><td><?php echo $stats['completed']; ?></td></tr>
                                    <tr><td>ملغي</td><td><?php echo $stats['cancelled']; ?></td><td>مرتجع</td><td><?php echo $stats['returned']; ?></td></tr>
                                </table>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="employees.php?action=test_telegram&id=<?php echo (int) $emp['id']; ?>" class="btn btn-default btn-xs" title="إرسال رسالة اختبار"><i class="fa fa-paper-plane"></i> تجربة</a>
                                <button class="btn btn-primary btn-xs" onclick="editEmployee(<?php echo (int) $emp['id']; ?>)"><i class="fa fa-edit"></i> تعديل</button>
                                <a href="employees.php?action=delete&id=<?php echo (int) $emp['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد من حذف هذا الموظف؟');"><i class="fa fa-trash"></i> حذف</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="professional-modal" id="addEmployeeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog emp-modal-lg" role="document">
        <form method="post" action="employees.php" style="display:contents;">
            <input type="hidden" name="action" value="create">
            <?php csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title"><?php echo htmlspecialchars($l['emp_modal_add_title'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="close-btn js-dismiss-employee-modal" aria-label="إغلاق"><i class="fa fa-times"></i></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" style="margin-bottom: 15px; border-bottom: 1px solid #e5e7eb; display: flex; gap: 15px; list-style: none; padding: 0;">
                    <li><a href="#" onclick="switchAddTab('basic'); return false;" id="add_tab_link_basic" style="text-decoration: none; padding-bottom: 8px; display: inline-block; font-weight: bold; color: #0f766e; border-bottom: 2px solid #0f766e;">البيانات الأساسية</a></li>
                    <li><a href="#" onclick="switchAddTab('products'); return false;" id="add_tab_link_products" style="text-decoration: none; padding-bottom: 8px; display: inline-block; color: #64748b;">صلاحيات المنتجات (اختياري)</a></li>
                </ul>
                <div id="add_tab_basic">
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
                <div id="add_tab_products" style="display:none;">
                    <input type="hidden" name="products_tab_present" value="1">
                    <div class="alert alert-info" style="font-size: 13px; margin-bottom: 10px; background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; padding: 10px; border-radius: 8px;">
                        <i class="fa fa-info-circle"></i> الوضع الافتراضي: إذا لم يتم تحديد أي منتج، يُسمح للموظف بالعمل على <strong>جميع المنتجات</strong>.
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; gap: 10px;">
                        <input type="text" id="addProductSearch" class="form-control input-sm" placeholder="ابحث عن منتج..." style="flex:1;" onkeyup="filterProductsList('add')">
                        <div style="white-space:nowrap;">
                            <button type="button" class="btn btn-default btn-xs" onclick="selectAllProducts('add', true)">تحديد الكل</button>
                            <button type="button" class="btn btn-default btn-xs" onclick="selectAllProducts('add', false)">إلغاء تحديد الكل</button>
                        </div>
                    </div>
                    <div style="max-height: 220px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 10px; border-radius: 6px; background: #f8fafc;">
                        <?php foreach ($all_active_products as $prod): ?>
                            <div class="form-check product-check-item" data-name="<?php echo htmlspecialchars($prod['p_name'], ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 6px;">
                                <input type="checkbox" name="allowed_products[]" value="<?php echo (int)$prod['p_id']; ?>" class="form-check-input add-prod-cb" id="add_prod_<?php echo (int)$prod['p_id']; ?>">
                                <label class="form-check-label" for="add_prod_<?php echo (int)$prod['p_id']; ?>" style="font-weight: normal; cursor: pointer;">
                                    <?php echo htmlspecialchars($prod['p_name'], ENT_QUOTES, 'UTF-8'); ?> <small class="text-muted">(#<?php echo (int)$prod['p_id']; ?>)</small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default js-dismiss-employee-modal"><?php echo htmlspecialchars($l['cancel'] ?? 'إلغاء', ENT_QUOTES, 'UTF-8'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($l['save'] ?? 'حفظ', ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="professional-modal" id="editEmployeeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog emp-modal-lg" role="document">
        <form method="post" action="employees.php" style="display:contents;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="employee_id" id="editEmployeeId" value="0">
            <?php csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title"><?php echo htmlspecialchars($l['emp_modal_edit_title'] ?? 'تعديل بيانات الموظف', ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="close-btn js-dismiss-employee-modal" aria-label="إغلاق"><i class="fa fa-times"></i></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" style="margin-bottom: 15px; border-bottom: 1px solid #e5e7eb; display: flex; gap: 15px; list-style: none; padding: 0;">
                    <li><a href="#" onclick="switchEditTab('basic'); return false;" id="edit_tab_link_basic" style="text-decoration: none; padding-bottom: 8px; display: inline-block; font-weight: bold; color: #0f766e; border-bottom: 2px solid #0f766e;">البيانات الأساسية</a></li>
                    <li><a href="#" onclick="switchEditTab('products'); return false;" id="edit_tab_link_products" style="text-decoration: none; padding-bottom: 8px; display: inline-block; color: #64748b;">صلاحيات المنتجات (اختياري)</a></li>
                </ul>
                <div id="edit_tab_basic">
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
                <div id="edit_tab_products" style="display:none;">
                    <input type="hidden" name="products_tab_present" value="1">
                    <div class="alert alert-info" style="font-size: 13px; margin-bottom: 10px; background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; padding: 10px; border-radius: 8px;">
                        <i class="fa fa-info-circle"></i> الوضع الافتراضي: إذا لم يتم تحديد أي منتج، يُسمح للموظف بالعمل على <strong>جميع المنتجات</strong>. عند تحديد منتج واحد أو أكثر، سيُقيد الموظف بهذه المنتجات فقط.
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px; gap: 10px;">
                        <input type="text" id="editProductSearch" class="form-control input-sm" placeholder="ابحث عن منتج..." style="flex:1;" onkeyup="filterProductsList('edit')">
                        <div style="white-space:nowrap;">
                            <button type="button" class="btn btn-default btn-xs" onclick="selectAllProducts('edit', true)">تحديد الكل</button>
                            <button type="button" class="btn btn-default btn-xs" onclick="selectAllProducts('edit', false)">إلغاء تحديد الكل</button>
                        </div>
                    </div>
                    <div style="max-height: 220px; overflow-y: auto; border: 1px solid #e5e7eb; padding: 10px; border-radius: 6px; background: #f8fafc;">
                        <?php foreach ($all_active_products as $prod): ?>
                            <div class="form-check product-check-item" data-name="<?php echo htmlspecialchars($prod['p_name'], ENT_QUOTES, 'UTF-8'); ?>" style="margin-bottom: 6px;">
                                <input type="checkbox" name="allowed_products[]" value="<?php echo (int)$prod['p_id']; ?>" class="form-check-input edit-prod-cb" id="edit_prod_<?php echo (int)$prod['p_id']; ?>">
                                <label class="form-check-label" for="edit_prod_<?php echo (int)$prod['p_id']; ?>" style="font-weight: normal; cursor: pointer;">
                                    <?php echo htmlspecialchars($prod['p_name'], ENT_QUOTES, 'UTF-8'); ?> <small class="text-muted">(#<?php echo (int)$prod['p_id']; ?>)</small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default js-dismiss-employee-modal"><?php echo htmlspecialchars($l['cancel'] ?? 'إلغاء', ENT_QUOTES, 'UTF-8'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($l['save'] ?? 'حفظ التغييرات', ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
var employeesData = <?php echo json_encode($employees, JSON_UNESCAPED_UNICODE); ?>;

function showEmployeeModal(modalId) {
    var modalElement = document.getElementById(modalId);
    if (!modalElement) return;

    modalElement.style.display = 'flex';
    // Small delay to allow CSS transition to kick in
    setTimeout(function() {
        modalElement.classList.add('in');
    }, 10);
}

function hideEmployeeModal(modalElement) {
    if (!modalElement) return;

    modalElement.classList.remove('in');
    setTimeout(function() {
        modalElement.style.display = 'none';
    }, 300); // Wait for transition
}

function switchAddTab(tab) {
    var basicTab = document.getElementById('add_tab_basic');
    var productsTab = document.getElementById('add_tab_products');
    var basicLink = document.getElementById('add_tab_link_basic');
    var productsLink = document.getElementById('add_tab_link_products');
    if (basicTab && productsTab && basicLink && productsLink) {
        basicTab.style.display = (tab === 'basic') ? 'block' : 'none';
        productsTab.style.display = (tab === 'products') ? 'block' : 'none';
        basicLink.style.borderBottom = (tab === 'basic') ? '2px solid #0f766e' : 'none';
        basicLink.style.fontWeight = (tab === 'basic') ? 'bold' : 'normal';
        basicLink.style.color = (tab === 'basic') ? '#0f766e' : '#64748b';
        productsLink.style.borderBottom = (tab === 'products') ? '2px solid #0f766e' : 'none';
        productsLink.style.fontWeight = (tab === 'products') ? 'bold' : 'normal';
        productsLink.style.color = (tab === 'products') ? '#0f766e' : '#64748b';
    }
}

function switchEditTab(tab) {
    var basicTab = document.getElementById('edit_tab_basic');
    var productsTab = document.getElementById('edit_tab_products');
    var basicLink = document.getElementById('edit_tab_link_basic');
    var productsLink = document.getElementById('edit_tab_link_products');
    if (basicTab && productsTab && basicLink && productsLink) {
        basicTab.style.display = (tab === 'basic') ? 'block' : 'none';
        productsTab.style.display = (tab === 'products') ? 'block' : 'none';
        basicLink.style.borderBottom = (tab === 'basic') ? '2px solid #0f766e' : 'none';
        basicLink.style.fontWeight = (tab === 'basic') ? 'bold' : 'normal';
        basicLink.style.color = (tab === 'basic') ? '#0f766e' : '#64748b';
        productsLink.style.borderBottom = (tab === 'products') ? '2px solid #0f766e' : 'none';
        productsLink.style.fontWeight = (tab === 'products') ? 'bold' : 'normal';
        productsLink.style.color = (tab === 'products') ? '#0f766e' : '#64748b';
    }
}

function selectAllProducts(mode, check) {
    var sel = (mode === 'edit') ? '#edit_tab_products .product-check-item' : '#add_tab_products .product-check-item';
    var items = document.querySelectorAll(sel);
    for (var i = 0; i < items.length; i++) {
        if (items[i].style.display !== 'none') {
            var cb = items[i].querySelector('input[type="checkbox"]');
            if (cb) cb.checked = check;
        }
    }
}

function filterProductsList(mode) {
    var qInput = (mode === 'edit') ? document.getElementById('editProductSearch') : document.getElementById('addProductSearch');
    if (!qInput) return;
    var q = (qInput.value || '').toLowerCase();
    var sel = (mode === 'edit') ? '#edit_tab_products .product-check-item' : '#add_tab_products .product-check-item';
    var items = document.querySelectorAll(sel);
    for (var i = 0; i < items.length; i++) {
        var name = (items[i].getAttribute('data-name') || '').toLowerCase();
        items[i].style.display = (name.indexOf(q) !== -1) ? 'block' : 'none';
    }
}

function openAddEmployeeModal() {
    switchAddTab('basic');
    var cbs = document.querySelectorAll('.add-prod-cb');
    for (var i = 0; i < cbs.length; i++) {
        cbs[i].checked = false;
    }
    var addSearch = document.getElementById('addProductSearch');
    if (addSearch) {
        addSearch.value = '';
        filterProductsList('add');
    }
    showEmployeeModal('addEmployeeModal');
}

function toggleReassignOptions(cb) {
    var editIdEl = document.getElementById('editEmployeeId');
    if(!editIdEl) return;
    var datasetActive = editIdEl.dataset ? editIdEl.dataset.active : 0;
    var originalIsActive = datasetActive ? parseInt(datasetActive) : 0;
    var div = document.getElementById('reassignOptions');
    if (div) {
        if (!cb.checked && originalIsActive === 1) {
            div.style.display = 'block';
        } else {
            div.style.display = 'none';
        }
    }
}

function editEmployee(id) {
    var emp = employeesData.find(function(e) { return parseInt(e.id) === id; });
    if (!emp) return;
    
    document.getElementById('editEmployeeId').value = emp.id;
    document.getElementById('editEmployeeId').dataset.active = emp.is_active;
    document.getElementById('editFullName').value = emp.full_name;
    document.getElementById('editEmail').value = emp.email;
    var tel = document.getElementById('editTelegram');
    if(tel) tel.value = emp.telegram_chat_id || '';
    
    var editIsActive = document.getElementById('editIsActive');
    if(editIsActive) {
        editIsActive.checked = parseInt(emp.is_active) === 1;
        toggleReassignOptions(editIsActive);
    }
    
    switchEditTab('basic');
    var allowed = emp.allowed_products || [];
    var cbs = document.querySelectorAll('.edit-prod-cb');
    for (var i = 0; i < cbs.length; i++) {
        var val = parseInt(cbs[i].value, 10);
        cbs[i].checked = (allowed.indexOf(val) !== -1 || allowed.indexOf(val.toString()) !== -1);
    }
    var editSearch = document.getElementById('editProductSearch');
    if (editSearch) {
        editSearch.value = '';
        filterProductsList('edit');
    }

    showEmployeeModal('editEmployeeModal');
}

window.openAddEmployeeModal = openAddEmployeeModal;
window.editEmployee = editEmployee;

document.addEventListener('click', function(event) {
    var addButton = event.target.closest('.js-open-add-employee');
    if (addButton) {
        event.preventDefault();
        openAddEmployeeModal();
        return;
    }

    var editButton = event.target.closest('.js-edit-employee');
    if (editButton) {
        event.preventDefault();
        editEmployee(parseInt(editButton.getAttribute('data-employee-id'), 10));
        return;
    }

    var confirmAction = event.target.closest('.js-confirm-action');
    if (confirmAction && !window.confirm(confirmAction.getAttribute('data-confirm') || 'هل أنت متأكد؟')) {
        event.preventDefault();
        event.stopPropagation();
        return;
    }

    var dismissButton = event.target.closest('.js-dismiss-employee-modal');
    if (dismissButton) {
        var modalElement = dismissButton.closest('.professional-modal');
        if (modalElement) {
            event.preventDefault();
            event.stopPropagation();
            hideEmployeeModal(modalElement);
        }
    }
}, true);

var editActiveCheckbox = document.getElementById('editIsActive');
if (editActiveCheckbox) {
    editActiveCheckbox.addEventListener('change', function() {
        toggleReassignOptions(editActiveCheckbox);
    });
}
</script>

<?php require_once('footer.php'); ?>
