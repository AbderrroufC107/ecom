<?php
if (!defined('STOCK_FUNCTIONS_LOADED')) {
    define('STOCK_FUNCTIONS_LOADED', true);

    function stock_get_all(PDO $pdo, array $filters = []) { global $dbRepo;
    global $dbRepo;

        $where = ["1 = 1"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = "p.ecat_id = ?";
            $params[] = $filters['category_id'];
        }
        
        $sql = "
            SELECT 
                p.p_id, p.p_name, p.p_sku, p.p_barcode, p.p_qty, p.p_featured_photo, p.p_is_active,
                v.variant_id, v.size_id, v.color_id, v.qty AS variant_qty, v.sku AS variant_sku, v.barcode AS variant_barcode,
                s.size_name, c.color_name
            FROM tbl_product p
            LEFT JOIN tbl_product_variant v ON p.p_id = v.p_id
            LEFT JOIN tbl_size s ON v.size_id = s.size_id
            LEFT JOIN tbl_color c ON v.color_id = c.color_id
            WHERE " . implode(" AND ", $where) . "
            ORDER BY p.p_id DESC
        ";
        
        $stmt = $dbRepo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process rows to aggregate by product and calculate reserved stock
        $products = [];
        foreach ($rows as $row) {
            $pid = $row['p_id'];
            if (!isset($products[$pid])) {
                $reserved = stock_get_reserved($pdo, $pid, null);
                $products[$pid] = [
                    'p_id' => $pid,
                    'p_name' => $row['p_name'],
                    'p_sku' => $row['p_sku'],
                    'p_barcode' => $row['p_barcode'],
                    'p_qty' => (int)$row['p_qty'],
                    'reserved' => $reserved,
                    'available' => (int)$row['p_qty'] - $reserved,
                    'p_featured_photo' => $row['p_featured_photo'],
                    'p_is_active' => $row['p_is_active'],
                    'has_variants' => false,
                    'variants' => []
                ];
            }

            if ($row['variant_id']) {
                $products[$pid]['has_variants'] = true;
                $variant_label = [];
                if ($row['size_name']) $variant_label[] = $row['size_name'];
                if ($row['color_name']) $variant_label[] = $row['color_name'];
                $variant_name_str = implode(' / ', $variant_label);
                
                $v_reserved = stock_get_reserved($pdo, $pid, $row['variant_id'], $variant_name_str);
                $products[$pid]['variants'][] = [
                    'variant_id' => $row['variant_id'],
                    'size_id' => $row['size_id'],
                    'color_id' => $row['color_id'],
                    'size_name' => $row['size_name'],
                    'color_name' => $row['color_name'],
                    'variant_name' => $variant_name_str,
                    'qty' => (int)$row['variant_qty'],
                    'reserved' => $v_reserved,
                    'available' => (int)$row['variant_qty'] - $v_reserved,
                    'sku' => $row['variant_sku'],
                    'barcode' => $row['variant_barcode']
                ];
            }
        }
        
        // Filter by stock status if requested
        if (!empty($filters['stock_status'])) {
            $status = $filters['stock_status'];
            $filtered = [];
            
            $stmt = $dbRepo->query("SELECT stock_low_threshold, stock_critical_threshold FROM tbl_settings LIMIT 1");
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            $low = (int)($settings['stock_low_threshold'] ?? 5);
            $critical = (int)($settings['stock_critical_threshold'] ?? 2);
            
            foreach ($products as $p) {
                $keep = false;
                if ($p['has_variants']) {
                    foreach ($p['variants'] as $v) {
                        if (_stock_matches_status($v['qty'], $status, $low, $critical)) {
                            $keep = true; break;
                        }
                    }
                } else {
                    if (_stock_matches_status($p['p_qty'], $status, $low, $critical)) {
                        $keep = true;
                    }
                }
                if ($keep) $filtered[$p['p_id']] = $p;
            }
            return array_values($filtered);
        }

        return array_values($products);
    }

    function _stock_matches_status($qty, $status, $low, $critical) { global $dbRepo;
    global $dbRepo;

        if ($status === 'out_of_stock' && $qty <= 0) return true;
        if ($status === 'low_stock' && $qty > 0 && $qty <= $low) return true;
        if ($status === 'in_stock' && $qty > $low) return true;
        return false;
    }

    function stock_get_reserved(PDO $pdo, int $product_id, ?int $variant_id = null, ?string $size_name = null): int { global $dbRepo;
        $sql = "
            SELECT SUM(quantity) as reserved 
            FROM tbl_order 
            WHERE product_id = ? 
            AND order_status IN ('Pending', 'Confirmed', 'Processing')
        ";
        $params = [$product_id];

        if ($variant_id !== null) {
            if ($size_name === null) {
                // Lookup the variant name string
                $stmt = $dbRepo->prepare("
                    SELECT s.size_name, c.color_name 
                    FROM tbl_product_variant v
                    LEFT JOIN tbl_size s ON v.size_id = s.size_id
                    LEFT JOIN tbl_color c ON v.color_id = c.color_id
                    WHERE v.variant_id = ?
                ");
                $stmt->execute([$variant_id]);
                $v = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($v) {
                    $labels = [];
                    if (!empty($v['size_name'])) $labels[] = $v['size_name'];
                    if (!empty($v['color_name'])) $labels[] = $v['color_name'];
                    $size_name = implode(' / ', $labels);
                }
            }

            if (!empty($size_name)) {
                $sql .= " AND order_size = ?";
                $params[] = $size_name;
            }
        }

        $stmt = $dbRepo->prepare($sql);
        $stmt->execute($params);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($res['reserved'] ?? 0);
    }

    function stock_update_quantity(PDO $pdo, int $product_id, ?int $variant_id, int $new_qty, string $reason, ?string $ref_type = null, ?int $ref_id = null, ?int $admin_id = null, ?string $notes = null) { global $dbRepo;
    global $dbRepo;

        if ($new_qty < 0) $new_qty = 0; // Prevent negative stock

        if ($variant_id) {
            // Update variant
            $stmt = $dbRepo->prepare("SELECT qty FROM tbl_product_variant WHERE variant_id = ?");
            $stmt->execute([$variant_id]);
            $old_qty = (int)$stmt->fetchColumn();
            
            if ($old_qty === $new_qty) return true;
            
            $stmt = $dbRepo->prepare("UPDATE tbl_product_variant SET qty = ? WHERE variant_id = ?");
            $stmt->execute([$new_qty, $variant_id]);
        } else {
            // Update parent product
            $stmt = $dbRepo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
            $stmt->execute([$product_id]);
            $old_qty = (int)$stmt->fetchColumn();
            
            if ($old_qty === $new_qty) return true;
            
            $stmt = $dbRepo->prepare("UPDATE tbl_product SET p_qty = ? WHERE p_id = ?");
            $stmt->execute([$new_qty, $product_id]);
        }
        
        $change = $new_qty - $old_qty;
        $type = $change > 0 ? 'in' : 'out';

        // Log movement (Old System)
        $stmt = $dbRepo->prepare("
            INSERT INTO tbl_stock_movements 
            (product_id, variant_id, type, quantity_before, quantity_change, quantity_after, reason, reference_type, reference_id, admin_id, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $product_id, $variant_id, $type, $old_qty, $change, $new_qty, $reason, $ref_type, $ref_id, $admin_id, $notes
        ]);

        // Permanent Audit Log (New ERP System)
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $audit_admin_id = $admin_id ?? ($_SESSION['user']['id'] ?? 0);
        $stmt = $dbRepo->prepare("
            INSERT INTO tbl_stock_audit_log 
            (product_id, variant_id, old_qty, new_qty, change_amount, reason, note, user_id, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $product_id, $variant_id, $old_qty, $new_qty, $change, $reason, $notes, $audit_admin_id, $ip_address
        ]);
        
        // If stock increased above threshold, clear any dismissals
        $stmt = $dbRepo->query("SELECT stock_low_threshold FROM tbl_settings LIMIT 1");
        $low_threshold = (int)$stmt->fetchColumn();
        if ($new_qty > $low_threshold) {
            if ($variant_id) {
                $stmt = $dbRepo->prepare("DELETE FROM tbl_stock_dismissals WHERE product_id = ? AND variant_id = ?");
                $stmt->execute([$product_id, $variant_id]);
            } else {
                $stmt = $dbRepo->prepare("DELETE FROM tbl_stock_dismissals WHERE product_id = ? AND variant_id IS NULL");
                $stmt->execute([$product_id]);
            }
        }

        return true;
    }

    function stock_get_alerts(PDO $pdo, int $admin_id) { global $dbRepo;
    global $dbRepo;

        $stmt = $dbRepo->query("SELECT stock_low_threshold, stock_alarm_enabled FROM tbl_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (empty($settings['stock_alarm_enabled'])) {
            return [];
        }

        $low = (int)($settings['stock_low_threshold'] ?? 5);
        
        $alerts = [];
        
        // Products without variants
        $sql = "
            SELECT p.p_id, p.p_name, p.p_qty, p.p_featured_photo, d.dismissed_qty
            FROM tbl_product p
            LEFT JOIN tbl_stock_dismissals d ON d.product_id = p.p_id AND d.variant_id IS NULL AND d.admin_id = ?
            WHERE p.p_is_active = 1 AND p.p_qty <= ?
            AND p.p_id NOT IN (SELECT p_id FROM tbl_product_variant)
        ";
        $stmt = $dbRepo->prepare($sql);
        $stmt->execute([$admin_id, $low]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['dismissed_qty'] !== null && (int)$row['p_qty'] >= (int)$row['dismissed_qty']) {
                continue; // Dismissed and stock hasn't dropped further
            }
            $alerts[] = [
                'type' => 'parent',
                'product_id' => $row['p_id'],
                'variant_id' => null,
                'name' => $row['p_name'],
                'qty' => $row['p_qty'],
                'photo' => $row['p_featured_photo'],
                'status' => $row['p_qty'] <= 0 ? 'out_of_stock' : 'low_stock'
            ];
        }

        // Variants
        $sql = "
            SELECT p.p_id, p.p_name, p.p_featured_photo, v.variant_id, v.qty, s.size_name, c.color_name, d.dismissed_qty
            FROM tbl_product_variant v
            INNER JOIN tbl_product p ON v.p_id = p.p_id
            LEFT JOIN tbl_size s ON v.size_id = s.size_id
            LEFT JOIN tbl_color c ON v.color_id = c.color_id
            LEFT JOIN tbl_stock_dismissals d ON d.product_id = p.p_id AND d.variant_id = v.variant_id AND d.admin_id = ?
            WHERE p.p_is_active = 1 AND v.qty <= ?
        ";
        $stmt = $dbRepo->prepare($sql);
        $stmt->execute([$admin_id, $low]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['dismissed_qty'] !== null && (int)$row['qty'] >= (int)$row['dismissed_qty']) {
                continue;
            }
            $variant_label = [];
            if ($row['size_name']) $variant_label[] = $row['size_name'];
            if ($row['color_name']) $variant_label[] = $row['color_name'];
            $variant_name_str = implode(' / ', $variant_label);

            $alerts[] = [
                'type' => 'variant',
                'product_id' => $row['p_id'],
                'variant_id' => $row['variant_id'],
                'name' => $row['p_name'] . ' (' . $variant_name_str . ')',
                'qty' => $row['qty'],
                'photo' => $row['p_featured_photo'],
                'status' => $row['qty'] <= 0 ? 'out_of_stock' : 'low_stock'
            ];
        }

        return $alerts;
    }

    function stock_dismiss_alert(PDO $pdo, int $admin_id, int $product_id, ?int $variant_id, int $current_qty) { global $dbRepo;
    global $dbRepo;

        $stmt = $dbRepo->prepare("
            INSERT INTO tbl_stock_dismissals (product_id, variant_id, admin_id, dismissed_qty, dismissed_at) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE dismissed_qty = VALUES(dismissed_qty), dismissed_at = NOW()
        ");
        $stmt->execute([$product_id, $variant_id, $admin_id, $current_qty]);
    }

    function stock_handle_order_status_change(PDO $pdo, array $order, string $current_status, string $target_status, ?int $admin_id = null) { global $dbRepo;
    global $dbRepo;

        $product_id = (int)$order['product_id'];
        $order_size = $order['order_size'] ?? null;
        $order_qty = (int)$order['quantity'];
        
        if ($order_qty <= 0) return;

        // Determine variant_id if size exists
        $variant_id = null;
        if (!empty($order_size)) {
            $stmt = $dbRepo->prepare("
                SELECT v.variant_id 
                FROM tbl_product_variant v
                LEFT JOIN tbl_size s ON v.size_id = s.size_id
                LEFT JOIN tbl_color c ON v.color_id = c.color_id
                WHERE v.p_id = ? AND (s.size_name = ? OR CONCAT(s.size_name, ' / ', c.color_name) = ? OR c.color_name = ?)
                LIMIT 1
            ");
            $stmt->execute([$product_id, $order_size, $order_size, $order_size]);
            $variant_id = $stmt->fetchColumn();
            if (!$variant_id) $variant_id = null;
        }

        // We only change real stock on final states (Completed vs Returned/Cancelled)
        // Reserved stock is handled dynamically via Pending/Confirmed/Processing.
        
        $was_completed = ($current_status === 'Completed');
        $is_completed = ($target_status === 'Completed');
        
        if ($is_completed && !$was_completed) {
            // Decrease stock
            $current_stock = _stock_get_current_qty($pdo, $product_id, $variant_id);
            $new_stock = max(0, $current_stock - $order_qty);
            stock_update_quantity($pdo, $product_id, $variant_id, $new_stock, 'Sale', 'order', $order['id'], $admin_id, 'Order completed');
        } 
        elseif ($was_completed && !$is_completed && in_array($target_status, ['Returned', 'Cancelled'])) {
            // Increase stock (Restore)
            $current_stock = _stock_get_current_qty($pdo, $product_id, $variant_id);
            $new_stock = $current_stock + $order_qty;
            stock_update_quantity($pdo, $product_id, $variant_id, $new_stock, 'Return', 'order', $order['id'], $admin_id, 'Order ' . $target_status);
        }
    }

    function _stock_get_current_qty(PDO $pdo, int $product_id, ?int $variant_id) { global $dbRepo;
    global $dbRepo;

        if ($variant_id) {
            $stmt = $dbRepo->prepare("SELECT qty FROM tbl_product_variant WHERE variant_id = ?");
            $stmt->execute([$variant_id]);
            return (int)$stmt->fetchColumn();
        } else {
            $stmt = $dbRepo->prepare("SELECT p_qty FROM tbl_product WHERE p_id = ?");
            $stmt->execute([$product_id]);
            return (int)$stmt->fetchColumn();
        }
    }

    function stock_sync_variants(PDO $pdo, int $product_id) { global $dbRepo;
    global $dbRepo;

        $sizes = [];
        $stmt = $dbRepo->prepare("SELECT size_id FROM tbl_product_size WHERE p_id = ?");
        $stmt->execute([$product_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sizes[] = $row['size_id'];
        }

        $colors = [];
        $stmt = $dbRepo->prepare("SELECT color_id FROM tbl_product_color WHERE p_id = ?");
        $stmt->execute([$product_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $colors[] = $row['color_id'];
        }

        if (empty($sizes) && empty($colors)) {
            $dbRepo->prepare("DELETE FROM tbl_product_variant WHERE p_id = ?")->execute([$product_id]);
            return;
        }

        if (empty($sizes)) $sizes = [null];
        if (empty($colors)) $colors = [null];

        // Fetch existing variants to preserve quantities and SKUs
        $existing = [];
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_product_variant WHERE p_id = ?");
        $stmt->execute([$product_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = ($row['size_id'] ?? 'null') . '_' . ($row['color_id'] ?? 'null');
            $existing[$key] = $row;
        }

        $dbRepo->prepare("DELETE FROM tbl_product_variant WHERE p_id = ?")->execute([$product_id]);

        $insert_stmt = $dbRepo->prepare("INSERT INTO tbl_product_variant (p_id, size_id, color_id, qty, sku, barcode, variant_price, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($sizes as $s) {
            foreach ($colors as $c) {
                $key = ($s ?? 'null') . '_' . ($c ?? 'null');
                if (isset($existing[$key])) {
                    $insert_stmt->execute([
                        $product_id, $s, $c, 
                        $existing[$key]['qty'], $existing[$key]['sku'], $existing[$key]['barcode'], 
                        $existing[$key]['variant_price'], $existing[$key]['is_active']
                    ]);
                } else {
                    $insert_stmt->execute([$product_id, $s, $c, 0, null, null, null, 1]);
                }
            }
        }
    }

    function stock_check_frontend_availability(PDO $pdo, int $product_id, ?string $size_name, ?int $color_id, int $requested_qty): bool { global $dbRepo;
        $variant_id = null;
        $variant_name_str = null;

        if ($size_name !== null || $color_id !== null) {
            $sql = "
                SELECT v.variant_id, s.size_name, c.color_name 
                FROM tbl_product_variant v
                LEFT JOIN tbl_size s ON v.size_id = s.size_id
                LEFT JOIN tbl_color c ON v.color_id = c.color_id
                WHERE v.p_id = ?
            ";
            $params = [$product_id];

            if ($size_name !== null && $size_name !== '') {
                $sql .= " AND s.size_name = ?";
                $params[] = $size_name;
            } else {
                $sql .= " AND v.size_id IS NULL";
            }

            if ($color_id !== null && $color_id > 0) {
                $sql .= " AND v.color_id = ?";
                $params[] = $color_id;
            } else {
                $sql .= " AND v.color_id IS NULL";
            }

            $stmt = $dbRepo->prepare($sql . " LIMIT 1");
            $stmt->execute($params);
            $v = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($v) {
                $variant_id = $v['variant_id'];
                $variant_labels = [];
                if (!empty($v['size_name'])) $variant_labels[] = $v['size_name'];
                if (!empty($v['color_name'])) $variant_labels[] = $v['color_name'];
                $variant_name_str = implode(' / ', $variant_labels);
            }
        }

        $current_qty = _stock_get_current_qty($pdo, $product_id, $variant_id);
        $reserved = stock_get_reserved($pdo, $product_id, $variant_id, $variant_name_str);
        
        $available = $current_qty - $reserved;
        
        return $available >= $requested_qty;
    }
}
?>
