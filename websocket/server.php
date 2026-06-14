<?php
/**
 * WebSocket Server for E-Com Admin Panel
 * Pure PHP implementation (no Composer/Ratchet needed)
 *
 * Usage:
 *   php websocket/server.php
 *
 * Broadcast via file (from broadcast.php helper):
 *   The server monitors a temp file for new broadcasts
 */

define('WS_PORT', 9001);
define('MAX_CLIENTS', 100);
define('BUFFER_SIZE', 65535);
define('BROADCAST_FILE', __DIR__ . '\\broadcast_queue.json');

$port = WS_PORT;

// Parse CLI args
foreach ($argv as $arg) {
    if (strpos($arg, '--port=') === 0) {
        $port = (int) substr($arg, 8);
    }
}

// Initialize broadcast queue file
if (!file_exists(BROADCAST_FILE)) {
    file_put_contents(BROADCAST_FILE, '[]');
}

// Create main socket
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, '0.0.0.0', $port);
socket_listen($server);
socket_set_nonblock($server);

$clients = [];
$sockets = [$server];

echo "==============================================\n";
echo "  WebSocket Server - E-Com Admin\n";
echo "  Port: {$port}\n";
echo "  PID: " . getmypid() . "\n";
echo "==============================================\n";
echo "Waiting for connections...\n\n";

while (true) {
    $read = $sockets;
    $write = $except = null;
    $tvSec = 1;

    $changed = socket_select($read, $write, $except, $tvSec);

    if ($changed === false) {
        echo "socket_select() failed\n";
        break;
    }

    // New connections
    if (in_array($server, $read)) {
        $newClient = socket_accept($server);
        if ($newClient !== false) {
            $addr = '';
            $cport = 0;
            socket_getpeername($newClient, $addr, $cport);
            $clientKey = spl_object_hash($newClient);
            $clients[$clientKey] = [
                'socket' => $newClient,
                'handshake' => false,
                'ip' => $addr,
                'port' => $cport,
                'connected_at' => time(),
            ];
            $sockets[] = $newClient;
            echo "[+] New connection from {$addr}:{$cport}\n";
        }
        unset($read[array_search($server, $read)]);
    }

    // Read from clients
    foreach ($read as $fd) {
        $clientKey = spl_object_hash($fd);
        $client = $clients[$clientKey] ?? null;
        if (!$client) continue;

        $data = @socket_recv($fd, $buffer, BUFFER_SIZE, 0);

        if ($data === false || $data == 0) {
            echo "[-] Client disconnected: {$client['ip']}:{$client['port']}\n";
            socket_close($fd);
            unset($clients[$clientKey]);
            unset($sockets[array_search($fd, $sockets)]);
            continue;
        }

        if (!$client['handshake']) {
            $response = doHandshake($buffer);
            if ($response) {
                socket_write($fd, $response);
                $clients[$clientKey]['handshake'] = true;
                echo "[*] Handshake completed: {$client['ip']}:{$client['port']}\n";
                sendJson($fd, [
                    'type' => 'connected',
                    'message' => 'تم الاتصال بنجاح',
                    'time' => time(),
                ]);
            } else {
                // Not a WebSocket - close
                socket_close($fd);
                unset($clients[$fdInt]);
                unset($sockets[array_search($fd, $sockets)]);
            }
        } else {
            $message = decodeFrame($buffer);
            if ($message !== null) {
                handleClientMessage($fd, $message);
            }
        }
    }

    // Check broadcast queue every 500ms
    checkBroadcastQueue($clients);
}

function doHandshake($buffer) {
    $headers = explode("\r\n", $buffer);
    $key = null;
    $upgrade = false;

    foreach ($headers as $header) {
        if (stripos($header, 'Upgrade: websocket') !== false) {
            $upgrade = true;
        }
        if (preg_match('/Sec-WebSocket-Key:\s*(.+)/i', $header, $m)) {
            $key = trim($m[1]);
        }
    }

    if (!$upgrade || !$key) {
        return null;
    }

    $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

    return "HTTP/1.1 101 Switching Protocols\r\n" .
           "Upgrade: websocket\r\n" .
           "Connection: Upgrade\r\n" .
           "Sec-WebSocket-Accept: {$acceptKey}\r\n" .
           "\r\n";
}

function decodeFrame($data) {
    if (strlen($data) < 2) return null;

    $opcode = ord($data[0]) & 0x0F;
    if ($opcode === 0x08) return null;

    $masked = (ord($data[1]) & 0x80) !== 0;
    $payloadLen = ord($data[1]) & 0x7F;

    $offset = 2;
    if ($payloadLen === 126) {
        if (strlen($data) < 4) return null;
        $payloadLen = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLen === 127) {
        if (strlen($data) < 10) return null;
        $payloadLen = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }

    if ($masked) {
        if (strlen($data) < $offset + 4) return null;
        $mask = substr($data, $offset, 4);
        $offset += 4;
        $payload = '';
        for ($i = 0; $i < $payloadLen; $i++) {
            $payload .= $data[$offset + $i] ^ $mask[$i % 4];
        }
    } else {
        $payload = substr($data, $offset, $payloadLen);
    }

    return $payload;
}

function encodeFrame($data) {
    $length = strlen($data);
    $frame = chr(0x81);

    if ($length <= 125) {
        $frame .= chr($length);
    } elseif ($length <= 65535) {
        $frame .= chr(126) . pack('n', $length);
    } else {
        $frame .= chr(127) . pack('J', $length);
    }

    return $frame . $data;
}

function sendJson($fd, $data) {
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    @socket_write($fd, encodeFrame($json));
}

function broadcast($message, &$clients) {
    $json = json_encode($message, JSON_UNESCAPED_UNICODE);
    $frame = encodeFrame($json);
    $sent = 0;

    foreach ($clients as &$client) {
        if ($client['handshake']) {
            $result = @socket_write($client['socket'], $frame);
            if ($result !== false) {
                $sent++;
            }
        }
    }

    return $sent;
}

function handleClientMessage($fd, $message) {
    $data = json_decode($message, true);
    if (!$data) return;

    $type = $data['type'] ?? '';

    switch ($type) {
        case 'ping':
            sendJson($fd, ['type' => 'pong', 'time' => time()]);
            break;
    }
}

function checkBroadcastQueue(&$clients) {
    static $lastCheck = 0;
    $now = microtime(true);

    if ($now - $lastCheck < 0.5) return;
    $lastCheck = $now;

    $file = BROADCAST_FILE;
    if (!file_exists($file)) return;

    $content = @file_get_contents($file);
    if ($content === false) return;

    $queue = json_decode($content, true);
    if (empty($queue)) return;

    // Clear the file
    file_put_contents($file, '[]');

    // Broadcast each message
    $totalClients = count($clients);
    foreach ($queue as $item) {
        $message = [
            'type' => $item['event'] ?? 'message',
            'payload' => $item['payload'] ?? $item,
            'time' => $item['time'] ?? time(),
        ];
        $sent = broadcast($message, $clients);
        echo "[>] Broadcast: {$message['type']} (sent to {$sent}/{$totalClients} clients)\n";
    }
}
