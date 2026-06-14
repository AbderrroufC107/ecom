<?php
/**
 * WebSocket Test Broadcast
 * Triggers a test broadcast to all connected WebSocket clients
 * 
 * Usage: test_broadcast.php?event=order.new
 *        test_broadcast.php?event=test
 *        test_broadcast.php?event=stock.update
 */

require_once __DIR__ . '/broadcast.php';

$event = $_GET['event'] ?? 'test';

$testPayloads = [
    'test' => [
        'message' => 'Test broadcast at ' . date('Y-m-d H:i:s'),
        'test_id' => rand(1000, 9999),
    ],
    'order.new' => [
        'id' => rand(10000, 99999),
        'customer_name' => 'عميل تجريبي',
        'customer_phone' => '0555' . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
        'product_name' => 'منتج تجريبي #' . rand(1, 100),
        'total_price' => rand(1000, 50000),
        'wilaya' => 'الجزائر',
    ],
    'order.status' => [
        'id' => rand(10000, 99999),
        'status' => 'confirmed',
        'message' => 'تم تأكيد الطلب',
    ],
    'stock.update' => [
        'product_id' => rand(1, 50),
        'product_name' => 'منتج #' . rand(1, 50),
        'stock' => rand(0, 100),
    ],
];

$payload = $testPayloads[$event] ?? $testPayloads['test'];
$result = ws_broadcast($event, $payload);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => $result,
    'event' => $event,
    'payload' => $payload,
    'time' => date('Y-m-d H:i:s'),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
