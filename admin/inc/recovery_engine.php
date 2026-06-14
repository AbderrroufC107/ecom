<?php
/**
 * Delivery Recovery Engine
 *
 * Sub-statuses from Ecotrack delivery attempts:
 *   no_answer          - Client did not answer
 *   busy               - Phone was busy
 *   unreachable        - Phone switched off / out of service
 *   postponed_by_client - Client asked to postpone
 *   wrong_location     - Address is incorrect
 *   refused_by_customer - Client refused the package
 *
 * Recovery actions per sub-status are defined in recovery_engine_sub_status_action().
 */

if (!function_exists('recovery_engine_ensure_tables')) {
    function recovery_engine_ensure_tables(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;

        $lock_file = __DIR__ . '/../cache/recovery_engine_tables.lock';
        if (file_exists($lock_file)) {
            $done = true;
            return;
        }

        if (!function_exists('audit_ensure_tables')) {
            require_once __DIR__ . '/audit.php';
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_order_contact_attempt (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                tracking_number VARCHAR(120) NOT NULL,
                status VARCHAR(120) NOT NULL DEFAULT '',
                sub_status VARCHAR(60) NOT NULL DEFAULT '',
                comment TEXT NULL,
                attempt_number INT NOT NULL DEFAULT 0,
                employee_id INT NOT NULL DEFAULT 0,
                attempt_date DATETIME NULL,
                raw_payload LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_coa_order (order_id),
                KEY idx_coa_tracking (tracking_number),
                KEY idx_coa_sub_status (sub_status),
                KEY idx_coa_date (attempt_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_recovery_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                tracking_number VARCHAR(120) NOT NULL DEFAULT '',
                order_contact_attempt_id INT NOT NULL DEFAULT 0,
                task_type VARCHAR(60) NOT NULL DEFAULT 'retry_call',
                sub_status VARCHAR(60) NOT NULL DEFAULT '',
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                assigned_to INT NOT NULL DEFAULT 0,
                notes TEXT NULL,
                scheduled_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rt_order (order_id),
                KEY idx_rt_status (status),
                KEY idx_rt_type (task_type),
                KEY idx_rt_assigned (assigned_to),
                KEY idx_rt_scheduled (scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_recovery_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                tracking_number VARCHAR(120) NOT NULL DEFAULT '',
                customer_phone VARCHAR(32) NOT NULL DEFAULT '',
                customer_name VARCHAR(190) NOT NULL DEFAULT '',
                refusal_reason TEXT NULL,
                sub_status VARCHAR(60) NOT NULL DEFAULT '',
                action_taken VARCHAR(60) NOT NULL DEFAULT 'pending_review',
                reviewed_by INT NOT NULL DEFAULT 0,
                reviewed_at DATETIME NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rq_order (order_id),
                KEY idx_rq_phone (customer_phone),
                KEY idx_rq_action (action_taken)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_customer_risk_timeline (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_phone VARCHAR(32) NOT NULL DEFAULT '',
                customer_name VARCHAR(190) NOT NULL DEFAULT '',
                event_type VARCHAR(60) NOT NULL DEFAULT '',
                event_label VARCHAR(255) NOT NULL DEFAULT '',
                order_id INT NOT NULL DEFAULT 0,
                previous_risk VARCHAR(30) NOT NULL DEFAULT '',
                new_risk VARCHAR(30) NOT NULL DEFAULT '',
                metadata LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_crt_phone (customer_phone),
                KEY idx_crt_event (event_type),
                KEY idx_crt_date (created_at),
                KEY idx_crt_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        admin_add_column_if_missing($pdo, 'tbl_order', 'total_contact_attempts', 'INT NOT NULL DEFAULT 0');
        admin_add_column_if_missing($pdo, 'tbl_order', 'last_contact_attempt_at', 'DATETIME NULL');
        admin_add_column_if_missing($pdo, 'tbl_order', 'last_sub_status', "VARCHAR(60) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'recovery_status', "VARCHAR(30) NOT NULL DEFAULT ''");
        admin_add_column_if_missing($pdo, 'tbl_order', 'ecotrack_remote_time', 'DATETIME NULL');

        @file_put_contents($lock_file, '1');
        $done = true;
    }
}

if (!function_exists('recovery_engine_sub_status_action')) {
    function recovery_engine_sub_status_action(string $sub_status): array
    {
        $actions = [
            'no_answer' => [
                'task_type' => 'retry_call',
                'delay_minutes' => 30,
                'risk_impact' => 0,
                'label_ar' => 'لم يجب',
                'icon' => 'fa-phone',
                'notify_employee' => true,
                'notify_telegram' => true,
            ],
            'busy' => [
                'task_type' => 'retry_call',
                'delay_minutes' => 15,
                'risk_impact' => 0,
                'label_ar' => 'مشغول',
                'icon' => 'fa-phone',
                'notify_employee' => true,
                'notify_telegram' => false,
            ],
            'unreachable' => [
                'task_type' => 'escalate',
                'delay_minutes' => 60,
                'risk_impact' => 1,
                'label_ar' => 'لا يمكن الوصول',
                'icon' => 'fa-exclamation-triangle',
                'notify_employee' => true,
                'notify_telegram' => true,
            ],
            'postponed_by_client' => [
                'task_type' => 'scheduled_followup',
                'delay_minutes' => 1440,
                'risk_impact' => 0,
                'label_ar' => 'أجلها العميل',
                'icon' => 'fa-calendar',
                'notify_employee' => true,
                'notify_telegram' => true,
            ],
            'wrong_location' => [
                'task_type' => 'address_correction',
                'delay_minutes' => 0,
                'risk_impact' => 1,
                'label_ar' => 'عنوان خطأ',
                'icon' => 'fa-map-marker',
                'notify_employee' => true,
                'notify_telegram' => true,
            ],
            'refused_by_customer' => [
                'task_type' => 'recovery_review',
                'delay_minutes' => 0,
                'risk_impact' => 2,
                'label_ar' => 'رفض العميل',
                'icon' => 'fa-ban',
                'notify_employee' => true,
                'notify_telegram' => true,
            ],
        ];

        $key = trim(str_replace([' ', '-'], '_', mb_strtolower($sub_status, 'UTF-8')));
        if (isset($actions[$key])) {
            return $actions[$key];
        }

        return [
            'task_type' => 'review',
            'delay_minutes' => 0,
            'risk_impact' => 0,
            'label_ar' => $sub_status,
            'icon' => 'fa-question-circle',
            'notify_employee' => false,
            'notify_telegram' => false,
        ];
    }
}

if (!function_exists('recovery_engine_normalize_sub_status')) {
    function recovery_engine_normalize_sub_status(string $raw): string
    {
        $raw = trim(mb_strtolower($raw, 'UTF-8'));
        $map = [
            'no answer' => 'no_answer',
            'no_answer' => 'no_answer',
            'pas de réponse' => 'no_answer',
            'لم يجب' => 'no_answer',
            'لم يرد' => 'no_answer',
            'لا يوجد رد' => 'no_answer',

            'busy' => 'busy',
            'occupé' => 'busy',
            'مشغول' => 'busy',
            'خط مشغول' => 'busy',

            'unreachable' => 'unreachable',
            'not reachable' => 'unreachable',
            'hors service' => 'unreachable',
            'hors zone' => 'unreachable',
            'لا يمكن الوصول' => 'unreachable',
            'رقم مغلق' => 'unreachable',
            'رقم خطأ' => 'unreachable',
            'غير متاح' => 'unreachable',

            'postponed' => 'postponed_by_client',
            'postponed by client' => 'postponed_by_client',
            'postponed_by_client' => 'postponed_by_client',
            'reporté' => 'postponed_by_client',
            'reporté par client' => 'postponed_by_client',
            'أجلها العميل' => 'postponed_by_client',
            'تأجيل' => 'postponed_by_client',
            'أجل' => 'postponed_by_client',
            'اتصل لاحقا' => 'postponed_by_client',

            'wrong location' => 'wrong_location',
            'wrong_location' => 'wrong_location',
            'wrong address' => 'wrong_location',
            'adresse erronée' => 'wrong_location',
            'mauvaise adresse' => 'wrong_location',
            'عنوان خطأ' => 'wrong_location',
            'عنوان خاطئ' => 'wrong_location',
            'لا يوجد عنوان' => 'wrong_location',

            'refused' => 'refused_by_customer',
            'refused by customer' => 'refused_by_customer',
            'refused_by_customer' => 'refused_by_customer',
            'refusé' => 'refused_by_customer',
            'refusé par client' => 'refused_by_customer',
            'رفض' => 'refused_by_customer',
            'رفض العميل' => 'refused_by_customer',
            'مرفوض' => 'refused_by_customer',
            'لا يريد' => 'refused_by_customer',
            'غير راغب' => 'refused_by_customer',
        ];

        return $map[$raw] ?? $raw;
    }
}

if (!function_exists('recovery_engine_parse_ecotrack_attempts')) {
    function recovery_engine_parse_ecotrack_attempts(PDO $pdo, array $tracking_data, string $tracking_number, int $order_id, array $order = []): array
    {
        recovery_engine_ensure_tables($pdo);

        $inserted = 0;
        $attempts = [];

        $history = [];
        if (isset($tracking_data['history']) && is_array($tracking_data['history'])) {
            $history = $tracking_data['history'];
        } elseif (isset($tracking_data['activities']) && is_array($tracking_data['activities'])) {
            $history = $tracking_data['activities'];
        }

        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order_contact_attempt WHERE order_id = ? AND attempt_number = ? AND status = ?");
        $insert_stmt = $pdo->prepare("
            INSERT IGNORE INTO tbl_order_contact_attempt
            (order_id, tracking_number, status, sub_status, comment, attempt_number, employee_id, attempt_date, raw_payload)
            VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
        ");

        $latest_sub_status = '';

        foreach ($history as $index => $entry) {
            if (!is_array($entry)) continue;

            $status = trim((string) ($entry['status'] ?? $entry['etat'] ?? $entry['state'] ?? ''));
            $note = trim((string) ($entry['note'] ?? $entry['content'] ?? $entry['remarque'] ?? $entry['comment'] ?? $entry['message'] ?? ''));
            $date_raw = trim((string) ($entry['date'] ?? $entry['created_at'] ?? $entry['changed_at'] ?? ''));
            $time_raw = trim((string) ($entry['time'] ?? $entry['heure'] ?? ''));
            $attempt_date = null;
            if ($date_raw !== '') {
                $attempt_date = $date_raw;
                if ($time_raw !== '') {
                    $attempt_date .= ' ' . $time_raw;
                }
            }

            $attempt_number = count($history) - $index;
            $sub_status = recovery_engine_normalize_sub_status($note);

            if ($sub_status !== '' && $status !== '') {
                $check_stmt->execute([$order_id, $attempt_number, $status]);
                $exists = (int) $check_stmt->fetchColumn();
                if ($exists === 0) {
                    $payload_json = json_encode($entry, JSON_UNESCAPED_UNICODE);
                    $insert_stmt->execute([
                        $order_id,
                        $tracking_number,
                        $status,
                        $sub_status,
                        $note,
                        $attempt_number,
                        $attempt_date,
                        $payload_json,
                    ]);
                    $inserted++;
                    $attempts[] = [
                        'status' => $status,
                        'sub_status' => $sub_status,
                        'note' => $note,
                        'attempt_number' => $attempt_number,
                        'attempt_date' => $attempt_date,
                    ];
                }
                if ($index === 0 || $index === count($history) - 1) {
                    $latest_sub_status = $sub_status;
                }
            }
        }

        if ($inserted > 0) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order_contact_attempt WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $total = (int) $stmt->fetchColumn();
            $pdo->prepare("UPDATE tbl_order SET total_contact_attempts = ?, last_contact_attempt_at = ?, last_sub_status = ? WHERE id = ?")
                ->execute([$total, $attempt_date ?? date('Y-m-d H:i:s'), $latest_sub_status, $order_id]);
        }

        return ['inserted' => $inserted, 'attempts' => $attempts, 'latest_sub_status' => $latest_sub_status];
    }
}

if (!function_exists('recovery_engine_process_sub_status')) {
    function recovery_engine_process_sub_status(PDO $pdo, int $order_id, string $tracking_number, string $sub_status, string $note = '', array $order = []): array
    {
        recovery_engine_ensure_tables($pdo);

        $action = recovery_engine_sub_status_action($sub_status);
        $results = [];

        $results['action'] = $action;

        $stmt = $pdo->prepare("SELECT id FROM tbl_recovery_tasks WHERE order_id = ? AND sub_status = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$order_id, $sub_status]);
        if ($stmt->fetchColumn()) {
            return ['skipped' => true, 'reason' => 'task_exists', 'action' => $action];
        }

        $now = new DateTime();
        $scheduled_at = null;
        if ($action['delay_minutes'] > 0) {
            $delay = clone $now;
            $delay->modify('+' . (int) $action['delay_minutes'] . ' minutes');
            $scheduled_at = $delay->format('Y-m-d H:i:s');
        }

        $phone = trim((string) ($order['customer_phone'] ?? ''));
        $name = trim((string) ($order['customer_name'] ?? ''));

        $stmt = $pdo->prepare("
            INSERT INTO tbl_recovery_tasks
            (order_id, tracking_number, task_type, sub_status, status, assigned_to, notes, scheduled_at)
            VALUES (?, ?, ?, ?, 'pending', 0, ?, ?)
        ");
        $stmt->execute([
            $order_id,
            $tracking_number,
            $action['task_type'],
            $sub_status,
            $note,
            $scheduled_at,
        ]);
        $task_id = (int) $pdo->lastInsertId();
    $results['task_id'] = $task_id;

    if (function_exists('audit_log_recovery')) {
        audit_log_recovery($pdo, $task_id, 'recovery_task_created', null, json_encode(['order_id' => $order_id, 'sub_status' => $sub_status, 'task_type' => $action['task_type'], 'note' => $note], JSON_UNESCAPED_UNICODE), 'recovery_engine');
    }

    $pdo->prepare("UPDATE tbl_order SET recovery_status = ? WHERE id = ?")->execute([$action['task_type'], $order_id]);

    if ($action['task_type'] === 'recovery_review') {
            $phone = trim((string) ($order['customer_phone'] ?? ''));
            $name = trim((string) ($order['customer_name'] ?? ''));
            $queue_stmt = $pdo->prepare("
                INSERT IGNORE INTO tbl_recovery_queue
                (order_id, tracking_number, customer_phone, customer_name, refusal_reason, sub_status, action_taken, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending_review', NOW())
            ");
            $queue_stmt->execute([$order_id, $tracking_number, $phone, $name, $note, $sub_status]);
            $results['queue_id'] = (int) $pdo->lastInsertId();
        }

        if ($action['risk_impact'] > 0) {
            $risk_result = recovery_engine_update_risk_score($pdo, $phone, $name, $sub_status, $action['risk_impact'], $order_id);
            $results['risk'] = $risk_result;
        }

        if ($action['notify_employee'] && function_exists('telegram_build_event_recovery')) {
            telegram_queue_recovery_notification($pdo, $order_id, $tracking_number, $sub_status, $action['label_ar'], $note);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_customer_risk_timeline
            (customer_phone, customer_name, event_type, event_label, order_id, metadata, created_at)
            VALUES (?, ?, 'recovery_task_created', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $phone,
            $name,
            $action['label_ar'] . ' - ' . $sub_status,
            $order_id,
            json_encode(['task_id' => $task_id, 'sub_status' => $sub_status, 'note' => $note], JSON_UNESCAPED_UNICODE),
        ]);

        return $results;
    }
}

if (!function_exists('recovery_engine_update_risk_score')) {
    function recovery_engine_update_risk_score(PDO $pdo, string $phone, string $name, string $sub_status, int $impact, int $order_id = 0): array
    {
        if ($phone === '') {
            return ['updated' => false, 'reason' => 'no_phone'];
        }

        $settings = recovery_engine_get_settings($pdo);
        $max_risk_before_blacklist = (int) ($settings['max_risk_before_blacklist'] ?? 3);

        $risk_count = 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_customer_risk_timeline WHERE customer_phone = ? AND event_type IN ('risk_increase', 'recovery_task_created')");
        $stmt->execute([$phone]);
        $risk_count = (int) $stmt->fetchColumn();

        $risk_count += $impact;
        $new_risk_level = 'warning';
        if ($risk_count >= $max_risk_before_blacklist) {
            $new_risk_level = 'banned';
        } elseif ($risk_count >= $max_risk_before_blacklist - 1) {
            $new_risk_level = 'high_risk';
        } elseif ($risk_count >= 2) {
            $new_risk_level = 'deposit_required';
        } elseif ($risk_count >= 1) {
            $new_risk_level = 'review';
        }

        $previous_level = $new_risk_level;

        $stmt = $pdo->prepare("
            INSERT INTO tbl_customer_risk_timeline
            (customer_phone, customer_name, event_type, event_label, order_id, previous_risk, new_risk, metadata, created_at)
            VALUES (?, ?, 'risk_increase', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $phone,
            $name,
            $sub_status . ' (impact: ' . $impact . ', total: ' . $risk_count . ')',
            $order_id,
            $previous_level,
            $new_risk_level,
            json_encode(['risk_count' => $risk_count, 'impact' => $impact], JSON_UNESCAPED_UNICODE),
        ]);

    if (function_exists('audit_log_security')) {
        audit_log_security($pdo, $order_id, 'risk_level_changed', $previous_level, $new_risk_level, 'recovery_engine');
    }

    if ($new_risk_level === 'banned') {
        $ban_result = recovery_engine_auto_blacklist($pdo, $phone, $name, $risk_count, $order_id);
        if (function_exists('audit_log_security') && !empty($ban_result['blacklisted'])) {
            audit_log_security($pdo, $ban_result['existing_id'] ?: (int) ($order_id), 'blacklist_added', null, json_encode(['phone' => $phone, 'risk_count' => $risk_count], JSON_UNESCAPED_UNICODE), 'recovery_engine');
        }
    }

    return [
            'updated' => true,
            'risk_count' => $risk_count,
            'new_risk_level' => $new_risk_level,
            'previous_level' => $previous_level,
        ];
    }
}

if (!function_exists('recovery_engine_auto_blacklist')) {
    function recovery_engine_auto_blacklist(PDO $pdo, string $phone, string $name, int $risk_count, int $order_id = 0): array
    {
        if (!function_exists('site_security_normalize_phone')) {
            require_once dirname(dirname(__DIR__)) . '/inc/site-security.php';
        }

        $normalized = function_exists('site_security_normalize_phone') ? site_security_normalize_phone($phone) : preg_replace('/[^\d]/', '', $phone);

        $stmt = $pdo->prepare("SELECT id FROM site_security_blacklist WHERE normalized_phone = ? LIMIT 1");
        $stmt->execute([$normalized]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE site_security_blacklist
                SET status = 'banned', rejected_orders_count = GREATEST(rejected_orders_count, ?), notes = CONCAT_WS('\n', notes, ?), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$risk_count, 'تلقائي: تراكم مخاطر التوصيل (' . $risk_count . ')', $existing]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO site_security_blacklist
                (phone, normalized_phone, customer_name, status, notes, rejected_orders_count, is_active, created_at)
                VALUES (?, ?, ?, 'banned', ?, ?, 1, NOW())
            ");
            $stmt->execute([$phone, $normalized, $name, 'تلقائي: تراكم مخاطر التوصيل (' . $risk_count . ')', $risk_count]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_customer_risk_timeline
            (customer_phone, customer_name, event_type, event_label, order_id, metadata, created_at)
            VALUES (?, ?, 'auto_blacklisted', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $phone, $name,
            'تم المنع التلقائي - تراكم ' . $risk_count . ' مخالفة توصيل',
            $order_id,
            json_encode(['risk_count' => $risk_count, 'source' => 'recovery_engine'], JSON_UNESCAPED_UNICODE),
        ]);

        return ['blacklisted' => true, 'normalized_phone' => $normalized, 'existing_id' => $existing ? (int) $existing : 0];
    }
}

if (!function_exists('recovery_engine_get_settings')) {
    function recovery_engine_get_settings(PDO $pdo): array
    {
        $defaults = [
            'max_risk_before_blacklist' => '3',
            'recovery_auto_retry_enabled' => '1',
            'recovery_auto_retry_max' => '3',
            'recovery_notify_employee' => '1',
            'recovery_notify_telegram' => '1',
            'recovery_address_correction_enabled' => '1',
        ];

        $settings = [];
        $stmt = $pdo->query("SELECT config_key, config_value FROM tbl_recovery_settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['config_key']] = $row['config_value'];
        }

        foreach ($defaults as $key => $val) {
            if (!isset($settings[$key])) {
                $settings[$key] = $val;
            }
        }

        return $settings;
    }
}

if (!function_exists('recovery_engine_save_settings')) {
    function recovery_engine_save_settings(PDO $pdo, array $settings): void
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_recovery_settings (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
        foreach ($settings as $key => $value) {
            $stmt->execute([trim((string) $key), trim((string) $value)]);
        }
    }
}

if (!function_exists('recovery_engine_ensure_settings_table')) {
    function recovery_engine_ensure_settings_table(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_recovery_settings (
                config_key VARCHAR(100) PRIMARY KEY,
                config_value TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $done = true;
    }
}

if (!function_exists('recovery_engine_get_customer_history')) {
    function recovery_engine_get_customer_history(PDO $pdo, string $phone): array
    {
        $phone = trim($phone);
        if ($phone === '') return ['phone' => '', 'events' => [], 'orders' => [], 'attempts' => [], 'tasks' => []];

        $events = $pdo->prepare("SELECT * FROM tbl_customer_risk_timeline WHERE customer_phone = ? ORDER BY created_at DESC LIMIT 100");
        $events->execute([$phone]);
        $events = $events->fetchAll(PDO::FETCH_ASSOC);

        $orders = $pdo->prepare("SELECT id, order_status, ecotrack_tracking, ecotrack_remote_status, total_contact_attempts, last_sub_status, recovery_status, product_name, total_price, order_date FROM tbl_order WHERE customer_phone = ? ORDER BY order_date DESC LIMIT 50");
        $orders->execute([$phone]);
        $orders = $orders->fetchAll(PDO::FETCH_ASSOC);

        $order_ids = array_column($orders, 'id');
        $attempts = [];
        $tasks = [];
        if (!empty($order_ids)) {
            $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
            $attempts_stmt = $pdo->prepare("SELECT * FROM tbl_order_contact_attempt WHERE order_id IN ($placeholders) ORDER BY attempt_date DESC LIMIT 200");
            $attempts_stmt->execute($order_ids);
            $attempts = $attempts_stmt->fetchAll(PDO::FETCH_ASSOC);

            $tasks_stmt = $pdo->prepare("SELECT * FROM tbl_recovery_tasks WHERE order_id IN ($placeholders) ORDER BY created_at DESC LIMIT 100");
            $tasks_stmt->execute($order_ids);
            $tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $blacklist = null;
        $bl_stmt = $pdo->prepare("SELECT * FROM site_security_blacklist WHERE normalized_phone = ? LIMIT 1");
        $bl_stmt->execute([preg_replace('/[^\d]/', '', $phone)]);
        $blacklist = $bl_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'phone' => $phone,
            'events' => $events,
            'orders' => $orders,
            'attempts' => $attempts,
            'tasks' => $tasks,
            'blacklist' => $blacklist,
        ];
    }
}

if (!function_exists('recovery_engine_feed_ai_insights')) {
    function recovery_engine_feed_ai_insights(PDO $pdo): array
    {
        $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

        $stmt = $pdo->prepare("
            SELECT sub_status, COUNT(*) AS cnt
            FROM tbl_order_contact_attempt
            WHERE created_at >= ?
            GROUP BY sub_status
            ORDER BY cnt DESC
        ");
        $stmt->execute([$seven_days_ago]);
        $sub_status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT o.wilaya, ca.sub_status, COUNT(*) AS cnt
            FROM tbl_order_contact_attempt ca
            INNER JOIN tbl_order o ON o.id = ca.order_id
            WHERE ca.created_at >= ?
            GROUP BY o.wilaya, ca.sub_status
            ORDER BY cnt DESC
            LIMIT 20
        ");
        $stmt->execute([$seven_days_ago]);
        $wilaya_substatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT ca.sub_status, COUNT(DISTINCT ca.order_id) AS order_count, COUNT(*) AS attempt_count
            FROM tbl_order_contact_attempt ca
            INNER JOIN tbl_order o ON o.id = ca.order_id
            WHERE ca.created_at >= ? AND ca.sub_status = 'refused_by_customer'
            GROUP BY ca.sub_status
        ");
        $stmt->execute([$seven_days_ago]);
        $refusal_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'sub_status_counts' => $sub_status_counts,
            'wilaya_substatus' => $wilaya_substatus,
            'refusal_stats' => $refusal_stats,
        ];
    }
}

if (!function_exists('recovery_engine_get_queue_counts')) {
    function recovery_engine_get_queue_counts(PDO $pdo): array
    {
        return [
            'pending' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_recovery_tasks WHERE status = 'pending'")->fetchColumn(),
            'overdue' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_recovery_tasks WHERE status = 'pending' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()")->fetchColumn(),
            'review' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_recovery_queue WHERE action_taken = 'pending_review'")->fetchColumn(),
            'refused' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_recovery_queue")->fetchColumn(),
            'total_attempts' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_order_contact_attempt")->fetchColumn(),
            'recent_failures' => (int) $pdo->query("SELECT COUNT(*) FROM tbl_order_contact_attempt WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND sub_status IN ('no_answer','busy','unreachable','refused_by_customer')")->fetchColumn(),
        ];
    }
}

if (!function_exists('recovery_engine_resolve_task')) {
    function recovery_engine_resolve_task(PDO $pdo, int $task_id, string $resolution, string $note = '', int $resolved_by = 0): bool
    {
        $stmt = $pdo->prepare("UPDATE tbl_recovery_tasks SET status = ?, notes = CONCAT_WS('\n', notes, ?), completed_at = NOW() WHERE id = ?");
        $stmt->execute([$resolution, $note, $task_id]);
        $ok = $stmt->rowCount() > 0;
        if ($ok && function_exists('audit_log_recovery')) {
            audit_log_recovery($pdo, $task_id, 'recovery_task_closed', null, json_encode(['resolution' => $resolution, 'note' => $note], JSON_UNESCAPED_UNICODE), 'admin_panel', $resolved_by);
        }
        return $ok;
    }
}

if (!function_exists('recovery_engine_resolve_queue_item')) {
    function recovery_engine_resolve_queue_item(PDO $pdo, int $queue_id, string $action_taken, string $note = '', int $reviewed_by = 0): bool
    {
        $stmt = $pdo->prepare("UPDATE tbl_recovery_queue SET action_taken = ?, notes = CONCAT_WS('\n', notes, ?), reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $stmt->execute([$action_taken, $note, $reviewed_by, $queue_id]);
        return $stmt->rowCount() > 0;
    }
}
