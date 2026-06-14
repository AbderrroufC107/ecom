<?php
if (!defined('AI_FUNCTIONS_LOADED')) {
    define('AI_FUNCTIONS_LOADED', true);

    if (!function_exists('ai_ensure_tables')) {
        function ai_ensure_tables(PDO $pdo): void
        {
            $lock_file = __DIR__ . '/../cache/ai_tables.lock';
            if (file_exists($lock_file)) {
                return;
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_ai_reports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    report_type VARCHAR(80) NOT NULL,
                    report_data LONGTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ai_report_type (report_type),
                    INDEX idx_ai_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            @file_put_contents($lock_file, '1');
        }
    }

    if (!function_exists('ai_save_report')) {
        function ai_save_report(PDO $pdo, string $type, $data): int
        {
            $stmt = $pdo->prepare("INSERT INTO tbl_ai_reports (report_type, report_data) VALUES (?, ?)");
            $stmt->execute([$type, is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE)]);
            return (int) $pdo->lastInsertId();
        }
    }

    if (!function_exists('ai_get_last_report')) {
        function ai_get_last_report(PDO $pdo, string $type): ?array
        {
            $stmt = $pdo->prepare("SELECT * FROM tbl_ai_reports WHERE report_type = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$type]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 1: Cancellation Analysis
    // ----------------------------------------------------------------
    if (!function_exists('ai_analyze_cancellations')) {
        function ai_analyze_cancellations(PDO $pdo, int $days = 90): array
        {
            $result = [
                'by_reason' => [],
                'by_employee' => [],
                'by_wilaya' => [],
                'by_product' => [],
                'total_cancellations' => 0,
            ];

            $stmt = $pdo->prepare("
                SELECT cr.reason, cr.employee_id, o.wilaya, o.product_name, e.full_name AS emp_name
                FROM tbl_order_cancellation_reason cr
                INNER JOIN tbl_order o ON o.id = cr.order_id
                LEFT JOIN tbl_employee e ON e.id = cr.employee_id
                WHERE cr.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $result['total_cancellations'] = count($rows);
            $reasons = [];
            $employees = [];
            $wilayas = [];
            $products = [];

            foreach ($rows as $r) {
                $reason = trim((string) ($r['reason'] ?? 'غير محدد'));
                if ($reason === '') $reason = 'غير محدد';
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;

                $emp = trim((string) ($r['emp_name'] ?? 'غير معروف'));
                $employees[$emp] = ($employees[$emp] ?? 0) + 1;

                $wilaya = trim((string) ($r['wilaya'] ?? 'غير معروفة'));
                if ($wilaya !== '') $wilayas[$wilaya] = ($wilayas[$wilaya] ?? 0) + 1;

                $prod = trim((string) ($r['product_name'] ?? 'غير معروف'));
                if ($prod !== '') $products[$prod] = ($products[$prod] ?? 0) + 1;
            }

            arsort($reasons);
            arsort($employees);
            arsort($wilayas);
            arsort($products);

            $total = $result['total_cancellations'];
            foreach ($reasons as $k => $v) {
                $result['by_reason'][] = ['label' => $k, 'count' => $v, 'pct' => $total > 0 ? round($v / $total * 100, 1) : 0];
            }
            foreach ($employees as $k => $v) {
                $result['by_employee'][] = ['label' => $k, 'count' => $v, 'pct' => $total > 0 ? round($v / $total * 100, 1) : 0];
            }
            foreach ($wilayas as $k => $v) {
                $result['by_wilaya'][] = ['label' => $k, 'count' => $v, 'pct' => $total > 0 ? round($v / $total * 100, 1) : 0];
            }
            foreach ($products as $k => $v) {
                $result['by_product'][] = ['label' => $k, 'count' => $v, 'pct' => $total > 0 ? round($v / $total * 100, 1) : 0];
            }

            ai_save_report($pdo, 'cancellation_analysis', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 2: Product Risk Score
    // ----------------------------------------------------------------
    if (!function_exists('ai_analyze_product_risk')) {
        function ai_analyze_product_risk(PDO $pdo, int $days = 90): array
        {
            $stmt = $pdo->prepare("
                SELECT
                    o.product_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned
                FROM tbl_order o
                WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY o.product_name
                HAVING total >= 3
                ORDER BY total DESC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $products = [];
            foreach ($rows as $r) {
                $total = (int) ($r['total'] ?? 0);
                $completed = (int) ($r['completed'] ?? 0);
                $cancelled = (int) ($r['cancelled'] ?? 0);
                $returned = (int) ($r['returned'] ?? 0);

                $delivery_rate = $total > 0 ? round($completed / $total * 100, 1) : 0;
                $cancel_rate = $total > 0 ? round($cancelled / $total * 100, 1) : 0;
                $return_rate = $total > 0 ? round($returned / $total * 100, 1) : 0;

                $risk_score = ($cancel_rate * 0.5) + ($return_rate * 1.0) - ($delivery_rate * 0.3);
                $risk_level = 'low';
                if ($risk_score > 20) $risk_level = 'high';
                elseif ($risk_score > 10) $risk_level = 'medium';

                $products[] = [
                    'product_name' => $r['product_name'],
                    'total' => $total,
                    'completed' => $completed,
                    'cancelled' => $cancelled,
                    'returned' => $returned,
                    'delivery_rate' => $delivery_rate,
                    'cancel_rate' => $cancel_rate,
                    'return_rate' => $return_rate,
                    'risk_score' => round($risk_score, 1),
                    'risk_level' => $risk_level,
                ];
            }

            usort($products, fn($a, $b) => $b['risk_score'] <=> $a['risk_score']);

            $counts = ['low' => 0, 'medium' => 0, 'high' => 0];
            foreach ($products as $p) {
                $counts[$p['risk_level']]++;
            }

            $result = ['products' => $products, 'summary' => $counts];
            ai_save_report($pdo, 'product_risk', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 3: Employee Performance Analysis
    // ----------------------------------------------------------------
    if (!function_exists('ai_analyze_employee_performance')) {
        function ai_analyze_employee_performance(PDO $pdo, int $days = 90): array
        {
            $stmt = $pdo->prepare("
                SELECT
                    e.id, e.full_name,
                    COUNT(DISTINCT oa.order_id) AS total,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Completed' THEN oa.order_id END) AS completed,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Cancelled' THEN oa.order_id END) AS cancelled,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Returned' THEN oa.order_id END) AS returned
                FROM tbl_employee e
                INNER JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.assigned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE e.is_active = 1
                GROUP BY e.id
                HAVING total >= 3
                ORDER BY completed DESC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $employees = [];
            $total_completed = 0;
            $total_cancelled = 0;
            $total_returned = 0;
            $total_all = 0;

            foreach ($rows as $r) {
                $t = (int) ($r['total'] ?? 0);
                $c = (int) ($r['completed'] ?? 0);
                $cn = (int) ($r['cancelled'] ?? 0);
                $rt = (int) ($r['returned'] ?? 0);
                $total_completed += $c;
                $total_cancelled += $cn;
                $total_returned += $rt;
                $total_all += $t;

                $del_rate = $t > 0 ? round($c / $t * 100, 1) : 0;
                $can_rate = $t > 0 ? round($cn / $t * 100, 1) : 0;

                $employees[] = [
                    'id' => (int) $r['id'],
                    'full_name' => $r['full_name'],
                    'total' => $t,
                    'completed' => $c,
                    'cancelled' => $cn,
                    'returned' => $rt,
                    'delivery_rate' => $del_rate,
                    'cancel_rate' => $can_rate,
                ];
            }

            $avg_delivery = $total_all > 0 ? round($total_completed / $total_all * 100, 1) : 0;
            $avg_cancel = $total_all > 0 ? round($total_cancelled / $total_all * 100, 1) : 0;

            $best = [];
            $weak = [];
            $unusual = [];

            foreach ($employees as $e) {
                if ($e['delivery_rate'] >= $avg_delivery + 10) {
                    $best[] = $e;
                }
                if ($e['delivery_rate'] <= $avg_delivery - 15 && $e['delivery_rate'] > 0) {
                    $weak[] = $e;
                }
                if ($e['cancel_rate'] >= $avg_cancel * 2 && $e['cancel_rate'] > 20) {
                    $unusual[] = $e;
                }
            }

            $recommendations = [];
            if (!empty($best)) {
                $names = array_map(fn($e) => $e['full_name'], array_slice($best, 0, 3));
                $recommendations[] = 'أفضل الموظفين: ' . implode('، ', $names) . ' — معدل توصيل ممتاز. يمكن الاستفادة من خبراتهم لتدريب الآخرين.';
            }
            if (!empty($weak)) {
                $names = array_map(fn($e) => $e['full_name'], array_slice($weak, 0, 3));
                $recommendations[] = 'موظفون بحاجة للدعم: ' . implode('، ', $names) . ' — معدل توصيل أقل من المتوسط. يوصى بتقديم تدريب إضافي.';
            }
            if (!empty($unusual)) {
                $names = array_map(fn($e) => $e['full_name'], array_slice($unusual, 0, 3));
                $recommendations[] = 'ارتفاع غير طبيعي في الإلغاء: ' . implode('، ', $names) . ' — يوصى بمراجعة أدائهم.';
            }
            if (empty($recommendations)) {
                $recommendations[] = 'أداء الفريق متوازن. لا توجد توصيات خاصة حالياً.';
            }

            $result = [
                'employees' => $employees,
                'averages' => ['delivery_rate' => $avg_delivery, 'cancel_rate' => $avg_cancel],
                'best' => $best,
                'weak' => $weak,
                'unusual' => $unusual,
                'recommendations' => $recommendations,
            ];

            ai_save_report($pdo, 'employee_performance', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 4: Wilaya Analysis
    // ----------------------------------------------------------------
    if (!function_exists('ai_analyze_wilayas')) {
        function ai_analyze_wilayas(PDO $pdo, int $days = 180): array
        {
            $stmt = $pdo->prepare("
                SELECT
                    o.wilaya,
                    COUNT(*) AS total,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned
                FROM tbl_order o
                WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY) AND o.wilaya != '' AND o.wilaya IS NOT NULL
                GROUP BY o.wilaya
                HAVING total >= 5
                ORDER BY total DESC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $wilayas = [];
            foreach ($rows as $r) {
                $t = (int) ($r['total'] ?? 0);
                $c = (int) ($r['completed'] ?? 0);
                $cn = (int) ($r['cancelled'] ?? 0);
                $rt = (int) ($r['returned'] ?? 0);
                $wilayas[] = [
                    'wilaya' => $r['wilaya'],
                    'total' => $t,
                    'completed' => $c,
                    'cancelled' => $cn,
                    'returned' => $rt,
                    'delivery_rate' => $t > 0 ? round($c / $t * 100, 1) : 0,
                    'cancel_rate' => $t > 0 ? round($cn / $t * 100, 1) : 0,
                    'return_rate' => $t > 0 ? round($rt / $t * 100, 1) : 0,
                ];
            }

            usort($wilayas, fn($a, $b) => $b['delivery_rate'] <=> $a['delivery_rate']);
            $best = array_slice($wilayas, 0, 5);
            $worst = array_slice(array_reverse($wilayas), 0, 5);

            usort($wilayas, fn($a, $b) => $b['return_rate'] <=> $a['return_rate']);
            $highest_return = array_slice($wilayas, 0, 5);

            $result = [
                'wilayas' => $wilayas,
                'best' => $best,
                'worst' => $worst,
                'highest_return' => $highest_return,
            ];

            ai_save_report($pdo, 'wilaya_analysis', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 5: Offer Analysis (product grouping)
    // ----------------------------------------------------------------
    if (!function_exists('ai_analyze_offers')) {
        function ai_analyze_offers(PDO $pdo, int $days = 90): array
        {
            $stmt = $pdo->prepare("
                SELECT
                    o.product_name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned,
                    COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_price ELSE 0 END), 0) AS revenue
                FROM tbl_order o
                WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY o.product_name
                HAVING total >= 3
                ORDER BY total DESC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $offers = [];
            foreach ($rows as $r) {
                $t = (int) ($r['total'] ?? 0);
                $c = (int) ($r['completed'] ?? 0);
                $cn = (int) ($r['cancelled'] ?? 0);
                $rev = (float) ($r['revenue'] ?? 0);
                $conversion_rate = $t > 0 ? round($c / $t * 100, 1) : 0;
                $del_rate = $t > 0 ? round($c / $t * 100, 1) : 0;
                $avg_revenue = $c > 0 ? round($rev / $c, 0) : 0;

                $offers[] = [
                    'product_name' => $r['product_name'],
                    'total' => $t,
                    'completed' => $c,
                    'cancelled' => $cn,
                    'returned' => (int) ($r['returned'] ?? 0),
                    'conversion_rate' => $conversion_rate,
                    'delivery_rate' => $del_rate,
                    'revenue' => $rev,
                    'avg_revenue_per_completed' => $avg_revenue,
                ];
            }

            usort($offers, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

            $result = ['offers' => $offers];
            ai_save_report($pdo, 'offer_analysis', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 6: Response Time Analysis
    // ----------------------------------------------------------------
    if (!function_exists('ai_analyze_response_time')) {
        function ai_analyze_response_time(PDO $pdo, int $days = 90): array
        {
            $stmt = $pdo->prepare("
                SELECT
                    e.id, e.full_name,
                    COALESCE(AVG(TIMESTAMPDIFF(HOUR, oa.assigned_at, 
                        COALESCE(
                            (SELECT MIN(al.created_at) FROM tbl_telegram_action_log al WHERE al.employee_id = e.id AND al.order_id = oa.order_id AND al.action_type = 'confirm'),
                            o.order_date
                        )
                    )), 0) AS avg_response_hours,
                    COUNT(DISTINCT CASE WHEN o.order_status = 'Confirmed' THEN oa.order_id END) AS confirmed_count
                FROM tbl_employee e
                INNER JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.assigned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE e.is_active = 1
                GROUP BY e.id
                HAVING confirmed_count >= 3
                ORDER BY avg_response_hours ASC
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response_data = [];
            foreach ($rows as $r) {
                $response_data[] = [
                    'id' => (int) $r['id'],
                    'full_name' => $r['full_name'],
                    'avg_response_hours' => round((float) ($r['avg_response_hours'] ?? 0), 1),
                    'confirmed_count' => (int) ($r['confirmed_count'] ?? 0),
                ];
            }

            $fastest = !empty($response_data) ? $response_data[0] : null;
            $slowest = !empty($response_data) ? $response_data[count($response_data) - 1] : null;

            $slow_employees = array_filter($response_data, fn($e) => $e['avg_response_hours'] > 24);

            $result = [
                'employees' => $response_data,
                'fastest' => $fastest,
                'slowest' => $slowest,
                'slow_employees' => array_values($slow_employees),
                'average_all' => !empty($response_data) ? round(array_sum(array_column($response_data, 'avg_response_hours')) / count($response_data), 1) : 0,
            ];

            ai_save_report($pdo, 'response_time', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 7: Revenue Forecast (simple linear regression)
    // ----------------------------------------------------------------
    if (!function_exists('ai_forecast_revenue')) {
        function ai_forecast_revenue(PDO $pdo): array
        {
            $stmt = $pdo->query("
                SELECT
                    DATE_FORMAT(order_date, '%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN order_status = 'Completed' THEN total_price ELSE 0 END), 0) AS revenue,
                    COUNT(CASE WHEN order_status = 'Completed' THEN 1 END) AS completed_count
                FROM tbl_order
                WHERE order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(order_date, '%Y-%m')
                ORDER BY month ASC
            ");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $months = [];
            $revenues = [];
            foreach ($rows as $r) {
                $months[] = $r['month'];
                $revenues[] = (float) ($r['revenue'] ?? 0);
            }

            $count = count($revenues);
            $forecast = ['7_days' => 0, '30_days' => 0, '90_days' => 0];

            if ($count >= 3) {
                $x = range(0, $count - 1);
                $x_sum = array_sum($x);
                $y_sum = array_sum($revenues);
                $xy_sum = 0;
                $x2_sum = 0;
                foreach ($x as $i => $v) {
                    $xy_sum += $v * $revenues[$i];
                    $x2_sum += $v * $v;
                }
                $slope = ($count * $xy_sum - $x_sum * $y_sum) / ($count * $x2_sum - $x_sum * $x_sum);
                $intercept = ($y_sum - $slope * $x_sum) / $count;

                $avg_daily = $count > 0 ? $y_sum / ($count * 30) : 0;
                $avg_monthly = $count > 0 ? $y_sum / $count : 0;

                $next_month_revenue = $intercept + $slope * $count;
                if ($next_month_revenue < 0) $next_month_revenue = $avg_monthly * 0.5;

                $forecast['7_days'] = round(max(0, $next_month_revenue / 30 * 7), 0);
                $forecast['30_days'] = round(max(0, $next_month_revenue), 0);
                $forecast['90_days'] = round(max(0, $next_month_revenue * 3), 0);
            } elseif ($count > 0) {
                $avg = $y_sum / $count;
                $forecast['7_days'] = round($avg / 30 * 7, 0);
                $forecast['30_days'] = round($avg, 0);
                $forecast['90_days'] = round($avg * 3, 0);
            }

            $trend = 'stable';
            if ($count >= 3) {
                $first_avg = $count >= 6 ? array_sum(array_slice($revenues, 0, 3)) / 3 : $revenues[0];
                $last_avg = array_sum(array_slice($revenues, -3)) / 3;
                if ($last_avg > $first_avg * 1.15) $trend = 'up';
                elseif ($last_avg < $first_avg * 0.85) $trend = 'down';
            }

            $result = [
                'historical' => array_map(fn($m, $r) => ['month' => $m, 'revenue' => $r], $months, $revenues),
                'forecast' => $forecast,
                'trend' => $trend,
                'avg_monthly_revenue' => $count > 0 ? round($y_sum / $count, 0) : 0,
            ];

            ai_save_report($pdo, 'revenue_forecast', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // ANALYSIS 8: Return Prediction
    // ----------------------------------------------------------------
    if (!function_exists('ai_predict_returns')) {
        function ai_predict_returns(PDO $pdo, int $days = 180): array
        {
            $stmt = $pdo->prepare("
                SELECT
                    o.product_name,
                    o.wilaya,
                    COUNT(*) AS total,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned_count
                FROM tbl_order o
                WHERE o.order_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY o.product_name, o.wilaya
                HAVING total >= 3
                ORDER BY total DESC
                LIMIT 100
            ");
            $stmt->execute([$days]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $predictions = [];
            foreach ($rows as $r) {
                $total = (int) ($r['total'] ?? 0);
                $returned = (int) ($r['returned_count'] ?? 0);
                $return_rate = $total > 0 ? round($returned / $total * 100, 1) : 0;

                $risk = 'low';
                if ($return_rate >= 20) $risk = 'high';
                elseif ($return_rate >= 10) $risk = 'medium';

                $predictions[] = [
                    'product_name' => $r['product_name'],
                    'wilaya' => $r['wilaya'],
                    'total' => $total,
                    'returned' => $returned,
                    'return_rate' => $return_rate,
                    'risk' => $risk,
                ];
            }

            usort($predictions, fn($a, $b) => $b['return_rate'] <=> $a['return_rate']);

            $high_risk = array_values(array_filter($predictions, fn($p) => $p['risk'] === 'high'));
            $overall_rate = !empty($predictions) ? round(array_sum(array_column($predictions, 'returned')) / max(1, array_sum(array_column($predictions, 'total'))) * 100, 1) : 0;

            $result = [
                'predictions' => $predictions,
                'high_risk' => $high_risk,
                'overall_return_rate' => $overall_rate,
                'total_analyzed' => count($predictions),
            ];

            ai_save_report($pdo, 'return_prediction', $result);
            return $result;
        }
    }

    // ----------------------------------------------------------------
    // AI Morning Report
    // ----------------------------------------------------------------
    if (!function_exists('ai_build_morning_report')) {
        function ai_build_morning_report(PDO $pdo): string
        {
            $cancellations = ai_analyze_cancellations($pdo);
            $products = ai_analyze_product_risk($pdo);
            $employees = ai_analyze_employee_performance($pdo);
            $wilayas = ai_analyze_wilayas($pdo);
            $offers = ai_analyze_offers($pdo);
            $response = ai_analyze_response_time($pdo);
            $forecast = ai_forecast_revenue($pdo);
            $returns = ai_predict_returns($pdo);

            $date_today = date('d/m/Y');
            $text = "\xF0\x9F\x93\x8A \xD8\xA7\xD9\x84\xD8\xAA\xD9\x82\xD8\xB1\xD9\x8A\xD8\xB1 \xD8\xA7\xD9\x84\xD9\x8A\xD9\x88\xD9\x85\xD9\x8A \xD9\x84\xD9\x84\xD8\xB0\xD9\x83\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD8\xA7\xD8\xB5\xD8\xB7\xD9\x86\xD8\xA7\xD8\xB9\xD9\x8A\n\n";
            $text .= "\xF0\x9F\x93\x85 {$date_today}\n\n";

            // High risk product
            $text .= "\xE2\x9D\x97 \xD8\xA3\xD8\xB9\xD9\x84\xD9\x89 \xD9\x85\xD9\x86\xD8\xAA\xD8\xAC \xD9\x85\xD8\xAE\xD8\xA7\xD8\xB7\xD8\xB1:\n";
            $high_risk_products = array_values(array_filter($products['products'] ?? [], fn($p) => $p['risk_level'] === 'high'));
            if (!empty($high_risk_products)) {
                $hrp = $high_risk_products[0];
                $text .= "\xF0\x9F\x94\xB4 " . htmlspecialchars($hrp['product_name'], ENT_QUOTES, 'UTF-8') . "\n";
                $text .= "\xF0\x9F\x93\x8A \xD9\x85\xD8\xAE\xD8\xA7\xD8\xB7\xD8\xB1: {$hrp['risk_score']} | \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1: {$hrp['cancel_rate']}% | \xD8\xA5\xD8\xB1\xD8\xAC\xD8\xA7\xD8\xB9: {$hrp['return_rate']}%\n\n";
            } else {
                $text .= "\xe2\x9c\x85 \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x88\xD8\xAC\xD8\xAF \xD9\x85\xD9\x86\xD8\xAA\xD8\xAC\xD8\xA7\xD8\xAA \xD8\xB9\xD8\xA7\xD9\x84\xD9\x8A\xD8\xA9 \xD8\xA7\xD9\x84\xD9\x85\xD8\xAE\xD8\xA7\xD8\xB7\xD8\xB1\n\n";
            }

            // Return prediction
            $text .= "\xF0\x9F\x94\x99 \xD8\xAE\xD8\xB7\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xA5\xD8\xB1\xD8\xAC\xD8\xA7\xD8\xB9:\n";
            $text .= "\xD8\xA7\xD9\x84\xD9\x85\xD8\xB9\xD8\xAF\xD9\x84 \xD8\xA7\xD9\x84\xD8\xB9\xD8\xA7\xD9\x85: {$returns['overall_return_rate']}%\n";
            if (!empty($returns['high_risk'])) {
                $hr = $returns['high_risk'][0];
                $text .= "\xE2\x9A\xA0\xEF\xB8\x8F \xD8\xA3\xD8\xB9\xD9\x84\xD9\x89 \xD8\xAE\xD8\xB7\xD8\xB1: " . htmlspecialchars($hr['product_name'], ENT_QUOTES, 'UTF-8') . " \xD9\x81\xD9\x8A " . htmlspecialchars($hr['wilaya'], ENT_QUOTES, 'UTF-8') . " ({$hr['return_rate']}%)\n\n";
            } else {
                $text .= "\xE2\x9C\x85 \xD9\x84\xD8\xA7 \xD9\x8A\xD9\x88\xD8\xAC\xD8\xAF \xD9\x85\xD9\x86\xD8\xAA\xD8\xAC\xD8\xA7\xD8\xAA \xD8\xB9\xD8\xA7\xD9\x84\xD9\x8A\xD8\xA9 \xD8\xAE\xD8\xB7\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xA5\xD8\xB1\xD8\xAC\xD8\xA7\xD8\xB9\n\n";
            }

            // Worst wilaya
            $text .= "\xF0\x9F\x97\xBA \xD8\xA3\xD8\xB3\xD9\x88\xD8\xA3 \xD8\xA7\xD9\x84\xD9\x88\xD9\x84\xD8\xA7\xD9\x8A\xD8\xA7\xD8\xAA:\n";
            if (!empty($wilayas['worst'])) {
                $ww = $wilayas['worst'][0];
                $text .= "\xF0\x9F\x94\xB4 " . htmlspecialchars($ww['wilaya'], ENT_QUOTES, 'UTF-8') . " (\xD8\xAA\xD9\x88\xD8\xB5\xD9\x8A\xD9\x84: {$ww['delivery_rate']}% - \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1: {$ww['cancel_rate']}%)\n\n";
            }

            // Best employee
            $text .= "\xF0\x9F\x8F\x86 \xD8\xA3\xD9\x81\xD8\xB6\xD9\x84 \xD9\x85\xD9\x88\xD8\xB8\xD9\x81:\n";
            if (!empty($employees['best'])) {
                $be = $employees['best'][0];
                $text .= "\xF0\x9F\x91\xA4 " . htmlspecialchars($be['full_name'], ENT_QUOTES, 'UTF-8') . " - \xD8\xAA\xD9\x88\xD8\xB5\xD9\x8A\xD9\x84: {$be['delivery_rate']}%\n\n";
            }

            // Revenue forecast
            $text .= "\xF0\x9F\x92\xB0 \xD8\xAA\xD9\x88\xD9\x82\xD8\xB9 \xD8\xA7\xD9\x84\xD8\xA5\xD9\x8A\xD8\xB1\xD8\xA7\xD8\xAF:\n";
            $fore = $forecast['forecast'];
            $trend_icon = $forecast['trend'] === 'up' ? "\xE2\x86\x91" : ($forecast['trend'] === 'down' ? "\xE2\x86\x93" : "\xE2\x86\x94");
            $text .= "{$trend_icon} 7 \xD8\xA3\xD9\x8A\xD8\xA7\xD9\x85: " . number_format($fore['7_days'], 0) . " \xD8\xAF\xD8\xAC\n";
            $text .= "30 \xD9\x8A\xD9\x88\xD9\x85: " . number_format($fore['30_days'], 0) . " \xD8\xAF\xD8\xAC\n";
            $text .= "90 \xD9\x8A\xD9\x88\xD9\x85: " . number_format($fore['90_days'], 0) . " \xD8\xAF\xD8\xAC\n\n";

            // Response time
            $text .= "\xE2\x8F\xB0 \xD9\x85\xD8\xAA\xD9\x88\xD8\xB3\xD8\xB7 \xD9\x88\xD9\x82\xD8\xAA \xD8\xA7\xD9\x84\xD8\xA7\xD8\xB3\xD8\xAA\xD8\xAC\xD8\xA7\xD8\xA8\xD8\xA9:\n";
            $text .= "\xD8\xA7\xD9\x84\xD9\x85\xD8\xB9\xD8\xAF\xD9\x84 \xD8\xA7\xD9\x84\xD8\xB9\xD8\xA7\xD9\x85: {$response['average_all']} \xD8\xB3\xD8\xA7\xD8\xB9\xD8\xA9\n";
            if ($response['fastest']) {
                $text .= "\xE2\x9A\xA1 \xD8\xA3\xD8\xB3\xD8\xB1\xD8\xB9: " . htmlspecialchars($response['fastest']['full_name'], ENT_QUOTES, 'UTF-8') . " ({$response['fastest']['avg_response_hours']} \xD8\xB3)\n";
            }

            $text .= "\n\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\xE2\x80\x94\n";

            // Recommendations
            $text .= "\xF0\x9F\x93\x8B \xD8\xA7\xD9\x84\xD8\xAA\xD9\x88\xD8\xB5\xD9\x8A\xD8\xA7\xD8\xAA:\n";
            foreach ($employees['recommendations'] as $rec) {
                $text .= "\xE2\x9E\xA1 " . htmlspecialchars($rec, ENT_QUOTES, 'UTF-8') . "\n";
            }

            if (!empty($cancellations['by_reason'])) {
                $top_reason = $cancellations['by_reason'][0];
                $text .= "\xE2\x9E\xA1 \xD8\xA3\xD9\x83\xD8\xAB\xD8\xB1 \xD8\xB3\xD8\xA8\xD8\xA8 \xD8\xA5\xD9\x84\xD8\xBA\xD8\xA7\xD8\xA1: " . htmlspecialchars($top_reason['label'], ENT_QUOTES, 'UTF-8') . " ({$top_reason['pct']}%)\n";
            }

            return $text;
        }
    }

    if (!function_exists('ai_send_morning_report')) {
        function ai_send_morning_report(PDO $pdo): array
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

            $text = ai_build_morning_report($pdo);
            $result = telegram_send_message($chat_id, $text);

            $reported_at = date('Y-m-d H:i:s');
            $report_data = [
                'sent_at' => $reported_at,
                'success' => $result['success'] ?? false,
                'error' => $result['error'] ?? null,
            ];
            ai_save_report($pdo, 'daily_morning_report', $report_data);

            return [
                'success' => $result['success'] ?? false,
                'sent_at' => $reported_at,
                'error' => $result['error'] ?? null,
            ];
        }
    }
}
