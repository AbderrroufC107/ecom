<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];
$order_id = (int) ($_GET['id'] ?? 0);

$access = telegram_verify_order_access($pdo, $order_id, $employee_id);
if (!$access) {
    header('Location: orders.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    header('Location: orders.php');
    exit;
}

require_once __DIR__ . '/../admin/inc/telegram_actions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'confirm') {
        if (telegram_can_modify_status($order['order_status']) && !telegram_already_processed($pdo, $order_id, 'confirm', $employee_id)) {
            $stmt = $pdo->prepare("UPDATE tbl_order SET order_status = 'Confirmed' WHERE id = ?");
            $stmt->execute([$order_id]);
            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($pdo, $order_id, 'Pending', 'Confirmed', 'تأكيد عبر بوابة الموظفين', $employee['full_name']);
            }
            telegram_log_action($pdo, $employee_id, $order_id, 'confirm', null, ['source' => 'staff_portal', 'status' => 'Confirmed']);
            $message = 'تم تأكيد الطلب بنجاح.';
            $order['order_status'] = 'Confirmed';
        } else {
            $error = 'لا يمكن تأكيد هذا الطلب في الوقت الحالي.';
        }
    }

    if ($action === 'cancel' && $order['order_status'] === 'Pending') {
        $reason = trim((string) ($_POST['cancel_reason'] ?? ''));
        if ($reason === '') $reason = 'إلغاء عبر بوابة الموظفين';

        $stmt = $pdo->prepare("UPDATE tbl_order SET order_status = 'Cancelled' WHERE id = ?");
        $stmt->execute([$order_id]);
        $stmt2 = $pdo->prepare("INSERT INTO tbl_order_cancellation_reason (order_id, employee_id, reason) VALUES (?, ?, ?)");
        $stmt2->execute([$order_id, $employee_id, $reason]);
        if (function_exists('admin_log_order_status_change')) {
            admin_log_order_status_change($pdo, $order_id, 'Pending', 'Cancelled', 'إلغاء عبر بوابة الموظفين: ' . $reason, $employee['full_name']);
        }
        telegram_log_action($pdo, $employee_id, $order_id, 'cancel', null, ['source' => 'staff_portal', 'reason' => $reason]);
        $message = 'تم إلغاء الطلب.';
        $order['order_status'] = 'Cancelled';
    }

    if ($action === 'edit' && $order['order_status'] === 'Pending') {
        $field = trim((string) ($_POST['edit_field'] ?? ''));
        $value = trim((string) ($_POST['edit_value'] ?? ''));
        $allowed_fields = ['customer_name', 'customer_phone', 'quantity', 'wilaya', 'commune', 'address', 'product_name', 'unit_price'];

        if (in_array($field, $allowed_fields, true) && $value !== '') {
            $old_value = (string) ($order[$field] ?? '');

            if ($field === 'customer_phone') {
                if (!preg_match('/^(05|06|07|+213|00213)?[0-9]{8,9}$/', preg_replace('/[^0-9+]/', '', $value))) {
                    $error = 'رقم الهاتف غير صالح.';
                }
            } elseif ($field === 'quantity') {
                $qty = (int) $value;
                if ($qty <= 0) $error = 'الكمية يجب أن تكون أكبر من 0.';
                else $value = (string) $qty;
            } elseif ($field === 'unit_price') {
                $price = (float) $value;
                if ($price <= 0) $error = 'السعر يجب أن يكون أكبر من 0.';
                else {
                    $value = (string) $price;
                    $new_total = $price * (int) ($order['quantity'] ?? 1);
                }
            }

            if ($error === '') {
                $pdo->beginTransaction();
                if ($field === 'unit_price') {
                    $stmt = $pdo->prepare("UPDATE tbl_order SET unit_price = ?, total_price = ? WHERE id = ?");
                    $stmt->execute([$value, $new_total, $order_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE tbl_order SET {$field} = ? WHERE id = ?");
                    $stmt->execute([$value, $order_id]);
                }

                $stmt = $pdo->prepare("INSERT INTO tbl_order_edit_log (order_id, employee_id, field_name, old_value, new_value) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$order_id, $employee_id, $field, $old_value, $value]);
                $pdo->commit();

                telegram_log_action($pdo, $employee_id, $order_id, 'edit', null, ['field' => $field, 'old' => $old_value, 'new' => $value]);
                $message = 'تم تحديث الحقل "' . $field . '" بنجاح.';
                $order[$field] = $value;
                if ($field === 'unit_price') $order['total_price'] = $new_total;
            }
        } else {
            $error = 'الرجاء اختيار حقل صحيح وإدخال قيمة.';
        }
    }
}

$telegram_id = trim((string) ($employee['telegram_chat_id'] ?? ''));
$has_telegram = $telegram_id !== '';

$page_title = 'طلب #' . $order_id;
?>

<div class="staff-card">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
        <div>
            <div class="staff-card-title" style="margin-bottom:0;">طلب رقم #<?php echo (int) $order['id']; ?></div>
        </div>
        <div>
            <span class="status-badge <?php echo strtolower($order['order_status'] ?? ''); ?>" style="font-size:14px;padding:6px 16px;">
                <?php echo htmlspecialchars($order['order_status'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($has_telegram): ?>
                <span class="badge bg-info" style="font-size:13px;"><i class="bi bi-telegram"></i> مرتبط بتيليجرام</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="staff-card">
            <div class="staff-card-title">معلومات العميل</div>
            <table class="table staff-table">
                <tr><td style="width:120px;">الاسم</td><td><strong><?php echo htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td></tr>
                <tr><td>الهاتف</td><td dir="ltr" style="text-align:right;"><?php echo htmlspecialchars($order['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>الولاية</td><td><?php echo htmlspecialchars($order['wilaya'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>البلدية</td><td><?php echo htmlspecialchars($order['commune'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>العنوان</td><td><?php echo htmlspecialchars($order['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="staff-card">
            <div class="staff-card-title">معلومات الطلب</div>
            <table class="table staff-table">
                <tr><td style="width:120px;">المنتج</td><td><strong><?php echo htmlspecialchars($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong></td></tr>
                <tr><td>الكمية</td><td><?php echo (int) ($order['quantity'] ?? 1); ?></td></tr>
                <tr><td>سعر الوحدة</td><td><?php echo number_format((float) ($order['unit_price'] ?? 0), 0); ?> دج</td></tr>
                <tr><td>المبلغ الإجمالي</td><td><strong><?php echo number_format((float) ($order['total_price'] ?? 0), 0); ?> دج</strong></td></tr>
                <tr><td>تاريخ الطلب</td><td><?php echo htmlspecialchars($order['order_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>نوع التوصيل</td><td><?php echo htmlspecialchars($order['delivery_type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            </table>
        </div>
    </div>
</div>

<?php if ($order['order_status'] === 'Pending'): ?>
    <div class="staff-card">
        <div class="staff-card-title">إجراءات سريعة</div>
        <div class="d-flex gap-2 flex-wrap mt-2">
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="confirm">
                <button type="submit" class="btn btn-success btn-staff" onclick="return confirm('تأكيد الطلب؟');">
                    <i class="bi bi-check-circle"></i> تأكيد الطلب
                </button>
            </form>

            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="cancel">
                <select name="cancel_reason" class="form-select form-select-sm d-inline-block" style="width:auto;display:inline-block!important;" required>
                    <option value="">سبب الإلغاء</option>
                    <option value="العميل ألغى">العميل ألغى</option>
                    <option value="رقم الهاتف غير صحيح">رقم الهاتف غير صحيح</option>
                    <option value="لا يوجد رد">لا يوجد رد</option>
                    <option value="تأخير في التوصيل">تأخير في التوصيل</option>
                    <option value="العميل غير مهتم">العميل غير مهتم</option>
                    <option value="مشكلة في المنتج">مشكلة في المنتج</option>
                </select>
                <button type="submit" class="btn btn-danger btn-staff" onclick="return confirm('إلغاء الطلب؟');">
                    <i class="bi bi-x-circle"></i> إلغاء الطلب
                </button>
            </form>

            <button class="btn btn-warning btn-staff" type="button" data-bs-toggle="collapse" data-bs-target="#editForm">
                <i class="bi bi-pencil"></i> تعديل الطلب
            </button>
        </div>

        <div class="collapse mt-3" id="editForm">
            <form method="post" class="row g-2">
                <input type="hidden" name="action" value="edit">
                <div class="col-md-4">
                    <select name="edit_field" class="form-select" required>
                        <option value="">اختر الحقل</option>
                        <option value="customer_name">اسم العميل</option>
                        <option value="customer_phone">رقم الهاتف</option>
                        <option value="quantity">الكمية</option>
                        <option value="wilaya">الولاية</option>
                        <option value="commune">البلدية</option>
                        <option value="address">العنوان</option>
                        <option value="product_name">اسم المنتج</option>
                        <option value="unit_price">سعر الوحدة</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="edit_value" class="form-control" placeholder="القيمة الجديدة" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-staff w-100" onclick="return confirm('تأكيد التعديل؟');">
                        <i class="bi bi-save"></i> حفظ التعديل
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($order['order_status'] === 'Cancelled'): ?>
    <div class="staff-card">
        <div class="staff-card-title">سبب الإلغاء</div>
        <?php
        $stmt = $pdo->prepare("SELECT reason, created_at FROM tbl_order_cancellation_reason WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$order_id]);
        $cancel = $stmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <?php if ($cancel): ?>
            <p><strong><?php echo htmlspecialchars($cancel['reason'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
            <small style="color:var(--text-secondary);"><?php echo htmlspecialchars($cancel['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
        <?php else: ?>
            <p class="text-muted">لا يوجد سبب مسجل.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$stmt = $pdo->prepare("SELECT * FROM tbl_order_edit_log WHERE order_id = ? ORDER BY edited_at DESC LIMIT 10");
$stmt->execute([$order_id]);
$edits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (!empty($edits)): ?>
    <div class="staff-card">
        <div class="staff-card-title">سجل التعديلات</div>
        <div class="table-responsive">
            <table class="table staff-table">
                <thead>
                    <tr><th>الحقل</th><th>القيمة القديمة</th><th>القيمة الجديدة</th><th>التاريخ</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($edits as $e): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($e['field_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($e['old_value'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($e['new_value'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="font-size:13px;color:var(--text-secondary);"><?php echo htmlspecialchars($e['edited_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<a href="orders.php" class="btn btn-outline-secondary btn-staff"><i class="bi bi-arrow-right"></i> العودة للطلبات</a>

<?php require_once __DIR__ . '/footer.php'; ?>
