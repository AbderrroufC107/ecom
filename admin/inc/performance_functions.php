<?php
if (!defined('PERFORMANCE_FUNCTIONS_LOADED')) {
    define('PERFORMANCE_FUNCTIONS_LOADED', true);

    require_once __DIR__ . '/employee_functions.php';

    if (!function_exists('performance_ensure_tables')) {
        function performance_ensure_tables(PDO $pdo): void
        {
            $lock_file = __DIR__ . '/../cache/performance_tables.lock';
            if (file_exists($lock_file)) {
                return;
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_employee_commission (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    order_id INT NOT NULL,
                    commission_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    commission_type VARCHAR(50) NOT NULL DEFAULT 'percentage',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ec_employee (employee_id),
                    INDEX idx_ec_order (order_id),
                    INDEX idx_ec_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_commission_payment (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    employee_id INT NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    paid_by VARCHAR(255) NOT NULL DEFAULT '',
                    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    notes TEXT DEFAULT NULL,
                    INDEX idx_cp_employee (employee_id),
                    INDEX idx_cp_paid (paid_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_performance_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    config_key VARCHAR(80) NOT NULL UNIQUE,
                    config_value VARCHAR(255) NOT NULL DEFAULT '',
                    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("INSERT IGNORE INTO tbl_performance_settings (config_key, config_value) VALUES
                ('score_completed', '10'),
                ('score_confirmed', '2'),
                ('score_cancelled', '-5'),
                ('score_returned', '-8'),
                ('score_late_processing', '-2'),
                ('late_processing_hours', '24'),
                ('commission_enabled', '0'),
                ('commission_mode', 'percentage'),
                ('commission_fixed_amount', '0'),
                ('commission_percentage', '5'),
                ('commission_min_order_amount', '0'),
                ('ranking_period', 'all'),
                ('last_ranking_report', '')
            ");

            @file_put_contents($lock_file, '1');
        }
    }

    if (!function_exists('performance_get_setting')) {
        function performance_get_setting(PDO $pdo, string $key, string $default = ''): string
        {
            static $cache = [];
            if (!isset($cache[$key])) {
                $stmt = $pdo->prepare("SELECT config_value FROM tbl_performance_settings WHERE config_key = ? LIMIT 1");
                $stmt->execute([$key]);
                $val = $stmt->fetchColumn();
                $cache[$key] = $val !== false ? (string) $val : $default;
            }
            return $cache[$key];
        }
    }

    if (!function_exists('performance_set_setting')) {
        function performance_set_setting(PDO $pdo, string $key, string $value): void
        {
            $stmt = $pdo->prepare("INSERT INTO tbl_performance_settings (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
            $stmt->execute([$key, $value]);
        }
    }

    if (!function_exists('performance_get_setting_int')) {
        function performance_get_setting_int(PDO $pdo, string $key, int $default = 0): int
        {
            return (int) performance_get_setting($pdo, $key, (string) $default);
        }
    }

    if (!function_exists('performance_get_setting_bool')) {
        function performance_get_setting_bool(PDO $pdo, string $key): bool
        {
            return performance_get_setting($pdo, $key, '0') === '1';
        }
    }

    if (!function_exists('performance_calculate_score')) {
        function performance_calculate_score(PDO $pdo, int $employee_id): int
        {
            $score_completed = performance_get_setting_int($pdo, 'score_completed', 10);
            $score_confirmed = performance_get_setting_int($pdo, 'score_confirmed', 2);
            $score_cancelled = performance_get_setting_int($pdo, 'score_cancelled', -5);
            $score_returned = performance_get_setting_int($pdo, 'score_returned', -8);
            $score_late = performance_get_setting_int($pdo, 'score_late_processing', -2);
            $late_hours = performance_get_setting_int($pdo, 'late_processing_hours', 24);

            $stmt = $pdo->prepare("
                SELECT
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Completed' THEN oa.order_id END) AS completed_count,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Confirmed' THEN oa.order_id END) AS confirmed_count,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Cancelled' THEN oa.order_id END) AS cancelled_count,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Returned' THEN oa.order_id END) AS returned_count,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Pending' AND o.order_date <= DATE_SUB(NOW(), INTERVAL ? HOUR) THEN oa.order_id END) AS late_count
                FROM tbl_order_assignment oa
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE oa.employee_id = ? AND oa.status = 'active'
            ");
            $stmt->execute([$late_hours, $employee_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $score = 0;
            $score += (int) ($row['completed_count'] ?? 0) * $score_completed;
            $score += (int) ($row['confirmed_count'] ?? 0) * $score_confirmed;
            $score += (int) ($row['cancelled_count'] ?? 0) * $score_cancelled;
            $score += (int) ($row['returned_count'] ?? 0) * $score_returned;
            $score += (int) ($row['late_count'] ?? 0) * $score_late;

            return $score;
        }
    }

    if (!function_exists('performance_get_kpis')) {
        function performance_get_kpis(PDO $pdo, int $employee_id): array
        {
            $rkpi = [
                'total_assigned' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'returned' => 0,
                'avg_processing_hours' => 0,
                'delivery_success_rate' => 0,
                'cancellation_rate' => 0,
                'return_rate' => 0,
                'score' => 0,
            ];

            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_assigned,
                    SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned_count
                FROM tbl_order_assignment oa
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE oa.employee_id = ? AND oa.status = 'active'
            ");
            $stmt->execute([$employee_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return $rkpi;
            }

            $total = (int) ($row['total_assigned'] ?? 0);
            $completed = (int) ($row['completed_count'] ?? 0);
            $cancelled = (int) ($row['cancelled_count'] ?? 0);
            $returned = (int) ($row['returned_count'] ?? 0);
            $confirmed = (int) ($row['confirmed_count'] ?? 0);
            $pending = (int) ($row['pending_count'] ?? 0);

            $rkpi['total_assigned'] = $total;
            $rkpi['pending'] = $pending;
            $rkpi['confirmed'] = $confirmed;
            $rkpi['completed'] = $completed;
            $rkpi['cancelled'] = $cancelled;
            $rkpi['returned'] = $returned;

            $processed = $completed + $cancelled + $returned;
            if ($processed > 0) {
                $stmt2 = $pdo->prepare("
                    SELECT COALESCE(AVG(TIMESTAMPDIFF(HOUR, o.order_date, NOW())), 0) AS avg_hours
                    FROM tbl_order_assignment oa
                    INNER JOIN tbl_order o ON o.id = oa.order_id
                    WHERE oa.employee_id = ? AND oa.status = 'active'
                    AND o.order_status IN ('Completed', 'Cancelled', 'Returned')
                ");
                $stmt2->execute([$employee_id]);
                $avg_row = $stmt2->fetch(PDO::FETCH_ASSOC);
                $rkpi['avg_processing_hours'] = round((float) ($avg_row['avg_hours'] ?? 0), 1);
            }

            $total_deliverable = $completed + $cancelled + $returned;
            if ($total_deliverable > 0) {
                $rkpi['delivery_success_rate'] = round(($completed / $total_deliverable) * 100, 1);
                $rkpi['cancellation_rate'] = round(($cancelled / $total_deliverable) * 100, 1);
                $rkpi['return_rate'] = round(($returned / $total_deliverable) * 100, 1);
            }

            $rkpi['score'] = performance_calculate_score($pdo, $employee_id);

            return $rkpi;
        }
    }

    if (!function_exists('performance_get_ranking')) {
        function performance_get_ranking(PDO $pdo, ?int $limit = null, string $period = 'all'): array
        {
            $score_completed = performance_get_setting_int($pdo, 'score_completed', 10);
            $score_confirmed = performance_get_setting_int($pdo, 'score_confirmed', 2);
            $score_cancelled = performance_get_setting_int($pdo, 'score_cancelled', -5);
            $score_returned = performance_get_setting_int($pdo, 'score_returned', -8);
            $score_late = performance_get_setting_int($pdo, 'score_late_processing', -2);
            $late_hours = performance_get_setting_int($pdo, 'late_processing_hours', 24);

            $stmt = $pdo->query("
                SELECT
                    e.id, e.full_name, e.email, e.telegram_chat_id,
                    COUNT(oa.id) AS total_assigned,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END), 0) AS confirmed_count,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed_count,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END), 0) AS returned_count,
                    COALESCE(SUM(CASE WHEN o.order_status IN ('Completed', 'Cancelled', 'Returned') THEN 1 ELSE 0 END), 0) AS processed_count,
                    COALESCE(AVG(CASE WHEN o.order_status IN ('Completed', 'Cancelled', 'Returned') THEN TIMESTAMPDIFF(HOUR, o.order_date, NOW()) END), 0) AS avg_processing_hours
                FROM tbl_employee e
                LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
                LEFT JOIN tbl_order o ON o.id = oa.order_id
                WHERE e.is_active = 1
                GROUP BY e.id, e.full_name, e.email, e.telegram_chat_id
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $ranking = [];

            foreach ($rows as $row) {
                $completed = (int) ($row['completed_count'] ?? 0);
                $confirmed = (int) ($row['confirmed_count'] ?? 0);
                $cancelled = (int) ($row['cancelled_count'] ?? 0);
                $returned = (int) ($row['returned_count'] ?? 0);
                $processed = $completed + $cancelled + $returned;

                $score = 0;
                $score += $completed * $score_completed;
                $score += $confirmed * $score_confirmed;
                $score += $cancelled * $score_cancelled;
                $score += $returned * $score_returned;
                $score += (int) ($row['pending_count'] ?? 0) * $score_late;

                $delivery_rate = $processed > 0 ? round(($completed / $processed) * 100, 1) : 0;
                $cancellation_rate = $processed > 0 ? round(($cancelled / $processed) * 100, 1) : 0;
                $return_rate = $processed > 0 ? round(($returned / $processed) * 100, 1) : 0;

                $ranking[] = [
                    'id' => (int) $row['id'],
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'telegram_chat_id' => $row['telegram_chat_id'],
                    'score' => $score,
                    'total_assigned' => (int) ($row['total_assigned'] ?? 0),
                    'completed' => $completed,
                    'confirmed' => $confirmed,
                    'cancelled' => $cancelled,
                    'returned' => $returned,
                    'delivery_success_rate' => $delivery_rate,
                    'cancellation_rate' => $cancellation_rate,
                    'return_rate' => $return_rate,
                    'avg_processing_hours' => round((float) ($row['avg_processing_hours'] ?? 0), 1),
                ];
            }

            usort($ranking, fn($a, $b) => $b['score'] <=> $a['score']);

            if ($limit !== null && $limit > 0) {
                $ranking = array_slice($ranking, 0, $limit);
            }

            return $ranking;
        }
    }

    if (!function_exists('performance_get_monthly_stats')) {
        function performance_get_monthly_stats(PDO $pdo, int $employee_id, int $months = 6): array
        {
            $stats = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-{$i} months"));
                $stats[$month] = [
                    'month' => $month,
                    'assigned' => 0,
                    'completed' => 0,
                    'confirmed' => 0,
                    'cancelled' => 0,
                    'returned' => 0,
                    'revenue' => 0,
                ];
            }

            $stmt = $pdo->prepare("
                SELECT
                    DATE_FORMAT(oa.assigned_at, '%Y-%m') AS month,
                    COUNT(*) AS assigned,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END), 0) AS revenue
                FROM tbl_order_assignment oa
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE oa.employee_id = ? AND oa.status = 'active'
                AND oa.assigned_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(oa.assigned_at, '%Y-%m')
                ORDER BY month ASC
            ");
            $stmt->execute([$employee_id, $months]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $m = $row['month'];
                if (isset($stats[$m])) {
                    $stats[$m] = [
                        'month' => $m,
                        'assigned' => (int) ($row['assigned'] ?? 0),
                        'completed' => (int) ($row['completed'] ?? 0),
                        'confirmed' => (int) ($row['confirmed'] ?? 0),
                        'cancelled' => (int) ($row['cancelled'] ?? 0),
                        'returned' => (int) ($row['returned'] ?? 0),
                        'revenue' => (float) ($row['revenue'] ?? 0),
                    ];
                }
            }

            return array_values($stats);
        }
    }

    if (!function_exists('performance_calculate_commission')) {
        function performance_calculate_commission(PDO $pdo, int $employee_id, int $order_id, float $order_total): array
        {
            $enabled = performance_get_setting_bool($pdo, 'commission_enabled');
            if (!$enabled) {
                return ['amount' => 0, 'type' => 'disabled'];
            }

            $mode = performance_get_setting($pdo, 'commission_mode', 'percentage');
            $fixed = (float) performance_get_setting($pdo, 'commission_fixed_amount', '0');
            $pct = (float) performance_get_setting($pdo, 'commission_percentage', '5');
            $min_order = (float) performance_get_setting($pdo, 'commission_min_order_amount', '0');

            if ($order_total < $min_order) {
                return ['amount' => 0, 'type' => 'below_minimum'];
            }

            $amount = 0.0;
            $type = $mode;

            switch ($mode) {
                case 'fixed':
                    $amount = $fixed;
                    break;
                case 'percentage':
                    $amount = round($order_total * $pct / 100, 2);
                    break;
                case 'hybrid':
                    $amount = $fixed + round($order_total * $pct / 100, 2);
                    break;
            }

            return ['amount' => $amount, 'type' => $mode];
        }
    }

    if (!function_exists('performance_record_commission')) {
        function performance_record_commission(PDO $pdo, int $employee_id, int $order_id, float $amount, string $type): void
        {
            if ($amount <= 0) return;
            $stmt = $pdo->prepare("
                INSERT INTO tbl_employee_commission (employee_id, order_id, commission_amount, commission_type)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$employee_id, $order_id, $amount, $type]);
            $commission_id = (int) $pdo->lastInsertId();
            if ($commission_id > 0 && function_exists('audit_log_commission')) {
                audit_log_commission($pdo, $commission_id, 'commission_created', null, json_encode(['employee_id' => $employee_id, 'order_id' => $order_id, 'amount' => $amount, 'type' => $type], JSON_UNESCAPED_UNICODE), 'system');
            }
        }
    }

    if (!function_exists('performance_get_commission_summary')) {
        function performance_get_commission_summary(PDO $pdo, ?int $employee_id = null): array
        {
            $where = '';
            $params = [];
            if ($employee_id !== null) {
                $where = 'WHERE employee_id = ?';
                $params[] = $employee_id;
            }

            $today = date('Y-m-d');
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $month_start = date('Y-m-01');

            $sql = "SELECT
                COALESCE(SUM(CASE WHEN DATE(created_at) = ? THEN commission_amount ELSE 0 END), 0) AS today,
                COALESCE(SUM(CASE WHEN DATE(created_at) >= ? THEN commission_amount ELSE 0 END), 0) AS this_week,
                COALESCE(SUM(CASE WHEN DATE(created_at) >= ? THEN commission_amount ELSE 0 END), 0) AS this_month,
                COALESCE(SUM(commission_amount), 0) AS total_unpaid
            FROM tbl_employee_commission ec
            LEFT JOIN tbl_commission_payment cp ON cp.employee_id = ec.employee_id AND cp.paid_at >= ec.created_at
            {$where}
            AND cp.id IS NULL";
            array_unshift($params, $today, $week_start, $month_start);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_paid FROM tbl_commission_payment {$where}");
            $stmt2->execute($employee_id !== null ? [$employee_id] : []);
            $paid_row = $stmt2->fetch(PDO::FETCH_ASSOC);

            return [
                'today' => (float) ($row['today'] ?? 0),
                'this_week' => (float) ($row['this_week'] ?? 0),
                'this_month' => (float) ($row['this_month'] ?? 0),
                'total_unpaid' => (float) ($row['total_unpaid'] ?? 0),
                'total_paid' => (float) ($paid_row['total_paid'] ?? 0),
            ];
        }
    }

    if (!function_exists('performance_get_dashboard_widgets')) {
        function performance_get_dashboard_widgets(PDO $pdo): array
        {
            $ranking = performance_get_ranking($pdo, null);

            $top = !empty($ranking) ? $ranking[0] : null;
            $worst = !empty($ranking) ? $ranking[count($ranking) - 1] : null;

            $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_order WHERE order_status = 'Pending'");
            $pending_orders = (int) $stmt->fetchColumn();

            $stmt = $pdo->query("
                SELECT 
                    COALESCE(SUM(CASE WHEN order_status IN ('Completed','Cancelled','Returned') THEN 1 ELSE 0 END), 0) AS processed,
                    COALESCE(SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END), 0) AS completed,
                    COALESCE(SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled
                FROM tbl_order
            ");
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);
            $processed = (int) ($overall['processed'] ?? 0);
            $delivery_rate = $processed > 0 ? round(((int) ($overall['completed'] ?? 0) / $processed) * 100, 1) : 0;
            $cancellation_rate = $processed > 0 ? round(((int) ($overall['cancelled'] ?? 0) / $processed) * 100, 1) : 0;

            $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM tbl_order WHERE order_status = 'Completed' AND DATE(order_date) >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)");
            $monthly_revenue = (float) $stmt->fetchColumn();

            return [
                'top_employee' => $top,
                'worst_employee' => $worst,
                'pending_orders' => $pending_orders,
                'delivery_rate' => $delivery_rate,
                'cancellation_rate' => $cancellation_rate,
                'monthly_revenue' => $monthly_revenue,
            ];
        }
    }

    if (!function_exists('telegram_build_ranking_report')) {
        function telegram_build_ranking_report(PDO $pdo): string
        {
            $ranking = performance_get_ranking($pdo, 10);
            $period = performance_get_setting($pdo, 'ranking_period', 'all');
            $period_label = $period === 'today' ? "\xD8\xA7\xD9\x84\xD9\x8A\xD9\x88\xD9\x85" : ($period === 'week' ? "\xD9\x87\xD8\xB0\xD8\xA7 \xD8\xA7\xD9\x84\xD8\xA3\xD8\xB3\xD8\xA8\xD9\x88\xD8\xB9" : "\xD8\xA7\xD9\x84\xD9\x83\xD9\x84");

            $emoji_trophy = "\xF0\x9F\x8F\x86";
            $emoji_medal = "\xF0\x9F\xA5\x88";
            $emoji_third = "\xF0\x9F\xA5\x89";

            $text = "\xF0\x9F\x93\x8A \xD8\xAA\xD9\x82\xD8\xB1\xD9\x8A\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xA3\xD8\xAF\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD9\x8A\xD9\x88\xD9\x85\xD9\x8A\n\n";
            $text .= "\xD9\x81\xD8\xAA\xD8\xB1\xD8\xA9 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x82\xD8\xB1\xD9\x8A\xD8\xB1: {$period_label}\n";
            $text .= "\xD8\xAA\xD8\xA7\xD8\xB1\xD9\x8A\xD8\xAE: " . date('d/m/Y') . "\n\n";

            foreach ($ranking as $i => $emp) {
                $pos = $i + 1;
                $icon = $pos === 1 ? $emoji_trophy : ($pos === 2 ? $emoji_medal : ($pos === 3 ? $emoji_third : "{$pos}."));
                $name = htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8');
                $score = $emp['score'];
                $rate = $emp['delivery_success_rate'];
                $cancelled = $emp['cancelled'];
                $completed = $emp['completed'];

                $text .= "{$icon} {$name} — {$score} \xD9\x86\xD9\x82\xD8\xB7\xD8\xA9\n";
                $text .= "   \xE2\x9C\x85 \xD8\xAA\xD8\xB3\xD9\x84\xD9\x8A\xD9\x85: {$completed} | \xE2\x9D\x8C \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1: {$cancelled} | \xF0\x9F\x93\x8A {$rate}%\n\n";
            }

            $text .= "\n\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\n";
            $text .= "\xF0\x9F\xA4\x96 \xD9\x86\xD8\xB8\xD8\xA7\xD9\x85 \xD8\xA7\xD9\x84\xD8\xAA\xD9\x82\xD9\x8A\xD9\x8A\xD9\x85 \xD8\xA7\xD9\x84\xD8\xA2\xD9\x84\xD9\x8A";

            return $text;
        }
    }

    if (!function_exists('performance_send_nightly_ranking')) {
        function performance_send_nightly_ranking(PDO $pdo): array
        {
            if (!function_exists('telegram_send_message')) {
                require_once __DIR__ . '/telegram_bot.php';
            }

            $chat_id = defined('EVENT_BOT_CHAT_ID') ? trim(EVENT_BOT_CHAT_ID) : '';
            if ($chat_id === '' && function_exists('telegram_get_event_setting')) {
                $chat_id = telegram_get_event_setting($pdo, 'event_bot_chat_id');
            }
            if ($chat_id === '') {
                return ['success' => false, 'error' => 'EVENT_BOT_CHAT_ID not configured'];
            }

            $text = telegram_build_ranking_report($pdo);
            $result = telegram_send_message($chat_id, $text);

            $reported_at = date('Y-m-d H:i:s');
            performance_set_setting($pdo, 'last_ranking_report', $reported_at);

            return [
                'success' => $result['success'] ?? false,
                'reported_at' => $reported_at,
                'error' => $result['error'] ?? null,
            ];
        }
    }

    if (!function_exists('performance_auto_record_commission')) {
        function performance_auto_record_commission(PDO $pdo, int $order_id): void
        {
            if (!performance_get_setting_bool($pdo, 'commission_enabled')) return;

            $stmt = $pdo->prepare("SELECT total_price FROM tbl_order WHERE id = ? AND order_status = 'Completed' LIMIT 1");
            $stmt->execute([$order_id]);
            $total = (float) ($stmt->fetchColumn() ?: 0);
            if ($total <= 0) return;

            $stmt = $pdo->prepare("SELECT employee_id FROM tbl_order_assignment WHERE order_id = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$order_id]);
            $employee_id = (int) ($stmt->fetchColumn() ?: 0);
            if ($employee_id <= 0) return;

            $check = $pdo->prepare("SELECT id FROM tbl_employee_commission WHERE order_id = ? AND employee_id = ? LIMIT 1");
            $check->execute([$order_id, $employee_id]);
            if ($check->fetch()) return;

            $commission = performance_calculate_commission($pdo, $employee_id, $order_id, $total);
            if ($commission['amount'] > 0) {
                performance_record_commission($pdo, $employee_id, $order_id, $commission['amount'], $commission['type']);
            }
        }
    }
}
