<?php
declare(strict_types=1);

// Already authenticated: $pdo, $store_id, $method, $action, $sub_id, $key_data

switch ($method) {
    case 'GET':
        if ($sub_id > 0) {
            // GET /orders/{id}
            $perm = 'orders.read';
            if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
                api_error('لا توجد صلاحية لقراءة الطلبات', 403, 'FORBIDDEN');
            }

            $stmt = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? AND store_id = ? LIMIT 1");
            $stmt->execute([$sub_id, $store_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                api_error('الطلب غير موجود', 404, 'NOT_FOUND');
            }

            api_success($order);
        }

        // GET /orders
        $perm = 'orders.read';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لقراءة الطلبات', 403, 'FORBIDDEN');
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;

        $where = "WHERE store_id = ?";
        $params = [$store_id];

        if (!empty($_GET['status'])) {
            $where .= " AND order_status = ?";
            $params[] = trim($_GET['status']);
        }
        if (!empty($_GET['phone'])) {
            $where .= " AND customer_phone LIKE ?";
            $params[] = '%' . trim($_GET['phone']) . '%';
        }
        if (!empty($_GET['date_from'])) {
            $where .= " AND order_date >= ?";
            $params[] = $_GET['date_from'] . ' 00:00:00';
        }
        if (!empty($_GET['date_to'])) {
            $where .= " AND order_date <= ?";
            $params[] = $_GET['date_to'] . ' 23:59:59';
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_order $where");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM tbl_order $where ORDER BY id DESC LIMIT ? OFFSET ?");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        api_success($orders, 200, [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => max(1, (int) ceil($total / $per_page)),
        ]);

    case 'POST':
        // POST /orders
        $perm = 'orders.write';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لكتابة الطلبات', 403, 'FORBIDDEN');
        }

        // Check order quota
        $quota = store_check_order_quota($pdo, $store_id);
        if (!$quota['allowed']) {
            api_error($quota['message'], 429, 'QUOTA_EXCEEDED');
        }

        $data = api_read_payload();

        $required = ['customer_name', 'customer_phone', 'product_name', 'total_price'];
        $missing = [];
        foreach ($required as $f) {
            if (empty($data[$f])) $missing[] = $f;
        }
        if (!empty($missing)) {
            api_error('الحقول التالية مطلوبة: ' . implode(', ', $missing), 422, 'VALIDATION_ERROR');
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_order (store_id, customer_name, customer_phone, wilaya, commune, delivery_type, product_name, quantity, total_price, order_status, order_date, note, payment_method, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?, NOW())
        ");
        $stmt->execute([
            $store_id,
            trim((string) ($data['customer_name'] ?? '')),
            trim((string) ($data['customer_phone'] ?? '')),
            trim((string) ($data['wilaya'] ?? '')),
            trim((string) ($data['commune'] ?? '')),
            trim((string) ($data['delivery_type'] ?? 'home')),
            trim((string) ($data['product_name'] ?? '')),
            (int) ($data['quantity'] ?? 1),
            (float) ($data['total_price'] ?? 0),
            trim((string) ($data['note'] ?? '')),
            trim((string) ($data['payment_method'] ?? 'cod')),
        ]);

        $order_id = (int) $pdo->lastInsertId();

        // Central Order Assignment
        if (file_exists(__DIR__ . '/../../admin/inc/employee_functions.php')) {
            require_once __DIR__ . '/../../admin/inc/employee_functions.php';
            if (function_exists('assign_order_by_strategy')) {
                assign_order_by_strategy($pdo, $order_id, 'api');
            }
        }

        // Track usage + webhook
        store_track_usage($pdo, $store_id, 'order');
        store_trigger_webhook($pdo, $store_id, 'order.created', ['order_id' => $order_id]);

        if (function_exists('audit_log')) {
            audit_log($pdo, [
                'entity_type' => 'order',
                'entity_id' => $order_id,
                'action_type' => 'api_order_created',
                'source' => 'api',
            ]);
        }

        api_success(['id' => $order_id], 201);

    case 'PUT':
        // PUT /orders/{id}
        if ($sub_id <= 0) {
            api_error('معرف الطلب مطلوب', 400, 'ID_REQUIRED');
        }

        $perm = 'orders.write';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لتعديل الطلبات', 403, 'FORBIDDEN');
        }

        $data = api_read_payload();
        $allowed_fields = ['customer_name', 'customer_phone', 'wilaya', 'commune', 'delivery_type', 'product_name', 'quantity', 'total_price', 'order_status', 'note'];

        $sets = [];
        $params = [];
        foreach ($allowed_fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`$f` = ?";
                $params[] = $data[$f];
            }
        }

        if (empty($sets)) {
            api_error('لا توجد حقول للتحديث', 400, 'NO_FIELDS');
        }

        $params[] = $sub_id;
        $params[] = $store_id;
        $stmt = $pdo->prepare("UPDATE tbl_order SET " . implode(', ', $sets) . " WHERE id = ? AND store_id = ?");
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            api_error('الطلب غير موجود أو لم يتم التحديث', 404, 'NOT_FOUND');
        }

        store_trigger_webhook($pdo, $store_id, 'order.confirmed', ['order_id' => $sub_id]);

        if (function_exists('audit_log')) {
            audit_log($pdo, [
                'entity_type' => 'order',
                'entity_id' => $sub_id,
                'action_type' => 'api_order_updated',
                'source' => 'api',
            ]);
        }

        api_success(['id' => $sub_id, 'updated' => true]);

    default:
        api_error('طريقة الطلب غير مدعومة', 405, 'METHOD_NOT_ALLOWED');
}
