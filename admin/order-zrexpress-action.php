<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/CSRF_Protect.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');

$csrf = new CSRF_Protect();

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

if (!function_exists('admin_zrexpress_save_order_state')) {
    function admin_zrexpress_save_order_state(PDO $pdo, $order_id, array $state)
    { global $dbRepo;
    global $dbRepo;

        $sql = "
            UPDATE tbl_order
            SET zrexpress_reference = ?,
                zrexpress_tracking = ?,
                zrexpress_status = ?,
                zrexpress_remote_status = ?,
                zrexpress_last_error = ?,
                zrexpress_last_payload = ?,
                zrexpress_last_response = ?
        ";

        $params = [
            (string) ($state['reference'] ?? ''),
            (string) ($state['tracking'] ?? ''),
            (string) ($state['status'] ?? ''),
            (string) ($state['remote_status'] ?? ''),
            $state['last_error'] ?? '',
            $state['last_payload'] ?? '',
            $state['last_response'] ?? ''
        ];

        if (array_key_exists('sent_at', $state)) {
            $sql .= ", zrexpress_sent_at = ?";
            $params[] = $state['sent_at'];
        }

        $sql .= " WHERE id = ? LIMIT 1";
        $params[] = (int) $order_id;

        $statement = $dbRepo->prepare($sql);
        $statement->execute($params);
    }
}

if (!function_exists('zrexpress_find_tracking_in_response')) {
    function zrexpress_find_tracking_in_response($json) { global $dbRepo;
    global $dbRepo;

        if (!is_array($json)) return '';
        if (!empty($json['Colis'][0]['Tracking'])) {
            return trim((string) $json['Colis'][0]['Tracking']);
        }
        if (!empty($json['tracking'])) {
            return trim((string) $json['tracking']);
        }
        foreach ($json as $k => $v) {
            if (strtolower($k) === 'tracking' && is_string($v) && trim($v) !== '') {
                return trim($v);
            }
            if (is_array($v)) {
                $res = zrexpress_find_tracking_in_response($v);
                if ($res !== '') return $res;
            }
        }
        return '';
    }
}

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

$order_id = (int) ($_POST['order_id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$redirect = trim((string) ($_POST['redirect'] ?? ''));

if ($redirect === '') {
    $redirect = $order_id > 0 ? 'order-details.php?id=' . $order_id : 'order.php';
}

if ($order_id <= 0) {
    admin_set_flash_message('orders', 'danger', 'معرف الطلب غير صالح.');
    header('Location: order.php');
    exit;
}

// Verify CSRF
if (!$csrf->validateRequest()) {
    admin_set_flash_message('orders', 'danger', 'فشل التحقق من رمز CSRF.');
    header('Location: ' . $redirect);
    exit;
}

$allowed_actions = ['create', 'sync', 'pret', 'delete'];
if (!in_array($action, $allowed_actions, true)) {
    admin_set_flash_message('orders', 'danger', 'عملية غير صالحة.');
    header('Location: ' . $redirect);
    exit;
}

admin_ensure_zrexpress_setting_columns($pdo);
admin_ensure_order_zrexpress_columns($pdo);

$statement = $dbRepo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
$statement->execute([$order_id]);
$order = $statement->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    admin_set_flash_message('orders', 'danger', 'الطلب غير موجود.');
    header('Location: order.php');
    exit;
}

$settings = zrexpress_normalize_settings(front_get_settings($pdo));
if (!zrexpress_is_configured($settings)) {
    admin_set_flash_message('orders', 'danger', 'إعدادات ZRexpress غير مكتملة. أضف التوكن والمفتاح أولاً.');
    header('Location: ' . $redirect);
    exit;
}

$reference = 'ZREX-' . $order['id'];
$tracking = trim((string) ($order['zrexpress_tracking'] ?? ''));
$status = trim((string) ($order['zrexpress_status'] ?? ''));

if ($action === 'create' && $tracking !== '') {
    admin_set_flash_message('orders', 'warning', 'تم إرسال هذا الطلب بالفعل إلى ZRexpress.');
    header('Location: ' . $redirect);
    exit;
}

if (in_array($action, ['sync', 'pret', 'delete'], true) && $tracking === '') {
    admin_set_flash_message('orders', 'warning', 'يجب إرسال الطلب أولاً قبل تنفيذ هذه العملية.');
    header('Location: ' . $redirect);
    exit;
}

$flash_type = 'success';
$flash_message = '';

if ($action === 'create') {
    $wilaya_code = function_exists('ecotrack_algeria_wilaya_code') ? ecotrack_algeria_wilaya_code($order['wilaya']) : '';
    if ($wilaya_code === '') {
        $wilaya_code = '31'; // Default fallback
    }

    $delivery_type = (strpos(strtolower($order['delivery_type'] ?? ''), 'stopdesk') !== false || strpos(strtolower($order['delivery_type'] ?? ''), 'office') !== false) ? '1' : '0';

    $payload_body = [
        "Colis" => [[
            "Tracking" => "",
            "TypeLivraison" => $delivery_type,
            "TypeColis" => "0",
            "Confrimee" => "1",
            "Client" => $order['customer_name'],
            "MobileA" => $order['customer_phone'],
            "MobileB" => "",
            "Adresse" => $order['address'],
            "IDWilaya" => (string) $wilaya_code,
            "Commune" => $order['commune'],
            "Total" => (string) $order['total_price'],
            "Note" => "",
            "TProduit" => $order['product_name'] . ' (x' . $order['quantity'] . ')',
            "id_Externe" => (string) $order['id'],
            "Source" => "BOOM STORE19E"
        ]]
    ];

    $payload_json = json_encode($payload_body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $request = zrexpress_api_request($pdo, $settings, 'POST', '/add_colis', [], $payload_body);

    if ($request['success'] && is_array($request['json'])) {
        $new_tracking = zrexpress_find_tracking_in_response($request['json']);
        if ($new_tracking !== '') {
            $flash_message = 'تم الإرسال إلى ZRexpress بنجاح. رقم التتبع: ' . $new_tracking;
            admin_zrexpress_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => $new_tracking,
                'status' => 'sent',
                'remote_status' => 'pret a expedier',
                'last_error' => '',
                'last_payload' => $payload_json,
                'last_response' => $request['response'],
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            if (function_exists('admin_send_order_status_telegram')) {
                admin_send_order_status_telegram($pdo, $order, '', 'pret a expedier', [
                    'tracking' => $new_tracking,
                    'note' => 'Sent to ZRexpress',
                    'remote_time' => date('Y-m-d H:i:s')
                ]);
            }
        } else {
            $flash_type = 'warning';
            $flash_message = 'تم الإرسال ولكن لم يتم استلام رقم التتبع من الاستجابة.';
            admin_zrexpress_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => '',
                'status' => 'error',
                'remote_status' => '',
                'last_error' => 'No tracking number in response',
                'last_payload' => $payload_json,
                'last_response' => $request['response']
            ]);
        }
    } else {
        $flash_type = 'danger';
        $flash_message = 'فشل الإرسال إلى ZRexpress: ' . ($request['error'] ?: 'Unknown error');
        admin_zrexpress_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => '',
            'status' => 'error',
            'remote_status' => '',
            'last_error' => $request['error'] ?: 'API error',
            'last_payload' => $payload_json,
            'last_response' => $request['response']
        ]);
    }
}

if ($action === 'sync') {
    $payload_body = [
        "Colis" => [
            ["Tracking" => $tracking]
        ]
    ];
    $payload_json = json_encode($payload_body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $request = zrexpress_api_request($pdo, $settings, 'POST', '/lire', [], $payload_body);

    if ($request['success'] && is_array($request['json'])) {
        $remote_status = zrexpress_find_situation_in_response($request['json'], $tracking);
        if ($remote_status !== '') {
            $flash_message = 'تمت المزامنة بنجاح. الحالة البعيدة: ' . $remote_status;
            $old_remote_status = trim((string) ($order['zrexpress_remote_status'] ?? ''));
            admin_zrexpress_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => $tracking,
                'status' => 'synced',
                'remote_status' => $remote_status,
                'last_error' => '',
                'last_payload' => $payload_json,
                'last_response' => $request['response']
            ]);
            if (function_exists('admin_send_order_status_telegram')) {
                admin_send_order_status_telegram($pdo, $order, $old_remote_status, $remote_status, [
                    'tracking' => $tracking,
                    'note' => 'ZRexpress Sync',
                    'remote_time' => date('Y-m-d H:i:s')
                ]);
            }
        } else {
            $flash_type = 'warning';
            $flash_message = 'تمت المزامنة ولكن لم يتم العثور على حالة الطرد في الاستجابة.';
            admin_zrexpress_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => $tracking,
                'status' => $status,
                'remote_status' => '',
                'last_error' => 'Status not found in response',
                'last_payload' => $payload_json,
                'last_response' => $request['response']
            ]);
        }
    } else {
        $flash_type = 'danger';
        $flash_message = 'فشل الاتصال بـ ZRexpress لمزامنة الحالة: ' . ($request['error'] ?: 'Unknown error');
        admin_zrexpress_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status,
            'remote_status' => '',
            'last_error' => $request['error'] ?: 'API error',
            'last_payload' => $payload_json,
            'last_response' => $request['response']
        ]);
    }
}

if ($action === 'pret') {
    $payload_body = [
        "Colis" => [
            ["Tracking" => $tracking]
        ]
    ];
    $payload_json = json_encode($payload_body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $request = zrexpress_api_request($pdo, $settings, 'POST', '/pret', [], $payload_body);

    if ($request['success']) {
        $flash_message = 'تم تغيير الحالة إلى جاهز للشحن بنجاح.';
        $old_remote_status = trim((string) ($order['zrexpress_remote_status'] ?? ''));
        admin_zrexpress_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => 'shipped',
            'remote_status' => 'pret a expedier',
            'last_error' => '',
            'last_payload' => $payload_json,
            'last_response' => $request['response']
        ]);
        if (function_exists('admin_send_order_status_telegram')) {
            admin_send_order_status_telegram($pdo, $order, $old_remote_status, 'pret a expedier', [
                'tracking' => $tracking,
                'note' => 'Ready to Ship',
                'remote_time' => date('Y-m-d H:i:s')
            ]);
        }
    } else {
        $flash_type = 'danger';
        $flash_message = 'فشل تغيير الحالة: ' . ($request['error'] ?: 'Unknown error');
        admin_zrexpress_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status,
            'remote_status' => '',
            'last_error' => $request['error'] ?: 'API error',
            'last_payload' => $payload_json,
            'last_response' => $request['response']
        ]);
    }
}

if ($action === 'delete') {
    admin_zrexpress_save_order_state($pdo, $order_id, [
        'reference' => '',
        'tracking' => '',
        'status' => '',
        'remote_status' => '',
        'last_error' => '',
        'last_payload' => '',
        'last_response' => '',
        'sent_at' => null
    ]);
    $flash_message = 'تم حذف بيانات تتبع ZRexpress محلياً بنجاح.';
}

admin_set_flash_message('orders', $flash_type, $flash_message);
header('Location: ' . $redirect);
exit;
