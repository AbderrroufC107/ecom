<?php
// ajax-zrexpress-autosync.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');
header('Content-Type: application/json; charset=UTF-8');

// Allow either admin session or a security key if called from cron
$authenticated = isset($_SESSION['user']);
if (!$authenticated && isset($_GET['key'])) {
    $settings = zrexpress_normalize_settings(front_get_settings($pdo));
    if ($_GET['key'] === $settings['zrexpress_key']) {
        $authenticated = true;
    }
}

if (!$authenticated) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$settings = zrexpress_normalize_settings(front_get_settings($pdo));
if (!zrexpress_is_configured($settings)) {
    echo json_encode(['success' => false, 'message' => 'ZRexpress not configured']);
    exit;
}

admin_ensure_order_zrexpress_columns($pdo);

// Find 5 orders that have a tracking number, and are not completely finished
$sql = "
    SELECT id, zrexpress_tracking, zrexpress_remote_status, customer_name, customer_phone, product_name, quantity, wilaya, commune, address, delivery_type
    FROM tbl_order 
    WHERE zrexpress_tracking IS NOT NULL 
      AND zrexpress_tracking != '' 
      AND (zrexpress_remote_status IS NULL OR (
          zrexpress_remote_status NOT LIKE '%Livré%'
          AND zrexpress_remote_status NOT LIKE '%LivrÃ%'
          AND zrexpress_remote_status NOT LIKE '%Livre%'
          AND zrexpress_remote_status NOT LIKE '%Delivered%'
          AND zrexpress_remote_status NOT LIKE '%Retour%'
          AND zrexpress_remote_status NOT LIKE '%Returned%'
          AND zrexpress_remote_status NOT LIKE '%Annulé%'
          AND zrexpress_remote_status NOT LIKE '%AnnulÃ%'
          AND zrexpress_remote_status NOT LIKE '%Annule%'
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

$synced_count = 0;

if (!function_exists('zrexpress_find_situation_in_response')) {
    function zrexpress_find_situation_in_response($json, $tracking) { global $dbRepo;
    global $dbRepo;

        if (!is_array($json)) return '';
        if (!empty($json['Colis']) && is_array($json['Colis'])) {
            foreach ($json['Colis'] as $c) {
                if (!empty($c['Tracking']) && strtolower(trim($c['Tracking'])) === strtolower(trim($tracking))) {
                    return trim((string) ($c['Situation'] ?? $c['status'] ?? $c['state'] ?? ''));
                }
            }
        }
        foreach ($json as $k => $v) {
            if (strtolower($k) === 'situation' && is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_array($v)) {
                $res = zrexpress_find_situation_in_response($v, $tracking);
                if ($res !== '') return $res;
            }
        }
        return '';
    }
}

foreach ($orders as $order) {
    $order_id = (int) $order['id'];
    $tracking = trim($order['zrexpress_tracking']);
    $previous_status = trim((string) ($order['zrexpress_remote_status'] ?? ''));

    $payload_body = [
        "Colis" => [
            ["Tracking" => $tracking]
        ]
    ];

    $request = zrexpress_api_request($pdo, $settings, 'POST', '/lire', [], $payload_body);

    if ($request['success'] && is_array($request['json'])) {
        $latest_status = zrexpress_find_situation_in_response($request['json'], $tracking);
        if ($latest_status !== '') {
            $dbRepo->prepare("UPDATE tbl_order SET zrexpress_remote_status = ? WHERE id = ? LIMIT 1")
                ->execute([$latest_status, $order_id]);

            if (function_exists('admin_send_order_status_telegram')) {
                admin_send_order_status_telegram($pdo, $order, $previous_status, $latest_status, [
                    'tracking' => $tracking,
                    'note' => 'ZRexpress Auto Sync',
                    'remote_time' => date('Y-m-d H:i:s')
                ]);
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
