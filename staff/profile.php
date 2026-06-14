<?php
require_once __DIR__ . '/header.php';

$employee_id = (int) $employee['id'];
$page_title = 'ملفي الشخصي';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if (!password_verify($current, $employee['password_hash'])) {
            $error = 'كلمة المرور الحالية غير صحيحة.';
        } elseif (strlen($new) < 6) {
            $error = 'كلمة المرور الجديدة يجب أن تكون 6 أحرف على الأقل.';
        } elseif ($new !== $confirm) {
            $error = 'كلمة المرور الجديدة وتأكيدها غير متطابقين.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE tbl_employee SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $employee_id]);
            $message = 'تم تغيير كلمة المرور بنجاح.';
        }
    }

    if (isset($_POST['update_telegram'])) {
        $chat_id = trim((string) ($_POST['telegram_chat_id'] ?? ''));
        $stmt = $pdo->prepare("UPDATE tbl_employee SET telegram_chat_id = ? WHERE id = ?");
        $stmt->execute([$chat_id, $employee_id]);
        $employee['telegram_chat_id'] = $chat_id;
        $message = 'تم تحديث معرف التيليجرام بنجاح.';
    }
}

$stmt = $pdo->prepare("SELECT * FROM tbl_telegram_action_log WHERE employee_id = ? ORDER BY created_at DESC LIMIT 30");
$stmt->execute([$employee_id]);
$actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_employee WHERE is_active = 1");
$stmt->execute();
$total_employees = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT MAX(created_at) AS last_action FROM tbl_telegram_action_log WHERE employee_id = ?");
$stmt->execute([$employee_id]);
$last_action = $stmt->fetch(PDO::FETCH_ASSOC);
$last_action_time = $last_action ? $last_action['last_action'] : null;
?>

<div class="row g-3">
    <div class="col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">معلومات الحساب</div>
            <table class="table staff-table">
                <tr><td style="width:100px;">الاسم</td><td><strong><?php echo htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8'); ?></strong></td></tr>
                <tr><td>البريد</td><td><?php echo htmlspecialchars($employee['email'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <tr><td>الحالة</td><td><span class="badge bg-success">نشط</span></td></tr>
                <tr><td>عدد الموظفين</td><td><?php echo $total_employees; ?></td></tr>
                <tr><td>آخر نشاط</td><td style="font-size:13px;"><?php echo $last_action_time ? htmlspecialchars($last_action_time, ENT_QUOTES, 'UTF-8') : 'لا يوجد'; ?></td></tr>
            </table>
        </div>

        <div class="staff-card">
            <div class="staff-card-title">تحديث معرف التيليجرام</div>
            <form method="post">
                <div class="mb-2">
                    <input type="text" name="telegram_chat_id" class="form-control"
                           value="<?php echo htmlspecialchars($employee['telegram_chat_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="أدخل معرف التيليجرام">
                </div>
                <button type="submit" name="update_telegram" class="btn btn-primary btn-staff">
                    <i class="bi bi-telegram"></i> حفظ
                </button>
            </form>
            <?php if (!empty($employee['telegram_chat_id'])): ?>
                <div class="mt-2" style="font-size:13px;color:var(--success);">
                    <i class="bi bi-check-circle"></i> مرتبط بتيليجرام
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">تغيير كلمة المرور</div>
            <form method="post">
                <div class="mb-2">
                    <label class="form-label" style="font-size:13px;">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label class="form-label" style="font-size:13px;">كلمة المرور الجديدة</label>
                    <input type="password" name="new_password" class="form-control" required minlength="6">
                </div>
                <div class="mb-2">
                    <label class="form-label" style="font-size:13px;">تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6">
                </div>
                <button type="submit" name="change_password" class="btn btn-warning btn-staff">
                    <i class="bi bi-key"></i> تغيير كلمة المرور
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-4">
        <div class="staff-card">
            <div class="staff-card-title">آخر الإجراءات</div>
            <?php if (empty($actions)): ?>
                <div class="staff-empty">
                    <i class="bi bi-activity"></i>
                    <p>لا توجد إجراءات بعد.</p>
                </div>
            <?php else: ?>
                <div style="max-height:400px;overflow-y:auto;">
                    <table class="table staff-table">
                        <thead>
                            <tr><th>الإجراء</th><th>الطلب</th><th>التاريخ</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actions as $a): ?>
                                <tr>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($a['action_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo $a['order_id'] > 0 ? '#'.$a['order_id'] : '--'; ?></td>
                                    <td style="font-size:12px;"><?php echo htmlspecialchars($a['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
