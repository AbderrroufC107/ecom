<?php
require_once __DIR__ . '/header.php';

// Provision table if not exists
telegram_ensure_complaints_table($pdo);

$employee_id = (int) $employee['id'];
$page_title = 'الشكاوى والملاحظات';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $subject = trim((string) ($_POST['subject'] ?? ''));
    $message_content = trim((string) ($_POST['message'] ?? ''));

    if ($subject === '' || $message_content === '') {
        $error_msg = 'يرجى ملء جميع الحقول المطلوبة.';
    } else {
        try {
            // Save locally first
            $stmt = $pdo->prepare("INSERT INTO tbl_complaints (employee_id, subject, message, telegram_status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$employee_id, $subject, $message_content]);
            $complaint_id = $pdo->lastInsertId();

            if (class_exists('EventManager')) {
                EventManager::dispatch('ComplaintCreated', $pdo, (int)$complaint_id);
                $success_msg = 'تم تسجيل الشكوى/الملاحظة وجاري إرسالها إلى المدير عبر Telegram.';
            } else {
                // Fetch manager Telegram credentials from database
                $stmt_settings = $pdo->query("SELECT telegram_bot_token, telegram_chat_id FROM tbl_settings WHERE id = 1");
                $settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
                
                $chat_id = trim((string) ($settings['telegram_chat_id'] ?? ''));
                if ($chat_id === '') {
                    // Fallback to event_bot_chat_id
                    $chat_id = telegram_get_event_setting($pdo, 'event_bot_chat_id');
                }

                if ($chat_id !== '') {
                    $emp_name = htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $emp_email = htmlspecialchars($employee['email'] ?? '', ENT_QUOTES, 'UTF-8');
                    $esc_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
                    $esc_msg = htmlspecialchars($message_content, ENT_QUOTES, 'UTF-8');

                    $tg_text = "<b>🔔 شكوى / ملاحظة جديدة من موظف</b>\n\n";
                    $tg_text .= "<b>👤 الموظف:</b> {$emp_name} (ID: {$employee_id})\n";
                    $tg_text .= "<b>📧 البريد الإلكتروني:</b> {$emp_email}\n";
                    $tg_text .= "<b>📌 الموضوع:</b> {$esc_subject}\n\n";
                    $tg_text .= "<b>💬 الملاحظة/الشكوى:</b>\n{$esc_msg}\n\n";
                    $tg_text .= "<b>📅 التاريخ:</b> " . date('Y-m-d H:i:s');

                    // Send via telegram_send_message
                    $result = telegram_send_message($chat_id, $tg_text);
                    
                    if (!empty($result['success'])) {
                        $upd = $pdo->prepare("UPDATE tbl_complaints SET telegram_status = 'sent', telegram_message_id = ? WHERE id = ?");
                        $upd->execute([$result['message_id'], $complaint_id]);
                        $success_msg = 'تم إرسال الشكوى/الملاحظة إلى المدير بنجاح في الوقت الحقيقي عبر تيليجرام.';
                    } else {
                        $upd = $pdo->prepare("UPDATE tbl_complaints SET telegram_status = 'failed' WHERE id = ?");
                        $upd->execute([$complaint_id]);
                        $error_msg = 'تم حفظ الشكوى محلياً، ولكن فشل إرسالها الفوري إلى تيليجرام: ' . htmlspecialchars($result['error'] ?? 'خطأ غير معروف');
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE tbl_complaints SET telegram_status = 'failed' WHERE id = ?");
                    $upd->execute([$complaint_id]);
                    $error_msg = 'تم حفظ الشكوى محلياً، ولكن لم يتم إرسالها لعدم إعداد معرّف المحادثة (Chat ID) للمدير.';
                }
            }
        } catch (Exception $e) {
            $error_msg = 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage();
        }
    }
}

// Fetch complaints history for the logged-in employee
$stmt = $pdo->prepare("SELECT * FROM tbl_complaints WHERE employee_id = ? ORDER BY created_at DESC");
$stmt->execute([$employee_id]);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="staff-card">
            <div class="staff-card-title mb-4">تقديم شكوى أو ملاحظة جديدة</div>
            
            <?php if ($success_msg !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_msg !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label" style="font-size: 13px;">الموضوع <span class="text-danger">*</span></label>
                    <input type="text" name="subject" class="form-control" placeholder="أدخل موضوع الشكوى أو الملاحظة" required maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-size: 13px;">تفاصيل الشكوى أو الملاحظة <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="6" placeholder="اكتب تفاصيل شكواك أو ملاحظاتك هنا..." required></textarea>
                </div>
                <button type="submit" name="submit_complaint" class="btn btn-primary btn-staff w-100">
                    <i class="bi bi-send-fill me-2"></i> إرسال إلى المدير (في الوقت الحقيقي)
                </button>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="staff-card">
            <div class="staff-card-title mb-4">سجل شكاواك وملاحظاتك السابقة</div>
            
            <?php if (empty($complaints)): ?>
                <div class="staff-empty">
                    <i class="bi bi-chat-left-dots"></i>
                    <p>لا توجد أي شكاوى أو ملاحظات سابقة.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table staff-table align-middle">
                        <thead>
                            <tr>
                                <th style="width: 80px;">رقم</th>
                                <th style="width: 150px;">الموضوع</th>
                                <th>الملاحظة/الشكوى</th>
                                <th style="width: 150px;">التاريخ</th>
                                <th style="width: 120px;">حالة الإرسال</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <tr>
                                    <td>#<?php echo $c['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($c['subject'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td>
                                        <div style="max-height: 80px; overflow-y: auto; font-size: 13px; white-space: pre-wrap; color: var(--text-secondary);">
                                            <?php echo htmlspecialchars($c['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td style="font-size: 12px; white-space: nowrap;"><?php echo htmlspecialchars($c['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($c['telegram_status'] === 'sent'): ?>
                                            <span class="badge bg-success" style="font-size: 11px;">
                                                <i class="bi bi-check-circle"></i> وصلت للمدير
                                            </span>
                                        <?php elseif ($c['telegram_status'] === 'failed'): ?>
                                            <span class="badge bg-danger" style="font-size: 11px;">
                                                <i class="bi bi-exclamation-circle"></i> فشل الإرسال
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 11px;">
                                                <i class="bi bi-hourglass-split"></i> معلقة
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
