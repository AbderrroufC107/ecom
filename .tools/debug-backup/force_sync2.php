<?php
require_once('inc/config.php');
require_once('inc/functions.php');

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
if (!ecotrack_is_configured($settings)) {
    die("Ecotrack not configured");
}

$sql = "
    SELECT id, ecotrack_tracking, ecotrack_remote_status 
    FROM tbl_order 
    WHERE ecotrack_tracking IS NOT NULL 
      AND ecotrack_tracking != '' 
      AND (ecotrack_remote_status IS NULL OR ecotrack_remote_status = '')
    LIMIT 200
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
    die("No active trackings to sync.");
}

$trackings = [];
$orders_map = [];
foreach ($orders as $o) {
    $trackings[] = $o['ecotrack_tracking'];
    $orders_map[$o['ecotrack_tracking']] = $o['id'];
}

$query = [];
foreach ($trackings as $t) {
    $query['trackings[]'] = $t;
}

$request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', $query, null, 'bearer');

$synced_count = 0;

if (!empty($request['success']) && !empty($request['json'])) {
    $data_items = [];
    if (isset($request['json']['results']) && is_array($request['json']['results'])) {
        $data_items = $request['json']['results'];
    } else {
        foreach ($request['json'] as $k => $v) {
            if ($k !== 'success' && $k !== 'message' && is_array($v)) {
                $data_items[$k] = $v;
            }
        }
    }

    foreach ($data_items as $tracking_number => $data) {
        if (!isset($orders_map[$tracking_number])) continue;
        
        $order_id = $orders_map[$tracking_number];
        $latest_status = '';
        
        if (is_array($data) && !empty($data['current_status'])) {
            $latest_status = trim((string)$data['current_status']);
        } elseif (is_array($data) && !empty($data['history']) && is_array($data['history'])) {
            $latest = end($data['history']);
            if (!empty($latest['status'])) {
                $latest_status = trim((string)$latest['status']);
            }
        } elseif (is_string($data)) {
            $latest_status = trim($data);
        } elseif (is_array($data) && !empty($data['status'])) {
            $latest_status = trim((string)$data['status']);
        }
        
        if ($latest_status !== '') {
            $pdo->prepare("UPDATE tbl_order SET ecotrack_remote_status = ? WHERE id = ? LIMIT 1")
                ->execute([$latest_status, $order_id]);
            $synced_count++;
        }
    }
}

echo "Synced $synced_count orders.\n";
