<?php
declare(strict_types=1);

$perm = 'analytics.read';
if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
    api_error('لا توجد صلاحية لقراءة التحليلات', 403, 'FORBIDDEN');
}

if ($method !== 'GET') {
    api_error('طريقة الطلب غير مدعومة', 405, 'METHOD_NOT_ALLOWED');
}

$stat = [];

switch ($resource) {
    case 'analytics':
        // GET /analytics — basic summary
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $stat['total_orders'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['total_orders'] = 0; }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order WHERE store_id = ? AND order_status = 'Completed'");
            $stmt->execute([$store_id]);
            $stat['completed_orders'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['completed_orders'] = 0; }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order WHERE store_id = ? AND order_status = 'Pending'");
            $stmt->execute([$store_id]);
            $stat['pending_orders'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['pending_orders'] = 0; }

        try {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM tbl_order WHERE store_id = ? AND order_status = 'Completed'");
            $stmt->execute([$store_id]);
            $stat['total_revenue'] = (float) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['total_revenue'] = 0; }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_product WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $stat['total_products'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['total_products'] = 0; }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_employee WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $stat['total_employees'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['total_employees'] = 0; }

        // Monthly usage
        $usage = store_get_monthly_usage($pdo, $store_id);
        $stat['monthly_usage'] = $usage;

        // Plan info
        $store = store_get($pdo, $store_id);
        $plan = store_get_plan_limits($store['plan_type'] ?? 'starter');
        $sub_status = store_get_subscription_status($pdo, $store_id);
        $stat['plan'] = [
            'type' => $store['plan_type'] ?? '',
            'label' => $plan['label_ar'] ?? '',
            'status' => $sub_status['status'] ?? '',
            'days_left' => $sub_status['days_left'] ?? $sub_status['trial_days_left'] ?? 0,
        ];

        api_success($stat);

    case 'performance':
        // GET /performance — employee performance summary
        try {
            $stmt = $pdo->prepare("
                SELECT e.employee_id, e.name, e.phone,
                    (SELECT COUNT(*) FROM tbl_order_assignment oa WHERE oa.employee_id = e.employee_id AND oa.store_id = e.store_id) AS assigned_orders,
                    (SELECT COUNT(*) FROM tbl_order_assignment oa INNER JOIN tbl_order o ON o.id = oa.order_id WHERE oa.employee_id = e.employee_id AND o.order_status = 'Completed' AND oa.store_id = e.store_id) AS completed_orders
                FROM tbl_employee e WHERE e.store_id = ? ORDER BY completed_orders DESC
            ");
            $stmt->execute([$store_id]);
            $stat['employees'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $stat['employees'] = []; }

        api_success($stat);

    case 'recovery':
        // GET /recovery — recovery metrics
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_recovery_tasks WHERE store_id = ? AND status = 'open'");
            $stmt->execute([$store_id]);
            $stat['open_tasks'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['open_tasks'] = 0; }

        try {
            $stmt = $pdo->prepare("SELECT sub_status, COUNT(*) AS cnt FROM tbl_order_contact_attempt WHERE store_id = ? GROUP BY sub_status ORDER BY cnt DESC");
            $stmt->execute([$store_id]);
            $stat['contact_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $stat['contact_breakdown'] = []; }

        api_success($stat);

    case 'risk':
        // GET /risk — customer risk distribution
        try {
            $stmt = $pdo->prepare("SELECT risk_level, COUNT(*) AS cnt FROM tbl_customer_risk_timeline WHERE store_id = ? GROUP BY risk_level ORDER BY cnt DESC");
            $stmt->execute([$store_id]);
            $stat['risk_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { $stat['risk_distribution'] = []; }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_security_blacklist WHERE store_id = ?");
            $stmt->execute([$store_id]);
            $stat['blacklisted'] = (int) $stmt->fetchColumn();
        } catch (Exception $e) { $stat['blacklisted'] = 0; }

        api_success($stat);

    default:
        api_error('غير معروف', 404, 'NOT_FOUND');
}
