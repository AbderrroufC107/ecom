<?php

if (!function_exists('audit_ensure_tables')) {
    function audit_ensure_tables(PDO $pdo): void
    {
        static $done = false;
        if ($done) return;

        $lock_file = __DIR__ . '/../cache/audit_tables.lock';
        if (file_exists($lock_file)) {
            $done = true;
            return;
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tbl_audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(60) NOT NULL DEFAULT '',
                entity_id INT NOT NULL DEFAULT 0,
                action_type VARCHAR(60) NOT NULL DEFAULT '',
                performed_by_type VARCHAR(30) NOT NULL DEFAULT 'admin_panel',
                performed_by_id INT NOT NULL DEFAULT 0,
                old_value LONGTEXT NULL,
                new_value LONGTEXT NULL,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                user_agent VARCHAR(500) NOT NULL DEFAULT '',
                source VARCHAR(30) NOT NULL DEFAULT 'admin_panel',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_al_entity (entity_type, entity_id),
                KEY idx_al_action (action_type),
                KEY idx_al_source (source),
                KEY idx_al_performer (performed_by_type, performed_by_id),
                KEY idx_al_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        @file_put_contents($lock_file, '1');
        $done = true;
    }
}

if (!function_exists('_audit_insert')) {
    function _audit_insert(PDO $pdo, array $params): int
    {
        audit_ensure_tables($pdo);

        $entity_type = substr(trim((string) ($params['entity_type'] ?? '')), 0, 60);
        $entity_id = (int) ($params['entity_id'] ?? 0);
        $action_type = substr(trim((string) ($params['action_type'] ?? '')), 0, 60);
        $performed_by_type = in_array(($params['performed_by_type'] ?? 'admin_panel'), ['admin_panel', 'staff_portal', 'telegram_bot', 'event_monitor', 'ecotrack_sync', 'recovery_engine', 'system'], true)
            ? (string) $params['performed_by_type'] : 'admin_panel';
        $performed_by_id = (int) ($params['performed_by_id'] ?? 0);
        $source = in_array(($params['source'] ?? 'admin_panel'), ['admin_panel', 'staff_portal', 'telegram_bot', 'event_monitor', 'ecotrack_sync', 'recovery_engine', 'system'], true)
            ? (string) $params['source'] : 'admin_panel';

        $old_value = isset($params['old_value']) ? (is_string($params['old_value']) ? $params['old_value'] : json_encode($params['old_value'], JSON_UNESCAPED_UNICODE)) : null;
        $new_value = isset($params['new_value']) ? (is_string($params['new_value']) ? $params['new_value'] : json_encode($params['new_value'], JSON_UNESCAPED_UNICODE)) : null;

        $ip_address = substr(trim((string) ($params['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
        $user_agent = substr(trim((string) ($params['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);

        $stmt = $pdo->prepare("
            INSERT INTO tbl_audit_log
            (entity_type, entity_id, action_type, performed_by_type, performed_by_id, old_value, new_value, ip_address, user_agent, source, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$entity_type, $entity_id, $action_type, $performed_by_type, $performed_by_id, $old_value, $new_value, $ip_address, $user_agent, $source]);

        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('audit_log_order')) {
    function audit_log_order(PDO $pdo, int $order_id, string $action_type, $old_value = null, $new_value = null, string $source = 'admin_panel', int $performer_id = 0): int
    {
        $performer_type = $source;
        return _audit_insert($pdo, [
            'entity_type' => 'order',
            'entity_id' => $order_id,
            'action_type' => $action_type,
            'performed_by_type' => $performer_type,
            'performed_by_id' => $performer_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => $source,
        ]);
    }
}

if (!function_exists('audit_log_employee')) {
    function audit_log_employee(PDO $pdo, int $employee_id, string $action_type, $old_value = null, $new_value = null, string $source = 'admin_panel', int $performer_id = 0): int
    {
        return _audit_insert($pdo, [
            'entity_type' => 'employee',
            'entity_id' => $employee_id,
            'action_type' => $action_type,
            'performed_by_type' => $source,
            'performed_by_id' => $performer_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => $source,
        ]);
    }
}

if (!function_exists('audit_log_security')) {
    function audit_log_security(PDO $pdo, int $entity_id, string $action_type, $old_value = null, $new_value = null, string $source = 'admin_panel', int $performer_id = 0): int
    {
        return _audit_insert($pdo, [
            'entity_type' => 'security',
            'entity_id' => $entity_id,
            'action_type' => $action_type,
            'performed_by_type' => $source,
            'performed_by_id' => $performer_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => $source,
        ]);
    }
}

if (!function_exists('audit_log_commission')) {
    function audit_log_commission(PDO $pdo, int $commission_id, string $action_type, $old_value = null, $new_value = null, string $source = 'admin_panel', int $performer_id = 0): int
    {
        return _audit_insert($pdo, [
            'entity_type' => 'commission',
            'entity_id' => $commission_id,
            'action_type' => $action_type,
            'performed_by_type' => $source,
            'performed_by_id' => $performer_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => $source,
        ]);
    }
}

if (!function_exists('audit_log_recovery')) {
    function audit_log_recovery(PDO $pdo, int $task_id, string $action_type, $old_value = null, $new_value = null, string $source = 'recovery_engine', int $performer_id = 0): int
    {
        return _audit_insert($pdo, [
            'entity_type' => 'recovery_task',
            'entity_id' => $task_id,
            'action_type' => $action_type,
            'performed_by_type' => $source,
            'performed_by_id' => $performer_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => $source,
        ]);
    }
}

if (!function_exists('audit_log_telegram')) {
    function audit_log_telegram(PDO $pdo, int $order_id, string $action_type, $old_value = null, $new_value = null, int $employee_id = 0): int
    {
        return _audit_insert($pdo, [
            'entity_type' => 'order',
            'entity_id' => $order_id,
            'action_type' => $action_type,
            'performed_by_type' => 'telegram_bot',
            'performed_by_id' => $employee_id,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => 'telegram_bot',
        ]);
    }
}

if (!function_exists('audit_log_system')) {
    function audit_log_system(PDO $pdo, string $entity_type, int $entity_id, string $action_type, $old_value = null, $new_value = null, string $source = 'system'): int
    {
        return _audit_insert($pdo, [
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'action_type' => $action_type,
            'performed_by_type' => 'system',
            'performed_by_id' => 0,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'source' => $source,
        ]);
    }
}

if (!function_exists('audit_get_for_entity')) {
    function audit_get_for_entity(PDO $pdo, string $entity_type, int $entity_id, int $limit = 100, int $offset = 0): array
    {
        $limit_int = (int) $limit;
        $offset_int = (int) $offset;
        $stmt = $pdo->prepare("SELECT * FROM tbl_audit_log WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC LIMIT $limit_int OFFSET $offset_int");
        $stmt->execute([$entity_type, $entity_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('audit_search')) {
    function audit_search(PDO $pdo, array $filters = [], int $page = 1, int $per_page = 50): array
    {
        $conditions = [];
        $params = [];

        if (!empty($filters['entity_type'])) {
            $conditions[] = 'a.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['entity_id'])) {
            $conditions[] = 'a.entity_id = ?';
            $params[] = (int) $filters['entity_id'];
        }
        if (!empty($filters['action_type'])) {
            $conditions[] = 'a.action_type = ?';
            $params[] = $filters['action_type'];
        }
        if (!empty($filters['source'])) {
            $conditions[] = 'a.source = ?';
            $params[] = $filters['source'];
        }
        if (!empty($filters['performed_by_type'])) {
            $conditions[] = 'a.performed_by_type = ?';
            $params[] = $filters['performed_by_type'];
        }
        if (!empty($filters['performed_by_id'])) {
            $conditions[] = 'a.performed_by_id = ?';
            $params[] = (int) $filters['performed_by_id'];
        }
        if (!empty($filters['phone'])) {
            $phone = trim((string) $filters['phone']);
            $conditions[] = "(a.entity_type = 'order' AND a.entity_id IN (SELECT id FROM tbl_order WHERE customer_phone LIKE ?))";
            $params[] = '%' . $phone . '%';
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['risk_level'])) {
            $conditions[] = "(a.entity_type = 'security' AND a.action_type LIKE ?)";
            $params[] = '%' . $filters['risk_level'] . '%';
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_audit_log a $where");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $offset = ($page - 1) * $per_page;
        $limit_int = (int) $per_page;
        $offset_int = (int) $offset;
        $stmt = $pdo->prepare("
            SELECT a.*,
                o.customer_name AS order_customer_name,
                o.customer_phone AS order_customer_phone,
                o.product_name AS order_product_name
            FROM tbl_audit_log a
            LEFT JOIN tbl_order o ON a.entity_type = 'order' AND a.entity_id = o.id
            $where
            ORDER BY a.created_at DESC
            LIMIT $limit_int OFFSET $offset_int
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => max(1, (int) ceil($total / $per_page)),
        ];
    }
}

if (!function_exists('audit_get_action_label')) {
    function audit_get_action_label(string $action_type): string
    {
        $labels = [
            'order_created' => 'إنشاء طلب',
            'order_edited' => 'تعديل طلب',
            'order_confirmed' => 'تأكيد طلب',
            'order_cancelled' => 'إلغاء طلب',
            'order_completed' => 'إتمام طلب',
            'order_returned' => 'إرجاع طلب',
            'order_reassigned' => 'إعادة توزيع طلب',
            'address_changed' => 'تغيير العنوان',
            'phone_changed' => 'تغيير الهاتف',
            'offer_changed' => 'تغيير العرض',
            'quantity_changed' => 'تغيير الكمية',

            'employee_created' => 'إضافة موظف',
            'employee_updated' => 'تحديث موظف',
            'employee_disabled' => 'تعطيل موظف',
            'employee_activated' => 'تفعيل موظف',
            'employee_password_changed' => 'تغيير كلمة المرور',
            'telegram_id_changed' => 'تغيير معرف تلغرام',

            'risk_level_changed' => 'تغيير مستوى المخاطر',
            'blacklist_added' => 'إضافة إلى القائمة السوداء',
            'blacklist_removed' => 'إزالة من القائمة السوداء',
            'deposit_required_triggered' => 'طلب إيداع',
            'security_override' => 'تجاوز أمني',

            'commission_created' => 'إنشاء عمولة',
            'commission_modified' => 'تعديل عمولة',
            'commission_paid' => 'دفع عمولة',
            'commission_deleted' => 'حذف عمولة',

            'recovery_task_created' => 'إنشاء مهمة استرداد',
            'recovery_task_assigned' => 'تعيين مهمة استرداد',
            'recovery_task_closed' => 'إغلاق مهمة استرداد',
            'recovery_task_escalated' => 'تصعيد مهمة استرداد',
            'recovery_task_failed' => 'فشل مهمة استرداد',

            'confirm_from_telegram' => 'تأكيد عبر تلغرام',
            'cancel_from_telegram' => 'إلغاء عبر تلغرام',
            'edit_from_telegram' => 'تعديل عبر تلغرام',
            'reassign_from_telegram' => 'إعادة توزيع عبر تلغرام',
        ];

        return $labels[$action_type] ?? $action_type;
    }
}

if (!function_exists('audit_get_source_label')) {
    function audit_get_source_label(string $source): string
    {
        $labels = [
            'admin_panel' => 'لوحة التحكم',
            'staff_portal' => 'بوابة الموظفين',
            'telegram_bot' => 'بوت تلغرام',
            'event_monitor' => 'مراقب الأحداث',
            'ecotrack_sync' => 'مزامنة إيكوتراك',
            'recovery_engine' => 'محرك الاسترداد',
            'system' => 'النظام',
        ];
        return $labels[$source] ?? $source;
    }
}

if (!function_exists('audit_get_action_icon')) {
    function audit_get_action_icon(string $action_type): string
    {
        $icons = [
            'order_created' => 'fa-plus-circle',
            'order_edited' => 'fa-pencil-square-o',
            'order_confirmed' => 'fa-check-circle',
            'order_cancelled' => 'fa-ban',
            'order_completed' => 'fa-check-square-o',
            'order_returned' => 'fa-undo',
            'order_reassigned' => 'fa-exchange',
            'address_changed' => 'fa-map-marker',
            'phone_changed' => 'fa-phone',
            'offer_changed' => 'fa-tag',
            'quantity_changed' => 'fa-sort-numeric-asc',

            'employee_created' => 'fa-user-plus',
            'employee_updated' => 'fa-user',
            'employee_disabled' => 'fa-user-times',
            'employee_activated' => 'fa-user-check',
            'employee_password_changed' => 'fa-key',
            'telegram_id_changed' => 'fa-telegram',

            'risk_level_changed' => 'fa-shield',
            'blacklist_added' => 'fa-gavel',
            'blacklist_removed' => 'fa-check',
            'deposit_required_triggered' => 'fa-money',
            'security_override' => 'fa-unlock-alt',

            'commission_created' => 'fa-money',
            'commission_modified' => 'fa-pencil',
            'commission_paid' => 'fa-credit-card',
            'commission_deleted' => 'fa-trash',

            'recovery_task_created' => 'fa-clipboard',
            'recovery_task_assigned' => 'fa-handshake-o',
            'recovery_task_closed' => 'fa-check',
            'recovery_task_escalated' => 'fa-exclamation-triangle',
            'recovery_task_failed' => 'fa-times-circle',

            'confirm_from_telegram' => 'fa-telegram',
            'cancel_from_telegram' => 'fa-telegram',
            'edit_from_telegram' => 'fa-telegram',
            'reassign_from_telegram' => 'fa-telegram',
        ];

        return $icons[$action_type] ?? 'fa-circle-o';
    }
}
