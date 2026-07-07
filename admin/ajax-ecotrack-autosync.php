<?php
// ajax-ecotrack-autosync.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');
if (file_exists(__DIR__ . '/inc/telegram_bot.php')) {
    require_once(__DIR__ . '/inc/telegram_bot.php');
}
if (file_exists(__DIR__ . '/inc/telegram_actions.php')) {
    require_once(__DIR__ . '/inc/telegram_actions.php');
}
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
if (!ecotrack_is_configured($settings)) {
    echo json_encode(['success' => false, 'message' => 'Ecotrack not configured']);
    exit;
}

// Find 5 orders that have a tracking number, are not completely finished (e.g. Delivered/Returned in Ecotrack),
// and haven't been synced in the last hour.
// Since we don't have a dedicated ecotrack_last_sync_at column, we can use a temporary file or just random select
// But actually, we can add a column or just use RAND() or check terminal remote statuses.
// Let's ensure the table has ecotrack_remote_status.
admin_ensure_order_ecotrack_columns($pdo);
site_security_ensure_order_columns($pdo);

$sql = "
    SELECT id, ecotrack_tracking, ecotrack_remote_status, customer_name, customer_phone, product_name, quantity, wilaya, commune, address, delivery_type, customer_ip, device_id
    FROM tbl_order 
    WHERE ecotrack_tracking IS NOT NULL 
      AND ecotrack_tracking != '' 
      AND (ecotrack_remote_status IS NULL OR (
          ecotrack_remote_status NOT LIKE '%Livré%'
          AND ecotrack_remote_status NOT LIKE '%LivrÃ%'
          AND ecotrack_remote_status NOT LIKE '%Livre%'
          AND ecotrack_remote_status NOT LIKE '%Delivered%'
          AND ecotrack_remote_status NOT LIKE '%Retour%'
          AND ecotrack_remote_status NOT LIKE '%Returned%'
          AND ecotrack_remote_status NOT LIKE '%Annulé%'
          AND ecotrack_remote_status NOT LIKE '%AnnulÃ%'
          AND ecotrack_remote_status NOT LIKE '%Annule%'
      ))
    ORDER BY RAND() 
    LIMIT 5
";

$stmt = $dbRepo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    echo json_encode(['success' => true, 'synced' => 0, 'message' => 'No active trackings to sync.']);
    exit;
}

$trackings = [];
$orders_map = [];
foreach ($orders as $o) {
    $trackings[] = $o['ecotrack_tracking'];
    $orders_map[$o['ecotrack_tracking']] = $o;
}

// Batch query Ecotrack for these trackings. ECOTRACK expects repeated trackings[] keys.
$query = ['trackings[]' => $trackings];

// Call trackings info endpoint
$request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', $query, null, 'bearer');

$synced_count = 0;

if (!empty($request['success']) && !empty($request['json'])) {
    // Some Ecotrack API versions return { "success": true, "results": { "TRACKING": { ... } } }
    // Others return { "TRACKING": { "current_status": "...", ... } } directly
    $data_items = [];
    if (isset($request['json']['results']) && is_array($request['json']['results'])) {
        $data_items = $request['json']['results'];
    } else {
        // If it doesn't have 'results', maybe the keys are tracking numbers
        foreach ($request['json'] as $k => $v) {
            if ($k !== 'success' && $k !== 'message' && is_array($v)) {
                $data_items[$k] = $v;
            }
        }
    }

    foreach ($data_items as $tracking_number => $data) {
        if (!isset($orders_map[$tracking_number])) continue;
        
        $order = $orders_map[$tracking_number];
        $order_id = (int) $order['id'];
        $previous_status = trim((string) ($order['ecotrack_remote_status'] ?? ''));
        $latest_status = '';
        $latest_time = null;
        $latest_note = '';
        
        // Data usually contains a history array
        if (is_array($data) && !empty($data['history']) && is_array($data['history'])) {
            $latest = end($data['history']);
            if (!empty($latest['status'])) {
                // if current_status exists, prefer its text for display, but use history for time
                if (!empty($data['current_status'])) {
                    $latest_status = trim((string)$data['current_status']);
                } else {
                    $latest_status = trim((string)$latest['status']);
                }
                
                if (!empty($latest['date']) && !empty($latest['time'])) {
                    $latest_time = $latest['date'] . ' ' . $latest['time'];
                }
                $latest_note = ecotrack_extract_remote_note($latest, $tracking_number);
            }
        } elseif (is_array($data) && !empty($data['current_status'])) {
            $latest_status = trim((string)$data['current_status']);
        } elseif (is_string($data)) {
            $latest_status = trim($data);
        } elseif (is_array($data) && !empty($data['status'])) {
            $latest_status = trim((string)$data['status']);
        }
        if ($latest_note === '') {
            $latest_note = ecotrack_extract_remote_note($data, $tracking_number);
        }
        
        if ($latest_status !== '') {
            $dbRepo->prepare("UPDATE tbl_order SET ecotrack_remote_status = ?, ecotrack_remote_time = ? WHERE id = ? LIMIT 1")
                ->execute([$latest_status, $latest_time, $order_id]);
            $risk_result = site_security_try_record_delivery_return(
                $pdo,
                array_merge($order, ['ecotrack_remote_status' => $latest_status]),
                $latest_status,
                $latest_note,
                'ecotrack_auto_sync'
            );
            if (!empty($risk_result['recorded'])) {
                error_log('Security risk recorded from ECOTRACK return for order #' . $order_id . ': ' . json_encode($risk_result, JSON_UNESCAPED_UNICODE));
            }
            $telegram_result = admin_send_order_status_telegram($pdo, $order, $previous_status, $latest_status, [
                'tracking' => $tracking_number,
                'note' => $latest_note,
                'remote_time' => $latest_time
            ]);
            if (empty($telegram_result['skipped']) && empty($telegram_result['success'])) {
                error_log('Order status Telegram failed for order #' . $order_id . ': ' . trim((string) ($telegram_result['error'] ?? 'send failed')));
            }

            // Alert the assigned employee if the driver could not reach the customer.
            // Only fire on an actual status change so repeated syncs don't spam.
            if ($previous_status !== $latest_status
                && function_exists('telegram_is_delivery_noanswer_status')
                && telegram_is_delivery_noanswer_status($latest_status, $latest_note)) {
                telegram_notify_employee_delivery_noanswer($pdo, $order_id, $latest_status, (string) $latest_note);
            }

            $synced_count++;
        }
    }
}

echo json_encode([
    'success' => true,
    'synced' => $synced_count,
    'message' => "Auto-synced $synced_count orders."
]);

