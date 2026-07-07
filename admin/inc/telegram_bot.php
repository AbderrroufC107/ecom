<?php
if (!defined('TELEGRAM_BOT_LOADED')) {
    define('TELEGRAM_BOT_LOADED', true);

    if (!function_exists('telegram_ensure_tables')) {
        function telegram_ensure_tables(PDO $pdo): void { global $dbRepo;
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_telegram_delivery_log (
                    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL DEFAULT 0,
                    employee_id INT NOT NULL DEFAULT 0, telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
                    telegram_message_id BIGINT DEFAULT NULL, delivery_status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    response_payload TEXT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_delivery_order (order_id), KEY idx_delivery_employee (employee_id),
                    KEY idx_delivery_status (delivery_status), KEY idx_delivery_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_order_edit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL DEFAULT 0,
                    action VARCHAR(50) NOT NULL DEFAULT '', details TEXT DEFAULT NULL,
                    performed_by INT DEFAULT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_order_id (order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_user (
                    id INT AUTO_INCREMENT PRIMARY KEY, full_name VARCHAR(255) DEFAULT '',
                    email VARCHAR(255) DEFAULT '', phone VARCHAR(50) DEFAULT '',
                    password VARCHAR(255) DEFAULT '', role VARCHAR(50) DEFAULT 'Employee',
                    telegram_chat_id VARCHAR(255) DEFAULT NULL, telegram_username VARCHAR(255) DEFAULT NULL,
                    telegram_first_name VARCHAR(255) DEFAULT NULL, telegram_is_linked TINYINT(1) DEFAULT 0,
                    telegram_linked_at DATETIME DEFAULT NULL, telegram_link_token VARCHAR(255) DEFAULT NULL,
                    telegram_link_expires DATETIME DEFAULT NULL, participate_in_assignment TINYINT(1) DEFAULT 1,
                    assignment_weight INT DEFAULT 1, availability_status VARCHAR(20) DEFAULT 'available',
                    max_active_orders INT DEFAULT 5, dashboard_prefs TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Exception $e) {}
        }
    }

    if (!function_exists('telegram_service_send')) {
        /**
         * Bridge to the working class-based transport in
         * admin/telegram/Services/TelegramService.php (real curl to
         * api.telegram.org). Interactive callback responses go through here
         * synchronously so button presses get instant feedback, instead of the
         * queue. Returns the raw Telegram API result array.
         */
        function telegram_service_send(string $method, array $params): array
        {
            global $pdo;
            $svcFile = __DIR__ . '/../telegram/Services/TelegramService.php';
            if (!class_exists('TelegramService') && is_file($svcFile)) {
                require_once $svcFile;
            }
            if (!class_exists('TelegramService') || !($pdo instanceof PDO)) {
                return ['ok' => false, 'description' => 'TelegramService unavailable'];
            }
            try {
                return TelegramService::getInstance($pdo)->apiCall($method, $params);
            } catch (Throwable $e) {
                return ['ok' => false, 'description' => $e->getMessage()];
            }
        }
    }

    if (!function_exists('telegram_send_message')) {
        // NOTE: 3rd arg is an optional inline-keyboard reply_markup (array), which
        // is how admin/inc/telegram_actions.php calls it. All other callers pass
        // only ($chat_id, $text).
        function telegram_send_message($chat_id, $message, $reply_markup = null) {
            $params = [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];
            if (is_array($reply_markup)) {
                $params['reply_markup'] = $reply_markup;
            }
            $res = telegram_service_send('sendMessage', $params);
            return !empty($res['ok']);
        }
    }
    if (!function_exists('telegram_send_order_notification')) {
        function telegram_send_order_notification($order_data, $chat_id = null) { return false; }
    }
    if (!function_exists('telegram_generate_link_token')) {
        function telegram_generate_link_token($user_id, $user_type = 'manager') { return ['success' => false, 'error' => 'Telegram bot not configured']; }
    }
    if (!function_exists('telegram_unlink_account')) {
        function telegram_unlink_account($user_id, $user_type = 'manager') { return ['success' => false, 'error' => 'Telegram bot not configured']; }
    }
    if (!function_exists('telegram_handle_webhook')) {
        function telegram_handle_webhook($data) { return ['ok' => false]; }
    }
    if (!function_exists('telegram_get_status_html')) {
        function telegram_get_status_html($emp) {
            $linked = !empty($emp['telegram_chat_id']) || !empty($emp['telegram_is_linked']);
            return $linked
                ? '<span class="label label-success" style="background:#16a34a;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;">مرتبط</span>'
                : '<span class="label label-default" style="background:#94a3b8;color:#fff;padding:4px 8px;border-radius:4px;font-size:11px;">غير مرتبط</span>';
        }
    }
    if (!function_exists('telegram_notify_assignment')) {
        function telegram_notify_assignment($pdo, $order_id, $employee_id) {
            $emp = function_exists('employee_get_by_id') ? employee_get_by_id($pdo, (int) $employee_id) : null;
            $chat = trim((string) ($emp['telegram_chat_id'] ?? ''));
            if ($chat === '') {
                return false;
            }
            global $dbRepo;
            $repo = ($dbRepo ?? null) ?: $pdo;
            $stmt = $repo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return false;
            }
            $text = telegram_build_order_notification($order, $emp);
            $buttons = telegram_build_action_buttons((int) $order_id, $order['order_status'] ?? 'Pending');
            return telegram_send_message($chat, $text, $buttons);
        }
    }
    if (!function_exists('telegram_send_test')) {
        function telegram_send_test($pdo, $employee_id) {
            $emp = function_exists('employee_get_by_id') ? employee_get_by_id($pdo, (int) $employee_id) : null;
            $chat = trim((string) ($emp['telegram_chat_id'] ?? ''));
            if ($chat === '') {
                return ['success' => false, 'message' => 'هذا الموظف غير مرتبط بحساب تيليجرام (لا يوجد معرّف دردشة).'];
            }
            $name = htmlspecialchars((string) ($emp['full_name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $ok = telegram_send_message($chat, "✅ <b>رسالة اختبار</b>\nتم ربط حسابك بنجاح يا {$name}. ستصلك إشعارات الطلبات هنا.");
            return $ok
                ? ['success' => true, 'message' => 'تم إرسال رسالة اختبار إلى تيليجرام بنجاح.']
                : ['success' => false, 'message' => 'تعذّر الإرسال. تأكد من ضبط توكن البوت وتفعيله في الإعدادات، ومن صحة معرّف الدردشة.'];
        }
    }

    // Message-text + inline-keyboard builders and the edit/answer transport used
    // by the fully-implemented legacy handlers in admin/inc/telegram_actions.php
    // (confirm / cancel / edit / note / ship / delete / reassign / back). These
    // were previously undefined (handlers fatal-errored) then no-op stubs (buttons
    // silently did nothing). Now wired to the real TelegramService transport so the
    // employee inline buttons actually work.
    if (!function_exists('telegram_edit_message_text')) {
        function telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup = null) {
            $params = [
                'chat_id' => $chat_id,
                'message_id' => (int) $message_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];
            if (is_array($reply_markup)) {
                $params['reply_markup'] = $reply_markup;
            }
            $res = telegram_service_send('editMessageText', $params);
            return !empty($res['ok']);
        }
    }
    if (!function_exists('telegram_answer_callback_query')) {
        function telegram_answer_callback_query($callback_id, $text = '') {
            $params = ['callback_query_id' => (string) $callback_id];
            if ($text !== '' && $text !== null) {
                $params['text'] = $text;
            }
            $res = telegram_service_send('answerCallbackQuery', $params);
            return !empty($res['ok']);
        }
    }
    if (!function_exists('telegram_build_order_notification')) {
        function telegram_build_order_notification($order, $employee, $extra = null) {
            $statusMap = [
                'Pending' => 'قيد الانتظار', 'Confirmed' => 'مؤكد', 'Completed' => 'مكتمل',
                'Cancelled' => 'ملغى', 'Returned' => 'مرتجع', 'Delivered' => 'تم التسليم',
            ];
            $st = (string) ($order['order_status'] ?? '');
            $stAr = $statusMap[$st] ?? $st;
            $esc = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };

            $lines = [];
            $lines[] = '🔔 <b>طلب #' . (int) ($order['id'] ?? 0) . '</b>';
            if (!empty($order['product_name']))  { $lines[] = '📦 ' . $esc($order['product_name']); }
            if (!empty($order['customer_name']))  { $lines[] = '👤 ' . $esc($order['customer_name']); }
            if (!empty($order['customer_phone'])) { $lines[] = '📞 ' . $esc($order['customer_phone']); }
            $loc = trim((string) ($order['wilaya'] ?? '') . ' - ' . (string) ($order['commune'] ?? ''), ' -');
            if ($loc !== '') { $lines[] = '📍 ' . $esc($loc); }
            if (isset($order['total_price'])) { $lines[] = '💰 ' . $esc(number_format((float) $order['total_price'], 2)) . ' دج'; }
            $lines[] = '📊 ' . $esc($stAr);
            if (!empty($employee['full_name'])) { $lines[] = '👔 ' . $esc($employee['full_name']); }

            // Call attempts summary (from tbl_order_call_log) so the employee sees
            // how many times, and when, the customer was called.
            $oid = (int) ($order['id'] ?? 0);
            if ($oid > 0) {
                global $dbRepo;
                try {
                    $cs = $dbRepo->prepare("SELECT COUNT(*) AS cnt, MAX(called_at) AS last_at, SUM(CASE WHEN call_status = 'no_answer' THEN 1 ELSE 0 END) AS no_ans FROM tbl_order_call_log WHERE order_id = ?");
                    $cs->execute([$oid]);
                    $crow = $cs->fetch(PDO::FETCH_ASSOC) ?: [];
                    $cnt = (int) ($crow['cnt'] ?? 0);
                    if ($cnt > 0) {
                        $noAns = (int) ($crow['no_ans'] ?? 0);
                        $lines[] = '📞 محاولات الاتصال: ' . $cnt . ($noAns > 0 ? ' (لم يرد: ' . $noAns . ')' : '');
                        if (!empty($crow['last_at'])) {
                            $lines[] = '🕐 آخر محاولة: ' . $esc(date('Y-m-d H:i', strtotime((string) $crow['last_at'])));
                        }
                    }
                } catch (Throwable $e) {
                    // tbl_order_call_log may not exist yet — skip silently.
                }
            }

            // $extra is already HTML-safe (built by the handlers) — append as-is.
            if ($extra !== null && $extra !== '' && is_string($extra)) { $lines[] = "\n" . $extra; }
            return implode("\n", $lines);
        }
    }
    if (!function_exists('telegram_build_action_buttons')) {
        function telegram_build_action_buttons($order_id, $status) {
            $oid = (int) $order_id;
            if ($status === 'Pending') {
                return ['inline_keyboard' => [
                    [['text' => '✅ تأكيد الطلب', 'callback_data' => "confirm:{$oid}"], ['text' => '✏️ تعديل الطلب', 'callback_data' => "edit:{$oid}"]],
                    [['text' => '📞 لم يرد', 'callback_data' => "noanswer:{$oid}"], ['text' => '📝 ملاحظة', 'callback_data' => "note:{$oid}"]],
                    [['text' => '🚚 إرسال للشركة', 'callback_data' => "ship:{$oid}"], ['text' => '❌ إلغاء الطلب', 'callback_data' => "cancel:{$oid}"]],
                    [['text' => '🗑 حذف الطلب', 'callback_data' => "delete:{$oid}"]],
                ]];
            }
            if ($status === 'Confirmed') {
                // Kept in sync with the initial notification in
                // admin/telegram/Providers/TelegramNotificationProvider.php.
                // "إنهاء المهمة" uses the emp_ prefix so it routes to the new
                // CallbackHandler; the rest route to the legacy handlers here.
                return ['inline_keyboard' => [
                    [['text' => '🚚 إرسال للشركة', 'callback_data' => "ship:{$oid}"], ['text' => '🏁 إنهاء المهمة', 'callback_data' => "emp_complete:{$oid}"]],
                    [['text' => '📝 ملاحظة', 'callback_data' => "note:{$oid}"], ['text' => '🗑 حذف الطلب', 'callback_data' => "delete:{$oid}"]],
                ]];
            }
            // Terminal states (Cancelled / Delivered / Returned): no actions.
            return ['inline_keyboard' => []];
        }
    }

    if (!function_exists('telegram_is_delivery_noanswer_status')) {
        /**
         * Heuristic: does an Ecotrack status/note mean the delivery driver tried
         * to reach the customer and failed (no answer / unreachable / suspended)?
         * Ecotrack has no single "no answer" status — the signal is the "suspendu"
         * status or a French/Arabic no-answer note in the tracking history.
         */
        function telegram_is_delivery_noanswer_status($status, $note = ''): bool
        {
            $hay = function_exists('mb_strtolower')
                ? mb_strtolower(trim((string) $status . ' ' . (string) $note))
                : strtolower(trim((string) $status . ' ' . (string) $note));
            if ($hay === '') {
                return false;
            }
            $needles = [
                'suspendu', 'tentative', 'injoignable', 'pas de reponse', 'pas de réponse',
                'ne repond', 'ne répond', 'no answer', 'absent', 'unreachable', 'client absent',
                'لم يرد', 'لا يرد', 'لم يجب', 'لا يجيب', 'غير متواجد', 'لم يُجب', 'معلّق', 'معلق',
            ];
            foreach ($needles as $n) {
                if (mb_strpos($hay, $n) !== false) {
                    return true;
                }
            }
            return false;
        }
    }

    if (!function_exists('telegram_notify_employee_delivery_noanswer')) {
        /**
         * Alerts the employee assigned to an order that the delivery driver could
         * not reach the customer, so they can follow up. Returns true if sent.
         */
        function telegram_notify_employee_delivery_noanswer(PDO $pdo, int $order_id, string $status, string $note = ''): bool
        { global $dbRepo;
            $stmt = $dbRepo->prepare("
                SELECT e.id, e.full_name, e.telegram_chat_id
                FROM tbl_order_assignment oa
                INNER JOIN tbl_employee e ON e.id = oa.employee_id
                WHERE oa.order_id = ? AND oa.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$order_id]);
            $emp = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$emp) {
                return false;
            }
            $chat = trim((string) ($emp['telegram_chat_id'] ?? ''));
            if ($chat === '') {
                return false;
            }

            $esc = static function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
            $lines = [];
            $lines[] = '⚠️ <b>تنبيه توصيل — الطلب #' . (int) $order_id . '</b>';
            $lines[] = 'حاول موزّع شركة التوصيل الاتصال بالزبون ولم يتم الرد.';
            if (trim($status) !== '') { $lines[] = '📊 الحالة: ' . $esc($status); }
            if (trim($note) !== '')   { $lines[] = '📝 ' . $esc($note); }
            $lines[] = 'يرجى متابعة الزبون في أقرب وقت.';

            $ok = telegram_send_message($chat, implode("\n", $lines));

            if (function_exists('telegram_log_action')) {
                telegram_log_action($pdo, (int) $emp['id'], $order_id, 'delivery_no_answer', null, ['status' => $status, 'note' => $note]);
            }
            return $ok;
        }
    }
}
