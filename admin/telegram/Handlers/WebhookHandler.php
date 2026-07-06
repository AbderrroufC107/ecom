<?php
/**
 * WebhookHandler Class
 *
 * Router for incoming text messages, commands, and conversation states.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Services/TelegramService.php';
require_once __DIR__ . '/../Services/TokenService.php';
require_once __DIR__ . '/../Services/LoggerService.php';
require_once __DIR__ . '/../Services/StateService.php';
require_once __DIR__ . '/../Services/EventManager.php';

class WebhookHandler
{
    private PDO $pdo;
    private TelegramService $bot;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->bot = TelegramService::getInstance($pdo);
    }

    /**
     * Handle incoming webhook requests.
     */
    public function handle(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));

        if ($chatId === '' || $text === '') {
            return;
        }

        // 1. Identify User Role and Details
        $user = $this->identifyUser($chatId);
        
        // Log incoming message
        LoggerService::logAction(
            $this->pdo,
            $chatId,
            $user ? $user['role'] : null,
            $user ? $user['id'] : null,
            'incoming',
            'text_message',
            $message,
            'success'
        );

        // 2. Check for active multi-step conversation states
        $state = StateService::getState($this->pdo, $chatId);
        if ($state && strpos($text, '/') !== 0) {
            $this->handleConversationState($chatId, $user, $state, $text);
            return;
        }

        // 3. Command Routing
        if (strpos($text, '/') === 0) {
            $parts = explode(' ', $text);
            $command = strtolower($parts[0]);
            $arg = $parts[1] ?? '';

            switch ($command) {
                case '/start':
                    $this->handleStartCommand($chatId, $user, $arg, $message);
                    break;
                case '/help':
                    $this->handleHelpCommand($chatId, $user);
                    break;
                case '/profile':
                    $this->handleProfileCommand($chatId, $user);
                    break;
                case '/tasks':
                    $this->handleTasksCommand($chatId, $user);
                    break;
                case '/status':
                    $this->handleStatusCommand($chatId, $user);
                    break;
                case '/settings':
                    $this->handleSettingsCommand($chatId, $user);
                    break;
                case '/reports':
                    $this->handleReportsCommand($chatId, $user);
                    break;
                case '/complaint':
                    $this->handleComplaintCommand($chatId, $user);
                    break;
                case '/leave':
                    $this->handleLeaveCommand($chatId, $user);
                    break;
                default:
                    $this->bot->sendMessage($chatId, "⚠️ أمر غير معروف. اكتب /help لعرض قائمة الأوامر المتاحة.");
                    break;
            }
        } else {
            // General text messages
            $this->bot->sendMessage($chatId, "💬 مرحبًا! يرجى كتابة /help لعرض قائمة الأوامر أو استخدام لوحة التحكم في موقع الويب.");
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
     * Handle multi-step conversation states.
     */
    private function handleConversationState(string $chatId, ?array $user, array $state, string $text): void
    {
        if (!$user) {
            $this->bot->sendMessage($chatId, "⚠️ يجب ربط حسابك أولاً.");
            StateService::clearState($this->pdo, $chatId);
            return;
        }

        $key = $state['state_key'];
        $payload = $state['payload'];

        if ($key === 'awaiting_complaint_subject') {
            $payload['subject'] = $text;
            StateService::setState($this->pdo, $chatId, 'awaiting_complaint_message', $payload);
            $this->bot->sendMessage($chatId, "📥 ممتاز. الآن يرجى كتابة تفاصيل الشكوى بالكامل:");
            return;
        }

        if ($key === 'awaiting_complaint_message') {
            $subject = $payload['subject'] ?? 'شكوى بدون عنوان';
            $message = $text;

            $this->pdo->beginTransaction();
            try {
                $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                    INSERT INTO `tbl_complaints` (`employee_id`, `subject`, `message`, `telegram_status`, `created_at`) 
                    VALUES (?, ?, ?, 'sent', NOW())
                ");
                $stmt->execute([$user['id'], $subject, $message]);
                $complaintId = (int) $this->pdo->lastInsertId();
                $this->pdo->commit();

                // Dispatch Complaint Event
                EventManager::dispatch('ComplaintCreated', $this->pdo, $complaintId);
                
                $this->bot->sendMessage($chatId, "✅ تم تقديم شكواك بنجاح وسيقوم المدير بمراجعتها قريباً.");
            } catch (Exception $e) {
                $this->pdo->rollBack();
                $this->bot->sendMessage($chatId, "⚠️ حدث خطأ أثناء تقديم الشكوى. يرجى المحاولة لاحقاً.");
            }

            StateService::clearState($this->pdo, $chatId);
            return;
        }

        if ($key === 'awaiting_leave_dates') {
            $payload['dates'] = $text;
            StateService::setState($this->pdo, $chatId, 'awaiting_leave_reason', $payload);
            $this->bot->sendMessage($chatId, "✍️ يرجى كتابة سبب طلب الإجازة:");
            return;
        }

        if ($key === 'awaiting_leave_reason') {
            $dates = $payload['dates'] ?? '';
            $reason = $text;

            // Dispatch leave request alert to managers
            $managers = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("SELECT telegram_chat_id FROM tbl_user WHERE telegram_is_linked = 1 AND status = 1")->fetchAll(PDO::FETCH_COLUMN);
            $msg = "ℹ️ <b>طلب إجازة جديد!</b>\n\n<b>الموظف:</b> {$user['name']}\n<b>الفترة:</b> {$dates}\n<b>السبب:</b> {$reason}";
            
            foreach ($managers as $mChat) {
                if (!empty($mChat)) {
                    $this->bot->sendMessage((string)$mChat, $msg);
                }
            }

            $this->bot->sendMessage($chatId, "✅ تم إرسال طلب إجازتك بنجاح إلى الإدارة.");
            StateService::clearState($this->pdo, $chatId);
            return;
        }

        if ($key === 'awaiting_manager_order_note') {
            global $dbRepo;

            $orderId = (int) ($payload['order_id'] ?? 0);
            if ($orderId <= 0) {
                StateService::clearState($this->pdo, $chatId);
                $this->bot->sendMessage($chatId, 'تعذر تحديد الطلب.');
                return;
            }

            try {
                if (!function_exists('telegram_send_order_note_to_delivery_company')) {
                    require_once __DIR__ . '/../../inc/telegram_actions.php';
                }
                $dbRepo->prepare("UPDATE tbl_order SET order_note = ? WHERE id = ?")->execute([$text, $orderId]);
                $result = telegram_send_order_note_to_delivery_company($this->pdo, $orderId, $text);
                StateService::clearState($this->pdo, $chatId);
                $this->bot->sendMessage($chatId, '✅ تم حفظ الملاحظة. ' . (string) ($result['message'] ?? ''));
            } catch (Exception $e) {
                StateService::clearState($this->pdo, $chatId);
                $this->bot->sendMessage($chatId, 'فشل حفظ الملاحظة: ' . $e->getMessage());
            }
            return;
        }

        // Unknown state fallback
        StateService::clearState($this->pdo, $chatId);
        $this->bot->sendMessage($chatId, "⚠️ تم إعادة تعيين الجلسة المنتهية. يرجى المحاولة مجدداً.");
    }

    /**
     * Route /start and /start TOKEN
     */
    private function handleStartCommand(string $chatId, ?array $user, string $token, array $message): void
    {
        if ($token === '') {
            if ($user) {
                $this->bot->sendMessage($chatId, "👋 مرحبًا بك مجددًا <b>{$user['name']}</b>! حسابك مرتبط بنجاح. اكتب /help لعرض قائمة الأوامر.");
            } else {
                $this->bot->sendMessage($chatId, "🔒 يرجى ربط حساب تيليجرام الخاص بك من لوحة تحكم المتجر بالنقر على زر 'ربط حساب Telegram'.");
            }
            return;
        }

        // Verification token provided
        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;

        $res = TokenService::verifyAndLink($this->pdo, $token, $chatId, $username, $firstName);

        if ($res['success']) {
            $roleLabel = ($res['role'] === 'employee') ? 'موظف' : 'مدير';
            $this->bot->sendMessage(
                $chatId, 
                "🎉 <b>تم ربط حسابك بنجاح!</b>\n\nمرحبًا بك <b>{$res['name']}</b> في نظام الإشعارات كـ <b>{$roleLabel}</b>.\nاكتب /help لاستعراض الأوامر المتاحة."
            );

            // Audit Log
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT id FROM tbl_user WHERE role = 'Super Admin' LIMIT 1");
            $stmt->execute();
            $adminId = (int) $stmt->fetchColumn() ?: 1;
            
            // Dispatch Registration Alert if Employee
            if ($res['role'] === 'employee') {
                EventManager::dispatch('EmployeeCreated', $this->pdo, $res['id']);
            }
        } else {
            $this->bot->sendMessage($chatId, "❌ <b>فشل الربط:</b>\n\n" . $res['error']);
        }
    }

    /**
     * Route /help
     */
    private function handleHelpCommand(string $chatId, ?array $user): void
    {
        if (!$user) {
            $this->bot->sendMessage($chatId, "🔒 حسابك غير مرتبط حاليًا. اربط حسابك باستخدام زر الربط في الملف الشخصي بموقع الويب.");
            return;
        }

        $help = "⚙️ <b>قائمة الأوامر المتاحة:</b>\n\n";
        $help .= "/help - عرض قائمة الأوامر\n";
        $help .= "/profile - عرض تفاصيل حسابك المرتبط\n";
        $help .= "/status - التحقق من حالة اتصال البوت\n";

        if ($user['role'] === 'employee') {
            $help .= "/tasks - عرض قائمة مهامك المعلقة والمؤكدة\n";
            $help .= "/complaint - تقديم شكوى جديدة للمدير\n";
            $help .= "/leave - تقديم طلب إجازة جديد\n";
        } elseif ($user['role'] === 'manager') {
            $help .= "/settings - تغيير لغة الإشعارات\n";
            $help .= "/reports - عرض تقرير المبيعات السريع\n";
        }

        $this->bot->sendMessage($chatId, $help);
    }

    /**
     * Route /profile
     */
    private function handleProfileCommand(string $chatId, ?array $user): void
    {
        if (!$user) {
            $this->bot->sendMessage($chatId, "⚠️ لا تملك ملفًا شخصيًا مرتبطًا بعد.");
            return;
        }

        $roleLabel = ($user['role'] === 'employee') ? 'موظف' : 'مدير';
        $profile = "👤 <b>ملف التعريف الخاص بك:</b>\n\n";
        $profile .= "<b>الاسم:</b> {$user['name']}\n";
        $profile .= "<b>البريد:</b> {$user['email']}\n";
        $profile .= "<b>الدور:</b> {$roleLabel}\n";
        $profile .= "<b>اللغة المفضلة:</b> " . strtoupper($user['lang']);

        $this->bot->sendMessage($chatId, $profile);
    }

    /**
     * Route /tasks (Employees only)
     */
    private function handleTasksCommand(string $chatId, ?array $user): void
    {
        if (!$user || $user['role'] !== 'employee') {
            $this->bot->sendMessage($chatId, "🚫 هذا الأمر مخصص للموظفين فقط.");
            return;
        }

        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                SELECT o.* FROM tbl_order o
                INNER JOIN tbl_order_assignment oa ON oa.order_id = o.id AND oa.status = 'active'
                WHERE oa.employee_id = ? AND o.order_status IN ('Pending', 'Confirmed')
                ORDER BY o.id DESC
                LIMIT 5
            ");
            $stmt->execute([$user['id']]);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $tasks = [];
        }

        if (empty($tasks)) {
            $this->bot->sendMessage($chatId, "✅ ليس لديك أي مهام معلقة أو قيد المعالجة حاليًا.");
            return;
        }

        $msg = "📋 <b>قائمة المهام النشطة الخاصة بك (أحدث 5):</b>\n\n";
        foreach ($tasks as $task) {
            $statusLabel = $task['order_status'] === 'Pending' ? 'قيد الانتظار' : 'قيد المعالجة';
            $msg .= "🔹 <b>طلب #{$task['id']}</b> | [{$statusLabel}]\n";
            $msg .= "المنتج: {$task['product_name']}\n";
            $msg .= "الولاية: {$task['wilaya']} | السعر: {$task['total_price']} دج\n";
            $msg .= "تفاصيل: /start task_{$task['id']} (أو انقر على الرسائل السابقة)\n\n";
        }

        $this->bot->sendMessage($chatId, $msg);
    }

    /**
     * Route /status
     */
    private function handleStatusCommand(string $chatId, ?array $user): void
    {
        $status = "🟢 <b>حالة النظام:</b> متصل ونشط.\n\n";
        $status .= "<b>التوقيت الحالي:</b> " . date('Y-m-d H:i:s') . "\n";
        if ($user) {
            $status .= "<b>المرتبط:</b> {$user['name']} (" . ucfirst($user['role']) . ")";
        } else {
            $status .= "<b>المرتبط:</b> حساب زائر غير مسجل.";
        }

        $this->bot->sendMessage($chatId, $status);
    }

    /**
     * Route /settings (Managers only)
     */
    private function handleSettingsCommand(string $chatId, ?array $user): void
    {
        if (!$user || $user['role'] !== 'manager') {
            $this->bot->sendMessage($chatId, "🚫 هذا الأمر مخصص للمديرين فقط.");
            return;
        }

        $buttons = [
            'inline_keyboard' => [
                [
                    ['text' => "🇸🇦 العربية", 'callback_data' => "mgr_lang:ar"],
                    ['text' => "🇺🇸 English", 'callback_data' => "mgr_lang:en"],
                    ['text' => "🇫🇷 Français", 'callback_data' => "mgr_lang:fr"]
                ]
            ]
        ];

        $this->bot->sendMessage($chatId, "🌐 <b>إعدادات اللغة المفضلة للإشعارات:</b>\n\nيرجى تحديد اللغة:", $buttons);
    }

    /**
     * Route /reports (Managers only)
     */
    private function handleReportsCommand(string $chatId, ?array $user): void
    {
        if (!$user || $user['role'] !== 'manager') {
            $this->bot->sendMessage($chatId, "🚫 هذا الأمر مخصص للمديرين فقط.");
            return;
        }

        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("
                SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN order_status = 'Completed' THEN total_price ELSE 0 END) AS revenue
                FROM tbl_order
                WHERE order_date >= DATE(NOW())
            ");
            $today = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $today = null;
        }

        if (!$today) {
            $this->bot->sendMessage($chatId, "⚠️ فشل إعداد التقرير اليومي.");
            return;
        }

        $revenue = number_format((float) ($today['revenue'] ?? 0), 0, '.', ' ');
        
        $msg = "📈 <b>تقرير المبيعات السريع لليوم:</b>\n\n";
        $msg .= "<b>إجمالي طلبات اليوم:</b> " . ($today['total'] ?? 0) . "\n";
        $msg .= "<b>الطلبات المؤكدة:</b> " . ($today['completed'] ?? 0) . "\n";
        $msg .= "<b>الطلبات الملغاة:</b> " . ($today['cancelled'] ?? 0) . "\n";
        $msg .= "<b>إجمالي الإيرادات المؤكدة:</b> {$revenue} دج";

        $this->bot->sendMessage($chatId, $msg);
    }

    /**
     * Route /complaint (Employees only)
     */
    private function handleComplaintCommand(string $chatId, ?array $user): void
    {
        if (!$user || $user['role'] !== 'employee') {
            $this->bot->sendMessage($chatId, "🚫 هذا الأمر مخصص للموظفين فقط.");
            return;
        }

        StateService::setState($this->pdo, $chatId, 'awaiting_complaint_subject');
        $this->bot->sendMessage($chatId, "✍️ يرجى كتابة عنوان أو موضوع الشكوى:");
    }

    /**
     * Route /leave (Employees only)
     */
    private function handleLeaveCommand(string $chatId, ?array $user): void
    {
        if (!$user || $user['role'] !== 'employee') {
            $this->bot->sendMessage($chatId, "🚫 هذا الأمر مخصص للموظفين فقط.");
            return;
        }

        StateService::setState($this->pdo, $chatId, 'awaiting_leave_dates');
        $this->bot->sendMessage($chatId, "📅 يرجى إدخال تواريخ الإجازة المطلوبة (مثال: 2026-07-01 إلى 2026-07-10):");
    }
}
