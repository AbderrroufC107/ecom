<?php

class DeliveryManager {
    
    public static function getAdapter($pdo, $company_id) { global $dbRepo;
    global $dbRepo;

        $stmt = $dbRepo->prepare("SELECT * FROM tbl_delivery_company WHERE id = ?");
        $stmt->execute([$company_id]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$company || !$company['api_enabled']) {
            throw new Exception("شركة التوصيل غير موجودة أو الـ API غير مفعل.");
        }
        
        $type = strtolower($company['api_type']);
        $creds = [
            'api_key' => $company['api_key'],
            'api_token' => $company['api_token'],
            'api_base_url' => $company['api_base_url']
        ];
        
        // Return the correct concrete class based on api_type
        if ($type === 'yalidine') {
            require_once 'YalidineAPI.php';
            return new YalidineAPI($creds);
        } elseif ($type === 'zrexpress') {
            require_once 'ZRExpressAPI.php';
            return new ZRExpressAPI($creds);
        } elseif ($type === 'ecotrack') {
            // we will build an ecotrack adapter if needed, for now throw error if no matching abstract adapter
            throw new Exception("Adapter for {$type} is not fully implemented in the ERP layer yet.");
        }
        
        throw new Exception("لا يوجد محول API (Adapter) لشركة التوصيل: {$type}");
    }

    public static function logAPIRequest($pdo, $order_id, $company_id, $endpoint, $method, $request, $response, $code, $time_ms) { global $dbRepo;
    global $dbRepo;

        $stmt = $dbRepo->prepare("INSERT INTO tbl_api_request_log (order_id, delivery_company_id, endpoint, method, request_body, response_body, http_code, response_time_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $order_id,
            $company_id,
            $endpoint,
            $method,
            is_array($request) ? json_encode($request, JSON_UNESCAPED_UNICODE) : $request,
            is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : $response,
            $code,
            $time_ms
        ]);
    }

    public static function logTimeline($pdo, $order_id, $action, $description, $company_id = null) { global $dbRepo;
    global $dbRepo;

        $admin_id = $_SESSION['user']['id'] ?? 0;
        $stmtTimeline = $dbRepo->prepare("INSERT INTO tbl_order_timeline (order_id, action, description, user_id, delivery_company_id) VALUES (?, ?, ?, ?, ?)");
        $stmtTimeline->execute([$order_id, $action, $description, $admin_id, $company_id]);
    }

    public static function validateOrderForAPI($order) { global $dbRepo;
    global $dbRepo;

        $errors = [];
        if (empty(trim((string)$order['customer_name']))) $errors[] = "اسم العميل مفقود.";
        if (empty(trim((string)$order['customer_phone']))) $errors[] = "رقم هاتف العميل مفقود.";
        if (empty(trim((string)$order['wilaya']))) $errors[] = "الولاية مفقودة.";
        if (empty(trim((string)$order['commune']))) $errors[] = "البلدية مفقودة.";
        if (empty(trim((string)$order['address']))) $errors[] = "العنوان مفقود.";
        if (empty(trim((string)$order['delivery_type']))) $errors[] = "نوع التوصيل مفقود.";
        if ($order['delivery_type'] === 'مكتب' && empty(trim((string)$order['desk'] ?? ''))) {
            // Desk logic
        }
        if (empty($order['total_price']) || $order['total_price'] <= 0) $errors[] = "إجمالي السعر غير صحيح.";

        if (!empty($errors)) {
            throw new Exception("يرجى تصحيح الأخطاء التالية قبل الإرسال: " . implode(" - ", $errors));
        }
    }

    public static function sendOrder($pdo, $order_id, $company_id) { global $dbRepo;
    global $dbRepo;

        // Fetch order
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_order WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) throw new Exception("الطلب غير موجود.");
        
        // Prevent duplicates
        if (!empty($order['tracking_number'])) {
            throw new Exception("الطلب يمتلك رقم تتبع بالفعل ({$order['tracking_number']}). لا يمكن إرساله مرة أخرى، يرجى تحديث الحالة بدلاً من ذلك.");
        }

        self::validateOrderForAPI($order);

        $adapter = self::getAdapter($pdo, $company_id);
        
        $start_time = microtime(true);
        try {
            $api_response = $adapter->createOrder($order);
            $end_time = microtime(true);
            $time_ms = round(($end_time - $start_time) * 1000);

            // Log API
            self::logAPIRequest($pdo, $order_id, $company_id, 'POST /createOrder', 'POST', $order, $api_response, 200, $time_ms);

            if ($api_response['success']) {
                $tracking = $api_response['tracking_number'];
                
                $pdo->beginTransaction();
                // Update order tracking
                $upd = $dbRepo->prepare("UPDATE tbl_order SET delivery_company_id = ?, tracking_number = ?, sync_status = 'Pending' WHERE id = ?");
                $upd->execute([$company_id, $tracking, $order_id]);

                self::logTimeline($pdo, $order_id, "تم إرسال الطلب لشركة التوصيل", "تم الحصول على رقم تتبع: {$tracking}", $company_id);

                $pdo->commit();
                return ['success' => true, 'tracking_number' => $tracking];
            } else {
                throw new Exception("ردت شركة التوصيل بخطأ: " . ($api_response['error'] ?? 'خطأ غير معروف'));
            }

        } catch (Exception $e) {
            $end_time = microtime(true);
            $time_ms = round(($end_time - $start_time) * 1000);
            self::logAPIRequest($pdo, $order_id, $company_id, 'POST /createOrder', 'POST', $order, ['error' => $e->getMessage()], 500, $time_ms);
            throw $e;
        }
    }

    public static function syncOrder($pdo, $order_id) { global $dbRepo;
    global $dbRepo;

        $stmt = $dbRepo->prepare("SELECT * FROM tbl_order WHERE id = ? FOR UPDATE");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || empty($order['tracking_number']) || empty($order['delivery_company_id'])) {
            throw new Exception("الطلب غير مؤهل للمزامنة.");
        }

        $adapter = self::getAdapter($pdo, $order['delivery_company_id']);
        
        $start_time = microtime(true);
        try {
            $api_response = $adapter->getOrderStatus($order['tracking_number']);
            $end_time = microtime(true);
            $time_ms = round(($end_time - $start_time) * 1000);

            self::logAPIRequest($pdo, $order_id, $order['delivery_company_id'], 'GET /status/' . $order['tracking_number'], 'GET', [], $api_response, 200, $time_ms);

            if ($api_response['success']) {
                $new_status = $api_response['status']; // e.g. "Delivered", "Returned"
                $current_status = $order['order_status'];

                if ($new_status !== $current_status) {
                    self::processStatusChange($pdo, $order, $new_status);
                }

                // Update attempts
                $dbRepo->prepare("UPDATE tbl_order SET sync_attempts = 0, sync_status = 'Synced' WHERE id = ?")->execute([$order_id]);

                return ['success' => true, 'new_status' => $new_status];
            } else {
                throw new Exception("ردت شركة التوصيل بخطأ: " . ($api_response['error'] ?? 'خطأ غير معروف'));
            }

        } catch (Exception $e) {
            $end_time = microtime(true);
            $time_ms = round(($end_time - $start_time) * 1000);
            self::logAPIRequest($pdo, $order_id, $order['delivery_company_id'], 'GET /status', 'GET', [], ['error' => $e->getMessage()], 500, $time_ms);
            
            // Handle Retry logic
            self::handleFailedSync($pdo, $order_id, $order['sync_attempts']);
            throw $e;
        }
    }

    private static function handleFailedSync($pdo, $order_id, $attempts) { global $dbRepo;
    global $dbRepo;

        $attempts++;
        // 1m, 5m, 15m, 1h, 6h
        $delays = [1, 5, 15, 60, 360];
        
        if ($attempts > 5) {
            // Give up
            $dbRepo->prepare("UPDATE tbl_order SET sync_attempts = ?, sync_status = 'Failed', next_sync_time = NULL WHERE id = ?")->execute([$attempts, $order_id]);
            
            // Insert Notification for Admin
            $dbRepo->prepare("INSERT INTO tbl_notification (title, message, type) VALUES (?, ?, ?)")->execute([
                "فشل مزامنة نهائي",
                "فشل المزامنة للطلب #{$order_id} بعد 5 محاولات.",
                "danger"
            ]);
            self::logTimeline($pdo, $order_id, "فشل مزامنة", "توقف النظام عن مزامنة الطلب لعدم استجابة الـ API.");
        } else {
            $delay_mins = $delays[$attempts - 1];
            $next_time = date('Y-m-d H:i:s', strtotime("+{$delay_mins} minutes"));
            $dbRepo->prepare("UPDATE tbl_order SET sync_attempts = ?, next_sync_time = ? WHERE id = ?")->execute([$attempts, $next_time, $order_id]);
        }
    }

    private static function processStatusChange($pdo, $order, $new_status) { global $dbRepo;
    global $dbRepo;

        // Must be called inside or wrap with a Transaction
        require_once dirname(__DIR__) . '/stock_functions.php';
        
        $pdo->beginTransaction();
        try {
            $dbRepo->prepare("UPDATE tbl_order SET order_status = ? WHERE id = ?")->execute([$new_status, $order['id']]);
            
            self::logTimeline($pdo, $order['id'], "تحديث حالة التوصيل", "تغيرت الحالة من {$order['order_status']} إلى {$new_status} بواسطة الـ API.", $order['delivery_company_id']);

            // Stock Side effects
            if ($new_status === 'Delivered') {
                // Deduct stock permanently if needed (assuming stock_functions handle this)
                stock_handle_order_status_change($pdo, $order, $order['order_status'], $new_status, 0);
            } elseif ($new_status === 'Returned') {
                stock_handle_order_status_change($pdo, $order, $order['order_status'], $new_status, 0);
                
                // Deal with commissions... etc (Requires performance_functions)
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
