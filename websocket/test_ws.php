<?php
/**
 * WebSocket Test Script
 * Tests: Connection, Handshake, Ping/Pong, Broadcast
 */

$host = '127.0.0.1';
$port = 9001;
$passed = 0;
$failed = 0;

function test($name, $result, $detail = '') {
    global $passed, $failed;
    if ($result) {
        $passed++;
        echo "[PASS] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
    } else {
        $failed++;
        echo "[FAIL] {$name}" . ($detail ? " - {$detail}" : "") . "\n";
    }
}

function wsConnect($host, $port) {
    $key = base64_encode(random_bytes(16));
    $fp = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$fp) return null;

    $req = "GET / HTTP/1.1\r\n" .
           "Host: {$host}:{$port}\r\n" .
           "Upgrade: websocket\r\n" .
           "Connection: Upgrade\r\n" .
           "Sec-WebSocket-Key: {$key}\r\n" .
           "Sec-WebSocket-Version: 13\r\n\r\n";

    fwrite($fp, $req);
    $response = fread($fp, 4096);

    if (strpos($response, '101 Switching Protocols') !== false) {
        return $fp;
    }
    fclose($fp);
    return null;
}

function wsRead($fp, $timeout = 2) {
    stream_set_timeout($fp, $timeout);
    $data = @fread($fp, 65535);
    if (!$data || strlen($data) < 2) return null;

    $payloadLen = ord($data[1]) & 0x7F;
    $offset = 2;
    if ($payloadLen === 126) $offset = 4;
    elseif ($payloadLen === 127) $offset = 10;

    return substr($data, $offset);
}

function wsSend($fp, $message) {
    $length = strlen($message);
    $frame = chr(0x81);
    if ($length <= 125) {
        $frame .= chr($length);
    } elseif ($length <= 65535) {
        $frame .= chr(126) . pack('n', $length);
    }
    fwrite($fp, $frame . $message);
}

echo "==============================================\n";
echo "  WebSocket Test Suite - E-Com\n";
echo "  Target: ws://{$host}:{$port}\n";
echo "==============================================\n\n";

// Test 1: Connection
echo "--- Test 1: Connection ---\n";
$fp = wsConnect($host, $port);
test("TCP Connection", $fp !== null, $fp ? "Connected" : "Connection refused");

if (!$fp) {
    echo "\nCannot continue without connection.\n";
    exit(1);
}

// Test 2: Handshake
echo "\n--- Test 2: Handshake ---\n";
test("WebSocket Handshake", true, "101 Switching Protocols");

// Test 3: Welcome message
echo "\n--- Test 3: Welcome Message ---\n";
$welcome = wsRead($fp);
$welcomeData = $welcome ? json_decode($welcome, true) : null;
test("Welcome Message", $welcomeData !== null && ($welcomeData['type'] ?? '') === 'connected',
    $welcome ? trim($welcome) : "No message");

// Test 4: Ping/Pong
echo "\n--- Test 4: Ping/Pong ---\n";
wsSend($fp, json_encode(['type' => 'ping']));
$pong = wsRead($fp);
$pongData = $pong ? json_decode($pong, true) : null;
test("Ping", true, "Sent");
test("Pong", $pongData !== null && ($pongData['type'] ?? '') === 'pong',
    $pong ? trim($pong) : "No response");

// Test 5: Broadcast via PHP
echo "\n--- Test 5: Broadcast ---\n";
$fp2 = wsConnect($host, $port);
if ($fp2) {
    // Read welcome for second client
    wsRead($fp2);

    // Trigger broadcast
    $broadcastUrl = "http://{$host}/ecom/websocket/test_broadcast.php?event=order.new";
    $response = @file_get_contents($broadcastUrl);
    $broadcastData = json_decode($response, true);

    test("Broadcast Trigger", $broadcastData['success'] ?? false, "HTTP request sent");

    // Read broadcast on client 1
    $broadcast1 = wsRead($fp, 3);
    $b1Data = $broadcast1 ? json_decode($broadcast1, true) : null;
    test("Client 1 Received Broadcast", $b1Data !== null && ($b1Data['type'] ?? '') === 'order.new',
        $broadcast1 ? trim(substr($broadcast1, 0, 100)) : "No broadcast");

    // Read broadcast on client 2
    $broadcast2 = wsRead($fp2, 2);
    $b2Data = $broadcast2 ? json_decode($broadcast2, true) : null;
    test("Client 2 Received Broadcast", $b2Data !== null && ($b2Data['type'] ?? '') === 'order.new',
        $broadcast2 ? "Received" : "No broadcast");

    fclose($fp2);
} else {
    test("Broadcast (second client)", false, "Could not connect second client");
}

fclose($fp);

// Summary
echo "\n==============================================\n";
echo "  RESULTS: {$passed} passed, {$failed} failed\n";
echo "==============================================\n";

exit($failed > 0 ? 1 : 0);
