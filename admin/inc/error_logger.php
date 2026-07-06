<?php

if (!function_exists('error_logger_ensure_tables')) {
    function error_logger_ensure_tables(PDO $pdo): void
    { global $dbRepo;
        static $done = false;
        if ($done) return;

        $lock_file = __DIR__ . '/../cache/error_logger_tables.lock';
        if (file_exists($lock_file)) {
            $done = true;
            return;
        }

        $dbRepo->executeCommand("
            CREATE TABLE IF NOT EXISTS tbl_system_error_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                component VARCHAR(60) NOT NULL DEFAULT '',
                error_message TEXT NULL,
                stack_trace LONGTEXT NULL,
                payload LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sel_component (component),
                KEY idx_sel_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        @file_put_contents($lock_file, '1');
        $done = true;
    }
}

if (!function_exists('error_logger_log')) {
    function error_logger_log(PDO $pdo, string $component, string $error_message, ?string $stack_trace = null, $payload = null): int
    { global $dbRepo;
        error_logger_ensure_tables($pdo);

        $payload_str = null;
        if ($payload !== null) {
            $payload_str = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $dbRepo->prepare("
            INSERT INTO tbl_system_error_log (component, error_message, stack_trace, payload, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            substr($component, 0, 60),
            mb_substr($error_message, 0, 65535),
            $stack_trace ? mb_substr($stack_trace, 0, 65535) : null,
            $payload_str ? mb_substr($payload_str, 0, 65535) : null,
        ]);

        return (int) $dbRepo->lastInsertId();
    }
}

if (!function_exists('error_logger_get_recent')) {
    function error_logger_get_recent(PDO $pdo, int $hours = 24, int $limit = 50): array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("
            SELECT * FROM tbl_system_error_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$hours, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('error_logger_count_recent')) {
    function error_logger_count_recent(PDO $pdo, int $hours = 24): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_system_error_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)");
        $stmt->execute([$hours]);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('error_logger_count_by_component')) {
    function error_logger_count_by_component(PDO $pdo, int $hours = 24): array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("
            SELECT component, COUNT(*) AS cnt
            FROM tbl_system_error_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY component
            ORDER BY cnt DESC
        ");
        $stmt->execute([$hours]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('error_logger_check_health')) {
    function error_logger_check_health(PDO $pdo): array
    { global $dbRepo;
        $checks = [];

        $error_count_1h = error_logger_count_recent($pdo, 1);
        $error_count_24h = error_logger_count_recent($pdo, 24);
        $by_component = error_logger_count_by_component($pdo, 24);

        $status = 'healthy';
        $issues = [];

        if ($error_count_1h > 10) {
            $status = 'critical';
            $issues[] = 'أكثر من 10 أخطاء في الساعة الأخيرة';
        } elseif ($error_count_24h > 50) {
            $status = 'warning';
            $issues[] = 'أكثر من 50 خطأ في 24 ساعة';
        }

        foreach ($by_component as $comp) {
            if ((int) $comp['cnt'] > 20) {
                $issues[] = 'المكون ' . $comp['component'] . ': ' . $comp['cnt'] . ' خطأ';
            }
        }

        $checks['errors_1h'] = $error_count_1h;
        $checks['errors_24h'] = $error_count_24h;
        $checks['by_component'] = $by_component;
        $checks['status'] = $status;
        $checks['issues'] = $issues;

        return $checks;
    }
}

if (!function_exists('error_logger_send_alert')) {
    function error_logger_send_alert(PDO $pdo, int $threshold = 5): void
    { global $dbRepo;
        $recent = error_logger_get_recent($pdo, 1, $threshold);
        if (count($recent) < $threshold) {
            return;
        }

        if (!function_exists('telegram_get_event_setting') || !function_exists('telegram_send_event')) {
            return;
        }

        $chat_id = defined('EVENT_BOT_CHAT_ID') ? trim(EVENT_BOT_CHAT_ID) : '';
        if ($chat_id === '') {
            $chat_id = telegram_get_event_setting($pdo, 'event_bot_chat_id');
        }
        if ($chat_id === '') {
            return;
        }

        $by_component = error_logger_count_by_component($pdo, 1);
        $lines = [];
        $lines[] = '⚠️ *تنبيه النظام - زيادة في الأخطاء*';
        $lines[] = 'آخر ساعة: ' . count($recent) . ' خطأ';
        $lines[] = '';
        foreach ($by_component as $comp) {
            $lines[] = '• ' . $comp['component'] . ': ' . $comp['cnt'];
        }
        $lines[] = '';
        $lines[] = 'آخر الأخطاء:';
        foreach (array_slice($recent, 0, 3) as $err) {
            $msg = mb_substr($err['error_message'] ?? '', 0, 100);
            $lines[] = '• `' . $msg . '`';
        }

        $text = implode("\n", $lines);
        telegram_send_event($text);
    }
}
