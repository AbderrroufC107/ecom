<?php
/**
 * CallbackHandler Class
 *
 * Router for inline button callback query clicks.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Services/TelegramService.php';
require_once __DIR__ . '/../Services/LoggerService.php';
require_once __DIR__ . '/../Services/EventManager.php';
require_once __DIR__ . '/../Services/StateService.php';
require_once __DIR__ . '/../../inc/telegram_actions.php';

class CallbackHandler
{
    private PDO $pdo;
    private TelegramService $bot;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bot = TelegramService::getInstance($pdo);
    }

    /**
     * Handle incoming callback query.
     */
    public function handle(array $callbackQuery): void
    {
        $callbackId = (string) ($callbackQuery['id'] ?? '');
        $data = (string) ($callbackQuery['data'] ?? '');
        $chatId = (string) ($callbackQuery['message']['chat']['id'] ?? '');
        $messageId = (int) ($callbackQuery['message']['message_id'] ?? 0);

        if ($callbackId === '' || $data === '') {
            return;
        }

        // 1. Identify User Role and Details
        $user = $this->identifyUser($chatId);
        if (!$user) {
            $this->bot->answerCallbackQuery($callbackId, "❌ حسابك غير مرتبط بالنظام.", true);
            return;
        }

        if (!$user['is_active']) {
            $this->bot->answerCallbackQuery($callbackId, "❌ حسابك غير نشط. يرجى مراجعة الإدارة.", true);
            return;
        }

        // Parse callback parameters: "action:order_id:extra"
        $parts = explode(':', $data);
        $action = $parts[0] ?? '';
        $param = $parts[1] ?? '';
        $extra = $parts[2] ?? '';

        // Log Callback click
        LoggerService::logAction(
            $this->pdo,
            $chatId,
            $user['role'],
            $user['id'],
            'callback',
            $action,
            $callbackQuery,
            'success'
        );

        // 2. Validate Role-Based Callback Execution
        if (strpos($action, 'emp_') === 0 && $user['role'] !== 'employee') {
            $this->bot->answerCallbackQuery($callbackId, "🚫 هذا الإجراء مخصص للموظفين فقط.", true);
            return;
        }

        if (strpos($action, 'mgr_') === 0 && $user['role'] !== 'manager') {
            $this->bot->answerCallbackQuery($callbackId, "🚫 هذا الإجراء مخصص للمديرين فقط.", true);
            return;
        }

        // 3. Command Routing
        switch ($action) {
            case 'emp_accept':
                $this->handleEmployeeAccept($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'emp_reject':
                $this->handleEmployeeRejectShow($callbackId, (int) $param, $user, $chatId, $messageId);
                break;
            case 'emp_reject_do':
                $this->handleEmployeeRejectExecute($callbackId, (int) $param, $user, $chatId, $messageId, $extra, $callbackQuery);
                break;
            case 'emp_start':
                $this->handleEmployeeStart($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'emp_complete':
                $this->handleEmployeeComplete($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'mgr_approve':
                $this->handleManagerApprove($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'mgr_cancel':
                $this->handleManagerCancel($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'mgr_ship':
                $this->handleManagerShip($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'mgr_note':
                $this->handleManagerNote($callbackId, (int) $param, $user, $chatId, $messageId);
                break;
            case 'mgr_delete':
                $this->handleManagerDelete($callbackId, (int) $param, $user, $chatId, $messageId);
                break;
            case 'mgr_reassign':
                $this->handleManagerReassign($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'mgr_close':
                $this->handleManagerClose($callbackId, (int) $param, $user, $chatId, $messageId, $callbackQuery);
                break;
            case 'mgr_lang':
                $this->handleManagerLanguage($callbackId, $param, $user, $chatId, $messageId);
                break;
            default:
                $this->bot->answerCallbackQuery($callbackId, "⚠️ إجراء غير مدعوم.");
                break;
        }
    }

    /**
     * Identify user role by checking registered telegram_chat_id.
     */
    private function identifyUser(string $chatId): ?array
    {
        // Check Employee
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT * FROM `tbl_employee` WHERE `telegram_chat_id` = ? AND `telegram_is_linked` = 1 LIMIT 1");
        $stmt->execute([$chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'role' => 'employee',
                'id' => (int) $row['id'],
                'name' => $row['full_name'],
                'email' => $row['email'],
                'lang' => $row['telegram_lang'] ?: 'ar',
                'is_active' => (int) ($row['is_active'] ?? 1) === 1
            ];
        }

        // Check Manager
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT * FROM `tbl_user` WHERE `telegram_chat_id` = ? AND `telegram_is_linked` = 1 LIMIT 1");
        $stmt->execute([$chatId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'role' => 'manager',
                'id' => (int) $row['id'],
                'name' => $row['full_name'],
                'email' => $row['email'],
                'lang' => $row['telegram_lang'] ?: 'ar',
                'is_active' => (int) ($row['status'] ?? 1) === 1
            ];
        }

        return null;
    }

    /**
     * Employee accepts a task.
     */
    private function handleEmployeeAccept(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            // Duplicate Protection: check status
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus !== 'Pending') {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ تم معالجة هذا الطلب مسبقًا.", true);
                return;
            }

            // Update status
            $update = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_order SET order_status = 'Confirmed' WHERE id = ?");
            $update->execute([$orderId]);

            // Save status change log
            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, 'Pending', 'Confirmed', "قبول المهمة عبر تيليجرام", "Telegram: " . $user['name']);
            }

            $this->pdo->commit();
            
            // Dispatch event to notify NotificationService
            EventManager::dispatch('OrderUpdated', $this->pdo, $orderId, 'Pending', 'Confirmed', "قبول المهمة عبر تيليجرام", "Telegram: " . $user['name']);

            $this->bot->answerCallbackQuery($callbackId, "✅ تم قبول المهمة بنجاح.");
            
            // Update message UI: remove accept/reject buttons, add execution buttons
            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n🟢 <b>حالة المهمة: تم القبول</b>";
            $buttons = [
                'inline_keyboard' => [
                    [
                        ['text' => "🚀 بدء التنفيذ", 'callback_data' => "emp_start:{$orderId}"],
                        ['text' => "🏁 إنهاء المهمة", 'callback_data' => "emp_complete:{$orderId}"]
                    ]
                ]
            ];
            $this->bot->editMessage($chatId, $messageId, $newText, $buttons);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل تنفيذ الإجراء.", true);
        }
    }

    /**
     * Show rejection causes keyboard.
     */
    private function handleEmployeeRejectShow(string $callbackId, int $orderId, array $user, string $chatId, int $messageId): void
    {
        $reasons = [
            'no_answer' => "📞 لا يرد",
            'wrong_number' => "🚫 رقم خاطئ",
            'rejected' => "❌ العميل ألغى الطلب",
            'duplicate' => "🔁 طلب مكرر"
        ];

        $keyboard = [];
        foreach ($reasons as $code => $label) {
            $keyboard[] = [['text' => $label, 'callback_data' => "emp_reject_do:{$orderId}:{$code}"]];
        }
        $keyboard[] = [['text' => "⬅️ تراجع", 'callback_data' => "emp_back:{$orderId}"]]; // fallback or cancel

        $newText = "🚫 يرجى تحديد سبب الرفض للطلب #{$orderId}:";
        $this->bot->editMessage($chatId, $messageId, $newText, ['inline_keyboard' => $keyboard]);
        $this->bot->answerCallbackQuery($callbackId);
    }

    /**
     * Process employee rejection query.
     */
    private function handleEmployeeRejectExecute(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, string $reasonCode, array $callbackQuery): void
    {
        $reasonMap = [
            'no_answer' => "العميل لا يرد",
            'wrong_number' => "رقم الهاتف خاطئ",
            'rejected' => "رفض الطلب من العميل",
            'duplicate' => "طلب مكرر"
        ];
        $reasonText = $reasonMap[$reasonCode] ?? "مرفوض من الموظف";

        $this->pdo->beginTransaction();
        try {
            // Duplicate Protection
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus !== 'Pending') {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ تم معالجة هذا الطلب مسبقًا.", true);
                return;
            }

            // Update status to Cancelled
            $update = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_order SET order_status = 'Cancelled' WHERE id = ?");
            $update->execute([$orderId]);

            // Save rejection reason
            $ins = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("INSERT INTO tbl_order_cancellation_reason (order_id, employee_id, reason, created_at) VALUES (?, ?, ?, NOW())");
            $ins->execute([$orderId, $user['id'], $reasonText]);

            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, 'Pending', 'Cancelled', "رفض المهمة: {$reasonText}", "Telegram: " . $user['name']);
            }

            $this->pdo->commit();
            
            // Dispatch event
            EventManager::dispatch('OrderUpdated', $this->pdo, $orderId, 'Pending', 'Cancelled', "رفض المهمة: {$reasonText}", "Telegram: " . $user['name']);

            $this->bot->answerCallbackQuery($callbackId, "🚫 تم تسجيل رفض المهمة.");

            // Update UI
            $newText = "❌ <b>طلب #{$orderId}</b>\n\n🔴 <b>حالة المهمة: تم الرفض</b>\n<b>السبب:</b> {$reasonText}";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل تنفيذ الإجراء.", true);
        }
    }

    /**
     * Employee starts execution of a task.
     */
    private function handleEmployeeStart(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            // Verify order status
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus !== 'Confirmed') {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ يجب قبول المهمة أولاً لبدء التنفيذ.", true);
                return;
            }

            // Save status log for progress tracking
            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, 'Confirmed', 'Confirmed', "بدء تنفيذ المهمة", "Telegram: " . $user['name']);
            }

            $this->pdo->commit();

            $this->bot->answerCallbackQuery($callbackId, "🚀 بالتوفيق! تم تسجيل بدء التنفيذ.");

            // Update UI: change start button to started state text
            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n⏳ <b>حالة التنفيذ: قيد المعالجة</b>";
            $buttons = [
                'inline_keyboard' => [
                    [
                        ['text' => "🏁 إنهاء المهمة", 'callback_data' => "emp_complete:{$orderId}"]
                    ]
                ]
            ];
            $this->bot->editMessage($chatId, $messageId, $newText, $buttons);

            // Notify managers
            $managers = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("SELECT telegram_chat_id FROM tbl_user WHERE telegram_is_linked = 1 AND status = 1")->fetchAll(PDO::FETCH_COLUMN);
            $msg = "🚀 الموظف <b>{$user['name']}</b> بدأ في تنفيذ المهمة للطلب #{ORDER_ID}"; // generic string
            $msg = str_replace('{ORDER_ID}', (string) $orderId, $msg);
            foreach ($managers as $mChat) {
                if (!empty($mChat)) {
                    $this->bot->sendMessage((string)$mChat, $msg);
                }
            }

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل تنفيذ الإجراء.", true);
        }
    }

    /**
     * Employee completes a task.
     */
    private function handleEmployeeComplete(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            // Verify order status
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus === 'Completed' || $currentStatus === 'Cancelled') {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ تم إكمال أو إلغاء هذا الطلب مسبقًا.", true);
                return;
            }

            // Update status to Completed
            $update = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_order SET order_status = 'Completed' WHERE id = ?");
            $update->execute([$orderId]);

            // Save status change log
            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, $currentStatus, 'Completed', "إنهاء المهمة عبر تيليجرام", "Telegram: " . $user['name']);
            }

            $this->pdo->commit();
            
            // Dispatch event
            EventManager::dispatch('OrderUpdated', $this->pdo, $orderId, $currentStatus, 'Completed', "إنهاء المهمة عبر تيليجرام", "Telegram: " . $user['name']);

            $this->bot->answerCallbackQuery($callbackId, "🎉 تم إكمال المهمة بنجاح!");

            // Update UI
            $newText = "🎉 <b>طلب #{$orderId}</b>\n\n🏁 <b>حالة المهمة: مكتملة بنجاح</b>";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل تنفيذ الإجراء.", true);
        }
    }

    /**
     * Manager approves a task.
     */
    private function handleManagerApprove(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus === 'Completed') {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ الطلب مؤكد بالفعل.");
                return;
            }

            $update = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_order SET order_status = 'Completed' WHERE id = ?");
            $update->execute([$orderId]);

            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, $currentStatus, 'Completed', "اعتماد المدير عبر تيليجرام", "Manager: " . $user['name']);
            }

            $this->pdo->commit();
            EventManager::dispatch('OrderUpdated', $this->pdo, $orderId, $currentStatus, 'Completed', "اعتماد المدير عبر تيليجرام", "Manager: " . $user['name']);

            $this->bot->answerCallbackQuery($callbackId, "✅ تم اعتماد الطلب.");
            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n✅ <b>حالة الاعتماد: معتمد</b>";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل الإجراء.", true);
        }
    }

    /**
     * Manager cancels a task.
     */
    private function handleManagerCancel(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? FOR UPDATE");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus === 'Cancelled') {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ الطلب ملغي بالفعل.");
                return;
            }

            $update = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_order SET order_status = 'Cancelled' WHERE id = ?");
            $update->execute([$orderId]);

            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, $currentStatus, 'Cancelled', "إلغاء المدير عبر تيليجرام", "Manager: " . $user['name']);
            }

            $this->pdo->commit();
            EventManager::dispatch('OrderUpdated', $this->pdo, $orderId, $currentStatus, 'Cancelled', "إلغاء المدير عبر تيليجرام", "Manager: " . $user['name']);

            $this->bot->answerCallbackQuery($callbackId, "❌ تم إلغاء الطلب.");
            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n🔴 <b>حالة الاعتماد: ملغي</b>";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل الإجراء.", true);
        }
    }

    private function handleManagerShip(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        try {
            $result = telegram_send_order_to_delivery_company($this->pdo, $orderId, 'Telegram Manager: ' . $user['name']);
            $this->bot->answerCallbackQuery($callbackId, (string) ($result['message'] ?? 'تمت معالجة الطلب.'), empty($result['success']) && empty($result['skipped']));

            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n" . (!empty($result['success']) ? '🚚 ' : '⚠️ ');
            $newText .= htmlspecialchars((string) ($result['message'] ?? ''), ENT_QUOTES, 'UTF-8');
            $this->bot->editMessage($chatId, $messageId, $newText, null);
        } catch (Exception $e) {
            $this->bot->answerCallbackQuery($callbackId, 'فشل الإرسال لشركة التوصيل.', true);
        }
    }

    private function handleManagerNote(string $callbackId, int $orderId, array $user, string $chatId, int $messageId): void
    {
        StateService::setState($this->pdo, $chatId, 'awaiting_manager_order_note', [
            'order_id' => $orderId,
            'manager_id' => $user['id'],
            'message_id' => $messageId
        ]);

        $this->bot->answerCallbackQuery($callbackId, 'أرسل الملاحظة الآن.');
        $this->bot->sendMessage($chatId, "📝 أرسل ملاحظة الطلب #{$orderId} الآن.");
    }

    private function handleManagerDelete(string $callbackId, int $orderId, array $user, string $chatId, int $messageId): void
    {
        global $dbRepo;

        try {
            $stmt = $dbRepo->prepare("SELECT ecotrack_tracking, tracking_number FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                $this->bot->answerCallbackQuery($callbackId, 'الطلب غير موجود.', true);
                return;
            }

            $tracking = trim((string) ($order['ecotrack_tracking'] ?? $order['tracking_number'] ?? ''));
            if ($tracking !== '' && function_exists('front_get_settings')) {
                $settings = ecotrack_normalize_settings(front_get_settings($this->pdo));
                if (ecotrack_is_configured($settings)) {
                    ecotrack_api_request($this->pdo, $settings, 'DELETE', '/api/v1/delete/order', ['tracking' => $tracking], null, 'bearer');
                }
            }

            $this->pdo->beginTransaction();
            foreach (['tbl_order_call_log', 'tbl_order_status_log', 'tbl_order_assignment', 'tbl_telegram_edit_session', 'tbl_telegram_delivery_log'] as $table) {
                if (function_exists('admin_db_table_exists') && admin_db_table_exists($this->pdo, $table)) {
                    $dbRepo->prepare("DELETE FROM `{$table}` WHERE order_id = ?")->execute([$orderId]);
                }
            }
            $dbRepo->prepare("DELETE FROM tbl_order WHERE id = ?")->execute([$orderId]);
            $this->pdo->commit();

            $this->bot->answerCallbackQuery($callbackId, 'تم حذف الطلب.');
            $this->bot->editMessage($chatId, $messageId, "🗑 تم حذف الطلب #{$orderId} بواسطة المدير.", null);
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->bot->answerCallbackQuery($callbackId, 'فشل حذف الطلب: ' . $e->getMessage(), true);
        }
    }

    /**
     * Manager reassigns a task.
     */
    private function handleManagerReassign(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            if (!function_exists('employee_get_next_for_assignment')) {
                require_once __DIR__ . '/../../inc/employee_functions.php';
            }

            $next = employee_get_next_for_assignment($this->pdo);
            if ($next === null) {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ لا يوجد موظفون نشطون آخرون.", true);
                return;
            }

            $newEmpId = (int) $next['id'];
            $success = employee_reassign_order($this->pdo, $orderId, $newEmpId, 'Telegram Manager: ' . $user['name']);

            if (!$success) {
                $this->pdo->rollBack();
                $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل تحويل المهمة.", true);
                return;
            }

            $this->pdo->commit();

            $this->bot->answerCallbackQuery($callbackId, "🔄 تم تحويل الطلب إلى: " . $next['full_name']);

            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n🔄 <b>التحويل: تم تحويله إلى {$next['full_name']}</b>";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

            // Notify newly assigned employee
            EventManager::dispatch('OrderAssigned', $this->pdo, $orderId, $newEmpId);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ حدث خطأ.", true);
        }
    }

    /**
     * Manager closes a task.
     */
    private function handleManagerClose(string $callbackId, int $orderId, array $user, string $chatId, int $messageId, array $callbackQuery): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT order_status FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $currentStatus = $stmt->fetchColumn();

            // Set order to completed (or clean state)
            $update = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_order SET order_status = 'Completed' WHERE id = ?");
            $update->execute([$orderId]);

            if (function_exists('admin_log_order_status_change')) {
                admin_log_order_status_change($this->pdo, $orderId, $currentStatus, 'Completed', "إغلاق المهمة من المدير", "Manager: " . $user['name']);
            }

            $this->pdo->commit();
            EventManager::dispatch('OrderUpdated', $this->pdo, $orderId, $currentStatus, 'Completed', "إغلاق المهمة من المدير", "Manager: " . $user['name']);

            $this->bot->answerCallbackQuery($callbackId, "🔒 تم إغلاق المهمة بنجاح.");
            $newText = ($callbackQuery['message']['text'] ?? '') . "\n\n🔒 <b>الحالة: مغلقة ومكتملة</b>";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ فشل تنفيذ الإجراء.", true);
        }
    }

    /**
     * Manager switches Preferred Language setting via Bot setting.
     */
    private function handleManagerLanguage(string $callbackId, string $langCode, array $user, string $chatId, int $messageId): void
    {
        $langCode = in_array($langCode, ['ar', 'en', 'fr'], true) ? $langCode : 'ar';
        
        $this->pdo->beginTransaction();
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE `tbl_user` SET `telegram_lang` = ? WHERE `id` = ?");
            $stmt->execute([$langCode, $user['id']]);
            $this->pdo->commit();

            $langLabel = $langCode === 'ar' ? 'العربية' : ($langCode === 'en' ? 'English' : 'Français');
            $this->bot->answerCallbackQuery($callbackId, "🌐 تم تغيير اللغة إلى {$langLabel} بنجاح.");
            
            $newText = "🌐 <b>تم تحديث الإعدادات!</b>\n\nاللغة المفضلة الحالية لإشعاراتك هي: <b>{$langLabel}</b>";
            $this->bot->editMessage($chatId, $messageId, $newText, null);

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->bot->answerCallbackQuery($callbackId, "⚠️ حدث خطأ أثناء تغيير اللغة.", true);
        }
    }
}
