<?php

if (!function_exists('exchange_requests_ensure_table')) {
    function exchange_requests_ensure_table(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS exchange_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                customer_name VARCHAR(255) NULL,
                customer_phone VARCHAR(32) NOT NULL,
                product_name VARCHAR(255) NULL,
                quantity INT NOT NULL DEFAULT 1,
                reason TEXT NOT NULL,
                proof_image VARCHAR(255) NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                delivered_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL,
                KEY idx_exchange_phone (customer_phone),
                KEY idx_exchange_order (order_id),
                KEY idx_exchange_status (status),
                KEY idx_exchange_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

if (!function_exists('exchange_requests_is_delivered_status')) {
    function exchange_requests_is_delivered_status($status): bool
    {
        $status = trim((string) $status);
        if ($status === '') {
            return false;
        }

        $lower = function_exists('mb_strtolower') ? mb_strtolower($status, 'UTF-8') : strtolower($status);
        foreach (['retour', 'returned', 'annul', 'cancel', 'echec', 'failed', 'non livr'] as $blocked) {
            if (strpos($lower, $blocked) !== false) {
                return false;
            }
        }

        foreach (['livr', 'delivered', 'تم التسليم', 'مسلم'] as $marker) {
            if (strpos($lower, $marker) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('exchange_requests_delivery_time')) {
    function exchange_requests_delivery_time(array $order): ?DateTimeImmutable
    {
        if (!exchange_requests_is_delivered_status($order['ecotrack_remote_status'] ?? '')) {
            return null;
        }

        $raw = trim((string) ($order['ecotrack_remote_time'] ?? ''));
        if ($raw === '' || $raw === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('exchange_requests_hours_since_delivery')) {
    function exchange_requests_hours_since_delivery(array $order): ?float
    {
        $deliveredAt = exchange_requests_delivery_time($order);
        if (!$deliveredAt) {
            return null;
        }

        $seconds = time() - $deliveredAt->getTimestamp();
        if ($seconds < 0) {
            return 0.0;
        }

        return $seconds / 3600;
    }
}

if (!function_exists('exchange_requests_status_labels')) {
    function exchange_requests_status_labels(): array
    {
        return [
            'pending' => 'قيد المراجعة',
            'approved' => 'مقبول',
            'rejected' => 'مرفوض',
            'completed' => 'مكتمل',
        ];
    }
}

if (!function_exists('exchange_requests_h')) {
    function exchange_requests_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
