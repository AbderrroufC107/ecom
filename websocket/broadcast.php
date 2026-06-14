<?php
/**
 * WebSocket Broadcast Helper
 * Include this in any PHP file that needs to broadcast via WebSocket
 *
 * Usage:
 *   require_once __DIR__ . '/websocket/broadcast.php';
 *   ws_broadcast('order.new', ['id' => 123, 'name' => 'John', 'total' => 5000]);
 */

define('WS_BROADCAST_FILE', __DIR__ . '/broadcast_queue.json');

function ws_broadcast(string $event, array $payload = []): bool
{
    $file = WS_BROADCAST_FILE;

    // Read existing queue
    $queue = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $queue = json_decode($content, true) ?: [];
    }

    // Add new message
    $queue[] = [
        'event' => $event,
        'payload' => $payload,
        'time' => time(),
    ];

    // Write back
    $result = file_put_contents($file, json_encode($queue, JSON_UNESCAPED_UNICODE));

    return $result !== false;
}

function ws_broadcast_order_new(array $orderData): bool
{
    return ws_broadcast('order.new', $orderData);
}

function ws_broadcast_order_status(array $orderData): bool
{
    return ws_broadcast('order.status', $orderData);
}

function ws_broadcast_stock_update(array $productData): bool
{
    return ws_broadcast('stock.update', $productData);
}
