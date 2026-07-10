<?php
if (!defined('EMPLOYEE_FUNCTIONS_LOADED')) {
    define('EMPLOYEE_FUNCTIONS_LOADED', true);

    if (file_exists(__DIR__ . '/telegram_bot.php')) {
        require_once __DIR__ . '/telegram_bot.php';
    }
    if (file_exists(__DIR__ . '/../telegram/bootstrap.php')) {
        require_once __DIR__ . '/../telegram/bootstrap.php';
    }

    if (!function_exists('employee_safe_alter')) {
        function employee_safe_alter(string $sql): void
        { global $dbRepo;
            try {
                $dbRepo->executeCommand($sql);
            } catch (Exception $e) {
                // Column/index already exists, or the target table isn't installed yet
            }
        }
    }

    if (!function_exists('employee_ensure_tables')) {
        function employee_ensure_tables(PDO $pdo): void
        { global $dbRepo;
            // v5 folds in columns (manager_id, commission_per_order, assignment_weight,
            // availability_status, max_active_orders on tbl_employee; manager_id on
            // tbl_order; the assignment fields on tbl_user) that earlier versions only
            // ever added via one-off scripts under scratch/, so a fresh install that
            // never ran those scripts was missing them entirely.
            $lock_file = __DIR__ . '/../cache/employee_tables_v5.lock';
            if (file_exists($lock_file)) {
                return;
            }

            $dbRepo->executeCommand("
                CREATE TABLE IF NOT EXISTS tbl_employee (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    manager_id INT DEFAULT NULL,
                    full_name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    telegram_chat_id VARCHAR(255) NOT NULL DEFAULT '',
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    assignment_weight INT NOT NULL DEFAULT 1,
                    availability_status VARCHAR(50) NOT NULL DEFAULT 'Available',
                    max_active_orders INT NOT NULL DEFAULT 50,
                    commission_per_order DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_employee_email (email),
                    KEY idx_employee_manager (manager_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            if (function_exists('telegram_ensure_tables')) {
                telegram_ensure_tables($pdo);
            }

            $dbRepo->executeCommand("
                CREATE TABLE IF NOT EXISTS tbl_order_assignment (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    employee_id INT NULL DEFAULT NULL,
                    user_id INT NULL DEFAULT NULL,
                    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    assigned_by VARCHAR(100) NOT NULL DEFAULT 'auto',
                    assignment_source VARCHAR(50) NOT NULL DEFAULT 'wrr',
                    status VARCHAR(50) NOT NULL DEFAULT 'active',
                    is_paid TINYINT(1) NOT NULL DEFAULT 0,
                    payment_id INT NULL DEFAULT NULL,
                    UNIQUE KEY uk_assignment_order (order_id),
                    KEY idx_assignment_employee (employee_id),
                    KEY idx_assignment_status (status),
                    KEY idx_assignment_assigned (assigned_at),
                    KEY idx_assignment_is_paid (is_paid),
                    KEY idx_assignment_payment_id (payment_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $dbRepo->executeCommand("
                CREATE TABLE IF NOT EXISTS tbl_employee_products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL DEFAULT 1,
                    employee_id INT NOT NULL,
                    product_id INT NOT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_emp_prod (tenant_id, employee_id, product_id),
                    KEY idx_emp_prod_tenant (tenant_id),
                    KEY idx_emp_prod_emp (employee_id),
                    KEY idx_emp_prod_prod (product_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $dbRepo->executeCommand("
                CREATE TABLE IF NOT EXISTS tbl_product_assignment (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL DEFAULT 1,
                    product_id INT NOT NULL UNIQUE,
                    employee_id INT NOT NULL DEFAULT 0,
                    assignment_mode VARCHAR(20) NOT NULL DEFAULT 'queue',
                    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_pa_tenant (tenant_id),
                    KEY idx_pa_prod_enabled (product_id, is_enabled),
                    KEY idx_pa_emp (employee_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Self-healing alters for installs created before this schema existed
            // (CREATE TABLE IF NOT EXISTS above is a no-op there, so these columns
            // would otherwise stay missing forever on any pre-v5 database).
            employee_safe_alter("ALTER TABLE tbl_employee ADD COLUMN manager_id INT DEFAULT NULL AFTER id");
            employee_safe_alter("ALTER TABLE tbl_employee ADD COLUMN assignment_weight INT NOT NULL DEFAULT 1 AFTER is_active");
            employee_safe_alter("ALTER TABLE tbl_employee ADD COLUMN availability_status VARCHAR(50) NOT NULL DEFAULT 'Available' AFTER assignment_weight");
            employee_safe_alter("ALTER TABLE tbl_employee ADD COLUMN max_active_orders INT NOT NULL DEFAULT 50 AFTER availability_status");
            employee_safe_alter("ALTER TABLE tbl_employee ADD COLUMN commission_per_order DECIMAL(12,2) NOT NULL DEFAULT 0.00");
            employee_safe_alter("ALTER TABLE tbl_employee ADD INDEX idx_employee_manager (manager_id)");

            employee_safe_alter("ALTER TABLE tbl_order_assignment ADD COLUMN assignment_source VARCHAR(50) NOT NULL DEFAULT 'wrr' AFTER assigned_by");
            employee_safe_alter("ALTER TABLE tbl_order_assignment MODIFY COLUMN employee_id INT NULL DEFAULT NULL");
            employee_safe_alter("ALTER TABLE tbl_order_assignment ADD COLUMN user_id INT NULL DEFAULT NULL AFTER employee_id");
            employee_safe_alter("ALTER TABLE tbl_order_assignment ADD COLUMN is_paid TINYINT(1) NOT NULL DEFAULT 0");
            employee_safe_alter("ALTER TABLE tbl_order_assignment ADD COLUMN payment_id INT NULL DEFAULT NULL");
            employee_safe_alter("ALTER TABLE tbl_order_assignment ADD INDEX idx_assignment_is_paid (is_paid)");
            employee_safe_alter("ALTER TABLE tbl_order_assignment ADD INDEX idx_assignment_payment_id (payment_id)");

            employee_safe_alter("ALTER TABLE tbl_employee_products ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id");
            employee_safe_alter("ALTER TABLE tbl_product_assignment ADD COLUMN tenant_id INT NOT NULL DEFAULT 1 AFTER id");
            employee_safe_alter("ALTER TABLE tbl_employee_products ADD INDEX idx_emp_prod_tenant (tenant_id)");
            employee_safe_alter("ALTER TABLE tbl_product_assignment ADD INDEX idx_pa_tenant (tenant_id)");

            // tbl_order / tbl_user are core tables owned outside this file, but the
            // assignment feature reads columns on them that historically were only
            // added by running scratch/multi_manager_migration.sql / update_schema_p9.php
            // by hand.
            employee_safe_alter("ALTER TABLE tbl_order ADD COLUMN manager_id INT DEFAULT NULL AFTER id");
            employee_safe_alter("ALTER TABLE tbl_order ADD INDEX idx_order_manager (manager_id)");

            employee_safe_alter("ALTER TABLE tbl_user ADD COLUMN participate_in_assignment TINYINT(1) NOT NULL DEFAULT 0 AFTER role");
            employee_safe_alter("ALTER TABLE tbl_user ADD COLUMN assignment_weight INT NOT NULL DEFAULT 1 AFTER participate_in_assignment");
            employee_safe_alter("ALTER TABLE tbl_user ADD COLUMN availability_status VARCHAR(50) NOT NULL DEFAULT 'Available' AFTER assignment_weight");
            employee_safe_alter("ALTER TABLE tbl_user ADD COLUMN max_active_orders INT NOT NULL DEFAULT 50 AFTER availability_status");

            // Normalize legacy lowercase values that a case-mismatch bug in
            // admin/profile-edit.php used to write ('available'/'busy'/'away'),
            // which the WRR assignment query never matched since it only ever
            // looks for the capitalized 'Available'.
            employee_safe_alter("UPDATE tbl_user SET availability_status = 'Available' WHERE availability_status = 'available'");
            employee_safe_alter("UPDATE tbl_user SET availability_status = 'Busy' WHERE availability_status = 'busy'");
            employee_safe_alter("UPDATE tbl_user SET availability_status = 'Away' WHERE availability_status = 'away'");

            @file_put_contents($lock_file, '1');
        }
    }

    if (!function_exists('employee_get_all')) {
        function employee_get_all(PDO $pdo, bool $active_only = false, ?int $manager_id = null): array
        { global $dbRepo;
            $conditions = [];
            if ($active_only) {
                $conditions[] = "is_active = 1";
            }
            if ($manager_id !== null && $manager_id > 0) {
                $conditions[] = "manager_id = " . (int) $manager_id;
            } elseif ($manager_id === 0) {
                $conditions[] = "(manager_id IS NULL OR manager_id = 0)";
            }
            $sql = "SELECT * FROM tbl_employee";
            if (!empty($conditions)) {
                $sql .= " WHERE " . implode(" AND ", $conditions);
            }
            $sql .= " ORDER BY full_name ASC, id ASC";
            $stmt = $dbRepo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('employee_get_by_id')) {
        function employee_get_by_id(PDO $pdo, int $id): ?array
        { global $dbRepo;
            $stmt = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_find_by_email')) {
        function employee_find_by_email(PDO $pdo, string $email, int $exclude_id = 0): ?array
        { global $dbRepo;
            if ($exclude_id > 0) {
                $stmt = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE email = ? AND id != ? LIMIT 1");
                $stmt->execute([$email, $exclude_id]);
            } else {
                $stmt = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_create')) {
        function employee_create(PDO $pdo, array $data): int
        { global $dbRepo;
            $full_name = mb_substr(trim(strip_tags($data['full_name'])), 0, 255);
            $email = mb_substr(trim($data['email']), 0, 255);
            $password = $data['password'];
            $telegram_chat_id = mb_substr(trim(strip_tags($data['telegram_chat_id'] ?? '')), 0, 255);
            $is_active = !empty($data['is_active']) ? 1 : 0;
            $manager_id = isset($data['manager_id']) ? (int) $data['manager_id'] : null;

            if ($full_name === '' || $email === '' || $password === '') {
                throw new InvalidArgumentException('بيانات الموظف غير مكتملة.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('البريد الإلكتروني غير صالح.');
            }

            $stmt = $dbRepo->prepare("
                INSERT INTO tbl_employee (full_name, email, password_hash, telegram_chat_id, is_active, commission_per_order, manager_id, assignment_weight, availability_status, max_active_orders)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $full_name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $telegram_chat_id,
                $is_active,
                (float) ($data['commission_per_order'] ?? 0.00),
                $manager_id,
                max(1, (int) ($data['assignment_weight'] ?? 1)),
                $data['availability_status'] ?? 'Available',
                max(1, (int) ($data['max_active_orders'] ?? 50))
            ]);
            $inserted_id = (int) $dbRepo->lastInsertId();

            if ($inserted_id > 0 && class_exists('EventManager')) {
                EventManager::dispatch('EmployeeCreated', $pdo, $inserted_id);
            }

            return $inserted_id;
        }
    }

    if (!function_exists('employee_update')) {
        function employee_update(PDO $pdo, int $id, array $data): void
        { global $dbRepo;
            $fields = [];
            $params = [];

            $fields[] = "full_name = ?";
            $params[] = mb_substr(trim(strip_tags($data['full_name'])), 0, 255);

            $fields[] = "email = ?";
            $params[] = mb_substr(trim($data['email']), 0, 255);

            if (!empty($data['password'])) {
                $fields[] = "password_hash = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            $fields[] = "telegram_chat_id = ?";
            $params[] = mb_substr(trim(strip_tags($data['telegram_chat_id'] ?? '')), 0, 255);

            $fields[] = "is_active = ?";
            $params[] = !empty($data['is_active']) ? 1 : 0;

            if (isset($data['commission_per_order'])) {
                $fields[] = "commission_per_order = ?";
                $params[] = (float) $data['commission_per_order'];
            }

            if (isset($data['assignment_weight'])) {
                $fields[] = "assignment_weight = ?";
                $params[] = max(1, (int) $data['assignment_weight']);
            }

            if (isset($data['availability_status'])) {
                $fields[] = "availability_status = ?";
                $params[] = $data['availability_status'];
            }

            if (isset($data['max_active_orders'])) {
                $fields[] = "max_active_orders = ?";
                $params[] = max(1, (int) $data['max_active_orders']);
            }

            $params[] = $id;
            $sql = "UPDATE tbl_employee SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $dbRepo->prepare($sql);
            $stmt->execute($params);
        }
    }

    if (!function_exists('employee_delete')) {
        function employee_delete(PDO $pdo, int $id): void
        { global $dbRepo;
            // Clean up dependents so deleting an employee never leaves orphan rows
            // (orphan assignments used to survive and show up as "موظف محذوف #N").
            $ownTx = false;
            if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $ownTx = true; }
            try {
                // Free their orders back to the unassigned pool — but keep any
                // assignment that was already paid, to preserve payout history.
                $dbRepo->prepare("DELETE FROM tbl_order_assignment WHERE employee_id = ? AND is_paid = 0")->execute([$id]);
                $dbRepo->prepare("UPDATE tbl_order_assignment SET status = 'employee_deleted' WHERE employee_id = ? AND is_paid = 1")->execute([$id]);

                // Remove product restrictions and exclusive product assignments
                $dbRepo->prepare("DELETE FROM tbl_employee_products WHERE employee_id = ?")->execute([$id]);
                $dbRepo->prepare("UPDATE tbl_product_assignment SET employee_id = 0, is_enabled = 0 WHERE employee_id = ?")->execute([$id]);

                // Finally, delete the employee
                $dbRepo->prepare("DELETE FROM tbl_employee WHERE id = ?")->execute([$id]);

                if ($ownTx) { $pdo->commit(); }
            } catch (\Throwable $e) {
                if ($ownTx) { $pdo->rollBack(); }
                throw $e;
            }
        }
    }

    if (!function_exists('employee_get_allowed_products')) {
        function employee_get_allowed_products(PDO $pdo, int $employee_id): array
        { global $dbRepo;
            $stmt = $dbRepo->prepare("SELECT product_id FROM tbl_employee_products WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
    }

    if (!function_exists('employee_has_product_restriction')) {
        function employee_has_product_restriction(PDO $pdo, int $employee_id): bool
        { global $dbRepo;
            $stmt = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_employee_products WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            return (int) $stmt->fetchColumn() > 0;
        }
    }

    if (!function_exists('employee_can_access_product')) {
        function employee_can_access_product(PDO $pdo, int $employee_id, int $product_id): bool
        { global $dbRepo;
            if (!employee_has_product_restriction($pdo, $employee_id)) {
                return true;
            }
            $stmt = $dbRepo->prepare("SELECT 1 FROM tbl_employee_products WHERE employee_id = ? AND product_id = ? LIMIT 1");
            $stmt->execute([$employee_id, $product_id]);
            return (bool) $stmt->fetchColumn();
        }
    }

    if (!function_exists('employee_can_access_order')) {
        function employee_can_access_order(PDO $pdo, int $employee_id, int $order_id): bool
        { global $dbRepo;
            if (!employee_has_product_restriction($pdo, $employee_id)) {
                return true;
            }
            $stmt = $dbRepo->prepare("SELECT product_id FROM tbl_order WHERE id = ? LIMIT 1");
            $stmt->execute([$order_id]);
            $product_id = (int) $stmt->fetchColumn();
            if ($product_id <= 0) {
                return true;
            }
            return employee_can_access_product($pdo, $employee_id, $product_id);
        }
    }

    if (!function_exists('employee_update_products')) {
        function employee_update_products(PDO $pdo, int $employee_id, array $product_ids): void
        { global $dbRepo;
            $stmt = $dbRepo->prepare("DELETE FROM tbl_employee_products WHERE employee_id = ?");
            $stmt->execute([$employee_id]);

            if (!empty($product_ids)) {
                $insert = $dbRepo->prepare("INSERT IGNORE INTO tbl_employee_products (employee_id, product_id) VALUES (?, ?)");
                foreach ($product_ids as $pid) {
                    $pid_int = (int) $pid;
                    if ($pid_int > 0) {
                        $insert->execute([$employee_id, $pid_int]);
                    }
                }
            }
        }
    }

    if (!function_exists('employee_get_next_for_assignment')) {
        function employee_get_next_for_assignment(PDO $pdo, ?int $product_id = null, ?int $manager_id = null): ?array
        { global $dbRepo;
            $managerFilter = '';
            if ($manager_id !== null && $manager_id > 0) {
                $mid = (int) $manager_id;
                $managerFilter = " AND e.manager_id = {$mid}";
            } else {
                // No manager scope: allow ALL employees to be picked by WRR.
                $managerFilter = "";
            }
            $emp_sql = "
                SELECT 'employee' AS type, e.id AS ref_id, e.full_name, e.assignment_weight, e.max_active_orders,
                (SELECT COUNT(oa1.id) FROM tbl_order_assignment oa1 JOIN tbl_order o1 ON o1.id = oa1.order_id WHERE oa1.employee_id = e.id AND oa1.status = 'active' AND o1.order_status NOT IN ('Delivered', 'Completed', 'Returned', 'Cancelled')) AS current_active_orders,
                (SELECT COUNT(oa2.id) FROM tbl_order_assignment oa2 WHERE oa2.employee_id = e.id AND oa2.status = 'active') AS total_assigned
                FROM tbl_employee e
                WHERE e.is_active = 1 AND e.availability_status = 'Available' {$managerFilter}
            ";
            if ($product_id !== null && $product_id > 0) {
                $pid = (int) $product_id;
                $emp_sql .= " AND (
                    NOT EXISTS (SELECT 1 FROM tbl_employee_products ep WHERE ep.employee_id = e.id)
                    OR EXISTS (SELECT 1 FROM tbl_employee_products ep2 WHERE ep2.employee_id = e.id AND ep2.product_id = {$pid})
                )";
            }
            $stmt1 = $dbRepo->query($emp_sql);
            $emp_participants = $stmt1->fetchAll(PDO::FETCH_ASSOC);

            // Fetch admins/managers eligible for this order's manager scope.
            // Without this scoping, any participating user (including a different
            // manager entirely) could be handed an order that belongs to another
            // manager's team, breaking the same manager/employee isolation that
            // the employee-side query above already enforces.
            $userManagerFilter = '';
            if ($manager_id !== null && $manager_id > 0) {
                // A manager-scoped order may only fall to that manager personally.
                $userManagerFilter = ' AND u.id = ' . (int) $manager_id;
            } else {
                // No manager on the order: distribute among Managers/Admins, not Super Admin.
                $userManagerFilter = " AND u.role IN ('Admin', 'Manager')";
            }
            $stmt2 = $dbRepo->query("
                SELECT 'user' AS type, u.id AS ref_id, u.full_name, u.assignment_weight, u.max_active_orders,
                (SELECT COUNT(oa3.id) FROM tbl_order_assignment oa3 JOIN tbl_order o3 ON o3.id = oa3.order_id WHERE oa3.user_id = u.id AND oa3.status = 'active' AND o3.order_status NOT IN ('Delivered', 'Completed', 'Returned', 'Cancelled')) AS current_active_orders,
                (SELECT COUNT(oa4.id) FROM tbl_order_assignment oa4 WHERE oa4.user_id = u.id AND oa4.status = 'active') AS total_assigned
                FROM tbl_user u
                WHERE u.participate_in_assignment = 1 AND u.availability_status = 'Available'{$userManagerFilter}
            ");
            $user_participants = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $participants = array_merge($emp_participants, $user_participants);

            if (empty($participants)) {
                return null;
            }

            // Filter out those who reached their max_active_orders
            $eligible = [];
            foreach ($participants as $p) {
                if ($p['current_active_orders'] < $p['max_active_orders']) {
                    $eligible[] = $p;
                }
            }

            if (empty($eligible)) {
                return null;
            }

            // Calculate Saturation and find lowest
            $best_candidate = null;
            $lowest_saturation = -1;

            foreach ($eligible as $p) {
                $weight = (int)$p['assignment_weight'] > 0 ? (int)$p['assignment_weight'] : 1;
                $saturation = (int)$p['total_assigned'] / $weight;
                
                if ($best_candidate === null || $saturation < $lowest_saturation) {
                    $best_candidate = $p;
                    $lowest_saturation = $saturation;
                }
            }

            if ($best_candidate) {
                return [
                    'id' => $best_candidate['ref_id'],
                    'type' => $best_candidate['type'],
                    'full_name' => $best_candidate['full_name']
                ];
            }

            return null;
        }
    }

    if (!function_exists('assign_order_by_strategy')) {
        function assign_order_by_strategy(PDO $pdo, int $order_id, string $triggered_by = 'auto', ?int $manager_id = null): ?int
        { global $dbRepo;
            employee_ensure_tables($pdo);
            $started_tx = false;
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $started_tx = true;
            }

            try {
                // 1. Duplicate & Lock Protection
                $check = $dbRepo->prepare("SELECT id FROM tbl_order_assignment WHERE order_id = ? FOR UPDATE");
                $check->execute([$order_id]);
                if ($row = $check->fetch(PDO::FETCH_ASSOC)) {
                    if ($started_tx) {
                        $pdo->commit();
                    }
                    return (int) $row['id'];
                }

                $order_check = $dbRepo->prepare("SELECT id, product_id, manager_id FROM tbl_order WHERE id = ? FOR UPDATE");
                $order_check->execute([$order_id]);
                $order_row = $order_check->fetch(PDO::FETCH_ASSOC);
                if (!$order_row) {
                    if ($started_tx) {
                        $pdo->rollBack();
                    }
                    return null;
                }
                $order_product_id = (int) ($order_row['product_id'] ?? 0);
                if ($manager_id === null) {
                    $manager_id = !empty($order_row['manager_id']) ? (int) $order_row['manager_id'] : null;
                }

                // 2. Check Exclusive Assignment
                $is_exclusive = false;
                $exc_emp_id = 0;
                $exc_mode = 'queue';
                if ($order_product_id > 0) {
                    $stmt_exc = $dbRepo->prepare("SELECT employee_id, assignment_mode, is_enabled FROM tbl_product_assignment WHERE product_id = ? AND is_enabled = 1 LIMIT 1");
                    $stmt_exc->execute([$order_product_id]);
                    if ($exc_row = $stmt_exc->fetch(PDO::FETCH_ASSOC)) {
                        $is_exclusive = true;
                        $exc_emp_id = (int) $exc_row['employee_id'];
                        $exc_mode = $exc_row['assignment_mode'] ?? 'queue';
                    }
                }

                if ($is_exclusive) {
                    // Do NOT run WRR! Check exclusive employee availability
                    $stmt_emp = $dbRepo->prepare("
                        SELECT e.id, e.is_active, e.availability_status, e.max_active_orders,
                        (SELECT COUNT(oa1.id) FROM tbl_order_assignment oa1 JOIN tbl_order o1 ON o1.id = oa1.order_id WHERE oa1.employee_id = e.id AND oa1.status = 'active' AND o1.order_status NOT IN ('Delivered', 'Completed', 'Returned', 'Cancelled')) AS current_active_orders
                        FROM tbl_employee e
                        WHERE e.id = ?
                    ");
                    $stmt_emp->execute([$exc_emp_id]);
                    $emp = $stmt_emp->fetch(PDO::FETCH_ASSOC);

                    $is_available = ($emp && !empty($emp['is_active']) && ($emp['availability_status'] ?? '') === 'Available' && (int)$emp['current_active_orders'] < (int)$emp['max_active_orders']);
                    
                    if ($exc_mode === 'direct' && $is_available) {
                        $status = 'active';
                        $source = 'exclusive_direct';
                    } else {
                        $status = 'waiting';
                        $source = ($exc_mode === 'direct') ? 'exclusive_direct' : 'exclusive_queue';
                    }
                    $stmt_ins = $dbRepo->prepare("
                        INSERT INTO tbl_order_assignment (order_id, employee_id, assigned_by, assignment_source, status)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt_ins->execute([$order_id, $exc_emp_id, $triggered_by, $source, $status]);
                    $assignment_id = (int) $dbRepo->lastInsertId();

                    if ($started_tx) {
                        $pdo->commit();
                    }

                    if ($assignment_id > 0) {
                        if (function_exists('audit_log_system')) {
                            audit_log_system($pdo, 'order_assigned_exclusive', "Order #{$order_id} assigned exclusively to Employee #{$exc_emp_id} (Mode: {$exc_mode}, Source: {$source})");
                        }
                        if (class_exists('EventManager')) {
                            EventManager::dispatch('OrderAssigned', $pdo, $order_id, $exc_emp_id);
                        } else {
                            telegram_ensure_tables($pdo);
                            telegram_notify_assignment($pdo, $order_id, $exc_emp_id);
                        }
                    }
                    return $assignment_id;
                }

                // 3. Normal WRR Execution
                $assignment_id = employee_assign_order($pdo, $order_id, null, $triggered_by, true, $manager_id);

                // After WRR picks someone, stamp the order's manager_id so future
                // scoping (dashboard, notifications, claim logic) works. The pick
                // may be an employee (attribute to *their* manager) or a manager/
                // admin picked directly (attribute to themselves).
                if ($assignment_id > 0 && ($manager_id === null || $manager_id <= 0)) {
                    try {
                        $oa_stmt = $dbRepo->prepare("SELECT employee_id, user_id FROM tbl_order_assignment WHERE id = ? LIMIT 1");
                        $oa_stmt->execute([$assignment_id]);
                        $oa_row = $oa_stmt->fetch(PDO::FETCH_ASSOC);
                        $picked_emp = (int)($oa_row['employee_id'] ?? 0);
                        $picked_user = (int)($oa_row['user_id'] ?? 0);
                        if ($picked_emp > 0) {
                            $emp_mgr = $dbRepo->prepare("SELECT manager_id FROM tbl_employee WHERE id = ? LIMIT 1");
                            $emp_mgr->execute([$picked_emp]);
                            $emp_mgr_id = $emp_mgr->fetchColumn();
                            if (!empty($emp_mgr_id)) {
                                $dbRepo->prepare("UPDATE tbl_order SET manager_id = ? WHERE id = ? AND (manager_id IS NULL OR manager_id = 0)")->execute([(int)$emp_mgr_id, $order_id]);
                            }
                        } elseif ($picked_user > 0) {
                            $dbRepo->prepare("UPDATE tbl_order SET manager_id = ? WHERE id = ? AND (manager_id IS NULL OR manager_id = 0)")->execute([$picked_user, $order_id]);
                        }
                    } catch (\Throwable $e) {}
                }

                if ($started_tx) {
                    $pdo->commit();
                }
                if ($assignment_id > 0) {
                    $stmt_oa = $dbRepo->prepare("SELECT employee_id, user_id FROM tbl_order_assignment WHERE id = ? LIMIT 1");
                    $stmt_oa->execute([$assignment_id]);
                    $oa_row = $stmt_oa->fetch(PDO::FETCH_ASSOC);
                    $assigned_emp_id = (int)($oa_row['employee_id'] ?? 0);
                    $assigned_user_id = (int)($oa_row['user_id'] ?? 0);
                    $notifyId = $assigned_emp_id > 0 ? $assigned_emp_id : $assigned_user_id;
                    if ($notifyId > 0) {
                        if (class_exists('EventManager')) {
                            EventManager::dispatch('OrderAssigned', $pdo, $order_id, $notifyId);
                        } else {
                            telegram_ensure_tables($pdo);
                            telegram_notify_assignment($pdo, $order_id, $notifyId);
                        }
                    }
                }
                return $assignment_id;

            } catch (Exception $e) {
                if ($started_tx) {
                    $pdo->rollBack();
                }
                error_log("Error in assign_order_by_strategy for order #{$order_id}: " . $e->getMessage());
                return null;
            }
        }
    }

    if (!function_exists('employee_assign_order')) {
        function employee_assign_order(PDO $pdo, int $order_id, ?int $employee_id = null, string $assigned_by = 'auto', bool $from_strategy = false, ?int $manager_id = null): ?int
        { global $dbRepo;
            if (!$from_strategy && $employee_id === null && function_exists('assign_order_by_strategy')) {
                return assign_order_by_strategy($pdo, $order_id, $assigned_by, $manager_id);
            }

            $check = $dbRepo->prepare("SELECT id FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            $check->execute([$order_id]);
            if ($check->fetch()) {
                return null;
            }

            $order_check = $dbRepo->prepare("SELECT id, product_id FROM tbl_order WHERE id = ? LIMIT 1");
            $order_check->execute([$order_id]);
            $order_row = $order_check->fetch(PDO::FETCH_ASSOC);
            if (!$order_row) {
                return null;
            }
            $order_product_id = (int) ($order_row['product_id'] ?? 0);

            $picked_user_id = null;
            if ($employee_id === null) {
                $next = employee_get_next_for_assignment($pdo, $order_product_id, $manager_id);
                if ($next === null) {
                    return null;
                }
                // WRR can pick either an employee (tbl_employee) or a manager/admin
                // opted into assignment (tbl_user) - they must land in different
                // columns, or a manager's user id gets stored as if it were an
                // employee id (and then resolves to nobody, since tbl_employee has
                // no row with that id).
                if (($next['type'] ?? 'employee') === 'user') {
                    $picked_user_id = (int) $next['id'];
                    $employee_id = null;
                } else {
                    $employee_id = (int) $next['id'];
                }
            } else {
                $emp = employee_get_by_id($pdo, $employee_id);
                if ($emp === null || empty($emp['is_active'])) {
                    return null;
                }
                if ($order_product_id > 0 && !employee_can_access_product($pdo, $employee_id, $order_product_id)) {
                    if ($assigned_by === 'manual' || strpos($assigned_by, 'manual') !== false) {
                        throw new Exception('لا يمكن إسناد هذا الطلب لأن المنتج غير موجود ضمن صلاحيات هذا الموظف.');
                    }
                    return null;
                }
            }

            $stmt = $dbRepo->prepare("
                INSERT INTO tbl_order_assignment (order_id, employee_id, user_id, assigned_by, assignment_source)
                VALUES (?, ?, ?, ?, 'wrr')
            ");
            $stmt->execute([$order_id, $employee_id, $picked_user_id, $assigned_by]);
            $assignment_id = (int) $dbRepo->lastInsertId();

            if ($assignment_id > 0 && !$from_strategy) {
                $notifyId = $employee_id ?? $picked_user_id;
                if (class_exists('EventManager')) {
                    EventManager::dispatch('OrderAssigned', $pdo, $order_id, $notifyId);
                } else {
                    telegram_ensure_tables($pdo);
                    telegram_notify_assignment($pdo, $order_id, $notifyId);
                }
            }

            return $assignment_id;
        }
    }

    if (!function_exists('employee_reassign_order')) {
        function employee_reassign_order(PDO $pdo, int $order_id, int $new_employee_id, string $changed_by = 'manual'): bool
        { global $dbRepo;
            $emp = employee_get_by_id($pdo, $new_employee_id);
            if ($emp === null || empty($emp['is_active'])) {
                return false;
            }

            if (!employee_can_access_order($pdo, $new_employee_id, $order_id)) {
                if ($changed_by === 'manual' || strpos($changed_by, 'manual') !== false) {
                    throw new Exception('لا يمكن إسناد هذا الطلب لأن المنتج غير موجود ضمن صلاحيات هذا الموظف.');
                }
                return false;
            }

            $existing = $dbRepo->prepare("SELECT id, status FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            $existing->execute([$order_id]);
            $current = $existing->fetch(PDO::FETCH_ASSOC);

            if ($current) {
                if ($current['status'] === 'reassigned') {
                    $stmt = $dbRepo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                    $stmt->execute([$new_employee_id, $changed_by, $order_id]);
                } else {
                    $stmt = $dbRepo->prepare("UPDATE tbl_order_assignment SET employee_id = ?, status = 'active', assigned_at = NOW(), assigned_by = ? WHERE order_id = ?");
                    $stmt->execute([$new_employee_id, $changed_by, $order_id]);
                }
            } else {
                $stmt = $dbRepo->prepare("INSERT INTO tbl_order_assignment (order_id, employee_id, assigned_by, assignment_source) VALUES (?, ?, ?, 'manual')");
                $stmt->execute([$order_id, $new_employee_id, $changed_by]);
            }

            if (class_exists('EventManager')) {
                EventManager::dispatch('OrderAssigned', $pdo, $order_id, $new_employee_id);
            } else {
                telegram_ensure_tables($pdo);
                telegram_notify_assignment($pdo, $order_id, $new_employee_id);
            }

            return true;
        }
    }

    if (!function_exists('employee_auto_assign_unassigned')) {
        function employee_auto_assign_unassigned(PDO $pdo, int $limit = 50): int
        { global $dbRepo;
            $count = 0;
            $limit_int = (int) $limit;
            $stmt = $dbRepo->prepare("
                SELECT o.id, o.manager_id FROM tbl_order o
                LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id
                WHERE oa.id IS NULL
                ORDER BY o.id ASC
                LIMIT $limit_int
            ");
            $stmt->execute();
            $unassigned = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($unassigned as $order) {
                $mid = !empty($order['manager_id']) ? (int) $order['manager_id'] : null;
                $assigned = assign_order_by_strategy($pdo, (int) $order['id'], 'auto_bootstrap', $mid);
                if ($assigned !== null) {
                    $count++;
                }
            }

            return $count;
        }
    }

    if (!function_exists('employee_get_assignment_for_order')) {
        function employee_get_assignment_for_order(PDO $pdo, int $order_id): ?array
        { global $dbRepo;
            $stmt = $dbRepo->prepare("
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
        { global $dbRepo;
            $stats = [
                'total_assigned' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'returned' => 0,
                'unpaid_completed' => 0,
                'commission_per_order' => 0.00,
                'unpaid_balance' => 0.00
            ];

            $emp = employee_get_by_id($pdo, $employee_id);
            if ($emp) {
                $stats['commission_per_order'] = (float) $emp['commission_per_order'];
            }

            $stmt = $dbRepo->prepare("
                SELECT
                    COUNT(*) AS total_assigned,
                    SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN o.order_status IN ('Completed','Delivered') THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned,
                    SUM(CASE WHEN o.order_status IN ('Completed','Delivered') AND oa.is_paid = 0 THEN 1 ELSE 0 END) AS unpaid_completed
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
                    'returned' => (int) $row['returned'],
                    'unpaid_completed' => (int) $row['unpaid_completed']
                ]);
            }
            
            $stats['unpaid_balance'] = $stats['unpaid_completed'] * $stats['commission_per_order'];

            return $stats;
        }
    }

    if (!function_exists('employee_get_all_stats')) {
        function employee_get_all_stats(PDO $pdo, ?int $manager_id = null): array
        { global $dbRepo;
            $manager_filter = "";
            $params = [];
            if ($manager_id !== null && $manager_id > 0) {
                $manager_filter = " AND e.manager_id = " . (int) $manager_id;
            } elseif ($manager_id === 0) {
                $manager_filter = " AND (e.manager_id IS NULL OR e.manager_id = 0)";
            }
            $stmt = $dbRepo->query("
                SELECT
                    e.id, e.full_name, e.email, e.is_active, e.commission_per_order,
                    COUNT(oa.id) AS total_assigned,
                    SUM(CASE WHEN o.order_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN o.order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN o.order_status IN ('Completed','Delivered') THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN o.order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN o.order_status = 'Returned' THEN 1 ELSE 0 END) AS returned,
                    SUM(CASE WHEN o.order_status IN ('Completed','Delivered') AND oa.is_paid = 0 THEN 1 ELSE 0 END) AS unpaid_completed
                FROM tbl_employee e
                LEFT JOIN tbl_order_assignment oa ON oa.employee_id = e.id AND oa.status = 'active'
                LEFT JOIN tbl_order o ON o.id = oa.order_id
                WHERE e.is_active = 1 {$manager_filter}
                GROUP BY e.id, e.full_name, e.email, e.is_active, e.commission_per_order
            ");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($result as &$row) {
                $row['total_assigned'] = (int) $row['total_assigned'];
                $row['pending'] = (int) $row['pending'];
                $row['confirmed'] = (int) $row['confirmed'];
                $row['completed'] = (int) $row['completed'];
                $row['cancelled'] = (int) $row['cancelled'];
                $row['returned'] = (int) $row['returned'];
                $row['unpaid_completed'] = (int) $row['unpaid_completed'];
                $row['commission_per_order'] = (float) $row['commission_per_order'];
                $row['unpaid_balance'] = $row['unpaid_completed'] * $row['commission_per_order'];
            }
            return $result;
        }
    }

    if (!function_exists('employee_search')) {
        function employee_search(PDO $pdo, string $query): array
        { global $dbRepo;
            $q = '%' . $query . '%';
            $stmt = $dbRepo->prepare("
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
        { global $dbRepo;
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
            $stmt = $dbRepo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!function_exists('employee_get_unassigned_orders_count')) {
        function employee_get_unassigned_orders_count(PDO $pdo): int
        { global $dbRepo;
            $stmt = $dbRepo->query("
                SELECT COUNT(*) FROM tbl_order o
                LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id
                WHERE oa.id IS NULL
            ");
            return (int) $stmt->fetchColumn();
        }
    }

    if (!function_exists('employee_get_current_admin_employee')) {
        function employee_get_current_admin_employee(PDO $pdo): ?array
        { global $dbRepo;
            if (empty($_SESSION['user']['email'])) {
                return null;
            }
            $stmt = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([$_SESSION['user']['email']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_get_product_assignment')) {
        function employee_get_product_assignment(PDO $pdo, int $product_id): ?array
        { global $dbRepo;
            employee_ensure_tables($pdo);
            if ($product_id <= 0) {
                return null;
            }
            $stmt = $dbRepo->prepare("SELECT product_id, employee_id, assignment_mode, is_enabled FROM tbl_product_assignment WHERE product_id = ? LIMIT 1");
            $stmt->execute([$product_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }
    }

    if (!function_exists('employee_save_product_assignment')) {
        function employee_save_product_assignment(PDO $pdo, int $product_id, int $is_enabled, int $employee_id = 0, string $assignment_mode = 'queue'): bool
        { global $dbRepo;
            employee_ensure_tables($pdo);
            if ($product_id <= 0) {
                return false;
            }
            $mode = in_array($assignment_mode, ['queue', 'direct'], true) ? $assignment_mode : 'queue';
            $enabled = ($is_enabled == 1 && $employee_id > 0) ? 1 : 0;
            $stmt = $dbRepo->prepare("
                INSERT INTO tbl_product_assignment (product_id, employee_id, assignment_mode, is_enabled, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    employee_id = VALUES(employee_id),
                    assignment_mode = VALUES(assignment_mode),
                    is_enabled = VALUES(is_enabled),
                    updated_at = NOW()
            ");
            return $stmt->execute([$product_id, $employee_id, $mode, $enabled]);
        }
    }

    // ---------------------------------------------------------------
    // Manager ownership of orders (for "confirm own only + claim")
    // ---------------------------------------------------------------

    if (!function_exists('order_owner_manager_id')) {
        /**
         * Which manager (tbl_user id) owns this order?
         *  - a manager who claimed it (assignment.user_id), or
         *  - the manager of the assigned employee (employee.manager_id).
         * Returns null if the order is unowned.
         */
        function order_owner_manager_id(PDO $pdo, int $orderId): ?int
        { global $dbRepo;
            $stmt = $dbRepo->prepare("
                SELECT a.user_id, e.manager_id
                FROM tbl_order_assignment a
                LEFT JOIN tbl_employee e ON e.id = a.employee_id
                WHERE a.order_id = ? AND a.status IN ('active','waiting')
                ORDER BY a.id DESC LIMIT 1");
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if (!empty($row['user_id']))    return (int) $row['user_id'];
                if (!empty($row['manager_id'])) return (int) $row['manager_id'];
            }
            return null;
        }
    }

    if (!function_exists('order_can_manager_change')) {
        /**
         * May the current user change/confirm this order?
         *  - Super Admin: yes (full access).
         *  - Employee: yes only for orders assigned to them.
         *  - Regular manager (Admin/Manager): only their own team's orders,
         *    or orders they claimed. Unowned orders require claiming first.
         *  - Any other role: yes (unchanged legacy behaviour).
         */
        function order_can_manager_change(PDO $pdo, int $orderId, array $sessionUser): bool
        { global $dbRepo;
            $role  = (string) ($sessionUser['role'] ?? '');
            $idRaw = (string) ($sessionUser['id'] ?? '');

            if ($role === 'Super Admin') return true;

            if ($role === 'Employee' || strpos($idRaw, 'emp_') === 0) {
                $empId = strpos($idRaw, 'emp_') === 0 ? (int) substr($idRaw, 4) : (int) $idRaw;
                $st = $dbRepo->prepare("SELECT 1 FROM tbl_order_assignment WHERE order_id=? AND employee_id=? AND status='active' LIMIT 1");
                $st->execute([$orderId, $empId]);
                return (bool) $st->fetchColumn();
            }

            if ($role === 'Admin' || $role === 'Manager') {
                $owner = order_owner_manager_id($pdo, $orderId);
                return $owner !== null && $owner === (int) $idRaw;
            }

            return true;
        }
    }

    if (!function_exists('order_claim_by_manager')) {
        /**
         * A manager "claims" an order — inserts themselves as its owner so they
         * can confirm it. Takes it over from any prior employee/manager.
         */
        function order_claim_by_manager(PDO $pdo, int $orderId, int $managerId): bool
        { global $dbRepo;
            if ($orderId <= 0 || $managerId <= 0) return false;
            $exists = $dbRepo->prepare("SELECT id FROM tbl_order_assignment WHERE order_id = ? LIMIT 1");
            $exists->execute([$orderId]);
            if ($exists->fetchColumn()) {
                $dbRepo->prepare("UPDATE tbl_order_assignment
                    SET user_id = ?, employee_id = NULL, status = 'active',
                        assigned_by = 'manager_claim', assignment_source = 'claim', assigned_at = NOW()
                    WHERE order_id = ?")->execute([$managerId, $orderId]);
            } else {
                $dbRepo->prepare("INSERT INTO tbl_order_assignment
                    (order_id, user_id, assigned_by, assignment_source, status)
                    VALUES (?, ?, 'manager_claim', 'claim', 'active')")->execute([$orderId, $managerId]);
            }
            return true;
        }
    }

}
