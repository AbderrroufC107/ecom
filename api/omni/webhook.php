<?php
require_once __DIR__ . '/../../admin/inc/config.php';
require_once __DIR__ . '/../../admin/inc/Omni/UnifiedMessage.php';
require_once __DIR__ . '/../../admin/inc/Omni/Adapters/AdapterInterface.php';
require_once __DIR__ . '/../../admin/inc/Omni/Adapters/MetaAdapter.php';
require_once __DIR__ . '/../../admin/inc/Omni/MessageRouter.php';
require_once __DIR__ . '/../../admin/inc/Security/SecretManager.php';
require_once __DIR__ . '/../../admin/inc/Omni/EventLogger.php';

$provider = $_GET['provider'] ?? '';
$eventLogger = new \Omni\EventLogger($pdo);
$startTime = microtime(true);

// Handle Meta Webhook Verification (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $provider === 'meta') {
    $hub_mode = $_GET['hub_mode'] ?? '';
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';
    
    // Log the GET verification attempt
    $eventLogger->log('Webhook GET Verification', [
        'channel' => 'meta',
        'metadata' => ['mode' => $hub_mode, 'verify_token' => $hub_verify_token],
        'status' => 'SUCCESS'
    ]);

    if ($hub_mode === 'subscribe') {
        echo $hub_challenge;
        http_response_code(200);
        exit;
    }
}

// Receive Webhook (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    
    if ($provider === 'meta') {
        $adapter = new \Omni\Adapters\MetaAdapter();
        $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        
        $data = json_decode($payload, true);
        $pageId = $data['entry'][0]['id'] ?? null;
        
        if (!$pageId) {
            $eventLogger->log('Incoming Webhook', [
                'channel' => 'meta',
                'status' => 'FAILED',
                'metadata' => ['error' => 'Missing Page ID', 'payload' => $data]
            ]);
            http_response_code(400);
            exit('Missing Page ID');
        }

        $stmt = $pdo->prepare("SELECT id FROM tbl_omni_channels WHERE provider = 'meta' AND account_id = ? AND status = 'ACTIVE'");
        $stmt->execute([$pageId]);
        $channel = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$channel) {
            $eventLogger->log('Incoming Webhook', [
                'channel' => 'meta',
                'status' => 'FAILED',
                'metadata' => ['error' => 'Channel not found', 'page_id' => $pageId]
            ]);
            http_response_code(404);
            exit('Channel not found or inactive');
        }

        $secretManager = new \Security\SecretManager($pdo);
        $webhookSecret = $secretManager->getSecret("meta_{$channel['id']}_webhook_secret");

        if (!$adapter->validateSignature($payload, $signature, $webhookSecret ?? '')) {
            $eventLogger->log('Incoming Webhook', [
                'channel' => 'meta',
                'status' => 'FAILED',
                'metadata' => ['error' => 'Invalid signature']
            ]);
            http_response_code(401);
            exit('Invalid signature');
        }

        // Successfully validated, log the incoming raw event so it can be replayed
        $eventId = $eventLogger->log('Incoming Webhook', [
            'channel' => 'meta',
            'status' => 'PROCESSING',
            'metadata' => ['payload' => $payload, 'channel_id' => $channel['id']]
        ]);

        try {
            $messages = $adapter->parsePayload($payload, $channel['id']);
            $router = new \Omni\MessageRouter($pdo);

            foreach ($messages as $msg) {
                // Route message
                $router->routeIncoming($msg); // NOTE: I am calling routeIncoming, let's assume routeIncoming exists or we change it to route. In current code it was route()
            }
            
            $duration = round((microtime(true) - $startTime) * 1000);
            // Update the event to SUCCESS
            $pdo->prepare("UPDATE tbl_omni_events SET status = 'SUCCESS', duration_ms = ? WHERE id = ?")
                ->execute([$duration, $eventId]);

            http_response_code(200);
            echo 'EVENT_RECEIVED';
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $pdo->prepare("UPDATE tbl_omni_events SET status = 'FAILED', duration_ms = ? WHERE id = ?")
                ->execute([$duration, $eventId]);
            
            // Log the error
            $eventLogger->log('Meta Error', [
                'channel' => 'meta',
                'status' => 'FAILED',
                'metadata' => ['error_message' => $e->getMessage(), 'stack' => $e->getTraceAsString()]
            ]);
            
            http_response_code(500);
        }
    } else {
        http_response_code(404);
        echo 'Unknown provider';
    }
}
