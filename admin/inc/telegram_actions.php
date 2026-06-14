<?php
if (!defined('TELEGRAM_ACTIONS_LOADED')) {
    define('TELEGRAM_ACTIONS_LOADED', true);

    if (!function_exists('telegram_actions_ensure_tables')) {
        function telegram_actions_ensure_tables(PDO $pdo): void
        {
            $lock_file = __DIR__ . '/../cache/telegram_actions_tables.lock';
            if (file_exists($lock_file)) {
                return;
            }

            if (function_exists('telegram_ensure_tables')) {
                telegram_ensure_tables($pdo);
            }

            @file_put_contents($lock_file, '1');
        }
    }

    if (!function_exists('telegram_log_action')) {
        function telegram_log_action(PDO $pdo, int $employee_id, int $order_id, string $action_type, $telegram_user_id = null, $payload = null): int
        {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_telegram_action_log (employee_id, order_id, action_type, telegram_user_id, payload)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $employee_id,
                $order_id,
                $action_type,
                $telegram_user_id !== null ? (int) $telegram_user_id : null,
                $payload !== null ? (is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE)) : null,
            ]);
            return (int) $pdo->lastInsertId();
        }
    }

    if (!function_exists('telegram_find_employee_by_chat_id')) {
        function telegram_find_employee_by_chat_id(PDO $pdo, string $chat_id): ?array
        {
            $stmt = $pdo->prepare("SELECT * FROM tbl_employee WHERE telegram_chat_id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$chat_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('telegram_verify_order_access')) {
        function telegram_verify_order_access(PDO $pdo, int $order_id, int $employee_id): ?array
        {
            $stmt = $pdo->prepare("
                SELECT oa.*, o.order_status, o.id AS oid
                FROM tbl_order_assignment oa
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE oa.order_id = ? AND oa.employee_id = ? AND oa.status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$order_id, $employee_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('telegram_can_modify_status')) {
        function telegram_can_modify_status(string $status): bool
        {
            return in_array($status, ['Pending'], true);
        }
    }

    if (!function_exists('telegram_already_processed')) {
        function telegram_already_processed(PDO $pdo, int $order_id, string $action_type, int $employee_id): bool
        {
            if ($action_type === 'confirm') {
                $stmt = $pdo->prepare("SELECT id FROM tbl_order WHERE id = ? AND order_status = 'Confirmed' LIMIT 1");
                $stmt->execute([$order_id]);
                return (bool) $stmt->fetch();
            }
            if ($action_type === 'cancel') {
                $stmt = $pdo->prepare("SELECT id FROM tbl_order WHERE id = ? AND order_status = 'Cancelled' LIMIT 1");
                $stmt->execute([$order_id]);
                return (bool) $stmt->fetch();
            }
            return false;
        }
    }

    if (!function_exists('telegram_handle_confirm')) {
        function telegram_handle_confirm(PDO $pdo, array $callback_query, int $order_id, int $employee_id): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');
            $telegram_user_id = (int) ($callback_query['from']['id'] ?? 0);

            $access = telegram_verify_order_access($pdo, $order_id, $employee_id);
            if (!$access) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xA7\xD9\x84\xD9\x88\xD8\xB5\xD9\x88\xD9\x84 \xD9\x84\xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8");
                return;
            }

            if (!telegram_can_modify_status($access['order_status'])) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xAA\xD9\x85 \xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\xD8\xA9 \xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD8\xB3\xD8\xA8\xD9\x82\xD9\x8B\xD8\xA7");
                return;
            }

            if (telegram_already_processed($pdo, $order_id, 'confirm', $employee_id)) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xAA\xD9\x85 \xD8\xAA\xD8\xA3\xD9\x83\xD9\x8A\xD8\xAF \xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD9\x86 \xD9\x82\xD8\xA8\xD9\x84");
                return;
            }

            $stmt = $pdo->prepare("UPDATE tbl_order SET order_status = 'Confirmed' WHERE id = ?");
            $stmt->execute([$order_id]);

            if (function_exists('admin_log_order_status_change')) {
                $employee = employee_get_by_id($pdo, $employee_id);
                $changed_by = $employee ? 'Telegram: ' . $employee['full_name'] : 'Telegram';
                admin_log_order_status_change($pdo, $order_id, 'Pending', 'Confirmed', "\xD8\xAA\xD8\xA3\xD9\x83\xD9\x8A\xD8\xAF \xD8\xB9\xD8\xA8\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x84\xD9\x8A\xD8\xAC\xD8\xB1\xD8\xA7\xD9\x85", $changed_by);
            }

            telegram_log_action($pdo, $employee_id, $order_id, 'confirm', $telegram_user_id, ['status' => 'Confirmed']);

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee = employee_get_by_id($pdo, $employee_id);

            $extra = "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xAA\xD8\xA3\xD9\x83\xD9\x8A\xD8\xAF \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD8\xA8\xD9\x88\xD8\xA7\xD8\xB3\xD8\xB7\xD8\xA9 " . htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
            $text = telegram_build_order_notification($order, $employee, $extra);
            $reply_markup = telegram_build_action_buttons($order_id, 'Confirmed');

            telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup);
            telegram_answer_callback_query($callback_id, "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xAA\xD8\xA3\xD9\x83\xD9\x8A\xD8\xAF \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD8\xA8\xD9\x86\xD8\xAC\xD8\xA7\xD8\xAD");
        }
    }

    if (!function_exists('telegram_handle_cancel_show_reasons')) {
        function telegram_handle_cancel_show_reasons(PDO $pdo, array $callback_query, int $order_id, int $employee_id): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');

            $access = telegram_verify_order_access($pdo, $order_id, $employee_id);
            if (!$access) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xA7\xD9\x84\xD9\x88\xD8\xB5\xD9\x88\xD9\x84 \xD9\x84\xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8");
                return;
            }
            if (!telegram_can_modify_status($access['order_status'])) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xAA\xD9\x85 \xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\xD8\xA9 \xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD8\xB3\xD8\xA8\xD9\x82\xD9\x8B\xD8\xA7");
                return;
            }

            telegram_answer_callback_query($callback_id);

            $text = "\xE2\x9D\x8C \xD8\xAA\xD8\xA3\xD9\x83\xD9\x8A\xD8\xAF \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 #{$order_id}\n\n";
            $text .= "\xD8\xA7\xD8\xAE\xD8\xAA\xD8\xB1 \xD8\xB3\xD8\xA8\xD8\xA8 \xD8\xA7\xD9\x84\xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1:";

            $reasons = [
                'no_answer' => "\xF0\x9F\x94\x99 \xD9\x84\xD8\xA7 \xD9\x8A\xD8\xB1\xD8\xAF",
                'wrong_number' => "\xF0\x9F\x94\x99 \xD8\xB1\xD9\x82\xD9\x85 \xD8\xAE\xD8\xA7\xD8\xB7\xD8\xA6",
                'rejected' => "\xF0\x9F\x94\x99 \xD8\xB1\xD9\x81\xD8\xB6 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8",
                'duplicate' => "\xF0\x9F\x94\x99 \xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD9\x83\xD8\xB1\xD8\xB1",
                'wrong_address' => "\xF0\x9F\x94\x99 \xD8\xB9\xD9\x86\xD9\x88\xD8\xA7\xD9\x86 \xD8\xBA\xD9\x8A\xD8\xB1 \xD8\xB5\xD8\xAD\xD9\x8A\xD8\xAD",
                'other' => "\xF0\x9F\x94\x99 \xD8\xA3\xD8\xAE\xD8\xB1\xD9\x89",
            ];

            $keyboard = [];
            $row = [];
            $count = 0;
            foreach ($reasons as $code => $label) {
                $row[] = ['text' => $label, 'callback_data' => "cancel_do:{$order_id}:{$code}"];
                $count++;
                if ($count % 2 === 0) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
            if (!empty($row)) {
                $keyboard[] = $row;
            }

            $reply_markup = ['inline_keyboard' => $keyboard];
            telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup);
        }
    }

    if (!function_exists('telegram_handle_cancel_do')) {
        function telegram_handle_cancel_do(PDO $pdo, array $callback_query, int $order_id, int $employee_id, string $reason_code): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');
            $telegram_user_id = (int) ($callback_query['from']['id'] ?? 0);

            $access = telegram_verify_order_access($pdo, $order_id, $employee_id);
            if (!$access) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xA7\xD9\x84\xD9\x88\xD8\xB5\xD9\x88\xD9\x84 \xD9\x84\xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8");
                return;
            }
            if (!telegram_can_modify_status($access['order_status'])) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xAA\xD9\x85 \xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\xD8\xA9 \xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD8\xB3\xD8\xA8\xD9\x82\xD9\x8B\xD8\xA7");
                return;
            }
            if (telegram_already_processed($pdo, $order_id, 'cancel', $employee_id)) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xAA\xD9\x85 \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD9\x86 \xD9\x82\xD8\xA8\xD9\x84");
                return;
            }

            $reason_map = [
                'no_answer' => "\xD9\x84\xD8\xA7 \xD9\x8A\xD8\xB1\xD8\xAF",
                'wrong_number' => "\xD8\xB1\xD9\x82\xD9\x85 \xD8\xAE\xD8\xA7\xD8\xB7\xD8\xA6",
                'rejected' => "\xD8\xB1\xD9\x81\xD8\xB6 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8",
                'duplicate' => "\xD8\xB7\xD9\x84\xD8\xA8 \xD9\x85\xD9\x83\xD8\xB1\xD8\xB1",
                'wrong_address' => "\xD8\xB9\xD9\x86\xD9\x88\xD8\xA7\xD9\x86 \xD8\xBA\xD9\x8A\xD8\xB1 \xD8\xB5\xD8\xAD\xD9\x8A\xD8\xAD",
                'other' => "\xD8\xA3\xD8\xAE\xD8\xB1\xD9\x89",
            ];
            $reason_text = $reason_map[$reason_code] ?? $reason_code;

            $stmt = $pdo->prepare("UPDATE tbl_order SET order_status = 'Cancelled' WHERE id = ?");
            $stmt->execute([$order_id]);

            $ins = $pdo->prepare("INSERT INTO tbl_order_cancellation_reason (order_id, employee_id, reason) VALUES (?, ?, ?)");
            $ins->execute([$order_id, $employee_id, $reason_text]);

            if (function_exists('admin_log_order_status_change')) {
                $employee = employee_get_by_id($pdo, $employee_id);
                $changed_by = $employee ? 'Telegram: ' . $employee['full_name'] : 'Telegram';
                admin_log_order_status_change($pdo, $order_id, 'Pending', 'Cancelled', "\xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD8\xB9\xD8\xA8\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x84\xD9\x8A\xD8\xAC\xD8\xB1\xD8\xA7\xD9\x85: {$reason_text}", $changed_by);
            }

            telegram_log_action($pdo, $employee_id, $order_id, 'cancel', $telegram_user_id, ['reason_code' => $reason_code, 'reason' => $reason_text]);

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee = employee_get_by_id($pdo, $employee_id);

            $extra = "\xE2\x9D\x8C \xD8\xAA\xD9\x85 \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8\n";
            $extra .= "\xD8\xA7\xD9\x84\xD8\xB3\xD8\xA8\xD8\xA8: {$reason_text}\n";
            $extra .= "\xD8\xA8\xD9\x88\xD8\xA7\xD8\xB3\xD8\xB7\xD8\xA9: " . htmlspecialchars($employee['full_name'] ?? '', ENT_QUOTES, 'UTF-8');

            $text = telegram_build_order_notification($order, $employee, $extra);
            $reply_markup = telegram_build_action_buttons($order_id, 'Cancelled');

            telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup);
            telegram_answer_callback_query($callback_id, "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD8\xA8\xD9\x86\xD8\xAC\xD8\xA7\xD8\xAD");
        }
    }

    if (!function_exists('telegram_get_editable_fields')) {
        function telegram_get_editable_fields(int $order_id): array
        {
            return [
                'customer_name' => "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD8\xA7\xD8\xB3\xD9\x85",
                'customer_phone' => "\xF0\x9F\x93\x9E \xD8\xA7\xD9\x84\xD9\x87\xD8\xA7\xD8\xAA\xD9\x81",
                'wilaya' => "\xF0\x9F\x93\x8D \xD8\xA7\xD9\x84\xD9\x88\xD9\x84\xD8\xA7\xD9\x8A\xD8\xA9",
                'commune' => "\xF0\x9F\x93\x8D \xD8\xA7\xD9\x84\xD8\xA8\xD9\x84\xD8\xAF\xD9\x8A\xD8\xA9",
                'address' => "\xF0\x9F\x8F\xA0 \xD8\xA7\xD9\x84\xD8\xB9\xD9\x86\xD9\x88\xD8\xA7\xD9\x86",
                'order_note' => "\xF0\x9F\x93\x9D \xD9\x85\xD9\x84\xD8\xA7\xD8\xAD\xD8\xB8\xD8\xA9",
                'quantity' => "\xF0\x9F\x94\xA2 \xD8\xA7\xD9\x84\xD9\x83\xD9\x85\xD9\x8A\xD8\xA9",
                'unit_price' => "\xF0\x9F\x92\xB0 \xD8\xA7\xD9\x84\xD8\xB9\xD8\xB1\xD8\xB6",
            ];
        }
    }

    if (!function_exists('telegram_get_field_label')) {
        function telegram_get_field_label(string $field): string
        {
            $labels = [
                'customer_name' => "\xD8\xA7\xD9\x84\xD8\xA7\xD8\xB3\xD9\x85",
                'customer_phone' => "\xD8\xA7\xD9\x84\xD9\x87\xD8\xA7\xD8\xAA\xD9\x81",
                'wilaya' => "\xD8\xA7\xD9\x84\xD9\x88\xD9\x84\xD8\xA7\xD9\x8A\xD8\xA9",
                'commune' => "\xD8\xA7\xD9\x84\xD8\xA8\xD9\x84\xD8\xAF\xD9\x8A\xD8\xA9",
                'address' => "\xD8\xA7\xD9\x84\xD8\xB9\xD9\x86\xD9\x88\xD8\xA7\xD9\x86",
                'order_note' => "\xD8\xA7\xD9\x84\xD9\x85\xD9\x84\xD8\xA7\xD8\xAD\xD8\xB8\xD8\xA9",
                'quantity' => "\xD8\xA7\xD9\x84\xD9\x83\xD9\x85\xD9\x8A\xD8\xA9",
                'unit_price' => "\xD8\xA7\xD9\x84\xD8\xB9\xD8\xB1\xD8\xB6",
            ];
            return $labels[$field] ?? $field;
        }
    }

    if (!function_exists('telegram_handle_edit_choose')) {
        function telegram_handle_edit_choose(PDO $pdo, array $callback_query, int $order_id, int $employee_id): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');

            $access = telegram_verify_order_access($pdo, $order_id, $employee_id);
            if (!$access) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xA7\xD9\x84\xD9\x88\xD8\xB5\xD9\x88\xD9\x84 \xD9\x84\xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8");
                return;
            }
            if (!telegram_can_modify_status($access['order_status'])) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86 \xD8\xAA\xD8\xB9\xD8\xAF\xD9\x8A\xD9\x84 \xD8\xB7\xD9\x84\xD8\xA8 \xD8\xAA\xD9\x85 \xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\xD8\xAA\xD9\x87 \xD9\x85\xD8\xB3\xD8\xA8\xD9\x82\xD9\x8B\xD8\xA7");
                return;
            }

            telegram_answer_callback_query($callback_id);

            $text = "\xE2\x9C\x8F\xEF\xB8\x8F \xD8\xAA\xD8\xB9\xD8\xAF\xD9\x8A\xD9\x84 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 #{$order_id}\n\n";
            $text .= "\xD9\x85\xD8\xA7 \xD8\xB0\xD8\xA7 \xD8\xAA\xD8\xB1\xD9\x8A\xD8\xAF \xD8\xA3\xD9\x86 \xD8\xAA\xD8\xB9\xD8\xAF\xD9\x84\xD8\x9F";

            $fields = telegram_get_editable_fields($order_id);
            $keyboard = [];
            $row = [];
            $count = 0;
            foreach ($fields as $field_key => $field_label) {
                $row[] = ['text' => $field_label, 'callback_data' => "edit_field:{$order_id}:{$field_key}"];
                $count++;
                if ($count % 2 === 0) {
                    $keyboard[] = $row;
                    $row = [];
                }
            }
            if (!empty($row)) {
                $keyboard[] = $row;
            }
            $keyboard[] = [
                ['text' => "\xE2\x9E\xA1 \xD8\xB9\xD9\x88\xD8\xAF\xD8\xA9", 'callback_data' => "back:{$order_id}"]
            ];

            $reply_markup = ['inline_keyboard' => $keyboard];
            telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup);
        }
    }

    if (!function_exists('telegram_handle_edit_field')) {
        function telegram_handle_edit_field(PDO $pdo, array $callback_query, int $order_id, int $employee_id, string $field_name): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');

            $valid_fields = telegram_get_editable_fields($order_id);
            if (!isset($valid_fields[$field_name])) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xAD\xD9\x82\xD9\x84 \xD8\xBA\xD9\x8A\xD8\xB1 \xD8\xB5\xD8\xA7\xD9\x84\xD8\xAD");
                return;
            }

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD9\x88\xD8\xAC\xD9\x88\xD8\xAF");
                return;
            }

            $current_value = $order[$field_name] ?? '';
            $field_label = telegram_get_field_label($field_name);

            $pdo->prepare("UPDATE tbl_telegram_edit_session SET status = 'cancelled' WHERE employee_id = ? AND status = 'pending'")->execute([$employee_id]);

            $ins = $pdo->prepare("
                INSERT INTO tbl_telegram_edit_session (employee_id, order_id, field_name, old_value, chat_id, message_id, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $ins->execute([$employee_id, $order_id, $field_name, (string) $current_value, $chat_id, $message_id]);

            telegram_log_action($pdo, $employee_id, $order_id, 'edit_pending', (int) ($callback_query['from']['id'] ?? 0), ['field' => $field_name, 'current_value' => $current_value]);

            $text = "\xE2\x9C\x8F\xEF\xB8\x8F \xD8\xAA\xD8\xB9\xD8\xAF\xD9\x8A\xD9\x84: {$field_label}\n\n";
            $text .= "\xD8\xA7\xD9\x84\xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAD\xD8\xA7\xD9\x84\xD9\x8A\xD8\xA9: " . htmlspecialchars((string) $current_value, ENT_QUOTES, 'UTF-8') . "\n\n";
            $text .= "\xD8\xA3\xD8\xB1\xD8\xB3\xD9\x84 \xD8\xA7\xD9\x84\xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAC\xD8\xAF\xD9\x8A\xD8\xAF\xD8\xA9:";

            $reply_markup = ['inline_keyboard' => [
                [['text' => "\xE2\x9E\xA1 \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD8\xAA\xD8\xB9\xD8\xAF\xD9\x8A\xD9\x84", 'callback_data' => "back:{$order_id}"]]
            ]];

            telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup);
            telegram_answer_callback_query($callback_id, "\xD8\xA3\xD8\xB1\xD8\xB3\xD9\x84 \xD8\xA7\xD9\x84\xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAC\xD8\xAF\xD9\x8A\xD8\xAF\xD8\xA9");
        }
    }

    if (!function_exists('telegram_validate_field_value')) {
        function telegram_validate_field_value(string $field_name, string $value, PDO $pdo = null): ?string
        {
            $value = trim($value);
            if ($value === '') {
                return "\xD8\xA7\xD9\x84\xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86 \xD8\xA3\xD9\x86 \xD8\xAA\xD9\x83\xD9\x88\xD9\x86 \xD9\x81\xD8\xA7\xD8\xB1\xD8\xBA\xD8\xA9";
            }

            switch ($field_name) {
                case 'customer_phone':
                    $cleaned = preg_replace('/[^0-9+]/', '', $value);
                    if (preg_match('/^(05|06|07|09)\d{8}$/', $cleaned)) {
                        return null;
                    }
                    if (preg_match('/^\+213(5|6|7|9)\d{8}$/', $cleaned)) {
                        return null;
                    }
                    if (preg_match('/^(213)(5|6|7|9)\d{8}$/', $cleaned)) {
                        return null;
                    }
                    return "\xD8\xB1\xD9\x82\xD9\x85 \xD8\xA7\xD9\x84\xD9\x87\xD8\xA7\xD8\xAA\xD9\x81 \xD8\xBA\xD9\x8A\xD8\xB1 \xD8\xB5\xD8\xAD\xD9\x8A\xD8\xAD. \xD9\x8A\xD8\xAC\xD8\xA8 \xD8\xA3\xD9\x86 \xD9\x8A\xD8\xA8\xD8\xAF\xD8\xA3 \xD8\xA8\xD9\x80 05 \xD8\xA3\xD9\x88 06 \xD8\xA3\xD9\x88 07 \xD8\xA3\xD9\x88 09";

                case 'quantity':
                    if (!ctype_digit($value) || (int) $value <= 0) {
                        return "\xD8\xA7\xD9\x84\xD9\x83\xD9\x85\xD9\x8A\xD8\xA9 \xD9\x8A\xD8\xAC\xD8\xA8 \xD8\xA3\xD9\x86 \xD8\xAA\xD9\x83\xD9\x88\xD9\x86 \xD8\xB1\xD9\x82\xD9\x85\xD9\x8B\xD8\xA7 \xD8\xB5\xD8\xAD\xD9\x8A\xD8\xAD\xD9\x8B\xD8\xA7 \xD8\xA3\xD9\x83\xD8\xAB\xD8\xB1 \xD9\x85\xD9\x86 0";
                    }
                    return null;

                case 'unit_price':
                    if (!is_numeric($value) || (float) $value < 0) {
                        return "\xD8\xA7\xD9\x84\xD8\xB3\xD8\xB9\xD8\xB1 \xD9\x8A\xD8\xAC\xD8\xA8 \xD8\xA3\xD9\x86 \xD9\x8A\xD9\x83\xD9\x88\xD9\x86 \xD8\xB1\xD9\x82\xD9\x85\xD9\x8B\xD8\xA7 \xD8\xB5\xD8\xAD\xD9\x8A\xD8\xAD\xD9\x8B\xD8\xA7";
                    }
                    return null;

                case 'wilaya':
                    if ($pdo !== null) {
                        $check = $pdo->prepare("SELECT COUNT(*) FROM tbl_wilaya WHERE name = ? OR id = ?");
                        $check->execute([$value, $value]);
                        if ((int) $check->fetchColumn() === 0) {
                            return "\xD8\xA7\xD9\x84\xD9\x88\xD9\x84\xD8\xA7\xD9\x8A\xD8\xA9 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD9\x88\xD8\xAC\xD9\x88\xD8\xAF\xD8\xA9 \xD9\x81\xD9\x8A \xD8\xA7\xD9\x84\xD9\x86\xD8\xB8\xD8\xA7\xD9\x85";
                        }
                    }
                    return null;

                default:
                    return null;
            }
        }
    }

    if (!function_exists('telegram_handle_edit_value')) {
        function telegram_handle_edit_value(PDO $pdo, array $message, int $employee_id): void
        {
            $chat_id = (string) ($message['chat']['id'] ?? '');
            $text = trim((string) ($message['text'] ?? ''));

            if ($text === '') {
                return;
            }

            $stmt = $pdo->prepare("
                SELECT * FROM tbl_telegram_edit_session
                WHERE employee_id = ? AND status = 'pending'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$employee_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return;
            }

            $order_id = (int) $session['order_id'];
            $field_name = $session['field_name'];
            $old_value = $session['old_value'];
            $original_message_id = (int) $session['message_id'];
            $field_label = telegram_get_field_label($field_name);

            $access = telegram_verify_order_access($pdo, $order_id, $employee_id);
            if (!$access) {
                $pdo->prepare("UPDATE tbl_telegram_edit_session SET status = 'cancelled' WHERE id = ?")->execute([$session['id']]);
                telegram_send_message($chat_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xA7\xD9\x84\xD9\x88\xD8\xB5\xD9\x88\xD9\x84 \xD9\x84\xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8");
                return;
            }
            if (!telegram_can_modify_status($access['order_status'])) {
                $pdo->prepare("UPDATE tbl_telegram_edit_session SET status = 'cancelled' WHERE id = ?")->execute([$session['id']]);
                telegram_send_message($chat_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86 \xD8\xAA\xD8\xB9\xD8\xAF\xD9\x8A\xD9\x84 \xD8\xB7\xD9\x84\xD8\xA8 \xD8\xAA\xD9\x85 \xD9\x85\xD8\xB9\xD8\xA7\xD9\x84\xD8\xAC\xD8\xAA\xD9\x87");
                return;
            }

            $validation_error = telegram_validate_field_value($field_name, $text, $pdo);
            if ($validation_error !== null) {
                $pdo->prepare("UPDATE tbl_telegram_edit_session SET updated_at = NOW() WHERE id = ?")->execute([$session['id']]);
                telegram_send_message($chat_id, "\xE2\x9D\x8C {$validation_error}\n\n\xD8\xA7\xD9\x84\xD8\xB1\xD8\xAC\xD8\xA7\xD8\xA1 \xD8\xA5\xD8\xB1\xD8\xB3\xD8\xA7\xD9\x84 \xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD8\xB5\xD8\xAD\xD9\x8A\xD8\xAD\xD8\xA9:");
                return;
            }

            $update_sql = "UPDATE tbl_order SET `{$field_name}` = ? WHERE id = ?";
            $stmt = $pdo->prepare($update_sql);
            $stmt->execute([$text, $order_id]);

            $log_stmt = $pdo->prepare("
                INSERT INTO tbl_order_edit_log (order_id, employee_id, field_name, old_value, new_value)
                VALUES (?, ?, ?, ?, ?)
            ");
            $log_stmt->execute([$order_id, $employee_id, $field_name, $old_value, $text]);

            $pdo->prepare("UPDATE tbl_telegram_edit_session SET status = 'completed' WHERE id = ?")->execute([$session['id']]);

            telegram_log_action($pdo, $employee_id, $order_id, 'edit_completed', null, [
                'field' => $field_name,
                'old_value' => $old_value,
                'new_value' => $text,
            ]);

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee = employee_get_by_id($pdo, $employee_id);

            $summary = "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xAA\xD8\xAD\xD8\xAF\xD9\x8A\xD8\xAB \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8\n\n";
            $summary .= "\xD8\xA7\xD9\x84\xD8\xAD\xD9\x82\xD9\x84: {$field_label}\n\n";
            $summary .= "\xD8\xA7\xD9\x84\xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD8\xA7\xD9\x84\xD9\x82\xD8\xAF\xD9\x8A\xD9\x85\xD8\xA9:\n" . htmlspecialchars((string) $old_value, ENT_QUOTES, 'UTF-8') . "\n\n";
            $summary .= "\xD8\xA7\xD9\x84\xD9\x82\xD9\x8A\xD9\x85\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAC\xD8\xAF\xD9\x8A\xD8\xAF\xD8\xA9:\n" . htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            telegram_send_message($chat_id, $summary);

            if ($original_message_id > 0) {
                $text = telegram_build_order_notification($order, $employee);
                $reply_markup = telegram_build_action_buttons($order_id, $order['order_status'] ?? 'Pending');
                telegram_edit_message_text($chat_id, $original_message_id, $text, $reply_markup);
            }
        }
    }

    if (!function_exists('telegram_handle_reassign')) {
        function telegram_handle_reassign(PDO $pdo, array $callback_query, int $order_id, int $employee_id_from): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD9\x88\xD8\xAC\xD9\x88\xD8\xAF");
                return;
            }

            if (!function_exists('employee_get_next_for_assignment')) {
                require_once __DIR__ . '/employee_functions.php';
            }
            $next = employee_get_next_for_assignment($pdo);
            if ($next === null) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x88\xD8\xAC\xD8\xAF \xD9\x85\xD9\x88\xD8\xB8\xD9\x81\xD9\x88\xD9\x86 \xD9\x86\xD8\xB4\xD8\xB7\xD9\x88\xD9\x86");
                return;
            }

            $new_employee_id = (int) $next['id'];
            $success = employee_reassign_order($pdo, $order_id, $new_employee_id, 'telegram_reassign');

            if (!$success) {
                telegram_answer_callback_query($callback_id, "\xE2\x9D\x8C \xD9\x81\xD8\xB4\xD9\x84 \xD8\xA5\xD8%B9\xD8\xA7\xD8\xAF\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x88\xD8\xB2\xD9\x8A\xD8\xB9");
                return;
            }

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_employee = employee_get_by_id($pdo, $new_employee_id);

            if ($new_employee) {
                $new_chat_id = trim((string) ($new_employee['telegram_chat_id'] ?? ''));
                if ($new_chat_id !== '') {
                    $new_text = telegram_build_order_notification($order, $new_employee);
                    $new_buttons = telegram_build_action_buttons($order_id, $order['order_status'] ?? 'Pending');
                    telegram_send_message($new_chat_id, $new_text, $new_buttons);
                }
            }

            telegram_log_action($pdo, $employee_id_from, $order_id, 'reassign', null, [
                'from_employee' => $employee_id_from,
                'to_employee' => $new_employee_id,
                'source' => 'event_monitor'
            ]);

            $text_old = "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xA5\xD8%B9\xD8\xA7\xD8\xAF\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x88\xD8\xB2\xD9\x8A\xD8\xB9\n\n";
            $text_old .= "\xF0\x9F\x94\xA2 \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8 #{$order_id}\n";
            $text_old .= "\xF0\x9F\x91\xA4 \xD8\xA7\xD9\x84\xD9\x85\xD9\x88\xD8\xB8\xD9\x81 \xD8\xA7\xD9\x84\xD8\xAC\xD8\xAF\xD9\x8A\xD8\xAF: " . htmlspecialchars($next['full_name'] ?? '', ENT_QUOTES, 'UTF-8');

            telegram_answer_callback_query($callback_id, "\xE2\x9C\x85 \xD8\xAA\xD9\x85 \xD8\xA5\xD8%B9\xD8\xA7\xD8\xAF\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x88\xD8\xB2\xD9\x8A\xD8\xB9 \xD9\x84ِ" . htmlspecialchars($next['full_name'] ?? '', ENT_QUOTES, 'UTF-8'));

            if ($message_id > 0) {
                $site_url = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
                $order_url = $site_url . '/admin/order-details.php?id=' . $order_id;
                $reply_markup = ['inline_keyboard' => [[['text' => "\xF0\x9F\x93\x84 \xD9\x81\xD8\xAA\xD8\xAD \xD8\xA7\xD9\x84\xD8\xB7\xD9\x84\xD8\xA8", 'url' => $order_url]]]];
                telegram_edit_message_text($chat_id, $message_id, $text_old, $reply_markup);
            }
        }
    }

    if (!function_exists('telegram_handle_back')) {
        function telegram_handle_back(PDO $pdo, array $callback_query, int $order_id, int $employee_id): void
        {
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $message_id = (int) ($callback_query['message']['message_id'] ?? 0);
            $callback_id = (string) ($callback_query['id'] ?? '');

            $pdo->prepare("UPDATE tbl_telegram_edit_session SET status = 'cancelled' WHERE employee_id = ? AND status = 'pending'")->execute([$employee_id]);

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $employee = employee_get_by_id($pdo, $employee_id);

            $extra = null;
            if ($order) {
                $text = telegram_build_order_notification($order, $employee, $extra);
                $reply_markup = telegram_build_action_buttons($order_id, $order['order_status'] ?? 'Pending');
                telegram_edit_message_text($chat_id, $message_id, $text, $reply_markup);
            }

            telegram_answer_callback_query($callback_id, "\xE2\x9E\xA1 \xD8\xA7\xD9\x84\xD8\xB1\xD8\xAC\xD9\x88\xD8\xB9");
        }
    }

    if (!function_exists('telegram_handle_callback_query')) {
        function telegram_handle_callback_query(PDO $pdo, array $callback_query): void
        {
            $data = (string) ($callback_query['data'] ?? '');
            $chat_id = (string) ($callback_query['message']['chat']['id'] ?? '');
            $from_id = (int) ($callback_query['from']['id'] ?? 0);

            $parts = explode(':', $data);
            $action = $parts[0] ?? '';
            $order_id = (int) ($parts[1] ?? 0);
            $extra = $parts[2] ?? '';

            if ($order_id <= 0) {
                telegram_answer_callback_query((string) ($callback_query['id'] ?? ''), "\xE2\x9D\x8C \xD8\xA8\xD9\x8A\xD8\xA7\xD9\x86\xD8\xA7\xD8\xAA \xD8\xBA\xD9\x8A\xD8\xB1 \xD8\xB5\xD8\xA7\xD9\x84\xD8\xAD\xD8\xA9");
                return;
            }

            $employee = telegram_find_employee_by_chat_id($pdo, $chat_id);
            if (!$employee) {
                telegram_answer_callback_query((string) ($callback_query['id'] ?? ''), "\xE2\x9D\x8C \xD9\x84\xD9\x85 \xD9\x8A\xD8\xAA\xD9\x85 \xD8\xA7\xD9\x84\xD8\xAA\xD8\xB9\xD8\xB1\xD9\x81 \xD8\xB9\xD9\x84\xD9\x8A\xD9\x83. \xD8\xAA\xD8\xAD\xD8\xAA\xD8\xA7\xD8\xAC \xD8\xA5\xD9\x84\xD9\x89 \xD8\xAD\xD8\xB3\xD8\xA7\xD8\xA8 \xD9\x85\xD9\x88\xD8\xB8\xD9\x81");
                return;
            }
            $employee_id = (int) $employee['id'];

            switch ($action) {
                case 'confirm':
                    telegram_handle_confirm($pdo, $callback_query, $order_id, $employee_id);
                    break;
                case 'cancel':
                    telegram_handle_cancel_show_reasons($pdo, $callback_query, $order_id, $employee_id);
                    break;
                case 'cancel_do':
                    telegram_handle_cancel_do($pdo, $callback_query, $order_id, $employee_id, $extra);
                    break;
                case 'edit':
                    telegram_handle_edit_choose($pdo, $callback_query, $order_id, $employee_id);
                    break;
                case 'edit_field':
                    telegram_handle_edit_field($pdo, $callback_query, $order_id, $employee_id, $extra);
                    break;
                case 'reassign':
                    telegram_handle_reassign($pdo, $callback_query, $order_id, $employee_id);
                    break;
                case 'back':
                    telegram_handle_back($pdo, $callback_query, $order_id, $employee_id);
                    break;
                default:
                    telegram_answer_callback_query((string) ($callback_query['id'] ?? ''), "\xE2\x9D\x8C \xD8\xA5\xD8\xAC\xD8\xB1\xD8\xA7\xD8\xA1 \xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xB9\xD8\xB1\xD9\x88\xD9\x81");
                    break;
            }
        }
    }

    if (!function_exists('telegram_handle_message')) {
        function telegram_handle_message(PDO $pdo, array $message): void
        {
            $chat_id = (string) ($message['chat']['id'] ?? '');
            $text = trim((string) ($message['text'] ?? ''));

            if ($text === '' || strpos($text, '/') === 0) {
                return;
            }

            $employee = telegram_find_employee_by_chat_id($pdo, $chat_id);
            if (!$employee) {
                return;
            }
            $employee_id = (int) $employee['id'];

            $stmt = $pdo->prepare("
                SELECT id FROM tbl_telegram_edit_session
                WHERE employee_id = ? AND status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$employee_id]);
            if (!$stmt->fetch()) {
                return;
            }

            telegram_handle_edit_value($pdo, $message, $employee_id);
        }
    }

    if (!function_exists('telegram_process_update')) {
        function telegram_process_update(PDO $pdo, array $update): void
        {
            if (isset($update['callback_query'])) {
                telegram_handle_callback_query($pdo, $update['callback_query']);
            } elseif (isset($update['message']) && isset($update['message']['text'])) {
                telegram_handle_message($pdo, $update['message']);
            }
        }
    }

    if (!function_exists('telegram_get_edits_for_order')) {
        function telegram_get_edits_for_order(PDO $pdo, int $order_id): array
        {
            $stmt = $pdo->prepare("
                SELECT e.*, emp.full_name AS employee_name
                FROM tbl_order_edit_log e
                LEFT JOIN tbl_employee emp ON emp.id = e.employee_id
                WHERE e.order_id = ?
                ORDER BY e.edited_at DESC
            ");
            $stmt->execute([$order_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('telegram_get_cancellation_for_order')) {
        function telegram_get_cancellation_for_order(PDO $pdo, int $order_id): ?array
        {
            $stmt = $pdo->prepare("
                SELECT c.*, emp.full_name AS employee_name
                FROM tbl_order_cancellation_reason c
                LEFT JOIN tbl_employee emp ON emp.id = c.employee_id
                WHERE c.order_id = ?
                ORDER BY c.id DESC LIMIT 1
            ");
            $stmt->execute([$order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('telegram_get_order_telegram_actions')) {
        function telegram_get_order_telegram_actions(PDO $pdo, int $order_id): array
        {
            $stmt = $pdo->prepare("
                SELECT a.*, emp.full_name AS employee_name
                FROM tbl_telegram_action_log a
                LEFT JOIN tbl_employee emp ON emp.id = a.employee_id
                WHERE a.order_id = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$order_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('telegram_get_employee_telegram_actions')) {
        function telegram_get_employee_telegram_actions(PDO $pdo, int $employee_id, int $limit = 20): array
        {
            $stmt = $pdo->prepare("
                SELECT a.*, o.id AS order_id_ref
                FROM tbl_telegram_action_log a
                LEFT JOIN tbl_order o ON o.id = a.order_id
                WHERE a.employee_id = ?
                ORDER BY a.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$employee_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
