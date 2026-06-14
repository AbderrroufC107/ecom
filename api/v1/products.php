<?php
declare(strict_types=1);

switch ($method) {
    case 'GET':
        $perm = 'products.read';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لقراءة المنتجات', 403, 'FORBIDDEN');
        }

        if ($sub_id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM tbl_product WHERE p_id = ? AND store_id = ? LIMIT 1");
            $stmt->execute([$sub_id, $store_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) api_error('المنتج غير موجود', 404, 'NOT_FOUND');
            api_success($product);
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;

        $where = "WHERE store_id = ?";
        $params = [$store_id];

        if (isset($_GET['is_active'])) {
            $where .= " AND p_is_active = ?";
            $params[] = (int) $_GET['is_active'];
        }
        if (!empty($_GET['search'])) {
            $where .= " AND (p_name LIKE ? OR p_id LIKE ?)";
            $s = '%' . trim($_GET['search']) . '%';
            $params[] = $s;
            $params[] = $s;
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_product $where");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM tbl_product $where ORDER BY p_id DESC LIMIT ? OFFSET ?");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        api_success($products, 200, [
            'page' => $page, 'per_page' => $per_page,
            'total' => $total, 'total_pages' => max(1, (int) ceil($total / $per_page)),
        ]);

    case 'POST':
        $perm = 'products.write';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لكتابة المنتجات', 403, 'FORBIDDEN');
        }

        $data = api_read_payload();
        if (empty($data['p_name'])) {
            api_error('اسم المنتج مطلوب', 422, 'VALIDATION_ERROR');
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_product (store_id, p_name, p_current_price, p_old_price, p_qty, p_description, p_is_active, p_featured_photo, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([
            $store_id,
            trim((string) ($data['p_name'] ?? '')),
            (float) ($data['p_current_price'] ?? 0),
            (float) ($data['p_old_price'] ?? 0),
            (int) ($data['p_qty'] ?? 0),
            trim((string) ($data['p_description'] ?? '')),
            trim((string) ($data['p_featured_photo'] ?? '')),
        ]);
        $prod_id = (int) $pdo->lastInsertId();

        if (function_exists('audit_log')) {
            audit_log($pdo, [
                'entity_type' => 'product',
                'entity_id' => $prod_id,
                'action_type' => 'api_product_created',
                'source' => 'api',
            ]);
        }

        api_success(['id' => $prod_id], 201);

    case 'PUT':
        if ($sub_id <= 0) api_error('معرف المنتج مطلوب', 400, 'ID_REQUIRED');

        $perm = 'products.write';
        if (!in_array($perm, $key_data['permissions_list'] ?? [], true) && !in_array('*', $key_data['permissions_list'] ?? [], true)) {
            api_error('لا توجد صلاحية لتعديل المنتجات', 403, 'FORBIDDEN');
        }

        $data = api_read_payload();
        $allowed = ['p_name', 'p_current_price', 'p_old_price', 'p_qty', 'p_description', 'p_is_active', 'p_featured_photo'];
        $sets = [];
        $params = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`$f` = ?";
                $params[] = $data[$f];
            }
        }
        if (empty($sets)) api_error('لا توجد حقول للتحديث', 400, 'NO_FIELDS');
        $params[] = $sub_id;
        $params[] = $store_id;

        $stmt = $pdo->prepare("UPDATE tbl_product SET " . implode(', ', $sets) . " WHERE p_id = ? AND store_id = ?");
        $stmt->execute($params);

        api_success(['id' => $sub_id, 'updated' => $stmt->rowCount() > 0]);

    default:
        api_error('طريقة الطلب غير مدعومة', 405, 'METHOD_NOT_ALLOWED');
}
