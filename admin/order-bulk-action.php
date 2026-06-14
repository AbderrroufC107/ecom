<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once('inc/config.php');
require_once('inc/functions.php');
require_once(dirname(__DIR__) . '/inc/site-security.php');
require_once('inc/performance_functions.php');

if (!function_exists('admin_orders_bulk_redirect')) {
    function admin_orders_bulk_redirect($target = 'order.php')
    {
        $target = trim((string) $target);
        if (!preg_match('/^order\.php(?:#[A-Za-z0-9_-]+)?$/', $target)) {
            $target = 'order.php';
        }

        header('Location: ' . $target);
        exit;
    }
}

if (!function_exists('admin_orders_bulk_sample_messages')) {
    function admin_orders_bulk_sample_messages(array $messages, $limit = 4)
    {
        $messages = array_values(array_filter(array_map('trim', $messages)));
        if (empty($messages)) {
            return '';
        }

        return implode(' | ', array_slice($messages, 0, max(1, (int) $limit)));
    }
}

$redirect = trim((string) ($_POST['redirect'] ?? 'order.php'));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    admin_orders_bulk_redirect($redirect);
}

$ids = isset($_POST['order_ids']) && is_array($_POST['order_ids']) ? array_values(array_unique(array_map('intval', $_POST['order_ids']))) : [];
$action = trim((string) ($_POST['action'] ?? ''));
$status_note = trim((string) ($_POST['status_note'] ?? ''));
$changed_by = trim((string) ($_SESSION['user']['full_name'] ?? ''));
$ecotrack_bulk_actions = ['ecotrack_create', 'ecotrack_delete', 'ecotrack_print_4up'];

if (empty($ids) || $action === '') {
    admin_set_flash_message('orders', 'danger', 'Ø§Ù„Ø±Ø¬Ø§Ø¡ ØªØ­Ø¯ÙŠØ¯ Ø§Ù„Ø·Ù„Ø¨Ø§Øª ÙˆØ§Ù„Ø¥Ø¬Ø±Ø§Ø¡ Ø§Ù„Ø¬Ù…Ø§Ø¹ÙŠ Ù‚Ø¨Ù„ Ø§Ù„Ù…ØªØ§Ø¨Ø¹Ø©.');
    admin_orders_bulk_redirect();
}

try {
    admin_ensure_order_call_log_table($pdo);
    admin_ensure_order_status_log_table($pdo);
    site_security_ensure_tables($pdo);
    site_security_ensure_order_columns($pdo);

    if ($action === 'ecotrack_print_4up') {
        $query = http_build_query(['order_ids' => $ids]);
        header('Location: order-ecotrack-bulk-label.php' . ($query !== '' ? '?' . $query : ''));
        exit;
    }

    if (in_array($action, $ecotrack_bulk_actions, true)) {
        admin_ensure_ecotrack_setting_columns($pdo);
        admin_ensure_order_ecotrack_columns($pdo);

        $settings = ecotrack_normalize_settings(front_get_settings($pdo));
        if (!ecotrack_is_configured($settings)) {
            throw new Exception('Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª ECOTRACK ØºÙŠØ± Ù…ÙƒØªÙ…Ù„Ø©. Ø£Ø¶Ù Ø§Ù„ØªÙˆÙƒÙ† ÙˆØ§Ù„Ø±Ø§Ø¨Ø· Ø£ÙˆÙ„Ù‹Ø§ Ù…Ù† Ø§Ù„Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª.');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $pdo->prepare("SELECT * FROM tbl_order WHERE id IN ($placeholders)");
        $statement->execute($ids);
        $orders = $statement->fetchAll(PDO::FETCH_ASSOC);

        if (!$orders) {
            throw new Exception('Ù„Ù… ÙŠØªÙ… Ø§Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ø£ÙŠ Ø·Ù„Ø¨Ø§Øª Ù…Ø·Ø§Ø¨Ù‚Ø© Ù„Ù„Ø¹Ù†Ø§ØµØ± Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.');
        }

        $orders_by_id = [];
        foreach ($orders as $order) {
            $orders_by_id[(int) $order['id']] = $order;
        }

        $success_count = 0;
        $skipped_count = 0;
        $failed_count = 0;
        $error_messages = [];
        $notice_messages = [];

        foreach ($ids as $id) {
            if (!isset($orders_by_id[$id])) {
                $skipped_count++;
                $error_messages[] = '#' . $id . ': Ø§Ù„Ø·Ù„Ø¨ ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯.';
                continue;
            }

            $order = $orders_by_id[$id];
            $reference = ecotrack_build_order_reference($order);
            $tracking = trim((string) ($order['ecotrack_tracking'] ?? ''));
            $status = trim((string) ($order['ecotrack_status'] ?? ''));
            $remote_status = trim((string) ($order['ecotrack_remote_status'] ?? ''));

            if ($action === 'ecotrack_create') {
                if ($tracking !== '') {
                    $skipped_count++;
                    $error_messages[] = '#' . $id . ': Ø§Ù„Ø·Ù„Ø¨ Ù…ÙØ±Ø³Ù„ Ù…Ø³Ø¨Ù‚Ù‹Ø§ Ø¥Ù„Ù‰ ECOTRACK.';
                    continue;
                }

                $request_context = ecotrack_create_order_request_context($pdo, $settings, $order);
                $request_body = $request_context['payload'];
                $prepared_order = $request_context['order'];
                $delivery_fallback_message = trim((string) ($request_context['fallback_message'] ?? ''));
                $request_payload_text = ecotrack_json_encode($request_body, true);
                $request = ecotrack_api_request($pdo, $settings, 'POST', '/api/v1/create/orders', [], $request_body, 'bearer');
                $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

                $result_entry = [];
                if (!empty($request['json']['results'][$reference]) && is_array($request['json']['results'][$reference])) {
                    $result_entry = $request['json']['results'][$reference];
                }

                if (!empty($result_entry['success']) && !empty($result_entry['tracking'])) {
                    $tracking = trim((string) $result_entry['tracking']);
                    $remote_status = trim((string) ($result_entry['status'] ?? $remote_status));

                    $pdo->beginTransaction();
                    if (($prepared_order['delivery_type'] ?? '') !== ($order['delivery_type'] ?? '')) {
                        $pdo->prepare("UPDATE tbl_order SET delivery_type = ? WHERE id = ? LIMIT 1")
                            ->execute([(string) $prepared_order['delivery_type'], $id]);
                        $order['delivery_type'] = $prepared_order['delivery_type'];
                    }
                    admin_ecotrack_save_order_state($pdo, $id, [
                        'reference' => $reference,
                        'tracking' => $tracking,
                        'status' => 'sent',
                        'remote_status' => $remote_status,
                        'last_error' => '',
                        'last_payload' => $request_payload_text,
                        'last_response' => $response_text
                    ], true);
                    admin_ecotrack_mark_order_sent_locally($pdo, $order, $changed_by !== '' ? $changed_by : null);
                    $pdo->commit();

                    $success_count++;
                    if ($delivery_fallback_message !== '') {
                        $notice_messages[] = '#' . $id . ': ' . $delivery_fallback_message;
                    }
                    continue;
                }

                $result_errors = $result_entry;
                unset($result_errors['success'], $result_errors['tracking']);
                $error_text = ecotrack_messages_to_text($result_errors);
                if ($error_text === '') {
                    $error_text = admin_ecotrack_request_error_text($request, 'ØªØ¹Ø°Ø± Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø·Ù„Ø¨ Ø¥Ù„Ù‰ ECOTRACK.');
                }

                admin_ecotrack_save_order_state($pdo, $id, [
                    'reference' => $reference,
                    'tracking' => '',
                    'status' => 'error',
                    'remote_status' => '',
                    'last_error' => $error_text,
                    'last_payload' => $request_payload_text,
                    'last_response' => $response_text
                ], false);

                $failed_count++;
                $error_messages[] = '#' . $id . ': ' . $error_text;
                continue;
            }

            if ($tracking === '') {
                $skipped_count++;
                $error_messages[] = '#' . $id . ': Ù„Ø§ ÙŠÙˆØ¬Ø¯ Ø±Ù‚Ù… ØªØªØ¨Ø¹ Ø¯Ø§Ø®Ù„ ECOTRACK.';
                continue;
            }

            $query = ['tracking' => $tracking];
            $request_payload_text = ecotrack_json_encode($query, true);
            $request = ecotrack_api_request($pdo, $settings, 'DELETE', '/api/v1/delete/order', $query, null, 'bearer');
            $response_text = ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null);

            if (admin_ecotrack_request_success($request)) {
                $pdo->beginTransaction();
                admin_ecotrack_save_order_state($pdo, $id, [
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
                admin_ecotrack_restore_local_order_status($pdo, $order, $changed_by !== '' ? $changed_by : null);
                $pdo->commit();

                $success_count++;
                continue;
            }

            $error_text = admin_ecotrack_request_error_text($request, 'ØªØ¹Ø°Ø± Ø­Ø°Ù Ø§Ù„Ø·Ù„Ø¨ Ù…Ù† ECOTRACK.');
            admin_ecotrack_save_order_state($pdo, $id, [
                'reference' => $reference,
                'tracking' => $tracking,
                'status' => $status !== '' ? $status : 'sent',
                'remote_status' => $remote_status,
                'last_error' => $error_text,
                'last_payload' => $request_payload_text,
                'last_response' => $response_text
            ], false);

            $failed_count++;
            $error_messages[] = '#' . $id . ': ' . $error_text;
        }

        if ($action === 'ecotrack_create') {
            if ($success_count > 0) {
                $message = 'ØªÙ… Ø¥Ø±Ø³Ø§Ù„ ' . $success_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª Ø¥Ù„Ù‰ ECOTRACK ÙˆØ§Ø¹ØªÙ…Ø§Ø¯Ù‡Ø§ ØªÙ„Ù‚Ø§Ø¦ÙŠÙ‹Ø§ Ø¯Ø§Ø®Ù„ Ø§Ù„Ù…ØªØ¬Ø±.';
                if ($skipped_count > 0) {
                    $message .= ' ØªÙ… ØªØ¬Ø§ÙˆØ² ' . $skipped_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª.';
                }
                if (!empty($notice_messages)) {
                    $message .= ' ' . admin_orders_bulk_sample_messages($notice_messages);
                }
                if ($failed_count > 0) {
                    $message .= ' ÙØ´Ù„ ' . $failed_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª. ' . admin_orders_bulk_sample_messages($error_messages);
                }
                admin_set_flash_message('orders', $failed_count > 0 ? 'warning' : 'success', $message);
            } else {
                admin_set_flash_message('orders', 'danger', 'Ù„Ù… ÙŠØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø£ÙŠ Ø·Ù„Ø¨ Ø¥Ù„Ù‰ ECOTRACK. ' . admin_orders_bulk_sample_messages($error_messages));
            }
        } else {
            if ($success_count > 0) {
                $message = 'ØªÙ… Ø­Ø°Ù ' . $success_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª Ù…Ù† ECOTRACK ÙˆØ¥Ø±Ø¬Ø§Ø¹Ù‡Ø§ Ø¥Ù„Ù‰ Ø­Ø§Ù„ØªÙ‡Ø§ Ø§Ù„Ù…Ø­Ù„ÙŠØ© Ø§Ù„Ø³Ø§Ø¨Ù‚Ø©.';
                if ($skipped_count > 0) {
                    $message .= ' ØªÙ… ØªØ¬Ø§ÙˆØ² ' . $skipped_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª.';
                }
                if ($failed_count > 0) {
                    $message .= ' ÙØ´Ù„ ' . $failed_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª. ' . admin_orders_bulk_sample_messages($error_messages);
                }
                admin_set_flash_message('orders', $failed_count > 0 ? 'warning' : 'success', $message);
            } else {
                admin_set_flash_message('orders', 'danger', 'Ù„Ù… ÙŠØªÙ… Ø­Ø°Ù Ø£ÙŠ Ø·Ù„Ø¨ Ù…Ù† ECOTRACK. ' . admin_orders_bulk_sample_messages($error_messages));
            }
        }

        admin_orders_bulk_redirect($redirect);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $pdo->prepare("SELECT id, order_status, customer_name, customer_phone, product_name, quantity, total_price, wilaya, commune, address, delivery_type, customer_ip, device_id FROM tbl_order WHERE id IN ($placeholders)");
    $statement->execute($ids);
    $orders = $statement->fetchAll(PDO::FETCH_ASSOC);

    if (!$orders) {
        throw new Exception('Ù„Ù… ÙŠØªÙ… Ø§Ù„Ø¹Ø«ÙˆØ± Ø¹Ù„Ù‰ Ø£ÙŠ Ø·Ù„Ø¨Ø§Øª Ù…Ø·Ø§Ø¨Ù‚Ø© Ù„Ù„Ø¹Ù†Ø§ØµØ± Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.');
    }

    $orders_by_id = [];
    foreach ($orders as $order) {
        $orders_by_id[(int) $order['id']] = $order;
    }

    $pdo->beginTransaction();

    $updated_count = 0;
    $deleted_count = 0;
    $skipped_count = 0;
    $auto_sms_queue = [];

    if ($action === 'delete') {
        $existing_ids = array_keys($orders_by_id);
        if ($existing_ids) {
            $delete_placeholders = implode(',', array_fill(0, count($existing_ids), '?'));

            $statement = $pdo->prepare("DELETE FROM tbl_order_call_log WHERE order_id IN ($delete_placeholders)");
            $statement->execute($existing_ids);

            $statement = $pdo->prepare("DELETE FROM tbl_order_status_log WHERE order_id IN ($delete_placeholders)");
            $statement->execute($existing_ids);

            $statement = $pdo->prepare("DELETE FROM tbl_order WHERE id IN ($delete_placeholders)");
            $statement->execute($existing_ids);

            $deleted_count = count($existing_ids);
        }
    } else {
        $target_status = admin_normalize_order_status($action);
        $statement = $pdo->prepare('UPDATE tbl_order SET order_status = ? WHERE id = ?');

        foreach ($ids as $id) {
            if (!isset($orders_by_id[$id])) {
                $skipped_count++;
                continue;
            }

            $current_status = admin_normalize_order_status($orders_by_id[$id]['order_status'] ?? '');
            if (!admin_can_transition_order_status($current_status, $target_status)) {
                $skipped_count++;
                continue;
            }

            $statement->execute([$target_status, $id]);
            $orders_by_id[$id]['order_status'] = $target_status;
            $orders_by_id[$id]['status'] = $target_status;
            $auto_sms_queue[] = $orders_by_id[$id];
            admin_log_order_status_change($pdo, $id, $current_status, $target_status, $status_note, $changed_by);
            if ($target_status === 'Returned') {
                site_security_try_record_delivery_return($pdo, $orders_by_id[$id], 'Returned', $status_note, 'manual_bulk_status');
            }
            if ($target_status === 'Completed') {
                performance_ensure_tables($pdo);
                performance_auto_record_commission($pdo, $id);
            }
            $updated_count++;
        }
    }

    $pdo->commit();

    $auto_sms_sent = 0;
    $auto_sms_failed = 0;
    if ($action !== 'delete' && !empty($auto_sms_queue)) {
        $event_key = admin_resolve_sms_status_event_key($target_status);
        foreach ($auto_sms_queue as $auto_sms_order) {
            $auto_sms_result = admin_send_order_sms_automation($pdo, $event_key, $auto_sms_order);
            if (!empty($auto_sms_result['skipped'])) {
                continue;
            }
            if (!empty($auto_sms_result['success'])) {
                $auto_sms_sent++;
            } else {
                $auto_sms_failed++;
            }
        }
    }

    if ($action === 'delete') {
        admin_set_flash_message('orders', 'success', 'ØªÙ… Ø­Ø°Ù ' . $deleted_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª Ø¨Ù†Ø¬Ø§Ø­.' . ($skipped_count > 0 ? ' ØªÙ… ØªØ¬Ø§ÙˆØ² ' . $skipped_count . ' Ø¹Ù†ØµØ± ØºÙŠØ± ØµØ§Ù„Ø­.' : ''));
    } else {
        $target_meta = admin_get_order_status_meta($action);
        if ($updated_count === 0) {
            admin_set_flash_message('orders', 'warning', 'Ù„Ù… ÙŠØªÙ… ØªØ­Ø¯ÙŠØ« Ø£ÙŠ Ø·Ù„Ø¨. ØºØ§Ù„Ø¨Ø§Ù‹ Ù„Ø£Ù† Ø§Ù„Ø­Ø§Ù„Ø§Øª Ø§Ù„Ø­Ø§Ù„ÙŠØ© Ù„Ø§ ØªØ³Ù…Ø­ Ø¨Ø§Ù„Ø¥Ø¬Ø±Ø§Ø¡ Ø§Ù„Ù…Ø­Ø¯Ø¯.');
        } else {
            $message = 'ØªÙ… ØªØ­Ø¯ÙŠØ« ' . $updated_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª Ø¥Ù„Ù‰ Ø­Ø§Ù„Ø© "' . $target_meta['label'] . '".';
            if ($skipped_count > 0) {
                $message .= ' ØªÙ… ØªØ¬Ø§ÙˆØ² ' . $skipped_count . ' Ø·Ù„Ø¨/Ø·Ù„Ø¨Ø§Øª Ù„Ø£Ù† Ø§Ù†ØªÙ‚Ø§Ù„ Ø§Ù„Ø­Ø§Ù„Ø© ØºÙŠØ± Ù…Ø³Ù…ÙˆØ­.';
            }
            if ($auto_sms_sent > 0) {
                $message .= ' ØªÙ… Ø¥Ø±Ø³Ø§Ù„ ' . $auto_sms_sent . ' SMS ØªÙ„Ù‚Ø§Ø¦ÙŠ.';
            }
            if ($auto_sms_failed > 0) {
                $message .= ' ØªØ¹Ø°Ø± Ø¥Ø±Ø³Ø§Ù„ ' . $auto_sms_failed . ' SMS ØªÙ„Ù‚Ø§Ø¦ÙŠ.';
            }
            admin_set_flash_message('orders', 'success', $message);
        }
    }
} catch (Exception $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    admin_set_flash_message('orders', 'danger', $exception->getMessage());
}

admin_orders_bulk_redirect($redirect);

