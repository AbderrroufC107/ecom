<?php
if (!defined('EMPLOYEE_FUNCTIONS_LOADED')) {
    define('EMPLOYEE_FUNCTIONS_LOADED', true);

    require_once __DIR__ . '/telegram_bot.php';

    if (!function_exists('employee_ensure_tables')) {
        function employee_ensure_tables(PDO $pdo): void
        {
            $lock_file = __DIR__ . '/../cache/employee_tables.lock';
            if (file_exists($lock_file)) {
                return;
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_employee (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_employee_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            if (function_exists('telegram_ensure_tables')) {
                telegram_ensure_tables($pdo);
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tbl_order_assignment (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    employee_id INT NOT NULL,
                    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    assigned_by VARCHAR(100) NOT NULL DEFAULT 'auto',
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    UNIQUE KEY uk_assignment_order (order_id),
                    KEY idx_assignment_employee (employee_id),
                    KEY idx_assignment_status (status),
                    KEY idx_assignment_assigned (assigned_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            @file_put_contents($lock_file, '1');
        }
    }

    if (!function_exists('employee_get_all')) {
        function employee_get_all(PDO $pdo, bool $active_only = false): array
        {
            $sql = "SELECT * FROM tbl_employee";
            if ($active_only) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY full_name ASC, id ASC";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('employee_get_by_id')) {
        function employee_get_by_id(PDO $pdo, int $id): ?array
        {
            $stmt = $pdo->prepare("SELECT * FROM tbl_employee WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_find_by_email')) {
        function employee_find_by_email(PDO $pdo, string $email, int $exclude_id = 0): ?array
        {
            if ($exclude_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM tbl_employee WHERE email = ? AND id != ? LIMIT 1");
                $stmt->execute([$email, $exclude_id]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM tbl_employee WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_create')) {
        function employee_create(PDO $pdo, array $data): int
        {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_employee (full_name, email, password_hash, telegram_chat_id, is_active)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                trim($data['full_name']),
                trim($data['email']),
                password_hash($data['password'], PASSWORD_DEFAULT),
                trim($data['telegram_chat_id'] ?? ''),
                !empty($data['is_active']) ? 1 : 0
            ]);
            return (int) $pdo->lastInsertId();
        }
    }

    if (!function_exists('employee_update')) {
        function employee_update(PDO $pdo, int $id, array $data): void
        {
            $fields = [];
            $params = [];

            $fields[] = "full_name = ?";
            $params[] = trim($data['full_name']);

            $fields[] = "email = ?";
            $params[] = trim($data['email']);

            if (!empty($data['password'])) {
                $fields[] = "password_hash = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $fields[] = "telegram_chat_id = ?";
            $params[] = trim($data['telegram_chat_id'] ?? '');

            $fields[] = "is_active = ?";
            $params[] = !empty($data['is_active']) ? 1 : 0;

            $params[] = $id;
            $sql = "UPDATE tbl_employee SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
    }

    if (!function_exists('employee_delete')) {
        function employee_delete(PDO $pdo, int $id): void
        {
            $stmt = $pdo->prepare("DELETE FROM tbl_employee WHERE id = ?");
            $stmt->execute([$id]);
        }
    }

    if (!function_exists('employee_get_next_for_assignment')) {
        function employee_get_next_for_assignment(PDO $pdo): ?array
        {
            $active = $pdo->query("SELECT id, full_name FROM tbl_employee WHERE is_active = 1 ORDER BY id ASC");
            $employees = $active->fetchAll(PDO::FETCH_ASSOC);

            if (empty($employees)) {
                return null;
            }

            if (count($employees) === 1) {
                return $employees[0];
            }

            $stmt = $pdo->query("
                SELECT oa.employee_id, COUNT(*) AS assignment_count
                FROM tbl_order_assignment oa
                INNER JOIN tbl_employee e ON e.id = oa.employee_id AND e.is_active = 1
                GROUP BY oa.employee_id
                ORDER BY assignment_count ASC, oa.employee_id ASC
                LIMIT 1
            ");
            $least_assigned = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($least_assigned) {
                foreach ($employees as $emp) {
                    if ((int) $emp['id'] === (int) $least_assigned['employee_id']) {
                        return $emp;
                    }
                }
            }

            return $employees[0];
        }
    }

    if (!function_exists('employee_assign_order')) {
        function employee_assign_order(PDO $pdo, int $order_id, ?int $employee_id = null, string $assigned_by = 'auto'): ?int
        {
            $check = $pdo->prepare("SELECT id FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            $check->execute([$order_id]);
            if ($check->fetch()) {
                return null;
            }

            $order_check = $pdo->prepare("SELECT id FROM tbl_order WHERE id = ? LIMIT 1");
            $order_check->execute([$order_id]);
            if (!$order_check->fetch()) {
                return null;
            }

            if ($employee_id === null) {
                $next = employee_get_next_for_assignment($pdo);
                if ($next === null) {
                    return null;
                }
                $employee_id = (int) $next['id'];
            } else {
                $emp = employee_get_by_id($pdo, $employee_id);
                if ($emp === null || empty($emp['is_active'])) {
                    return null;
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO tbl_order_assignment (order_id, employee_id, assigned_by)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$order_id, $employee_id, $assigned_by]);
            $assignment_id = (int) $pdo->lastInsertId();

            telegram_ensure_tables($pdo);
            telegram_notify_assignment($pdo, $order_id, $employee_id);

            return $assignment_id;
        }
    }

    if (!function_exists('employee_reassign_order')) {
        function employee_reassign_order(PDO $pdo, int $order_id, int $new_employee_id, string $changed_by = 'manual'): bool
        {
            $emp = employee_get_by_id($pdo, $new_employee_id);
            if ($emp === null || empty($emp['is_active'])) {
                return false;
            }

            $existing = $pdo->prepare("SELECT id, status FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            $existing->execute([$order_id]);
            $current = $existing->fetch(PDO::FETCH_ASSOC);

            if ($current) {
                if ($current['status'] === 'reassigned') {
                    $stmt = $pdo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                    $stmt->execute([$new_employee_id, $changed_by, $order_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                    $stmt->execute([$new_employee_id, $changed_by, $order_id]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO tbl_order_assignment (order_id, employee_id, assigned_by) VALUES (?, ?, ?)");
                $stmt->execute([$order_id, $new_employee_id, $changed_by]);
            }

            telegram_ensure_tables($pdo);
            telegram_notify_assignment($pdo, $order_id, $new_employee_id);

            return true;
        }
    }

    if (!function_exists('employee_auto_assign_unassigned')) {
        function employee_auto_assign_unassigned(PDO $pdo, int $limit = 50): int
        {
            $count = 0;
            $limit_int = (int) $limit;
            $stmt = $pdo->prepare("
                SELECT o.id FROM tbl_order o
                LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id
                WHERE oa.id IS NULL
                ORDER BY o.id ASC
                LIMIT $limit_int
            ");
            $stmt->execute();
            $unassigned = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($unassigned as $order) {
                $assigned = employee_assign_order($pdo, (int) $order['id']);
                if ($assigned !== null) {
                    $count++;
                }
            }

            return $count;
        }
    }

    if (!function_exists('employee_get_assignment_for_order')) {
        function employee_get_assignment_for_order(PDO $pdo, int $order_id): ?array
        {
            $stmt = $pdo->prepare("
                SELECT oa.*, e.full_name AS employee_name, e.email AS employee_email, e.is_active AS employee_active
                FROM tbl_order_assignment oa
                LEFT JOIN tbl_employee e ON e.id = oa.employee_id
                WHERE oa.order_id = ?
                LIMIT 1
            ");
            $stmt->execute([$order_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_get_stats')) {
        function employee_get_stats(PDO $pdo, int $employee_id): array
        {
            $stats = [
                'total_assigned' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'returned' => 0
            ];

            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) AS total_assigned,
                    SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned
                FROM tbl_order_assignment oa
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE oa.employee_id = ? AND oa.status = 'active'
            ");
            $stmt->execute([$employee_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $stats = array_merge($stats, [
                    'total_assigned' => (int) $row['total_assigned'],
                    'pending' => (int) $row['pending'],
                    'confirmed' => (int) $row['confirmed'],
                    'completed' => (int) $row['completed'],
                    'cancelled' => (int) $row['cancelled'],
                    'returned' => (int) $row['returned']
                ]);
            }

            return $stats;
        }
    }

    if (!function_exists('employee_get_all_stats')) {
        function employee_get_all_stats(PDO $pdo): array
        {
            $stmt = $pdo->query("
                SELECT
                    e.id, e.full_name, e.email, e.is_active,
                    COUNT(oa.id) AS total_assigned,
                    SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned
                FROM tbl_employee e
                LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
                LEFT JOIN tbl_order o ON o.id = oa.order_id
                WHERE e.is_active = 1
                GROUP BY e.id, e.full_name, e.email, e.is_active
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as &$row) {
                $row['total_assigned'] = (int) $row['total_assigned'];
                $row['pending'] = (int) $row['pending'];
                $row['confirmed'] = (int) $row['confirmed'];
                $row['completed'] = (int) $row['completed'];
                $row['cancelled'] = (int) $row['cancelled'];
                $row['returned'] = (int) $row['returned'];
            }
            return $result;
        }
    }

    if (!function_exists('employee_search')) {
        function employee_search(PDO $pdo, string $query): array
        {
            $q = '%' . $query . '%';
            $stmt = $pdo->prepare("
                SELECT * FROM tbl_employee
                WHERE full_name LIKE ? OR email LIKE ?
                ORDER BY full_name ASC, id ASC
            ");
            $stmt->execute([$q, $q]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('employee_get_assigned_orders')) {
        function employee_get_assigned_orders(PDO $pdo, int $employee_id, string $status_filter = ''): array
        {
            $sql = "
                SELECT o.*, oa.assigned_at, oa.assigned_by
                FROM tbl_order_assignment oa
                INNER JOIN tbl_order o ON o.id = oa.order_id
                WHERE oa.employee_id = ? AND oa.status = 'active'
            ";
            $params = [$employee_id];

            if ($status_filter !== '') {
                $sql .= " AND o.order_status = ?";
                $params[] = $status_filter;
            }

            $sql .= " ORDER BY o.order_date DESC, o.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('employee_get_unassigned_orders_count')) {
        function employee_get_unassigned_orders_count(PDO $pdo): int
        {
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM tbl_order o
                LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id
                WHERE oa.id IS NULL
            ");
            return (int) $stmt->fetchColumn();
        }
    }

    if (!function_exists('employee_get_current_admin_employee')) {
        function employee_get_current_admin_employee(PDO $pdo): ?array
        {
            if (empty($_SESSION['user']['email'])) {
                return null;
            }
            $stmt = $pdo->prepare("SELECT * FROM tbl_employee WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$_SESSION['user']['email']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

}
