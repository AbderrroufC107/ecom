<?php
/**
 * order-claim.php
 * A regular manager "claims" an order (inserts themselves as its owner) so they
 * can confirm/handle it — for orders that arrived via Telegram or the site and
 * belong to another manager / no manager yet.
 */
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/employee_functions.php');

$isAjax = (isset($_REQUEST['ajax']) && $_REQUEST['ajax'] == 1);
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

function claim_respond(bool $ok, string $msg, bool $isAjax, string $redirect = 'order.php'): void
{
    if ($isAjax) {
        echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    }
    admin_set_flash_message('orders', $ok ? 'success' : 'danger', $msg);
    $redirect = preg_match('/^order(-details\.php\?id=\d+)?(\.php)?(#[A-Za-z0-9_-]+)?$/', $redirect) ? $redirect : 'order.php';
    header('Location: ' . $redirect);
    exit;
}

if (!isset($_SESSION['user'])) {
    claim_respond(false, 'غير مصرّح. سجّل الدخول.', $isAjax);
}

$role     = (string) ($_SESSION['user']['role'] ?? '');
$uid      = (int) ($_SESSION['user']['id'] ?? 0);
$orderId  = (int) ($_REQUEST['id'] ?? 0);
$redirect = (string) ($_REQUEST['redirect'] ?? 'order.php');

// Only regular managers claim. Super Admin doesn't take orders; employees can't claim.
if ($role !== 'Admin' && $role !== 'Manager') {
    claim_respond(false, 'الاستلام متاح للمدير العادي فقط.', $isAjax, $redirect);
}
if ($orderId <= 0) {
    claim_respond(false, 'رقم الطلب غير صحيح.', $isAjax, $redirect);
}

// Order must exist
$st = $dbRepo->prepare("SELECT id FROM tbl_order WHERE id = ? LIMIT 1");
$st->execute([$orderId]);
if (!$st->fetchColumn()) {
    claim_respond(false, 'الطلب غير موجود.', $isAjax, $redirect);
}

try {
    order_claim_by_manager($pdo, $orderId, $uid);
    if (function_exists('audit_log_system')) {
        audit_log_system($pdo, 'order_claimed', "Manager #{$uid} claimed order #{$orderId}");
    }
    claim_respond(true, 'تم استلام الطلب. أصبح ضمن طلباتك ويمكنك تأكيده الآن.', $isAjax, $redirect);
} catch (\Throwable $e) {
    error_log('[order-claim] ' . $e->getMessage());
    claim_respond(false, 'تعذّر استلام الطلب: ' . $e->getMessage(), $isAjax, $redirect);
}
