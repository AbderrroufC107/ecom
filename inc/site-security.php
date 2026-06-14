<?php

if (!function_exists('site_security_normalize_phone')) {
    function site_security_normalize_phone($phone)
    {
        $phone = preg_replace('/[^\d]/', '', (string) $phone);
        if (strpos($phone, '213') === 0) {
            $phone = substr($phone, 3);
        }
        if (strlen($phone) === 9 && preg_match('/^[5-7]/', $phone)) {
            $phone = '0' . $phone;
        }
        return $phone;
    }
}

if (!function_exists('site_security_phone_variants')) {
    function site_security_phone_variants($phone)
    {
        $normalized = site_security_normalize_phone($phone);
        $digits = preg_replace('/[^\d]/', '', (string) $phone);
        $variants = array_filter([
            $normalized,
            $digits,
            $normalized !== '' ? ltrim($normalized, '0') : '',
            $normalized !== '' && strpos($normalized, '0') === 0 ? '213' . substr($normalized, 1) : '',
        ], function ($value) {
            return trim((string) $value) !== '';
        });

        return array_values(array_unique($variants));
    }
}

if (!function_exists('site_security_normalize_text')) {
    function site_security_normalize_text($value)
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}

if (!function_exists('site_security_normalize_address')) {
    function site_security_normalize_address($value)
    {
        $value = site_security_normalize_text($value);
        $value = preg_replace('/[^\p{L}\p{N}\s\-\/]+/u', '', $value);
        return trim((string) $value);
    }
}

if (!function_exists('site_security_client_ip')) {
    function site_security_client_ip()
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? ''
        ];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        return '';
    }
}

if (!function_exists('site_security_device_id')) {
    function site_security_device_id($value = null)
    {
        $value = $value ?? ($_POST['device_id'] ?? $_COOKIE['site_device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? '');
        $value = preg_replace('/[^a-zA-Z0-9_\-:.]/', '', (string) $value);
        return substr($value, 0, 128);
    }
}

if (!function_exists('site_security_table_columns')) {
    function site_security_table_columns(PDO $pdo, $table)
    {
        $columns = [];
        try {
            $statement = $pdo->query("SHOW COLUMNS FROM `" . str_replace('`', '``', (string) $table) . "`");
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $columns[strtolower((string) $column['Field'])] = true;
            }
        } catch (PDOException $e) {
            error_log('site_security_table_columns failed: ' . $e->getMessage());
        }
        return $columns;
    }
}

if (!function_exists('site_security_add_column_if_missing')) {
    function site_security_add_column_if_missing(PDO $pdo, $table, $column, $definition)
    {
        $columns = site_security_table_columns($pdo, $table);
        if (isset($columns[strtolower((string) $column)])) {
            return;
        }
        $pdo->exec("ALTER TABLE `" . str_replace('`', '``', (string) $table) . "` ADD COLUMN `" . str_replace('`', '``', (string) $column) . "` " . $definition);
    }
}

if (!function_exists('site_security_ensure_order_columns')) {
    function site_security_ensure_order_columns(PDO $pdo)
    {
        site_security_add_column_if_missing($pdo, 'tbl_order', 'customer_ip', 'VARCHAR(64) NULL');
        site_security_add_column_if_missing($pdo, 'tbl_order', 'device_id', 'VARCHAR(128) NULL');
        site_security_add_column_if_missing($pdo, 'tbl_order', 'user_agent', 'VARCHAR(255) NULL');
    }
}

if (!function_exists('site_security_ensure_incomplete_order_columns')) {
    function site_security_ensure_incomplete_order_columns(PDO $pdo)
    {
        site_security_add_column_if_missing($pdo, 'incomplete_orders', 'address', 'VARCHAR(255) NULL');
        site_security_add_column_if_missing($pdo, 'incomplete_orders', 'customer_ip', 'VARCHAR(64) NULL');
        site_security_add_column_if_missing($pdo, 'incomplete_orders', 'device_id', 'VARCHAR(128) NULL');
        site_security_add_column_if_missing($pdo, 'incomplete_orders', 'user_agent', 'VARCHAR(255) NULL');
    }
}

if (!function_exists('site_security_ensure_tables')) {
    function site_security_ensure_tables(PDO $pdo)
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $lock_file = __DIR__ . '/../admin/cache/site_security_tables.lock';
        if (file_exists($lock_file)) {
            $ensured = true;
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS blocked_phones (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(32) NOT NULL,
                normalized_phone VARCHAR(32) NOT NULL,
                note TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                UNIQUE KEY unique_blocked_phone (normalized_phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_security_blacklist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                phone VARCHAR(32) NULL,
                normalized_phone VARCHAR(32) NULL,
                customer_name VARCHAR(190) NULL,
                normalized_name VARCHAR(190) NULL,
                wilaya VARCHAR(190) NULL,
                commune VARCHAR(190) NULL,
                address VARCHAR(255) NULL,
                normalized_address VARCHAR(255) NULL,
                ip_address VARCHAR(64) NULL,
                device_id VARCHAR(128) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'warning',
                notes TEXT NULL,
                rejected_orders_count INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                KEY idx_security_phone (normalized_phone),
                KEY idx_security_ip (ip_address),
                KEY idx_security_device (device_id),
                KEY idx_security_address (normalized_address),
                KEY idx_security_status (status),
                KEY idx_security_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS site_security_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                source VARCHAR(50) NOT NULL DEFAULT 'system',
                remote_status VARCHAR(190) NULL,
                note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_security_event (order_id, event_type),
                KEY idx_security_event_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $migration_sql = "
            INSERT INTO site_security_blacklist (phone, normalized_phone, status, notes, rejected_orders_count, is_active, created_at, updated_at)
            SELECT bp.phone, bp.normalized_phone, 'banned', bp.note, 0, bp.is_active, bp.created_at, bp.updated_at
            FROM blocked_phones bp
            WHERE bp.normalized_phone <> ''
              AND NOT EXISTS (
                SELECT 1 FROM site_security_blacklist sb
                WHERE sb.normalized_phone = bp.normalized_phone
              )
        ";

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $pdo->exec($migration_sql);
                break;
            } catch (PDOException $e) {
                $driver_code = (int)($e->errorInfo[1] ?? 0);
                if ($attempt === 0 && ($driver_code === 1213 || $driver_code === 1205)) {
                    usleep(150000);
                    continue;
                }
                throw $e;
            }
        }

        @file_put_contents($lock_file, '1');
        $ensured = true;
    }
}

if (!function_exists('site_security_build_order_context')) {
    function site_security_build_order_context(array $data = [])
    {
        $phone = $data['phone'] ?? $data['customer_phone'] ?? $_POST['customer_phone'] ?? '';
        $name = $data['customer_name'] ?? $data['name'] ?? $_POST['customer_name'] ?? '';
        $wilaya = $data['wilaya'] ?? $_POST['wilaya'] ?? '';
        $commune = $data['commune'] ?? $data['city'] ?? $_POST['commune'] ?? '';
        $address = $data['address'] ?? $data['customer_address'] ?? $_POST['address'] ?? $_POST['customer_address'] ?? '';
        $ip = $data['ip_address'] ?? $data['ip'] ?? site_security_client_ip();
        $device_id = $data['device_id'] ?? site_security_device_id();

        return [
            'phone' => site_security_normalize_phone($phone),
            'raw_phone' => trim((string) $phone),
            'customer_name' => trim((string) $name),
            'normalized_name' => site_security_normalize_text($name),
            'wilaya' => trim((string) $wilaya),
            'commune' => trim((string) $commune),
            'address' => trim((string) $address),
            'normalized_address' => site_security_normalize_address($address),
            'ip_address' => trim((string) $ip),
            'device_id' => site_security_device_id($device_id),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)
        ];
    }
}

if (!function_exists('site_security_status_action')) {
    function site_security_status_action($status)
    {
        $status = (string) $status;
        if ($status === 'banned') {
            return 'reject';
        }
        if ($status === 'deposit_required' || $status === 'high_risk') {
            return 'deposit';
        }
        if ($status === 'review' || $status === 'warning') {
            return 'review';
        }
        return 'review';
    }
}

if (!function_exists('site_security_action_priority')) {
    function site_security_action_priority($action)
    {
        return ['allow' => 0, 'review' => 1, 'deposit' => 2, 'reject' => 3][$action] ?? 1;
    }
}

if (!function_exists('site_security_evaluate_order')) {
    function site_security_evaluate_order(PDO $pdo, array $context = [])
    {
        site_security_ensure_tables($pdo);
        $context = site_security_build_order_context($context);
        $conditions = [];
        $params = [];

        if ($context['phone'] !== '') {
            $phone_variants = site_security_phone_variants($context['phone']);
            if ($phone_variants) {
                $conditions[] = "(normalized_phone IS NOT NULL AND normalized_phone <> '' AND normalized_phone IN (" . implode(',', array_fill(0, count($phone_variants), '?')) . "))";
                $params = array_merge($params, $phone_variants);
            }
        }
        if ($context['ip_address'] !== '') {
            $conditions[] = "(ip_address IS NOT NULL AND ip_address <> '' AND ip_address = ?)";
            $params[] = $context['ip_address'];
        }
        if ($context['device_id'] !== '') {
            $conditions[] = "(device_id IS NOT NULL AND device_id <> '' AND device_id = ?)";
            $params[] = $context['device_id'];
        }
        if ($context['normalized_address'] !== '') {
            $conditions[] = "(normalized_address IS NOT NULL AND normalized_address <> '' AND normalized_address = ?)";
            $params[] = $context['normalized_address'];
        }
        if ($context['normalized_name'] !== '' && ($context['wilaya'] !== '' || $context['commune'] !== '')) {
            $conditions[] = "(normalized_name IS NOT NULL AND normalized_name <> '' AND normalized_name = ? AND (wilaya = ? OR commune = ?))";
            $params[] = $context['normalized_name'];
            $params[] = $context['wilaya'];
            $params[] = $context['commune'];
        }

        if (!$conditions) {
            return [
                'matched' => false,
                'action' => 'allow',
                'status' => 'clear',
                'message' => '',
                'matches' => [],
                'context' => $context
            ];
        }

        $sql = "SELECT * FROM site_security_blacklist WHERE is_active = 1 AND (" . implode(' OR ', $conditions) . ") ORDER BY FIELD(status, 'banned', 'deposit_required', 'high_risk', 'review', 'warning') ASC, rejected_orders_count DESC, id DESC";
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        $matches = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!$matches) {
            return [
                'matched' => false,
                'action' => 'allow',
                'status' => 'clear',
                'message' => '',
                'matches' => [],
                'context' => $context
            ];
        }

        $action = 'review';
        $status = (string)($matches[0]['status'] ?? 'review');
        foreach ($matches as $match) {
            $candidate = site_security_status_action($match['status'] ?? 'review');
            if (site_security_action_priority($candidate) > site_security_action_priority($action)) {
                $action = $candidate;
                $status = (string)($match['status'] ?? $status);
            }
        }

        return [
            'matched' => true,
            'action' => $action,
            'status' => $status,
            'message' => site_security_action_message($action),
            'matches' => $matches,
            'context' => $context
        ];
    }
}

if (!function_exists('site_security_action_message')) {
    function site_security_action_message($action)
    {
        if ($action === 'reject') {
            return 'لا يمكن إرسال الطلب بهذه البيانات. يرجى التواصل مع خدمة العملاء.';
        }
        if ($action === 'deposit') {
            return 'هذا العميل عالي الخطورة. لا يتم تأكيد الطلب إلا بعد دفع عربون أو مراجعة الإدارة.';
        }
        return 'هذا الطلب يحتاج مراجعة يدوية قبل التأكيد. يرجى التواصل مع خدمة العملاء.';
    }
}

if (!function_exists('site_security_record_rejected_attempt')) {
    function site_security_record_rejected_attempt(PDO $pdo, array $evaluation)
    {
        if (empty($evaluation['matches'])) {
            return;
        }
        $ids = [];
        foreach ($evaluation['matches'] as $match) {
            $id = (int)($match['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare("
            UPDATE site_security_blacklist
            SET status = CASE
                    WHEN status = 'banned' THEN 'banned'
                    WHEN rejected_orders_count + 1 >= 3 THEN 'banned'
                    WHEN status = 'deposit_required' THEN 'deposit_required'
                    WHEN rejected_orders_count + 1 >= 2 THEN 'high_risk'
                    ELSE status
                END,
                rejected_orders_count = rejected_orders_count + 1,
                updated_at = NOW()
            WHERE id IN ($placeholders)
        ");
        $statement->execute($ids);
    }
}

if (!function_exists('site_security_status_for_rejected_count')) {
    function site_security_status_for_rejected_count($count)
    {
        $count = (int) $count;
        if ($count >= 3) {
            return 'banned';
        }
        if ($count >= 2) {
            return 'high_risk';
        }
        return 'warning';
    }
}

if (!function_exists('site_security_is_delivery_return_status')) {
    function site_security_is_delivery_return_status($status, $note = '')
    {
        $text = site_security_normalize_text((string) $status . ' ' . (string) $note);
        if ($text === '') {
            return false;
        }

        $patterns = [
            '/\bretour/i',
            '/\bretourn/i',
            '/\breturned\b/i',
            '/\breturn\b/i',
            '/\brefus/i',
            '/\brefuse/i',
            '/\brefused\b/i',
            '/\bfailed delivery\b/i',
            '/\bechec\b/i',
            '/\béchec\b/iu',
            '/رفض/u',
            '/مرفوض/u',
            '/مرتجع/u',
            '/ارجاع/u',
            '/إرجاع/u',
            '/رجع/u',
            '/لم يستلم/u',
            '/غير مستلم/u'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('site_security_build_context_from_order')) {
    function site_security_build_context_from_order(array $order)
    {
        return [
            'customer_name' => $order['customer_name'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? $order['phone'] ?? '',
            'wilaya' => $order['wilaya'] ?? '',
            'commune' => $order['commune'] ?? '',
            'address' => $order['address'] ?? $order['customer_address'] ?? '',
            'ip_address' => $order['customer_ip'] ?? $order['ip_address'] ?? $order['ip'] ?? '',
            'device_id' => $order['device_id'] ?? ''
        ];
    }
}

if (!function_exists('site_security_fill_rule_context')) {
    function site_security_fill_rule_context(PDO $pdo, array $ids, array $context, $note = '')
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = [
            $context['raw_phone'] ?? $context['phone'] ?? '',
            $context['phone'] ?? '',
            $context['customer_name'] ?? '',
            $context['normalized_name'] ?? '',
            $context['wilaya'] ?? '',
            $context['commune'] ?? '',
            $context['address'] ?? '',
            $context['normalized_address'] ?? '',
            $context['ip_address'] ?? '',
            $context['device_id'] ?? '',
            trim((string) $note) !== '' ? trim((string) $note) : null
        ];
        $params = array_merge($params, $ids);

        $statement = $pdo->prepare("
            UPDATE site_security_blacklist
            SET phone = CASE WHEN (phone IS NULL OR phone = '') AND ? <> '' THEN ? ELSE phone END,
                normalized_phone = CASE WHEN (normalized_phone IS NULL OR normalized_phone = '') AND ? <> '' THEN ? ELSE normalized_phone END,
                customer_name = CASE WHEN (customer_name IS NULL OR customer_name = '') AND ? <> '' THEN ? ELSE customer_name END,
                normalized_name = CASE WHEN (normalized_name IS NULL OR normalized_name = '') AND ? <> '' THEN ? ELSE normalized_name END,
                wilaya = CASE WHEN (wilaya IS NULL OR wilaya = '') AND ? <> '' THEN ? ELSE wilaya END,
                commune = CASE WHEN (commune IS NULL OR commune = '') AND ? <> '' THEN ? ELSE commune END,
                address = CASE WHEN (address IS NULL OR address = '') AND ? <> '' THEN ? ELSE address END,
                normalized_address = CASE WHEN (normalized_address IS NULL OR normalized_address = '') AND ? <> '' THEN ? ELSE normalized_address END,
                ip_address = CASE WHEN (ip_address IS NULL OR ip_address = '') AND ? <> '' THEN ? ELSE ip_address END,
                device_id = CASE WHEN (device_id IS NULL OR device_id = '') AND ? <> '' THEN ? ELSE device_id END,
                notes = CASE
                    WHEN ? IS NULL THEN notes
                    WHEN notes IS NULL OR notes = '' THEN ?
                    WHEN notes NOT LIKE CONCAT('%', ?, '%') THEN CONCAT(notes, '\n', ?)
                    ELSE notes
                END,
                updated_at = NOW()
            WHERE id IN ($placeholders)
        ");

        $expanded = [];
        for ($i = 0; $i < 10; $i++) {
            $expanded[] = $params[$i];
            $expanded[] = $params[$i];
        }
        $expanded[] = $params[10];
        $expanded[] = $params[10];
        $expanded[] = $params[10];
        $expanded[] = $params[10];
        $expanded = array_merge($expanded, $ids);
        $statement->execute($expanded);
    }
}

if (!function_exists('site_security_record_order_risk_event')) {
    function site_security_record_order_risk_event(PDO $pdo, array $order, $event_type = 'delivery_return', $remote_status = '', $note = '', $source = 'system')
    {
        site_security_ensure_tables($pdo);
        $order_id = (int)($order['id'] ?? $order['order_id'] ?? 0);
        if ($order_id <= 0) {
            return ['recorded' => false, 'reason' => 'missing_order_id'];
        }

        $event_type = trim((string) $event_type) !== '' ? trim((string) $event_type) : 'delivery_return';
        if ($event_type === 'delivery_return' && !site_security_is_delivery_return_status($remote_status, $note)) {
            return ['recorded' => false, 'reason' => 'not_return_status'];
        }

        $context = site_security_build_order_context(site_security_build_context_from_order($order));
        $has_signal = $context['phone'] !== ''
            || $context['ip_address'] !== ''
            || $context['device_id'] !== ''
            || $context['normalized_address'] !== ''
            || ($context['normalized_name'] !== '' && ($context['wilaya'] !== '' || $context['commune'] !== ''));
        if (!$has_signal) {
            return ['recorded' => false, 'reason' => 'missing_security_signals'];
        }

        $event_insert = $pdo->prepare("
            INSERT IGNORE INTO site_security_events (order_id, event_type, source, remote_status, note, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $event_insert->execute([
            $order_id,
            $event_type,
            substr((string) $source, 0, 50),
            substr((string) $remote_status, 0, 190),
            trim((string) $note) !== '' ? trim((string) $note) : null
        ]);

        if ($event_insert->rowCount() === 0) {
            return ['recorded' => false, 'reason' => 'already_recorded'];
        }

        $rule_note = trim(implode(' | ', array_filter([
            'تلقائي: طرد مرتجع أو مرفوض',
            trim((string) $remote_status) !== '' ? 'حالة التوصيل: ' . trim((string) $remote_status) : '',
            trim((string) $note) !== '' ? 'ملاحظة: ' . trim((string) $note) : ''
        ])));

        $evaluation = site_security_evaluate_order($pdo, $context);
        if (!empty($evaluation['matches'])) {
            site_security_record_rejected_attempt($pdo, $evaluation);
            $ids = [];
            foreach ($evaluation['matches'] as $match) {
                $ids[] = (int)($match['id'] ?? 0);
            }
            site_security_fill_rule_context($pdo, $ids, $context, $rule_note);
            $ids = array_values(array_filter(array_unique($ids)));
            $refreshed_matches = [];
            $current_action = 'allow';
            $current_status = 'warning';
            if ($ids) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $refresh = $pdo->prepare("SELECT id, status, rejected_orders_count FROM site_security_blacklist WHERE id IN ($placeholders)");
                $refresh->execute($ids);
                $refreshed_matches = $refresh->fetchAll(PDO::FETCH_ASSOC);
                foreach ($refreshed_matches as $refreshed_match) {
                    $candidate_status = $refreshed_match['status'] ?? 'review';
                    $candidate_action = site_security_status_action($candidate_status);
                    if (site_security_action_priority($candidate_action) > site_security_action_priority($current_action)) {
                        $current_action = $candidate_action;
                        $current_status = $candidate_status;
                    }
                }
            }
            return [
                'recorded' => true,
                'created' => false,
                'action' => $current_action,
                'status' => $current_status,
                'rule_ids' => $ids,
                'matches' => $refreshed_matches
            ];
        }

        $statement = $pdo->prepare("
            INSERT INTO site_security_blacklist (
                phone, normalized_phone, customer_name, normalized_name, wilaya, commune, address, normalized_address,
                ip_address, device_id, status, notes, rejected_orders_count, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
        ");
        $statement->execute([
            $context['raw_phone'] !== '' ? $context['raw_phone'] : $context['phone'],
            $context['phone'],
            $context['customer_name'],
            $context['normalized_name'],
            $context['wilaya'],
            $context['commune'],
            $context['address'],
            $context['normalized_address'],
            $context['ip_address'],
            $context['device_id'],
            site_security_status_for_rejected_count(1),
            $rule_note
        ]);

        return [
            'recorded' => true,
            'created' => true,
            'action' => 'review',
            'status' => 'warning',
            'rule_ids' => [(int) $pdo->lastInsertId()]
        ];
    }
}

if (!function_exists('site_security_record_delivery_return')) {
    function site_security_record_delivery_return(PDO $pdo, array $order, $remote_status = '', $note = '', $source = 'ecotrack')
    {
        return site_security_record_order_risk_event($pdo, $order, 'delivery_return', $remote_status, $note, $source);
    }
}

if (!function_exists('site_security_try_record_delivery_return')) {
    function site_security_try_record_delivery_return(PDO $pdo, array $order, $remote_status = '', $note = '', $source = 'ecotrack')
    {
        try {
            return site_security_record_delivery_return($pdo, $order, $remote_status, $note, $source);
        } catch (Throwable $e) {
            error_log('site_security_try_record_delivery_return failed: ' . $e->getMessage());
            return ['recorded' => false, 'reason' => 'error', 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('site_security_reject_if_needed')) {
    function site_security_reject_if_needed(PDO $pdo, array $context = [])
    {
        $evaluation = site_security_evaluate_order($pdo, $context);
        if ($evaluation['action'] !== 'allow') {
            site_security_record_rejected_attempt($pdo, $evaluation);
            throw new Exception($evaluation['message']);
        }
        return $evaluation;
    }
}

if (!function_exists('site_security_is_phone_blocked')) {
    function site_security_is_phone_blocked(PDO $pdo, $phone)
    {
        $evaluation = site_security_evaluate_order($pdo, ['phone' => $phone]);
        return $evaluation['action'] !== 'allow';
    }
}
