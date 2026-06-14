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

if (!function_exists('admin_ecotrack_redirect_target')) {
    function admin_ecotrack_redirect_target($raw_target, $fallback_order_id = 0)
    {
        $raw_target = trim((string) $raw_target);
        if ($raw_target === '') {
            return $fallback_order_id > 0 ? 'order-details.php?id=' . (int) $fallback_order_id : 'order.php';
        }

        if (preg_match('/^order-details\.php\?id=(\d+)(#[a-zA-Z0-9_-]+)?$/', $raw_target, $matches)) {
            $target = 'order-details.php?id=' . (int) $matches[1];
            if (!empty($matches[2])) {
                $target .= $matches[2];
            }
            return $target;
        }

        return $fallback_order_id > 0 ? 'order-details.php?id=' . (int) $fallback_order_id : 'order.php';
    }
}

if (!function_exists('admin_ecotrack_save_order_state')) {
    function admin_ecotrack_save_order_state(PDO $pdo, $order_id, array $state, $touch_sent_at = false)
    {
        $sql = "
            UPDATE tbl_order
            SET ecotrack_reference = ?,
                ecotrack_tracking = ?,
                ecotrack_status = ?,
                ecotrack_remote_status = ?,
                ecotrack_last_error = ?,
                ecotrack_last_payload = ?,
                ecotrack_last_response = ?
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

        if (array_key_exists('last_updates', $state)) {
            $sql .= ", ecotrack_last_updates = ?";
            $params[] = $state['last_updates'];
        }

        if (array_key_exists('last_order_info', $state)) {
            $sql .= ", ecotrack_last_order_info = ?";
            $params[] = $state['last_order_info'];
        }

        if (array_key_exists('last_tracking_info', $state)) {
            $sql .= ", ecotrack_last_tracking_info = ?";
            $params[] = $state['last_tracking_info'];
        }

        if (array_key_exists('last_trackings_info', $state)) {
            $sql .= ", ecotrack_last_trackings_info = ?";
            $params[] = $state['last_trackings_info'];
        }

        if (array_key_exists('sent_at', $state)) {
            $sql .= ", ecotrack_sent_at = ?";
            $params[] = $state['sent_at'];
        } elseif ($touch_sent_at) {
            $sql .= ", ecotrack_sent_at = NOW()";
        }

        $sql .= " WHERE id = ? LIMIT 1";
        $params[] = (int) $order_id;

        $statement = $pdo->prepare($sql);
        $statement->execute($params);
    }
}

if (!function_exists('admin_ecotrack_request_success')) {
    function admin_ecotrack_request_success(array $request)
    {
        $request_ok = !empty($request['success']);
        if ($request_ok && is_array($request['json']) && array_key_exists('success', $request['json'])) {
            $request_ok = !empty($request['json']['success']);
        }

        return $request_ok;
    }
}

if (!function_exists('admin_ecotrack_request_error_text')) {
    function admin_ecotrack_request_error_text(array $request, $fallback = '')
    {
        $error_text = '';

        if (!empty($request['json']['errors'])) {
            $error_text = ecotrack_messages_to_text($request['json']['errors']);
        }
        if ($error_text === '' && !empty($request['json']['message'])) {
            $error_text = trim((string) $request['json']['message']);
        }
        if ($error_text === '' && !empty($request['error'])) {
            $error_text = trim((string) $request['error']);
        }
        if ($error_text === '') {
            $error_text = trim((string) $fallback);
        }

        return $error_text;
    }
}

if (!function_exists('admin_ecotrack_is_ajax_request')) {
    function admin_ecotrack_is_ajax_request()
    {
        if (!empty($_POST['ajax'])) {
            return true;
        }

        $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower((string) $requested_with) === 'xmlhttprequest';
    }
}

if (!function_exists('admin_ecotrack_respond')) {
    function admin_ecotrack_respond($is_ajax, $success, $type, $message, $redirect)
    {
        if ($is_ajax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => (bool) $success,
                'type' => (string) $type,
                'message' => (string) $message,
                'redirect' => (string) $redirect
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if ((string) $message !== '') {
            admin_set_flash_message('orders', (string) $type, (string) $message);
        }

        header('Location: ' . $redirect);
        exit;
    }
}

$order_id = isset($_POST['order_id']) ? (int) $_POST['order_id'] : 0;
$redirect = admin_ecotrack_redirect_target($_POST['redirect'] ?? '', $order_id);
$is_ajax_request = admin_ecotrack_is_ajax_request();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

$csrf->verifyRequest();

$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$allowed_actions = ['create', 'update', 'sync', 'ship', 'delete', 'add_note', 'list_updates', 'tracking_info', 'trackings_info', 'order_info'];
if (!in_array($action, $allowed_actions, true)) {
    admin_set_flash_message('orders', 'danger', 'عملية ECOTRACK غير صالحة.');
    header('Location: ' . $redirect);
    exit;
}

admin_ensure_ecotrack_setting_columns($pdo);
admin_ensure_order_ecotrack_columns($pdo);
site_security_ensure_order_columns($pdo);

$statement = $pdo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
$statement->execute([$order_id]);
$order = $statement->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    admin_set_flash_message('orders', 'danger', 'الطلب غير موجود.');
    header('Location: order.php');
    exit;
}

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
if (!ecotrack_is_configured($settings)) {
    admin_set_flash_message('orders', 'danger', 'إعدادات ECOTRACK غير مكتملة. أضف التوكن أولاً من الإعدادات.');
    header('Location: ' . $redirect);
    exit;
}

$reference = ecotrack_build_order_reference($order);
$tracking = trim((string) ($order['ecotrack_tracking'] ?? ''));
$status = trim((string) ($order['ecotrack_status'] ?? ''));
$remote_status = trim((string) ($order['ecotrack_remote_status'] ?? ''));
$changed_by = trim((string) ($_SESSION['user']['full_name'] ?? ''));

if ($action === 'create' && $tracking !== '') {
    admin_set_flash_message('orders', 'warning', 'هذا الطلب أُرسل مسبقًا إلى ECOTRACK. استخدم التحديث أو المزامنة.');
    header('Location: ' . $redirect);
    exit;
}

if (in_array($action, ['update', 'sync', 'ship', 'delete', 'add_note'], true) && $tracking === '') {
    admin_set_flash_message('orders', 'warning', 'يجب إرسال الطلب إلى ECOTRACK أولاً قبل تنفيذ هذه العملية.');
    header('Location: ' . $redirect);
    exit;
}

if (in_array($action, ['list_updates', 'tracking_info', 'trackings_info', 'order_info'], true) && $tracking === '') {
    admin_set_flash_message('orders', 'warning', 'يجب إرسال الطلب إلى ECOTRACK أولاً قبل تنفيذ هذه العملية.');
    header('Location: ' . $redirect);
    exit;
}

$flash_type = 'success';
$flash_message = '';

if ($action === 'create') {
    $request_context = ecotrack_create_order_request_context($pdo, $settings, $order);
    $request_body = $request_context['payload'];
    $prepared_order = $request_context['order'];
    $delivery_fallback_message = trim((string) ($request_context['fallback_message'] ?? ''));
    $request_entry = (array) ($request_body['orders']['0'] ?? []);
    $request_wilaya_code = trim((string) ($request_entry['code_wilaya'] ?? ''));
    $request_commune_name = trim((string) ($request_entry['commune'] ?? ''));
    $request_payload_text = ecotrack_json_encode($request_body, true);

    if ($request_wilaya_code === '' || $request_commune_name === '') {
        $flash_type = 'danger';
        $flash_message = 'تعذر إرسال الطلب إلى ECOTRACK: بيانات الولاية/البلدية غير مكتملة. تأكد من الولاية والبلدية ثم أعد المحاولة.';
        admin_ecotrack_respond($is_ajax_request, false, $flash_type, $flash_message, $redirect);
    }

    $request = ecotrack_api_request($pdo, $settings, 'POST', '/api/v1/create/orders', [], $request_body, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    $result_entry = [];
    if (!empty($request['json']['results'][$reference]) && is_array($request['json']['results'][$reference])) {
        $result_entry = $request['json']['results'][$reference];
    }

    if (!empty($result_entry['success']) && !empty($result_entry['tracking'])) {
        $tracking = trim((string) $result_entry['tracking']);
        $status = 'sent';
        $remote_status = trim((string) ($result_entry['status'] ?? $remote_status));

        try {
            $pdo->beginTransaction();
            if (($prepared_order['delivery_type'] ?? '') !== ($order['delivery_type'] ?? '')) {
                $pdo->prepare("UPDATE tbl_order SET delivery_type = ? WHERE id = ? LIMIT 1")
                    ->execute([(string) $prepared_order['delivery_type'], $order_id]);
                $order['delivery_type'] = $prepared_order['delivery_type'];
            }
            admin_ecotrack_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => $tracking,
                'status' => $status,
                'remote_status' => $remote_status,
                'last_error' => '',
                'last_payload' => $request_payload_text,
                'last_response' => $response_text
            ], true);

            admin_ecotrack_mark_order_sent_locally($pdo, $order, $changed_by !== '' ? $changed_by : null);
            $pdo->commit();
            $flash_message = 'تم إرسال الطلب إلى ECOTRACK بنجاح واعتماده تلقائيًا داخل المتجر. رقم التتبع: ' . $tracking;
            if ($delivery_fallback_message !== '') {
                $flash_message .= ' ' . $delivery_fallback_message;
            }
            site_security_try_record_delivery_return(
                $pdo,
                array_merge($order, [
                    'delivery_type' => $prepared_order['delivery_type'] ?? ($order['delivery_type'] ?? ''),
                    'ecotrack_tracking' => $tracking,
                    'ecotrack_remote_status' => $remote_status
                ]),
                $remote_status,
                '',
                'ecotrack_create'
            );
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $flash_type = 'warning';
            $flash_message = 'تم إرسال الطلب إلى ECOTRACK، لكن تعذر تحديث الحالة المحلية: ' . $exception->getMessage();
        }
    } else {
        $result_errors = $result_entry;
        unset($result_errors['success'], $result_errors['tracking']);

        $error_text = ecotrack_messages_to_text($result_errors);
        if ($error_text === '') {
            $error_text = admin_ecotrack_request_error_text($request, 'تعذر إرسال الطلب إلى ECOTRACK.');
        }

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => '',
            'status' => 'error',
            'remote_status' => '',
            'last_error' => $error_text,
            'last_payload' => $request_payload_text,
            'last_response' => $response_text
        ], false);

        $flash_type = 'danger';
        $flash_message = 'فشل إرسال الطلب إلى ECOTRACK: ' . $error_text;
    }
}

if ($action === 'update') {
    $request_context = ecotrack_create_order_request_context($pdo, $settings, $order);
    $prepared_order = $request_context['order'];
    $delivery_fallback_message = trim((string) ($request_context['fallback_message'] ?? ''));
    $request_entry = (array) (($request_context['payload']['orders']['0'] ?? []));
    $request_wilaya_code = trim((string) ($request_entry['code_wilaya'] ?? ''));
    $request_commune_name = trim((string) ($request_entry['commune'] ?? ''));
    if ($request_wilaya_code === '' || $request_commune_name === '') {
        $flash_type = 'danger';
        $flash_message = 'تعذر تحديث الطلب داخل ECOTRACK: بيانات الولاية/البلدية غير مكتملة. تأكد من الولاية والبلدية ثم أعد المحاولة.';
        admin_ecotrack_respond($is_ajax_request, false, $flash_type, $flash_message, $redirect);
    }

    $query = ecotrack_filter_query_params(ecotrack_update_order_query($pdo, $settings, $prepared_order));
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'POST', '/api/v1/update/order', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        $next_status = ($status === 'synced' || $status === 'shipped') ? $status : 'sent';

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $next_status,
            'remote_status' => $remote_status,
            'last_error' => '',
            'last_payload' => $request_payload_text,
            'last_response' => $response_text
        ], true);

        $flash_message = 'تم تحديث الطلب داخل ECOTRACK بنجاح.';
        if (($prepared_order['delivery_type'] ?? '') !== ($order['delivery_type'] ?? '')) {
            $pdo->prepare("UPDATE tbl_order SET delivery_type = ? WHERE id = ? LIMIT 1")
                ->execute([(string) $prepared_order['delivery_type'], $order_id]);
        }
        if ($delivery_fallback_message !== '') {
            $flash_message .= ' ' . $delivery_fallback_message;
        }
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر تحديث الطلب داخل ECOTRACK.');

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => $error_text,
            'last_payload' => $request_payload_text,
            'last_response' => $response_text
        ], false);

        $flash_type = 'danger';
        $flash_message = 'فشل تحديث الطلب داخل ECOTRACK: ' . $error_text;
    }
}

if ($action === 'ship') {
    $ask_collection = isset($_POST['ask_collection']) ? (int) $_POST['ask_collection'] : 0;
    if ($ask_collection !== 1) {
        $ask_collection = 0;
    }

    $query = ['tracking' => $tracking, 'ask_collection' => $ask_collection];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'POST', '/api/v1/valid/order', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => 'shipped',
            'remote_status' => $remote_status,
            'last_error' => '',
            'last_payload' => $request_payload_text,
            'last_response' => $response_text
        ], true);

        $flash_message = 'تم تأكيد الشحن داخل ECOTRACK بنجاح.';
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر تأكيد الشحن داخل ECOTRACK.');

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => $error_text,
            'last_payload' => $request_payload_text,
            'last_response' => $response_text
        ], false);

        $flash_type = 'danger';
        $flash_message = 'فشل تأكيد الشحن داخل ECOTRACK: ' . $error_text;
    }
}

if ($action === 'delete') {
    $query = ['tracking' => $tracking];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'DELETE', '/api/v1/delete/order', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        try {
            $pdo->beginTransaction();
            admin_ecotrack_save_order_state($pdo, $order_id, [
                'reference' => $reference,
                'tracking' => '',
                'status' => 'pending',
                'remote_status' => '',
                'last_error' => '',
                'last_payload' => $request_payload_text,
                'last_response' => $response_text,
                'last_order_info' => '',
                'last_updates' => '',
                'last_tracking_info' => '',
                'last_trackings_info' => '',
                'sent_at' => null
            ], false);

            $restored_status = admin_ecotrack_restore_local_order_status($pdo, $order, $changed_by !== '' ? $changed_by : null);
            $pdo->commit();

            $restored_meta = admin_get_order_status_meta($restored_status);
            $flash_message = 'تم حذف الطلب من ECOTRACK بنجاح وإرجاعه إلى الحالة "' . $restored_meta['label'] . '".';
        } catch (Exception $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $flash_type = 'warning';
            $flash_message = 'تم حذف الطلب من ECOTRACK، لكن تعذر استرجاع الحالة المحلية: ' . $exception->getMessage();
        }
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر حذف الطلب من ECOTRACK.');

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => $error_text,
            'last_payload' => $request_payload_text,
            'last_response' => $response_text
        ], false);

        $flash_type = 'danger';
        $flash_message = 'فشل حذف الطلب من ECOTRACK: ' . $error_text;
    }
}

if ($action === 'add_note') {
    $content = trim((string) ($_POST['tracking_note'] ?? ''));
    $query = ['tracking' => $tracking, 'content' => $content];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'POST', '/api/v1/add/maj', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        $flash_message = 'تمت إضافة ملاحظة التتبع بنجاح.';
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر إضافة ملاحظة التتبع داخل ECOTRACK.');
        $flash_type = 'danger';
        $flash_message = 'فشل إضافة ملاحظة التتبع: ' . $error_text;
    }
}

if ($action === 'list_updates') {
    $query = ['tracking' => $tracking];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/maj', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => '',
            'last_payload' => $request_payload_text,
            'last_response' => $response_text,
            'last_updates' => $response_text
        ], false);

        $flash_message = 'تم جلب قائمة التحديثات بنجاح.';
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر جلب قائمة التحديثات.');
        $flash_type = 'danger';
        $flash_message = 'فشل جلب التحديثات: ' . $error_text;
    }
}

if ($action === 'tracking_info') {
    $query = ['tracking' => $tracking];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/tracking/info', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        $previous_remote_status = $remote_status;
        $synced_status = ecotrack_extract_remote_status($request['json'] ?? null, $tracking);
        $status_note = ecotrack_extract_remote_note($request['json'] ?? null, $tracking);
        if ($synced_status !== '') {
            $remote_status = $synced_status;
        }

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => '',
            'last_payload' => $request_payload_text,
            'last_response' => $response_text,
            'last_tracking_info' => $response_text
        ], false);

        site_security_try_record_delivery_return(
            $pdo,
            array_merge($order, ['ecotrack_tracking' => $tracking, 'ecotrack_remote_status' => $remote_status]),
            $remote_status,
            $status_note,
            'ecotrack_tracking_info'
        );

        $telegram_result = admin_send_order_status_telegram($pdo, array_merge($order, ['ecotrack_tracking' => $tracking]), $previous_remote_status, $remote_status, [
            'tracking' => $tracking,
            'note' => $status_note
        ]);
        if (empty($telegram_result['skipped']) && empty($telegram_result['success'])) {
            error_log('Order status Telegram failed for order #' . $order_id . ': ' . trim((string) ($telegram_result['error'] ?? 'send failed')));
        }

        $flash_message = 'تم جلب سجل تتبع الشحنة بنجاح.';
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر جلب سجل تتبع الشحنة.');
        $flash_type = 'danger';
        $flash_message = 'فشل جلب سجل التتبع: ' . $error_text;
    }
}

if ($action === 'trackings_info') {
    $query = ['trackings[]' => $tracking];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        $previous_remote_status = $remote_status;
        $synced_status = ecotrack_extract_remote_status($request['json'] ?? null, $tracking);
        $status_note = ecotrack_extract_remote_note($request['json'] ?? null, $tracking);
        if ($synced_status !== '') {
            $remote_status = $synced_status;
        }

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => '',
            'last_payload' => $request_payload_text,
            'last_response' => $response_text,
            'last_trackings_info' => $response_text
        ], false);

        site_security_try_record_delivery_return(
            $pdo,
            array_merge($order, ['ecotrack_tracking' => $tracking, 'ecotrack_remote_status' => $remote_status]),
            $remote_status,
            $status_note,
            'ecotrack_trackings_info'
        );

        $telegram_result = admin_send_order_status_telegram($pdo, array_merge($order, ['ecotrack_tracking' => $tracking]), $previous_remote_status, $remote_status, [
            'tracking' => $tracking,
            'note' => $status_note
        ]);
        if (empty($telegram_result['skipped']) && empty($telegram_result['success'])) {
            error_log('Order status Telegram failed for order #' . $order_id . ': ' . trim((string) ($telegram_result['error'] ?? 'send failed')));
        }

        $flash_message = 'تم جلب تاريخ العمليات بنجاح.';
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر جلب تاريخ العمليات.');
        $flash_type = 'danger';
        $flash_message = 'فشل جلب تاريخ العمليات: ' . $error_text;
    }
}

if ($action === 'order_info') {
    $query = ['tracking' => $tracking];
    $request_payload_text = ecotrack_json_encode($query, true);
    $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/orders', $query, null, 'bearer');
    $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

    if (admin_ecotrack_request_success($request)) {
        $previous_remote_status = $remote_status;
        $synced_status = ecotrack_extract_remote_status($request['json'] ?? null, $tracking);
        $status_note = ecotrack_extract_remote_note($request['json'] ?? null, $tracking);
        if ($synced_status !== '') {
            $remote_status = $synced_status;
        }

        admin_ecotrack_save_order_state($pdo, $order_id, [
            'reference' => $reference,
            'tracking' => $tracking,
            'status' => $status !== '' ? $status : 'sent',
            'remote_status' => $remote_status,
            'last_error' => '',
            'last_payload' => $request_payload_text,
            'last_response' => $response_text,
            'last_order_info' => $response_text
        ], false);

        site_security_try_record_delivery_return(
            $pdo,
            array_merge($order, ['ecotrack_tracking' => $tracking, 'ecotrack_remote_status' => $remote_status]),
            $remote_status,
            $status_note,
            'ecotrack_order_info'
        );

        $telegram_result = admin_send_order_status_telegram($pdo, array_merge($order, ['ecotrack_tracking' => $tracking]), $previous_remote_status, $remote_status, [
            'tracking' => $tracking,
            'note' => $status_note
        ]);
        if (empty($telegram_result['skipped']) && empty($telegram_result['success'])) {
            error_log('Order status Telegram failed for order #' . $order_id . ': ' . trim((string) ($telegram_result['error'] ?? 'send failed')));
        }

        $flash_message = 'تم جلب معلومات الطلب من ECOTRACK بنجاح.';
    } else {
        $error_text = admin_ecotrack_request_error_text($request, 'تعذر جلب معلومات الطلب.');
        $flash_type = 'danger';
        $flash_message = 'فشل جلب معلومات الطلب: ' . $error_text;
    }
}

admin_ecotrack_respond($is_ajax_request, $flash_type === 'success', $flash_type, $flash_message, $redirect);

