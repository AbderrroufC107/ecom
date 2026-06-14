<?php
/**
 * Event Monitor Script
 *
 * Run every 1 minute via cron:
 *   * * * * * php /path/to/admin/scripts/event-monitor.php
 *
 * Or via web request (with secret key):
 *   https://site.com/admin/scripts/event-monitor.php?key=YOUR_SECRET
 *
 * Or via existing polling system (ajax).
 */

$is_web = (PHP_SAPI !== 'cli');
if ($is_web) {
    $secret = defined('EVENT_MONITOR_SECRET') ? EVENT_MONITOR_SECRET : '';
    $key = trim((string) ($_GET['key'] ?? ''));
    if ($secret !== '' && $key !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../inc/config.php';

require_once __DIR__ . '/../inc/employee_functions.php';
require_once __DIR__ . '/../inc/telegram_bot.php';
require_once __DIR__ . '/../inc/audit.php';
require_once __DIR__ . '/../inc/error_logger.php';

audit_ensure_tables($pdo);
error_logger_ensure_tables($pdo);

telegram_ensure_tables($pdo);

if (defined('EVENT_BOT_ENABLED') && EVENT_BOT_ENABLED !== '1') {
    $enabled = telegram_event_setting_bool($pdo, 'event_bot_enabled');
    if (!$enabled) {
        if ($is_web) { echo json_encode(['ok' => false, 'error' => 'Event bot disabled']); exit; }
        exit;
    }
}

$chat_id = defined('EVENT_BOT_CHAT_ID') ? trim(EVENT_BOT_CHAT_ID) : '';
if ($chat_id === '') {
    $chat_id = telegram_get_event_setting($pdo, 'event_bot_chat_id');
}
if ($chat_id === '') {
    if ($is_web) { echo json_encode(['ok' => false, 'error' => 'EVENT_BOT_CHAT_ID not configured']); exit; }
    exit;
}

$log = [];

$last_run = telegram_get_event_setting($pdo, 'last_event_monitor_run');

$stmt = $pdo->prepare("UPDATE tbl_event_settings SET config_value = NOW() WHERE config_key = 'last_event_monitor_run'");
$stmt->execute();
if ($stmt->rowCount() === 0) {
    $pdo->prepare("INSERT IGNORE INTO tbl_event_settings (config_key, config_value) VALUES ('last_event_monitor_run', NOW())")->execute();
}

$unprocessed_minutes = telegram_event_setting_int($pdo, 'event_unprocessed_order_minutes', 15);
$inactivity_minutes = telegram_event_setting_int($pdo, 'event_employee_inactivity_minutes', 60);
$cancellation_threshold = telegram_event_setting_int($pdo, 'event_cancellation_threshold', 40);
$cancellation_hours = telegram_event_setting_int($pdo, 'event_cancellation_hours', 1);
$failed_attempts = telegram_event_setting_int($pdo, 'event_failed_telegram_attempts', 3);

$reported = [];

if (telegram_event_setting_bool($pdo, 'event_unprocessed_order_enabled')) {
    $stmt = $pdo->prepare("
        SELECT o.*, e.full_name AS emp_name, e.id AS emp_id
        FROM tbl_order o
        INNER JOIN tbl_order_assignment oa ON oa.order_id = o.id AND oa.status = 'active'
        INNER JOIN tbl_employee e ON e.id = oa.employee_id AND e.is_active = 1
        WHERE o.order_status = 'Pending'
        AND o.order_date <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY o.order_date ASC
        LIMIT 20
    ");
    $stmt->execute([$unprocessed_minutes]);
    $unprocessed = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($unprocessed as $order) {
        $oid = (int) $order['id'];
        if (telegram_was_event_recently_sent($pdo, 'unprocessed_order', $oid, 0, $unprocessed_minutes)) {
            continue;
        }
        $emp = ['id' => $order['emp_id'], 'full_name' => $order['emp_name']];
        $text = telegram_build_event_unprocessed($order, $emp, $unprocessed_minutes);
        $buttons = telegram_build_event_buttons($oid);
        $result = telegram_send_event($text, $buttons);
        telegram_log_event($pdo, 'unprocessed_order', $oid, (int) $order['emp_id'], [
            'minutes' => $unprocessed_minutes,
            'sent' => $result['success'] ?? false,
        ]);
        $log[] = 'unprocessed_order:' . $oid;
    }
}

if (telegram_event_setting_bool($pdo, 'event_unassigned_orders_enabled')) {
    $stmt = $pdo->query("
        SELECT o.* FROM tbl_order o
        LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id
        WHERE oa.id IS NULL
        ORDER BY o.id ASC
        LIMIT 10
    ");
    $unassigned = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($unassigned as $order) {
        $oid = (int) $order['id'];
        if (telegram_was_event_recently_sent($pdo, 'unassigned_orders', $oid, 0, 30)) {
            continue;
        }
        $text = telegram_build_event_unassigned($order);
        $buttons = telegram_build_event_buttons($oid);
        $result = telegram_send_event($text, $buttons);
        telegram_log_event($pdo, 'unassigned_orders', $oid, 0, [
            'sent' => $result['success'] ?? false,
        ]);
        $log[] = 'unassigned_orders:' . $oid;
    }
}

if (telegram_event_setting_bool($pdo, 'event_employee_inactivity_enabled')) {
    $stmt = $pdo->query("
        SELECT e.id, e.full_name, e.telegram_chat_id,
            COUNT(DISTINCT o.id) AS pending_count,
            MAX(a.created_at) AS last_action_at
        FROM tbl_employee e
        INNER JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
        INNER JOIN tbl_order o ON o.id = oa.order_id AND o.order_status = 'Pending'
        LEFT JOIN tbl_telegram_action_log a ON a.employee_id = e.id
        WHERE e.is_active = 1
        GROUP BY e.id
        HAVING pending_count > 0
            AND (last_action_at IS NULL OR last_action_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE))
        ORDER BY pending_count DESC
        LIMIT 10
    ");
    $stmt->execute([$inactivity_minutes]);
    $inactive_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($inactive_employees as $ie) {
        $eid = (int) $ie['id'];
        if (telegram_was_event_recently_sent($pdo, 'employee_inactivity', 0, $eid, $inactivity_minutes)) {
            continue;
        }
        $emp = ['id' => $eid, 'full_name' => $ie['full_name']];
        $text = telegram_build_event_inactivity($emp, (int) $ie['pending_count']);
        $result = telegram_send_event($text);
        telegram_log_event($pdo, 'employee_inactivity', 0, $eid, [
            'pending_count' => (int) $ie['pending_count'],
            'sent' => $result['success'] ?? false,
        ]);
        $log[] = 'employee_inactivity:' . $eid;
    }
}

if (telegram_event_setting_bool($pdo, 'event_ecotrack_status_enabled')) {
    $stmt = $pdo->prepare("
        SELECT id, ecotrack_status, product_name, customer_name, customer_phone, total_price, order_date
        FROM tbl_order
        WHERE ecotrack_status IS NOT NULL AND ecotrack_status != ''
        ORDER BY id DESC
        LIMIT 100
    ");
    $stmt->execute();
    $ecotrack_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($ecotrack_orders as $eo) {
        $oid = (int) $eo['id'];
        $current_status = trim((string) ($eo['ecotrack_status'] ?? ''));

        $snap = $pdo->prepare("
            SELECT id, payload FROM tbl_event_log
            WHERE event_type = 'ecotrack_status_snapshot' AND order_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $snap->execute([$oid]);
        $snap_row = $snap->fetch(PDO::FETCH_ASSOC);

        $last_status = '';
        if ($snap_row) {
            $payload = json_decode($snap_row['payload'], true);
            $last_status = (string) ($payload['status'] ?? '');
        }

        if ($last_status === $current_status) {
            continue;
        }

        if ($last_status !== '') {
            $interesting = [
                'En pr?paration', 'Exp?di?', 'En livraison', 'Livr?', 'Retourn?',
            ];
            $changed = false;
            foreach ($interesting as $s) {
                if ($current_status === $s && $last_status !== $s) {
                    $changed = true;
                    break;
                }
            }

            if ($changed) {
                if (!telegram_was_event_recently_sent($pdo, 'ecotrack_status_changed', $oid, 0, 30)) {
                    $assignment = $pdo->prepare("
                        SELECT e.id, e.full_name FROM tbl_order_assignment oa
                        INNER JOIN tbl_employee e ON e.id = oa.employee_id
                        WHERE oa.order_id = ? AND oa.status = 'active'
                        LIMIT 1
                    ");
                    $assignment->execute([$oid]);
                    $emp = $assignment->fetch(PDO::FETCH_ASSOC) ?: null;

                    $text = telegram_build_event_ecotrack($eo, $last_status, $current_status, $emp);
                    $buttons = telegram_build_event_buttons($oid);
                    $result = telegram_send_event($text, $buttons);
                    telegram_log_event($pdo, 'ecotrack_status_changed', $oid, $emp ? (int) $emp['id'] : 0, [
                        'from' => $last_status,
                        'to' => $current_status,
                        'sent' => $result['success'] ?? false,
                    ]);
                    $log[] = 'ecotrack_status_changed:' . $oid;
                }
            }

            if ($current_status === 'Livr?' && $last_status !== 'Livr?') {
                if (!telegram_was_event_recently_sent($pdo, 'delivered', $oid, 0, 60)) {
                    $assignment = $pdo->prepare("
                        SELECT e.id, e.full_name FROM tbl_order_assignment oa
                        INNER JOIN tbl_employee e ON e.id = oa.employee_id
                        WHERE oa.order_id = ? AND oa.status = 'active'
                        LIMIT 1
                    ");
                    $assignment->execute([$oid]);
                    $emp = $assignment->fetch(PDO::FETCH_ASSOC) ?: null;

                    $stmt2 = $pdo->prepare("UPDATE tbl_order SET order_status = 'Completed' WHERE id = ? AND order_status != 'Completed' AND order_status != 'Cancelled' AND order_status != 'Returned'");
                    $stmt2->execute([$oid]);
                    performance_auto_record_commission($pdo, $oid);

                    $text = telegram_build_event_delivered($eo, $emp);
                    $buttons = telegram_build_event_buttons($oid);
                    $result = telegram_send_event($text, $buttons);
                    telegram_log_event($pdo, 'delivered', $oid, $emp ? (int) $emp['id'] : 0, [
                        'ecotrack_status' => $current_status,
                        'sent' => $result['success'] ?? false,
                    ]);
                    $log[] = 'delivered:' . $oid;
                }
            }

            if ($current_status === 'Retourn?' && $last_status !== 'Retourn?') {
                if (!telegram_was_event_recently_sent($pdo, 'returned', $oid, 0, 60)) {
                    $assignment = $pdo->prepare("
                        SELECT e.id, e.full_name FROM tbl_order_assignment oa
                        INNER JOIN tbl_employee e ON e.id = oa.employee_id
                        WHERE oa.order_id = ? AND oa.status = 'active'
                        LIMIT 1
                    ");
                    $assignment->execute([$oid]);
                    $emp = $assignment->fetch(PDO::FETCH_ASSOC) ?: null;

                    $reason = '';
                    $stmt2 = $pdo->prepare("SELECT reason FROM tbl_order_cancellation_reason WHERE order_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt2->execute([$oid]);
                    $rr = $stmt2->fetchColumn();
                    if ($rr) $reason = (string) $rr;

                    $stmt3 = $pdo->prepare("UPDATE tbl_order SET order_status = 'Returned' WHERE id = ? AND order_status != 'Returned' AND order_status != 'Cancelled'");
                    $stmt3->execute([$oid]);

                    $text = telegram_build_event_returned($eo, $reason, $emp);
                    $buttons = telegram_build_event_buttons($oid);
                    $result = telegram_send_event($text, $buttons);
                    telegram_log_event($pdo, 'returned', $oid, $emp ? (int) $emp['id'] : 0, [
                        'ecotrack_status' => $current_status,
                        'reason' => $reason,
                        'sent' => $result['success'] ?? false,
                    ]);
                    $log[] = 'returned:' . $oid;
                }
            }
        }

        $save = $pdo->prepare("INSERT INTO tbl_event_log (event_type, order_id, payload) VALUES ('ecotrack_status_snapshot', ?, ?)");
        $save->execute([$oid, json_encode(['status' => $current_status], JSON_UNESCAPED_UNICODE)]);
    }
}

if (telegram_event_setting_bool($pdo, 'event_high_cancellation_enabled')) {
    $stmt = $pdo->query("
        SELECT
            e.id, e.full_name,
            COUNT(DISTINCT oa.order_id) AS total_assigned,
            COUNT(DISTINCT CASE WHEN o.order_status = 'Cancelled' THEN oa.order_id END) AS total_cancelled
        FROM tbl_employee e
        INNER JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.assigned_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        INNER JOIN tbl_order o ON o.id = oa.order_id
        WHERE e.is_active = 1
        GROUP BY e.id
        HAVING total_assigned >= 3 AND (total_cancelled * 100.0 / total_assigned) >= ?
    ");
    $stmt->execute([$cancellation_threshold]);
    $high_cancel = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($high_cancel as $hc) {
        $eid = (int) $hc['id'];
        if (telegram_was_event_recently_sent($pdo, 'high_cancellation', 0, $eid, 60)) {
            continue;
        }
        $total = (int) $hc['total_assigned'];
        $cancelled = (int) $hc['total_cancelled'];
        $pct = $total > 0 ? round(($cancelled * 100.0) / $total) : 0;
        $emp = ['id' => $eid, 'full_name' => $hc['full_name']];
        $text = telegram_build_event_high_cancellation($emp, $cancelled, $total, $pct);
        $result = telegram_send_event($text);
        telegram_log_event($pdo, 'high_cancellation', 0, $eid, [
            'total' => $total,
            'cancelled' => $cancelled,
            'percentage' => $pct,
            'sent' => $result['success'] ?? false,
        ]);
        $log[] = 'high_cancellation:' . $eid;
    }
}

if (telegram_event_setting_bool($pdo, 'event_failed_telegram_enabled')) {
    $stmt = $pdo->prepare("
        SELECT
            d.employee_id, e.full_name, COUNT(*) AS failed_count
        FROM tbl_telegram_delivery_log d
        INNER JOIN tbl_employee e ON e.id = d.employee_id
        WHERE d.delivery_status = 'failed'
        AND d.created_at >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)
        AND d.employee_id > 0
        GROUP BY d.employee_id
        HAVING failed_count >= ?
        LIMIT 10
    ");
    $stmt->execute([$failed_attempts]);
    $failed_deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($failed_deliveries as $fd) {
        $eid = (int) $fd['employee_id'];
        if (telegram_was_event_recently_sent($pdo, 'failed_telegram_delivery', 0, $eid, 60)) {
            continue;
        }
        $emp = ['id' => $eid, 'full_name' => $fd['full_name']];
        $text = telegram_build_event_failed_telegram($emp, (int) $fd['failed_count']);
        $result = telegram_send_event($text);
        telegram_log_event($pdo, 'failed_telegram_delivery', 0, $eid, [
            'failed_count' => (int) $fd['failed_count'],
            'sent' => $result['success'] ?? false,
        ]);
        $log[] = 'failed_telegram_delivery:' . $eid;
    }
}

$nightly_report_enabled = telegram_event_setting_bool($pdo, 'event_bot_enabled');
if ($nightly_report_enabled) {
    require_once __DIR__ . '/../inc/performance_functions.php';
    performance_ensure_tables($pdo);

    $last_report = performance_get_setting($pdo, 'last_ranking_report', '');
    $send = false;
    if ($last_report === '') {
        $send = true;
    } else {
        $last_ts = strtotime($last_report);
        $now_ts = time();
        $today_date = date('Y-m-d');
        $report_date = date('Y-m-d', $last_ts);
        if ($report_date !== $today_date) {
            $hour = (int) date('H');
            if ($hour >= 23) {
                $send = true;
            }
        }
    }

    if ($send) {
        $result = performance_send_nightly_ranking($pdo);
        $log[] = 'nightly_ranking:' . ($result['success'] ? 'sent' : 'failed');
    }
}

$ai_report_enabled = telegram_event_setting_bool($pdo, 'event_bot_enabled');
if ($ai_report_enabled) {
    require_once __DIR__ . '/../inc/ai_functions.php';
    ai_ensure_tables($pdo);

    $last_ai_report = performance_get_setting($pdo, 'last_ai_morning_report', '');
    $send_ai = false;
    if ($last_ai_report === '') {
        $send_ai = true;
    } else {
        $last_ai_ts = strtotime($last_ai_report);
        $now_ts = time();
        $today_date = date('Y-m-d');
        $ai_report_date = date('Y-m-d', $last_ai_ts);
        if ($ai_report_date !== $today_date) {
            $hour = (int) date('H');
            if ($hour >= 8 && $hour <= 9) {
                $send_ai = true;
            }
        }
    }

    if ($send_ai) {
        ai_analyze_cancellations($pdo);
        ai_analyze_product_risk($pdo);
        ai_analyze_employee_performance($pdo);
        ai_analyze_wilayas($pdo);
        ai_analyze_offers($pdo);
        ai_analyze_response_time($pdo);
        ai_forecast_revenue($pdo);
        ai_predict_returns($pdo);
        $result_ai = ai_send_morning_report($pdo);
        performance_set_setting($pdo, 'last_ai_morning_report', date('Y-m-d H:i:s'));
        $log[] = 'ai_morning_report:' . ($result_ai['success'] ? 'sent' : ('failed:' . ($result_ai['error'] ?? 'unknown')));
    }
}

$health_issues = [];

$last_cron = telegram_get_event_setting($pdo, 'last_event_monitor_run');
if ($last_cron !== '') {
    $cron_age = time() - strtotime($last_cron);
    if ($cron_age > 600) {
        $health_issues[] = 'cron_stopped:' . $cron_age . 's';
    }
}

$error_count_1h = error_logger_count_recent($pdo, 1);
if ($error_count_1h > 10) {
    error_logger_send_alert($pdo, 5);
    $health_issues[] = 'error_spike:' . $error_count_1h;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_order WHERE ecotrack_last_error != '' AND ecotrack_last_error IS NOT NULL AND ecotrack_updated_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $ecotrack_err_1h = (int) $stmt->fetchColumn();
    if ($ecotrack_err_1h > 5) {
        $text = "⚠️ *تنبيه ECOTRACK*\n" . $ecotrack_err_1h . " خطأ مزامنة في آخر ساعة.";
        telegram_send_event($text);
        $health_issues[] = 'ecotrack_errors:' . $ecotrack_err_1h;
    }
} catch (Exception $e) {
    error_logger_log($pdo, 'event_monitor', 'فشل التحقق من صحة ECOTRACK: ' . $e->getMessage());
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_telegram_delivery_log WHERE delivery_status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $telegram_fail_30m = (int) $stmt->fetchColumn();
    if ($telegram_fail_30m > 10) {
        $text = "⚠️ *تنبيه تلغرام*\n" . $telegram_fail_30m . " رسالة فاشلة في آخر 30 دقيقة.";
        telegram_send_event($text);
        $health_issues[] = 'telegram_failures:' . $telegram_fail_30m;
    }
} catch (Exception $e) {
    error_logger_log($pdo, 'event_monitor', 'فشل التحقق من صحة تلغرام: ' . $e->getMessage());
}

if (!empty($health_issues)) {
    error_logger_log($pdo, 'event_monitor', 'تم رصد مشاكل صحية: ' . implode(', ', $health_issues));
    $log[] = 'health_issues:' . implode(',', $health_issues);
}

if ($is_web) {
    echo json_encode(['ok' => true, 'events' => $log, 'count' => count($log), 'health_issues' => $health_issues]);
    exit;
}
