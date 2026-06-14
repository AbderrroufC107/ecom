<?php
function ensure_incomplete_orders_table(PDO $pdo): bool
{
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'incomplete_orders'");
        if ($table_check && $table_check->rowCount() === 0) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `incomplete_orders` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `customer_name` varchar(255) NOT NULL,
                `customer_phone` varchar(50) NOT NULL,
                `product_id` int(11) DEFAULT NULL,
                `product_name` varchar(255) DEFAULT NULL,
                `quantity` int(11) DEFAULT NULL,
                `unit_price` float DEFAULT NULL,
                `total_price` float DEFAULT NULL,
                `selected_size` varchar(50) DEFAULT NULL,
                `selected_color` varchar(50) DEFAULT NULL,
                `wilaya` varchar(100) DEFAULT NULL,
                `commune` varchar(100) DEFAULT NULL,
                `address` varchar(255) DEFAULT NULL,
                `delivery_type` varchar(50) DEFAULT NULL,
                `customer_ip` varchar(64) DEFAULT NULL,
                `device_id` varchar(128) DEFAULT NULL,
                `user_agent` varchar(255) DEFAULT NULL,
                `last_updated` datetime DEFAULT NULL,
                `created_at` datetime DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        $existing = [];
        $columns = $pdo->query("SHOW COLUMNS FROM `incomplete_orders`");
        if ($columns) {
            foreach ($columns->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $existing[strtolower($column['Field'])] = true;
            }
        }

        $alter = [];
        if (!isset($existing['product_id'])) {
            $alter[] = "ADD COLUMN `product_id` int(11) DEFAULT NULL";
        }
        if (!isset($existing['product_name'])) {
            $alter[] = "ADD COLUMN `product_name` varchar(255) DEFAULT NULL";
        }
        if (!isset($existing['quantity'])) {
            $alter[] = "ADD COLUMN `quantity` int(11) DEFAULT NULL";
        }
        if (!isset($existing['unit_price'])) {
            $alter[] = "ADD COLUMN `unit_price` float DEFAULT NULL";
        }
        if (!isset($existing['total_price'])) {
            $alter[] = "ADD COLUMN `total_price` float DEFAULT NULL";
        }
        if (!isset($existing['selected_size'])) {
            $alter[] = "ADD COLUMN `selected_size` varchar(50) DEFAULT NULL";
        }
        if (!isset($existing['selected_color'])) {
            $alter[] = "ADD COLUMN `selected_color` varchar(50) DEFAULT NULL";
        }
        if (!isset($existing['wilaya'])) {
            $alter[] = "ADD COLUMN `wilaya` varchar(100) DEFAULT NULL";
        }
        if (!isset($existing['commune'])) {
            $alter[] = "ADD COLUMN `commune` varchar(100) DEFAULT NULL";
        }
        if (!isset($existing['address'])) {
            $alter[] = "ADD COLUMN `address` varchar(255) DEFAULT NULL";
        }
        if (!isset($existing['delivery_type'])) {
            $alter[] = "ADD COLUMN `delivery_type` varchar(50) DEFAULT NULL";
        }
        if (!isset($existing['customer_ip'])) {
            $alter[] = "ADD COLUMN `customer_ip` varchar(64) DEFAULT NULL";
        }
        if (!isset($existing['device_id'])) {
            $alter[] = "ADD COLUMN `device_id` varchar(128) DEFAULT NULL";
        }
        if (!isset($existing['user_agent'])) {
            $alter[] = "ADD COLUMN `user_agent` varchar(255) DEFAULT NULL";
        }
        if (!isset($existing['last_updated'])) {
            $alter[] = "ADD COLUMN `last_updated` datetime DEFAULT NULL";
        }
        if (!isset($existing['created_at'])) {
            $alter[] = "ADD COLUMN `created_at` datetime DEFAULT current_timestamp()";
        }

        if (!empty($alter)) {
            $pdo->exec("ALTER TABLE `incomplete_orders` " . implode(", ", $alter));
        }

        return true;
    } catch (PDOException $e) {
        error_log('ensure_incomplete_orders_table failed: ' . $e->getMessage());
        return false;
    }
}
