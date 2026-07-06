<?php
/**
 * NotificationService Class
 *
 * Listens to EventManager hooks and routes notifications to all active Providers.
 */

declare(strict_types=1);

class NotificationService
{
    /**
     * Subscribe NotificationService methods to EventManager events.
     */
    public static function bootstrap(): void
    { global $dbRepo;
        EventManager::subscribe('OrderCreated', [self::class, 'handleOrderCreated']);
        EventManager::subscribe('OrderAssigned', [self::class, 'handleOrderAssigned']);
        EventManager::subscribe('OrderUpdated', [self::class, 'handleOrderUpdated']);
        EventManager::subscribe('ComplaintCreated', [self::class, 'handleComplaintCreated']);
        EventManager::subscribe('EmployeeCreated', [self::class, 'handleEmployeeCreated']);
    }

    /**
     * Helper to fetch all active managers with Telegram linked.
     */
    private static function getLinkedManagers(PDO $pdo): array
    { global $dbRepo;
        try {
            $stmt = $dbRepo->query("
                SELECT * FROM `tbl_user` 
                WHERE `telegram_is_linked` = 1 
                  AND `telegram_chat_id` IS NOT NULL 
                  AND `status` = 1
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Helper to fetch employee by ID.
     */
    private static function getEmployee(PDO $pdo, int $employeeId): ?array
    { global $dbRepo;
        try {
            $stmt = $dbRepo->prepare("SELECT * FROM `tbl_employee` WHERE `id` = ? LIMIT 1");
            $stmt->execute([$employeeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Helper to fetch order details by ID.
     */
    private static function getOrder(PDO $pdo, int $orderId): ?array
    { global $dbRepo;
        try {
            $stmt = $dbRepo->prepare("SELECT * FROM `tbl_order` WHERE `id` = ? LIMIT 1");
            $stmt->execute([$orderId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Triggered when a new order is placed.
     */
    public static function handleOrderCreated(PDO $pdo, int $orderId): void
    { global $dbRepo;
        $order = self::getOrder($pdo, $orderId);
        if (!$order) {
            return;
        }

        $managers = self::getLinkedManagers($pdo);
        if (empty($managers)) {
            return;
        }

        $data = [
            'order_id' => $order['id'],
            'product_name' => $order['product_name'] ?? '',
            'total_price' => $order['total_price'] ?? '0',
            'customer_name' => $order['customer_name'] ?? '',
            'wilaya' => $order['wilaya'] ?? ''
        ];

        foreach (ProviderManager::getActiveProviders() as $provider) {
            $provider->broadcastNotification($managers, 'new_order', $data);
        }
    }

    /**
     * Triggered when a task (order) is assigned to an employee.
     */
    public static function handleOrderAssigned(PDO $pdo, int $orderId, int $employeeId): void
    { global $dbRepo;
        $order = self::getOrder($pdo, $orderId);
        $employee = self::getEmployee($pdo, $employeeId);
        
        if (!$order || !$employee) {
            return;
        }

        foreach (ProviderManager::getActiveProviders() as $provider) {
            $provider->sendTaskNotification($employee, $order, 'task_assigned');
        }
    }

    /**
     * Triggered when order details or status changes.
     */
    public static function handleOrderUpdated(PDO $pdo, int $orderId, string $fromStatus, string $toStatus, string $statusNote = '', ?string $changedBy = null): void
    { global $dbRepo;
        $order = self::getOrder($pdo, $orderId);
        if (!$order) {
            return;
        }

        // 1. Fetch assigned employee
        try {
            $stmt = $dbRepo->prepare("
                SELECT employee_id FROM `tbl_order_assignment` 
                WHERE `order_id` = ? AND `status` = 'active' 
                LIMIT 1
            ");
            $stmt->execute([$orderId]);
            $empId = $stmt->fetchColumn();
        } catch (Exception $e) {
            $empId = false;
        }

        $employee = $empId ? self::getEmployee($pdo, (int) $empId) : null;

        // 2. Notify employee of task updates/cancellations
        if ($employee) {
            foreach (ProviderManager::getActiveProviders() as $provider) {
                if ($toStatus === 'Cancelled') {
                    $provider->sendTaskNotification($employee, $order, 'task_cancelled');
                } elseif ($fromStatus !== $toStatus) {
                    $provider->sendTaskNotification($employee, $order, 'task_assigned'); // re-send interactive buttons for new state
                } else {
                    $provider->sendTaskNotification($employee, $order, 'task_updated');
                }
            }
        }

        // 3. Notify managers of employee status change actions
        if ($fromStatus !== $toStatus) {
            $managers = self::getLinkedManagers($pdo);
            if (!empty($managers)) {
                $empName = $employee ? $employee['full_name'] : 'النظام';
                $data = [
                    'order_id' => $orderId,
                    'employee_name' => $empName,
                    'status' => $toStatus,
                    'reason' => $statusNote
                ];

                $templateKey = 'employee_status_change';
                if ($toStatus === 'Completed') {
                    $templateKey = 'task_completed';
                } elseif ($toStatus === 'Cancelled') {
                    $templateKey = 'task_rejected'; // maps to reject cause
                } elseif ($toStatus === 'Confirmed' && $fromStatus === 'Pending') {
                    $templateKey = 'task_accepted';
                }

                foreach (ProviderManager::getActiveProviders() as $provider) {
                    $provider->broadcastNotification($managers, $templateKey, $data);
                }
            }
        }
    }

    /**
     * Triggered when a new complaint is filed.
     */
    public static function handleComplaintCreated(PDO $pdo, int $complaintId): void
    { global $dbRepo;
        try {
            $stmt = $dbRepo->prepare("
                SELECT c.*, e.full_name AS emp_name, e.telegram_chat_id, e.telegram_lang, e.telegram_is_linked
                FROM `tbl_complaints` c
                INNER JOIN `tbl_employee` e ON e.id = c.employee_id
                WHERE c.id = ? 
                LIMIT 1
            ");
            $stmt->execute([$complaintId]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return;
        }

        if (!$complaint) {
            return;
        }

        // Send receipt confirmation to employee
        if (!empty($complaint['telegram_is_linked']) && !empty($complaint['telegram_chat_id'])) {
            $employee = [
                'telegram_chat_id' => $complaint['telegram_chat_id'],
                'telegram_is_linked' => $complaint['telegram_is_linked'],
                'telegram_lang' => $complaint['telegram_lang']
            ];
            $orderDummy = []; // Dummy array
            
            foreach (ProviderManager::getActiveProviders() as $provider) {
                $provider->sendTaskNotification($employee, $orderDummy, 'complaint_received', [
                    'subject' => $complaint['subject']
                ]);
            }
        }

        // Broadcast complaint details to all managers
        $managers = self::getLinkedManagers($pdo);
        if (!empty($managers)) {
            $data = [
                'employee_name' => $complaint['emp_name'],
                'subject' => $complaint['subject'],
                'message' => $complaint['message']
            ];

            foreach (ProviderManager::getActiveProviders() as $provider) {
                $provider->broadcastNotification($managers, 'new_complaint', $data);
            }
        }
    }

    /**
     * Triggered when a new employee account is registered.
     */
    public static function handleEmployeeCreated(PDO $pdo, int $employeeId): void
    { global $dbRepo;
        $employee = self::getEmployee($pdo, $employeeId);
        if (!$employee) {
            return;
        }

        $managers = self::getLinkedManagers($pdo);
        if (empty($managers)) {
            return;
        }

        $data = [
            'employee_name' => $employee['full_name'],
            'email' => $employee['email']
        ];

        foreach (ProviderManager::getActiveProviders() as $provider) {
            $provider->broadcastNotification($managers, 'employee_registered', $data);
        }
    }
}
