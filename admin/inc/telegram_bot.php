<?php
if (!defined('TELEGRAM_BOT_LOADED')) {
    define('TELEGRAM_BOT_LOADED', true);

    if (!function_exists('telegram_ensure_tables')) {
        function telegram_ensure_tables(PDO $pdo): void
        {
            $lock_file = __DIR__ . '/../cache/telegram_tables.lock';
            if (file_exists($lock_file)) {
                return;
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_telegram_delivery_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL DEFAULT 0,
                    employee_id INT NOT NULL DEFAULT 0,
                    telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
                    telegram_message_id BIGINT DEFAULT NULL,
                    delivery_status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    response_payload TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_delivery_order (order_id),
                    KEY idx_delivery_employee (employee_id),
                    KEY idx_delivery_status (delivery_status),
                    KEY idx_delivery_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_order_edit_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    employee_id INT NOT NULL,
                    field_name VARCHAR(100) NOT NULL,
                    old_value TEXT DEFAULT NULL,
                    new_value TEXT DEFAULT NULL,
                    edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_edit_order (order_id),
                    KEY idx_edit_employee (employee_id),
                    KEY idx_edit_field (field_name),
                    KEY idx_edit_at (edited_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_order_cancellation_reason (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    employee_id INT NOT NULL,
                    reason VARCHAR(255) NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_cancel_order (order_id),
                    KEY idx_cancel_employee (employee_id),
                    KEY idx_cancel_reason (reason),
                    KEY idx_cancel_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_telegram_action_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    order_id INT NOT NULL DEFAULT 0,
                    action_type VARCHAR(50) NOT NULL,
                    telegram_user_id BIGINT DEFAULT NULL,
                    payload TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_action_employee (employee_id),
                    KEY idx_action_order (order_id),
                    KEY idx_action_type (action_type),
                    KEY idx_action_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_telegram_edit_session (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    order_id INT NOT NULL,
                    field_name VARCHAR(100) NOT NULL,
                    old_value TEXT DEFAULT NULL,
                    chat_id VARCHAR(255) NOT NULL DEFAULT '',
                    message_id BIGINT DEFAULT NULL,
                    status VARCHAR(50) NOT NULL DEFAULT 'pending',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_session_employee (employee_id),
                    KEY idx_session_order (order_id),
                    KEY idx_session_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_event_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(80) NOT NULL,
                    order_id INT NOT NULL DEFAULT 0,
                    employee_id INT NOT NULL DEFAULT 0,
                    payload TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_event_type (event_type),
                    KEY idx_event_order (order_id),
                    KEY idx_event_employee (employee_id),
                    KEY idx_event_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_event_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    config_key VARCHAR(80) NOT NULL UNIQUE,
                    config_value VARCHAR(255) NOT NULL DEFAULT '',
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("INSERT IGNORE INTO tbl_event_settings (config_key, config_value) VALUES
                ('event_unprocessed_order_enabled', '1'),
                ('event_unprocessed_order_minutes', '15'),
                ('event_employee_inactivity_enabled', '1'),
                ('event_employee_inactivity_minutes', '60'),
                ('event_ecotrack_status_enabled', '1'),
                ('event_delivered_enabled', '1'),
                ('event_returned_enabled', '1'),
                ('event_high_cancellation_enabled', '1'),
                ('event_cancellation_threshold', '40'),
                ('event_cancellation_hours', '1'),
                ('event_failed_telegram_enabled', '1'),
                ('event_failed_telegram_attempts', '3'),
                ('event_unassigned_orders_enabled', '1'),
                ('event_bot_chat_id', ''),
                ('last_event_monitor_run', '')
            ");

            @file_put_contents($lock_file, '1');
        }
    }

    if (!function_exists('telegram_api_call')) {
        function telegram_api_call(string $method, array $data): array
        {
            $token = defined('TELEGRAM_BOT_TOKEN') ? TELEGRAM_BOT_TOKEN : '';
            if ($token === '' || $token === 'BOT_TOKEN') {
                return ['ok' => false, 'description' => 'BOT_TOKEN not configured'];
            }

            $url = "https://api.telegram.org/bot{$token}/{$method}";
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);

            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => $json,
                    'timeout' => 15,
                    'ignore_errors' => true,
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                $err = error_get_last();
                return ['ok' => false, 'description' => $err['message'] ?? 'HTTP request failed'];
            }

            $decoded = json_decode($response, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'description' => 'Invalid JSON response from Telegram'];
            }

            return $decoded;
        }
    }

    if (!function_exists('telegram_send_message')) {
        function telegram_send_message(string $chat_id, string $text, ?array $reply_markup = null): array
        {
            $data = [
                'chat_id' => $chat_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if ($reply_markup !== null) {
                $data['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
            }

            $max_attempts = 3;
            $last_error = '';

            for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
                $result = telegram_api_call('sendMessage', $data);
                if (!empty($result['ok'])) {
                    return [
                        'success' => true,
                        'message_id' => $result['result']['message_id'] ?? null,
                        'error' => null,
                    ];
                }
                $last_error = $result['description'] ?? 'Unknown Telegram error';
                if ($attempt < $max_attempts) {
                    sleep(1);
                }
            }

            return [
                'success' => false,
                'message_id' => null,
                'error' => $last_error,
            ];
        }
    }

    if (!function_exists('telegram_edit_message_text')) {
        function telegram_edit_message_text(string $chat_id, int $message_id, string $text, ?array $reply_markup = null): array
        {
            $data = [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ];

            if ($reply_markup !== null) {
                $data['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
            }

            $result = telegram_api_call('editMessageText', $data);
            if (!empty($result['ok'])) {
                return [
                    'success' => true,
                    'message_id' => $result['result']['message_id'] ?? $message_id,
                    'error' => null,
                ];
            }
            return [
                'success' => false,
                'message_id' => $message_id,
                'error' => $result['description'] ?? 'Failed to edit message',
            ];
        }
    }

    if (!function_exists('telegram_edit_message_reply_markup')) {
        function telegram_edit_message_reply_markup(string $chat_id, int $message_id, ?array $reply_markup): array
        {
            $data = [
                'chat_id' => $chat_id,
                'message_id' => $message_id,
            ];

            if ($reply_markup !== null) {
                $data['reply_markup'] = json_encode($reply_markup, JSON_UNESCAPED_UNICODE);
            }

            $result = telegram_api_call('editMessageReplyMarkup', $data);
            if (!empty($result['ok'])) {
                return ['success' => true, 'error' => null];
            }
            return ['success' => false, 'error' => $result['description'] ?? 'Failed to edit reply markup'];
        }
    }

    if (!function_exists('telegram_answer_callback_query')) {
        function telegram_answer_callback_query(string $callback_query_id, string $text = ''): void
        {
            $data = ['callback_query_id' => $callback_query_id];
            if ($text !== '') {
                $data['text'] = $text;
            }
            telegram_api_call('answerCallbackQuery', $data);
        }
    }

    if (!function_exists('telegram_log_delivery')) {
        function telegram_log_delivery(PDO $pdo, array $data): int
        {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_telegram_delivery_log
                    (order_id, employee_id, telegram_chat_id, telegram_message_id, delivery_status, response_payload)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int) ($data['order_id'] ?? 0),
                (int) ($data['employee_id'] ?? 0),
                $data['telegram_chat_id'] ?? '',
                $data['telegram_message_id'] ?? null,
                $data['delivery_status'] ?? 'pending',
                $data['response_payload'] ?? null,
            ]);
            return (int) $pdo->lastInsertId();
        }
    }

    if (!function_exists('telegram_status_label')) {
        function telegram_status_label(string $status): string
        {
            $map = [
                'Pending' => "\xF0\x9F\x9F\xA1 \xD9\x82\xD9\x8A\xD8\xAF \xD8\xA7\xD9\x84\xD8\xA7\xD9\x86\xD8\xAA\xD8\xB8\xD8\xA7\xD8\xB1",
                'Confirmed' => "\xF0\x9F\x94\xB5 \xD9\x82\xD9\x8A\xD8\xAF \xD8\xA7\xD9\x84\xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\xD8\xA9",
                'Completed' => "\xE2\x9C\x85 \xD9\x85\xD8\xA4\xD9\x83\xD8\xAF",
                'Cancelled' => "\xE2\x9D\x8C \xD9\x85\xD9\x84\xD8\xBA\xD9\x8A",
                'Returned' => "\xF0\x9F\x94\x84 \xD9\x85\xD8\xB1\xD8\xAA\xD8\xAC\xD8\xB9",
            ];
            return $map[$status] ?? $status;
        }
    }

    if (!function_exists('telegram_build_order_notification')) {
        function telegram_build_order_notification(array $order, array $employee, ?string $extra_note = null): string
        {
            $product_name = htmlspecialchars($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $customer_name = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $customer_phone = htmlspecialchars($order['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8');
            $wilaya = htmlspecialchars($order['wilaya'] ?? '', ENT_QUOTES, 'UTF-8');
            $commune = htmlspecialchars($order['commune'] ?? '', ENT_QUOTES, 'UTF-8');
            $total_price = htmlspecialchars((string) ($order['total_price'] ?? '0'), ENT_QUOTES, 'UTF-8');
            $order_date = htmlspecialchars($order['order_date'] ?? '', ENT_QUOTES, 'UTF-8');
            $status = $order['order_status'] ?? 'Pending';
            $note = $order['order_note'] ?? '';
            $note_text = $note !== '' ? "\n\xD9\x85\xD9\x84\xD8\xA7\xD8\xAD\xD8\xB8\xD8\xA9: " . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') : '';

            $text = "\xF0\x9F\x9B\x92 \xD8\xB7\xD9\x84\xD8\xA8 #{$order['id']}\n";
            $text .= telegram_status_label($status) . "\n";
            $text .= str_repeat("\xE2\x94\x81", 16) . "\n\n";
            $text .= "\xF0\x9F\x91\xA4 {$customer_name}\n";
            $text .= "\xF0\x9F\x93\x9E {$customer_phone}\n";
            $text .= "\xF0\x9F\x93\x8D {$wilaya} - {$commune}\n\n";
            $text .= "\xF0\x9F\x93\xA6 {$product_name}\n";
            $text .= "\xF0\x9F\x92\xB0 {$total_price} \xD8\xAF\xD8\xAC\n\n";
            $text .= "\xF0\x9F\x95\x90 {$order_date}";
            $text .= $note_text;

            if ($extra_note !== null) {
                $text .= "\n\n" . $extra_note;
            }

            return $text;
        }
    }

    if (!function_exists('telegram_build_action_buttons')) {
        function telegram_build_action_buttons(int $order_id, string $status): array
        {
            $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
            $order_url = $site_url . '/admin/order-details.php?id=' . $order_id;

            if ($status === 'Pending') {
                return [
                    'inline_keyboard' => [
                        [
                            ['text' => "\xE2\x9C\x85 \xD8\xAA\xD8\xA3\xD9\x83\xD9\x8A\xD8\xAF \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'callback_data' => "confirm:{$order_id}"],
                            ['text' => "\xE2\x9C\x8F\xEF\xB8\x8F \xD8\xAA\xD8\xB9\xD8\xAF\xD9\x8A\xD9\x84 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'callback_data' => "edit:{$order_id}"],
                        ],
                        [
                            ['text' => "\xF0\x9F\x93\x84 \xD9\x81\xD8\xAA\xD8\xAD \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'url' => $order_url],
                            ['text' => "\xE2\x9D\x8C \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'callback_data' => "cancel:{$order_id}"],
                        ],
                    ]
                ];
            }

            return [
                'inline_keyboard' => [
                    [
                        ['text' => "\xF0\x9F\x93\x84 \xD9\x81\xD8\xAA\xD8\xAD \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'url' => $order_url],
                    ]
                ]
            ];
        }
    }

    if (!function_exists('telegram_notify_assignment')) {
        function telegram_notify_assignment(PDO $pdo, int $order_id, int $employee_id): bool
        {
            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return false;
            }

            if (!function_exists('employee_get_by_id')) {
                require_once __DIR__ . '/employee_functions.php';
            }
            $employee = employee_get_by_id($pdo, $employee_id);
            if (!$employee) {
                return false;
            }

            $chat_id = trim((string) ($employee['telegram_chat_id'] ?? ''));
            if ($chat_id === '') {
                return false;
            }

            $status = $order['order_status'] ?? 'Pending';
            $text = telegram_build_order_notification($order, $employee);
            $reply_markup = telegram_build_action_buttons($order_id, $status);

            $result = telegram_send_message($chat_id, $text, $reply_markup);

            telegram_log_delivery($pdo, [
                'order_id' => $order_id,
                'employee_id' => $employee_id,
                'telegram_chat_id' => $chat_id,
                'telegram_message_id' => $result['message_id'],
                'delivery_status' => $result['success'] ? 'sent' : 'failed',
                'response_payload' => $result['error'] ?? json_encode($result),
            ]);

            return $result['success'];
        }
    }

    if (!function_exists('telegram_send_test')) {
        function telegram_send_test(PDO $pdo, int $employee_id): array
        {
            if (!function_exists('employee_get_by_id')) {
                require_once __DIR__ . '/employee_functions.php';
            }
            $employee = employee_get_by_id($pdo, $employee_id);
            if (!$employee) {
                return ['success' => false, 'message' => 'الموظف غير موجود.'];
            }

            $chat_id = trim((string) ($employee['telegram_chat_id'] ?? ''));
            if ($chat_id === '') {
                return ['success' => false, 'message' => 'معرّف التيليجرام غير موجود.'];
            }

            $employee_name = htmlspecialchars($employee['full_name'], ENT_QUOTES, 'UTF-8');
            $text = "\xE2\x9C\x85 Telegram connection successful\n\n";
            $text .= "\xD8\xA7\xD8\xAE\xD8\xAA\xD8\xA8\xD8\xA7\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xA7\xD8\xAA\xD8\xB5\xD8\xA7\xD9\x84 \xD9\x86\xD8\xAC\xD8\xAD\n\n";
            $text .= "\xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$employee_name}";

            $result = telegram_send_message($chat_id, $text, null);

            telegram_log_delivery($pdo, [
                'order_id' => 0,
                'employee_id' => $employee_id,
                'telegram_chat_id' => $chat_id,
                'telegram_message_id' => $result['message_id'],
                'delivery_status' => $result['success'] ? 'test_sent' : 'test_failed',
                'response_payload' => $result['error'] ?? json_encode($result),
            ]);

            if ($result['success']) {
                return ['success' => true, 'message' => 'تم إرسال رسالة الاختبار بنجاح.'];
            }

            return ['success' => false, 'message' => 'فشل الإرسال: ' . ($result['error'] ?? 'خطأ غير معروف')];
        }
    }

    if (!function_exists('telegram_get_status_html')) {
        function telegram_get_status_html(array $employee): string
        {
            $chat_id = trim((string) ($employee['telegram_chat_id'] ?? ''));
            if ($chat_id === '') {
                return '<span style="color:#b91c1c;font-weight:700;">❌ Missing Chat ID</span>';
            }
            return '<span style="color:#15803d;font-weight:700;">✅ Connected</span>';
        }
    }

    if (!function_exists('telegram_send_event')) {
        function telegram_send_event(string $text, ?array $reply_markup = null): array
        {
            $chat_id = defined('EVENT_BOT_CHAT_ID') ? trim(EVENT_BOT_CHAT_ID) : '';
            if ($chat_id === '') {
                $pdo = null;
                if (function_exists('telegram_get_event_setting')) {
                    $pdo = $GLOBALS['pdo'] ?? null;
                    if ($pdo !== null) {
                        $chat_id = telegram_get_event_setting($pdo, 'event_bot_chat_id');
                    }
                }
                if ($chat_id === '') {
                    return ['success' => false, 'error' => 'EVENT_BOT_CHAT_ID not configured'];
                }
            }
            return telegram_send_message($chat_id, $text, $reply_markup);
        }
    }

    if (!function_exists('telegram_get_event_setting')) {
        function telegram_get_event_setting(PDO $pdo, string $key, string $default = ''): string
        {
            static $cache = [];
            if (!isset($cache[$key])) {
                $stmt = $pdo->prepare("SELECT config_value FROM tbl_event_settings WHERE config_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $val = $stmt->fetchColumn();
                $cache[$key] = $val !== false ? (string) $val : $default;
            }
            return $cache[$key];
        }
    }

    if (!function_exists('telegram_event_setting_bool')) {
        function telegram_event_setting_bool(PDO $pdo, string $key): bool
        {
            return telegram_get_event_setting($pdo, $key, '0') === '1';
        }
    }

    if (!function_exists('telegram_event_setting_int')) {
        function telegram_event_setting_int(PDO $pdo, string $key, int $default = 0): int
        {
            return (int) telegram_get_event_setting($pdo, $key, (string) $default);
        }
    }

    if (!function_exists('telegram_log_event')) {
        function telegram_log_event(PDO $pdo, string $event_type, int $order_id = 0, int $employee_id = 0, $payload = null): int
        {
            $stmt = $pdo->prepare("INSERT INTO tbl_event_log (event_type, order_id, employee_id, payload) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $event_type,
                $order_id,
                $employee_id,
                $payload !== null ? (is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE)) : null,
            ]);
            return (int) $pdo->lastInsertId();
        }
    }

    if (!function_exists('telegram_was_event_recently_sent')) {
        function telegram_was_event_recently_sent(PDO $pdo, string $event_type, int $order_id = 0, int $employee_id = 0, int $minutes_back = 60): bool
        {
            $stmt = $pdo->prepare("
                SELECT id FROM tbl_event_log
                WHERE event_type = ? AND order_id = ? AND employee_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                LIMIT 1
            ");
            $stmt->execute([$event_type, $order_id, $employee_id, $minutes_back]);
            return (bool) $stmt->fetch();
        }
    }

    if (!function_exists('telegram_build_event_unprocessed')) {
        function telegram_build_event_unprocessed(array $order, array $employee, int $minutes): string
        {
            $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
            $url = $site_url . '/admin/order-details.php?id=' . $order['id'];
            $name = htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $phone = htmlspecialchars($order['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8');
            $customer = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $product = htmlspecialchars($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars((string) ($order['total_price'] ?? '0'), ENT_QUOTES, 'UTF-8');

            $text = "\xE2\x9A\xA0\xEF\xB8\x8F \xD8\xB7\xD9\x84\xD8\xA8 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\n\n";
            $text .= "\xF0\x9F\x94\xA2 #{$order['id']}\n";
            $text .= "\xF0\x9F\x91\xA4 {$customer}\n";
            $text .= "\xF0\x9F\x93\x9E {$phone}\n";
            $text .= "\xF0\x9F\x93\xA6 {$product}\n";
            $text .= "\xF0\x9F\x92\xB0 {$price} \xD8\xAF\xD8\xAC\n\n";
            $text .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$name}\n";
            $text .= "\xE2\x8F\xB0 \xD9\x85\xD9\x86\xD8\xB0: {$minutes} \xD8\xAF\xD9\x82\xD9\x8A\xD9\x82\xD8\xA9";

            return $text;
        }
    }

    if (!function_exists('telegram_build_event_buttons')) {
        function telegram_build_event_buttons(int $order_id): array
        {
            $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
            $order_url = $site_url . '/admin/order-details.php?id=' . $order_id;
            return [
                'inline_keyboard' => [
                    [
                        ['text' => "\xF0\x9F\x93\x84 \xD9\x81\xD8\xAA\xD8\xAD \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'url' => $order_url],
                        ['text' => "\xF0\x9F\x94\x80 \xD8\xA5\xD8\xB9\xD8\xA7\xD8\xAF\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x88\xD8\xB2\xD9\x8A\xD8\xB9", 'callback_data' => "reassign:{$order_id}"],
                    ],
                ]
            ];
        }
    }

    if (!function_exists('telegram_build_event_unassigned')) {
        function telegram_build_event_unassigned(array $order): string
        {
            $phone = htmlspecialchars($order['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8');
            $customer = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $product = htmlspecialchars($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars((string) ($order['total_price'] ?? '0'), ENT_QUOTES, 'UTF-8');

            $text = "\xE2\x9A\xA0\xEF\xB8\x8F \xD8\xB7\xD9\x84\xD8\xA8 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD9\x88\xD8\xB2\xD8\xB9\n\n";
            $text .= "\xF0\x9F\x94\xA2 #{$order['id']}\n";
            $text .= "\xF0\x9F\x91\xA4 {$customer}\n";
            $text .= "\xF0\x9F\x93\x9E {$phone}\n";
            $text .= "\xF0\x9F\x93\xA6 {$product}\n";
            $text .= "\xF0\x9F\x92\xB0 {$price} \xD8\xAF\xD8\xAC";

            return $text;
        }
    }

    if (!function_exists('telegram_build_event_inactivity')) {
        function telegram_build_event_inactivity(array $employee, int $pending_count): string
        {
            $name = htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $text = "\xE2\x9A\xA0\xEF\xB8\x8F \xD9\x85\xD9\x88\xD8\xB8\xD9\x81 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x86\xD8\xB4\xD8\xB7\n\n";
            $text .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$name}\n";
            $text .= "\xF0\x9F\x93\x8B \xD8\xB7\xD9\x84\xD8\xA8\xD8\xA7\xD8\xAA \xD9\x85\xD8\xB9\xD9\x84\xD9\x82\xD8\xA9: {$pending_count}";
            return $text;
        }
    }

    if (!function_exists('telegram_build_event_ecotrack')) {
        function telegram_build_event_ecotrack(array $order, string $old_status, string $new_status, array $employee = null): string
        {
            $emp_name = $employee ? htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8') : "\xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xAD\xD8\xAF\xD8\xAF";
            $text = "\xF0\x9F\x9A\x9A \xD8\xAA\xD8\xAD\xD8\xAF\xD9\x8A\xD8\xAB \xD8\xAD\xD8\xA7\xD9\x84\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xB4\xD8\xAD\xD9\x86\xD8\xA9\n\n";
            $text .= "\xF0\x9F\x94\xA2 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8: #{$order['id']}\n\n";
            $text .= "\xD8\xA7\xD9\x84\xD8\xAD\xD8\xA7\xD9\x84\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xB3\xD8\xA7\xD8\xA8\xD9\x82\xD8\xA9: {$old_status}\n";
            $text .= "\xD8\xA7\xD9\x84\xD8\xAD\xD8\xA7\xD9\x84\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAC\xD8\xAF\xD9\x8A\xD8\xAF\xD8\xA9: {$new_status}\n\n";
            $text .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$emp_name}";
            return $text;
        }
    }

    if (!function_exists('telegram_build_event_delivered')) {
        function telegram_build_event_delivered(array $order, array $employee = null): string
        {
            $emp_name = $employee ? htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8') : "\xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xAD\xD8\xAF\xD8\xAF";
            $customer = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $phone = htmlspecialchars($order['customer_phone'] ?? '', ENT_QUOTES, 'UTF-8');
            $product = htmlspecialchars($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $price = htmlspecialchars((string) ($order['total_price'] ?? '0'), ENT_QUOTES, 'UTF-8');

            $text = "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xA7\xD9\x84\xD8\xAA\xD8\xB3\xD9\x84\xD9\x8A\xD9\x85\n\n";
            $text .= "\xF0\x9F\x94\xA2 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 #{$order['id']}\n";
            $text .= "\xF0\x9F\x91\xA4 {$customer}\n";
            $text .= "\xF0\x9F\x93\x9E {$phone}\n";
            $text .= "\xF0\x9F\x93\xA6 {$product}\n";
            $text .= "\xF0\x9F\x92\xB0 {$price} \xD8\xAF\xD8\xAC\n\n";
            $text .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81 \xD8\xA7\xD9\x84\xD9\x85\xD8\xB3\xD8\xA4\xD9\x88\xD9\x84: {$emp_name}";
            return $text;
        }
    }

    if (!function_exists('telegram_build_event_returned')) {
        function telegram_build_event_returned(array $order, string $reason = '', array $employee = null): string
        {
            $emp_name = $employee ? htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8') : "\xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xAD\xD8\xAF\xD8\xAF";
            $customer = htmlspecialchars($order['customer_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $product = htmlspecialchars($order['product_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $reason_text = $reason !== '' ? "\n\xD8\xB3\xD8\xA8\xD8\xA8 \xD8\xA7\xD9\x84\xD9\x85\xD8\xB1\xD8\xAA\xD8\xAC\xD8\xB9: " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') : '';

            $text = "\xE2\x9D\x8C \xD9\x85\xD8\xB1\xD8\xAA\xD8\xAC\xD8\xB9\n\n";
            $text .= "\xF0\x9F\x94\xA2 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 #{$order['id']}\n";
            $text .= "\xF0\x9F\x91\xA4 {$customer}\n";
            $text .= "\xF0\x9F\x93\xA6 {$product}";
            $text .= $reason_text;
            $text .= "\n\n\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$emp_name}";
            return $text;
        }
    }

    if (!function_exists('telegram_build_event_high_cancellation')) {
        function telegram_build_event_high_cancellation(array $employee, int $cancellations, int $total, float $percentage): string
        {
            $name = htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $text = "\xE2\x9A\xA0\xEF\xB8\x8F \xD9\x85\xD8\xB9\xD8\xAF\xD9\x84 \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD9\x85\xD8\xB1\xD8\xAA\xD9\x81\xD8\xB9\n\n";
            $text .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$name}\n";
            $text .= "\xF0\x9F\x93\x8A \xD8\xA7\xD9\x84\xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1\xD8\xA7\xD8\xAA: {$cancellations}\n";
            $text .= "\xF0\x9F\x93\x88 \xD8\xA7\xD9\x84\xD9\x86\xD8\xB3\xD8\xA8\xD8\xA9: {$percentage}%";
            return $text;
        }
    }

    if (!function_exists('telegram_build_event_failed_telegram')) {
        function telegram_build_event_failed_telegram(array $employee, int $failed_count): string
        {
            $name = htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $text = "\xE2\x9A\xA0\xEF\xB8\x8F \xD9\x81\xD8\xB4\xD9\x84 \xD8\xAA\xD9\x88\xD8\xB5\xD9\x8A\xD9\x84 \xD8\xAA\xD9\x84\xD8\xBA\xD8\xB1\xD8\xA7\xD9\x85\n\n";
            $text .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81: {$name}\n";
            $text .= "\xE2\x9D\x8C \xD8%B9\xD8\xAF\xD8\xAF \xD8\xA7\xD9\x84\xD9\x85\xD8\xAD\xD8\xA7\xD9\x88\xD9\x84\xD8\xA7\xD8\xAA \xD8\xA7\xD9\x84\xD9\x81\xD8\xA7\xD8\xB4\xD9\x84\xD8\xA9: {$failed_count}";
            return $text;
        }
    }
}
