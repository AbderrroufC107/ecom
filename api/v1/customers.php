<?php
declare(strict_types=1);

switch ($method) {
    case 'GET':
        $perm = 'customers.read';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لقراءة العملاء', 403, 'FORBIDDEN');
        }

        if ($sub_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_id = ? AND store_id = ? LIMIT 1");
            $stmt->execute([$sub_id, $store_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$customer) api_error('العميل غير موجود', 404, 'NOT_FOUND');
            api_success($customer);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;

        $where = "WHERE store_id = ?";
        $params = [$store_id];

        if (!empty($_GET['phone'])) {
            $where .= " AND (cust_phone LIKE ? OR cust_phone2 LIKE ?)";
            $p = '%' . trim($_GET['phone']) . '%';
            $params[] = $p;
            $params[] = $p;
        }
        if (!empty($_GET['search'])) {
            $where .= " AND (cust_name LIKE ? OR cust_email LIKE ?)";
            $s = '%' . trim($_GET['search']) . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_customer $where");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM tbl_customer $where ORDER BY cust_id DESC LIMIT ? OFFSET ?");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);

        api_success($stmt->fetchAll(PDO::FETCH_ASSOC), 200, [
            'page' => $page, 'per_page' => $per_page,
            'total' => $total, 'total_pages' => max(1, (int) ceil($total / $per_page)),
        ]);

    default:
        api_error('طريقة الطلب غير مدعومة', 405, 'METHOD_NOT_ALLOWED');
}
