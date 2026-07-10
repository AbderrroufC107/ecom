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

// Secondary bot (order-status) - only shown when a genuinely separate bot
// token is configured; otherwise personal order-status alerts already ride
// on the main bot linked above.
require_once __DIR__ . '/../admin/telegram/Services/SecondaryBotLinkService.php';
$order_status_bot_available = SecondaryBotLinkService::hasDedicatedBot($pdo, 'order_status');
$order_status_link = $order_status_bot_available
    ? SecondaryBotLinkService::getLinkStatus($pdo, 'employee', $employee_id, 'order_status')
    : null;
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

        <div class="staff-card" id="telegram-link-card">
            <div class="staff-card-title">ربط حساب Telegram</div>
            
            <?php if (!empty($employee['telegram_is_linked'])): ?>
                <div class="text-center py-3">
                    <div class="mb-2">
                        <i class="bi bi-telegram text-primary" style="font-size: 48px;"></i>
                    </div>
                    <div class="badge bg-success mb-3" style="font-size: 14px; padding: 8px 12px;">
                        <i class="bi bi-check-circle-fill"></i> حسابك مرتبط بالبوت
                    </div>
                    <table class="table staff-table text-start" style="font-size: 13px;">
                        <tr>
                            <td style="width: 130px;">المعرف (Username)</td>
                            <td><strong><?php echo !empty($employee['telegram_username']) ? '@' . htmlspecialchars($employee['telegram_username'], ENT_QUOTES, 'UTF-8') : '--'; ?></strong></td>
                        </tr>
                        <tr>
                            <td>الاسم في تيليجرام</td>
                            <td><?php echo !empty($employee['telegram_first_name']) ? htmlspecialchars($employee['telegram_first_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></td>
                        </tr>
                        <tr>
                            <td>تاريخ الربط</td>
                            <td><?php echo !empty($employee['telegram_linked_at']) ? htmlspecialchars($employee['telegram_linked_at'], ENT_QUOTES, 'UTF-8') : '--'; ?></td>
                        </tr>
                    </table>
                    <button type="button" class="btn btn-danger w-100 btn-staff mt-2" onclick="telegramUnlink()">
                        <i class="bi bi-x-circle"></i> إلغاء الربط
                    </button>
                </div>
            <?php else: ?>
                <div class="text-center py-3" id="tg-initial-state">
                    <div class="mb-3 text-secondary">
                        <i class="bi bi-telegram" style="font-size: 48px; opacity: 0.5;"></i>
                    </div>
                    <p style="font-size: 13px; color: var(--text-secondary);" class="mb-3">
                        اربط حسابك لتلقي الإشعارات الفورية عن المهام الجديدة والتحديثات مباشرة في Telegram.
                    </p>
                    <button type="button" class="btn btn-primary w-100 btn-staff" onclick="telegramGenerateLink()">
                        <i class="bi bi-link-45deg"></i> توليد رابط الربط
                    </button>
                </div>

                <div class="py-2 d-none" id="tg-pending-state">
                    <div class="alert alert-info" style="font-size: 13px; line-height: 1.6;">
                        <i class="bi bi-info-circle"></i> 
                        تم توليد رمز ربط آمن. اضغط على الزر أدناه لفتح Telegram، ثم اضغط على زر <strong>Start</strong> أو <strong>ابدأ</strong> لتأكيد عملية الربط.
                    </div>
                    <a href="#" id="tg-deep-link-btn" target="_blank" class="btn btn-success w-100 btn-staff mb-2">
                        <i class="bi bi-telegram"></i> افتح Telegram للربط
                    </a>
                    <div class="text-center text-secondary mb-3" style="font-size: 12px;">
                        ينتهي صلاحية هذا الرمز بعد: <span id="tg-timer" class="fw-bold text-danger">15:00</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary w-100 btn-staff" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> لقد أكملت الربط، تحديث الصفحة
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($order_status_bot_available): ?>
        <div class="staff-card" id="telegram-status-link-card">
            <div class="staff-card-title">بوت حالة الطلب (منفصل)</div>
            <p style="font-size: 12px; color: var(--text-secondary);" class="mb-2">
                يوجد بوت منفصل لتنبيهات حالة الطلب. اربطه هنا أيضًا لتصلك تنبيهات حالة طلباتك شخصيًا — هذا مستقل عن الربط أعلاه.
            </p>

            <?php if (!empty($order_status_link['is_linked'])): ?>
                <div class="text-center py-3">
                    <div class="badge bg-success mb-3" style="font-size: 14px; padding: 8px 12px;">
                        <i class="bi bi-check-circle-fill"></i> مرتبط
                    </div>
                    <table class="table staff-table text-start" style="font-size: 13px;">
                        <tr>
                            <td style="width: 130px;">المعرف (Username)</td>
                            <td><strong><?php echo !empty($order_status_link['telegram_username']) ? '@' . htmlspecialchars($order_status_link['telegram_username'], ENT_QUOTES, 'UTF-8') : '--'; ?></strong></td>
                        </tr>
                        <tr>
                            <td>تاريخ الربط</td>
                            <td><?php echo !empty($order_status_link['linked_at']) ? htmlspecialchars($order_status_link['linked_at'], ENT_QUOTES, 'UTF-8') : '--'; ?></td>
                        </tr>
                    </table>
                    <button type="button" class="btn btn-danger w-100 btn-staff mt-2" onclick="telegramStatusUnlink()">
                        <i class="bi bi-x-circle"></i> إلغاء الربط
                    </button>
                </div>
            <?php else: ?>
                <div class="text-center py-3" id="tg-status-initial-state">
                    <button type="button" class="btn btn-primary w-100 btn-staff" onclick="telegramStatusGenerateLink()">
                        <i class="bi bi-link-45deg"></i> ربط بوت حالة الطلب
                    </button>
                </div>

                <div class="py-2 d-none" id="tg-status-pending-state">
                    <div class="alert alert-info" style="font-size: 13px; line-height: 1.6;">
                        <i class="bi bi-info-circle"></i>
                        تم توليد رمز ربط آمن. اضغط على الزر أدناه لفتح Telegram، ثم اضغط على زر <strong>Start</strong> أو <strong>ابدأ</strong> لتأكيد عملية الربط.
                    </div>
                    <a href="#" id="tg-status-deep-link-btn" target="_blank" class="btn btn-success w-100 btn-staff mb-2">
                        <i class="bi bi-telegram"></i> افتح Telegram للربط
                    </a>
                    <div class="text-center text-secondary mb-3" style="font-size: 12px;">
                        ينتهي صلاحية هذا الرمز بعد: <span id="tg-status-timer" class="fw-bold text-danger">15:00</span>
                    </div>
                    <button type="button" class="btn btn-outline-secondary w-100 btn-staff" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> لقد أكملت الربط، تحديث الصفحة
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
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

<script>
let tgTimerInterval = null;

function telegramGenerateLink() {
    const btn = document.querySelector('#tg-initial-state button');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري التوليد...';

    fetch('../admin/telegram-link-action.php?action=generate&user_type=employee')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tg-initial-state').classList.add('d-none');
                document.getElementById('tg-pending-state').classList.remove('d-none');
                
                const linkBtn = document.getElementById('tg-deep-link-btn');
                linkBtn.href = data.url;
                
                // Start 15-minute countdown
                let timeLeft = 15 * 60;
                const timerSpan = document.getElementById('tg-timer');
                
                if (tgTimerInterval) clearInterval(tgTimerInterval);
                tgTimerInterval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(tgTimerInterval);
                        timerSpan.textContent = 'منتهي الصلاحية';
                        linkBtn.classList.add('disabled');
                        linkBtn.href = '#';
                    } else {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        timerSpan.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }, 1000);
            } else {
                alert('خطأ في توليد الرابط: ' + (data.error || 'خطأ غير معروف'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-link-45deg"></i> توليد رابط الربط';
            }
        })
        .catch(err => {
            alert('فشل الاتصال بالخادم: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-link-45deg"></i> توليد رابط الربط';
        });
}

function telegramUnlink() {
    if (!confirm('هل أنت متأكد من رغبتك في إلغاء ربط حساب Telegram؟ لن تتلقى أي إشعارات بعد ذلك.')) {
        return;
    }
    
    const card = document.getElementById('telegram-link-card');
    card.style.opacity = '0.6';
    card.style.pointerEvents = 'none';

    fetch('../admin/telegram-link-action.php?action=unlink&user_type=employee')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('خطأ في إلغاء الربط: ' + (data.error || 'خطأ غير معروف'));
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            }
        })
        .catch(err => {
            alert('فشل الاتصال بالخادم: ' + err.message);
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
        });
}

let tgStatusTimerInterval = null;

function telegramStatusGenerateLink() {
    const btn = document.querySelector('#tg-status-initial-state button');
    if (!btn) return;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> جاري التوليد...';

    fetch('../admin/telegram-link-action.php?action=generate&user_type=employee&bot_purpose=order_status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tg-status-initial-state').classList.add('d-none');
                document.getElementById('tg-status-pending-state').classList.remove('d-none');

                const linkBtn = document.getElementById('tg-status-deep-link-btn');
                linkBtn.href = data.url;

                let timeLeft = 15 * 60;
                const timerSpan = document.getElementById('tg-status-timer');

                if (tgStatusTimerInterval) clearInterval(tgStatusTimerInterval);
                tgStatusTimerInterval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(tgStatusTimerInterval);
                        timerSpan.textContent = 'منتهي الصلاحية';
                        linkBtn.classList.add('disabled');
                        linkBtn.href = '#';
                    } else {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        timerSpan.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }, 1000);
            } else {
                alert('خطأ في توليد الرابط: ' + (data.error || 'خطأ غير معروف'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-link-45deg"></i> ربط بوت حالة الطلب';
            }
        })
        .catch(err => {
            alert('فشل الاتصال بالخادم: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-link-45deg"></i> ربط بوت حالة الطلب';
        });
}

function telegramStatusUnlink() {
    if (!confirm('هل أنت متأكد من رغبتك في إلغاء ربط هذا البوت؟')) {
        return;
    }

    const card = document.getElementById('telegram-status-link-card');
    card.style.opacity = '0.6';
    card.style.pointerEvents = 'none';

    fetch('../admin/telegram-link-action.php?action=unlink&user_type=employee&bot_purpose=order_status')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('خطأ في إلغاء الربط: ' + (data.error || 'خطأ غير معروف'));
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            }
        })
        .catch(err => {
            alert('فشل الاتصال بالخادم: ' + err.message);
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
        });
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
