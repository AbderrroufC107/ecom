<?php
$file = 'c:/xampp/htdocs/ecom/admin/inc/employee_functions.php';
$content = file_get_contents($file);

// Replace employee_get_next_for_assignment
$get_next_search = <<<EOF
    if (!function_exists('employee_get_next_for_assignment')) {
        function employee_get_next_for_assignment(PDO \$pdo): ?array
        {
            \$active = \$pdo->query("SELECT id, full_name FROM tbl_employee WHERE is_active = 1 ORDER BY id ASC");
            \$employees = \$active->fetchAll(PDO::FETCH_ASSOC);

            if (empty(\$employees)) {
                return null;
            }

            if (count(\$employees) === 1) {
                return \$employees[0];
            }

            \$stmt = \$pdo->query("
                SELECT e.id AS employee_id, COUNT(oa.order_id) AS assignment_count
                FROM tbl_employee e
                LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
                WHERE e.is_active = 1
                GROUP BY e.id
                ORDER BY assignment_count ASC, e.id ASC
                LIMIT 1
            ");
            \$next = \$stmt->fetch(PDO::FETCH_ASSOC);

            if (\$next) {
                // Return employee details
                foreach (\$employees as \$emp) {
                    if (\$emp['id'] == \$next['employee_id']) {
                        return \$emp;
                    }
                }
            }

            return \$employees[0];
        }
    }
EOF;

$get_next_replace = <<<EOF
    if (!function_exists('employee_get_next_for_assignment')) {
        function employee_get_next_for_assignment(PDO \$pdo): ?array
        {
            // Gather all participants: Employees + Admins
            \$stmt = \$pdo->query("
                SELECT 
                    'employee' AS type, e.id AS ref_id, e.full_name, e.assignment_weight, e.max_active_orders,
                    (SELECT COUNT(oa1.id) FROM tbl_order_assignment oa1 JOIN tbl_order o1 ON o1.id = oa1.order_id WHERE oa1.employee_id = e.id AND oa1.status = 'active' AND o1.order_status NOT IN ('Delivered', 'Returned', 'Cancelled')) AS current_active_orders,
                    (SELECT COUNT(oa2.id) FROM tbl_order_assignment oa2 WHERE oa2.employee_id = e.id AND oa2.status = 'active') AS total_assigned
                FROM tbl_employee e
                WHERE e.is_active = 1 AND e.availability_status = 'Available'
                
                UNION ALL
                
                SELECT 
                    'user' AS type, u.id AS ref_id, u.full_name, u.assignment_weight, u.max_active_orders,
                    (SELECT COUNT(oa3.id) FROM tbl_order_assignment oa3 JOIN tbl_order o3 ON o3.id = oa3.order_id WHERE oa3.user_id = u.id AND oa3.status = 'active' AND o3.order_status NOT IN ('Delivered', 'Returned', 'Cancelled')) AS current_active_orders,
                    (SELECT COUNT(oa4.id) FROM tbl_order_assignment oa4 WHERE oa4.user_id = u.id AND oa4.status = 'active') AS total_assigned
                FROM tbl_user u
                WHERE u.participate_in_assignment = 1 AND u.availability_status = 'Available'
            ");
            \$participants = \$stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty(\$participants)) {
                return null;
            }

            // Filter out those who reached their max_active_orders
            \$eligible = [];
            foreach (\$participants as \$p) {
                if (\$p['current_active_orders'] < \$p['max_active_orders']) {
                    \$eligible[] = \$p;
                }
            }

            if (empty(\$eligible)) {
                // Everyone is maxed out, return null to pause assignment
                return null;
            }

            // Calculate Saturation and find lowest
            \$best_candidate = null;
            \$lowest_saturation = -1;

            foreach (\$eligible as \$p) {
                \$weight = (int)\$p['assignment_weight'] > 0 ? (int)\$p['assignment_weight'] : 1;
                \$saturation = (int)\$p['total_assigned'] / \$weight;
                
                if (\$best_candidate === null || \$saturation < \$lowest_saturation) {
                    \$best_candidate = \$p;
                    \$lowest_saturation = \$saturation;
                }
            }

            if (\$best_candidate) {
                // Return a generic format that assign_order understands
                return [
                    'id' => \$best_candidate['ref_id'],
                    'type' => \$best_candidate['type'],
                    'full_name' => \$best_candidate['full_name']
                ];
            }

            return null;
        }
    }
EOF;
$content = str_replace($get_next_search, $get_next_replace, $content);

// Replace employee_assign_order
$assign_search = <<<EOF
        function employee_assign_order(PDO \$pdo, int \$order_id, ?int \$employee_id = null, string \$assigned_by = 'auto'): ?int
        {
            \$check = \$pdo->prepare("SELECT id FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            \$check->execute([\$order_id]);
            if (\$check->fetch()) {
                return null;
            }

            \$order_check = \$pdo->prepare("SELECT id FROM tbl_order WHERE id = ? LIMIT 1");
            \$order_check->execute([\$order_id]);
            if (!\$order_check->fetch()) {
                return null;
            }

            if (\$employee_id === null) {
                \$next = employee_get_next_for_assignment(\$pdo);
                if (\$next === null) {
                    return null;
                }
                \$employee_id = (int) \$next['id'];
            } else {
                \$emp = employee_get_by_id(\$pdo, \$employee_id);
                if (\$emp === null || empty(\$emp['is_active'])) {
                    return null;
                }
            }

            \$stmt = \$pdo->prepare("
                INSERT INTO tbl_order_assignment (order_id, employee_id, assigned_by)
                VALUES (?, ?, ?)
            ");
            \$stmt->execute([\$order_id, \$employee_id, \$assigned_by]);
            \$assignment_id = (int) \$pdo->lastInsertId();

            if (\$assignment_id > 0) {
                if (class_exists('EventManager')) {
                    EventManager::dispatch('OrderAssigned', \$pdo, \$order_id, \$employee_id);
                } else {
                    telegram_ensure_tables(\$pdo);
                    telegram_notify_assignment(\$pdo, \$order_id, \$employee_id);
                }
            }

            return \$assignment_id;
        }
EOF;

$assign_replace = <<<EOF
        function employee_assign_order(PDO \$pdo, int \$order_id, ?int \$employee_id = null, string \$assigned_by = 'auto', ?int \$user_id = null): ?int
        {
            \$check = \$pdo->prepare("SELECT id FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            \$check->execute([\$order_id]);
            if (\$check->fetch()) {
                return null;
            }

            \$order_check = \$pdo->prepare("SELECT id FROM tbl_order WHERE id = ? LIMIT 1");
            \$order_check->execute([\$order_id]);
            if (!\$order_check->fetch()) {
                return null;
            }

            if (\$employee_id === null && \$user_id === null) {
                \$next = employee_get_next_for_assignment(\$pdo);
                if (\$next === null) {
                    return null; // Nobody available or capacity reached
                }
                if (\$next['type'] === 'user') {
                    \$user_id = (int) \$next['id'];
                } else {
                    \$employee_id = (int) \$next['id'];
                }
            } else if (\$employee_id !== null) {
                \$emp = employee_get_by_id(\$pdo, \$employee_id);
                if (\$emp === null || empty(\$emp['is_active'])) {
                    return null;
                }
            }

            \$stmt = \$pdo->prepare("
                INSERT INTO tbl_order_assignment (order_id, employee_id, user_id, assigned_by)
                VALUES (?, ?, ?, ?)
            ");
            \$stmt->execute([\$order_id, \$employee_id, \$user_id, \$assigned_by]);
            \$assignment_id = (int) \$pdo->lastInsertId();

            if (\$assignment_id > 0) {
                // If it's an employee, trigger telegram
                if (\$employee_id !== null) {
                    if (class_exists('EventManager')) {
                        EventManager::dispatch('OrderAssigned', \$pdo, \$order_id, \$employee_id);
                    } else {
                        if (function_exists('telegram_ensure_tables')) {
                            telegram_ensure_tables(\$pdo);
                            telegram_notify_assignment(\$pdo, \$order_id, \$employee_id);
                        }
                    }
                }
            }

            return \$assignment_id;
        }
EOF;
$content = str_replace($assign_search, $assign_replace, $content);


// Replace employee_reassign_order
$reassign_search = <<<EOF
        function employee_reassign_order(PDO \$pdo, int \$order_id, int \$new_employee_id, string \$changed_by = 'manual'): bool
        {
            \$existing = \$pdo->prepare("SELECT id, status FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            \$existing->execute([\$order_id]);
            \$row = \$existing->fetch(PDO::FETCH_ASSOC);

            if (\$row) {
                if (\$row['status'] === 'active') {
                    \$stmt = \$pdo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                    \$stmt->execute([\$new_employee_id, \$changed_by, \$order_id]);
                } else {
                    \$stmt = \$pdo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                    \$stmt->execute([\$new_employee_id, \$changed_by, \$order_id]);
                }
            } else {
                \$stmt = \$pdo->prepare("INSERT INTO tbl_order_assignment (order_id, employee_id, assigned_by) VALUES (?, ?, ?)");
                \$stmt->execute([\$order_id, \$new_employee_id, \$changed_by]);
            }
            return true;
        }
EOF;

$reassign_replace = <<<EOF
        function employee_reassign_order(PDO \$pdo, int \$order_id, int \$new_employee_id, string \$changed_by = 'manual', ?int \$new_user_id = null): bool
        {
            \$existing = \$pdo->prepare("SELECT id, status FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            \$existing->execute([\$order_id]);
            \$row = \$existing->fetch(PDO::FETCH_ASSOC);

            \$emp_id = \$new_employee_id > 0 ? \$new_employee_id : null;
            \$usr_id = \$new_user_id > 0 ? \$new_user_id : null;

            if (\$row) {
                \$stmt = \$pdo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, user_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                \$stmt->execute([\$emp_id, \$usr_id, \$changed_by, \$order_id]);
            } else {
                \$stmt = \$pdo->prepare("INSERT INTO tbl_order_assignment (order_id, employee_id, user_id, assigned_by) VALUES (?, ?, ?, ?)");
                \$stmt->execute([\$order_id, \$emp_id, \$usr_id, \$changed_by]);
            }
            return true;
        }
EOF;
$content = str_replace($reassign_search, $reassign_replace, $content);

file_put_contents($file, $content);
echo "employee_functions.php logic updated successfully.\n";
