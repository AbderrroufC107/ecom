<?php
/**
 * ═══════════════════════════════════════════════════════════════
 *  META WEBHOOK ENDPOINT
 *  Handles incoming Messenger, Instagram, and Facebook Comments
 * ═══════════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/Security/SecretManager.php';
require_once __DIR__ . '/../admin/inc/Omni/EventLogger.php';
require_once __DIR__ . '/../admin/inc/Omni/Adapters/AdapterInterface.php';
require_once __DIR__ . '/../admin/inc/Omni/Adapters/MetaAdapter.php';
require_once __DIR__ . '/../admin/inc/Omni/UnifiedMessage.php';
require_once __DIR__ . '/../admin/inc/Omni/MessageRouter.php';

use Security\SecretManager;
use Omni\EventLogger;
use Omni\Adapters\MetaAdapter;
use Omni\MessageRouter;

$logger = new EventLogger($pdo);

// ─── 1. Verification (GET) ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hub_mode      = $_GET['hub_mode'] ?? '';
    $hub_challenge = $_GET['hub_challenge'] ?? '';
    $hub_verify_token = $_GET['hub_verify_token'] ?? '';

    if ($hub_mode === 'subscribe' && !empty($hub_verify_token)) {
        $sm = new SecretManager($pdo);
        $savedToken = $sm->getSecret('meta_verify_token') ?? '';
        
        if ($hub_verify_token === $savedToken) {
            $logger->log('Meta Webhook Verified', ['ip' => $_SERVER['REMOTE_ADDR'], 'status' => 'SUCCESS']);
            echo $hub_challenge;
            http_response_code(200);
            exit;
        } else {
            $logger->log('Meta Webhook Verification Failed', ['ip' => $_SERVER['REMOTE_ADDR'], 'status' => 'FAILED', 'reason' => 'Invalid verify token']);
            http_response_code(403);
            exit;
        }
    }
    http_response_code(400);
    exit;
}

// ─── 2. Payload Processing (POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

    // Log raw entry briefly
    $logId = $logger->log('Meta Incoming Payload', ['signature' => $signature, 'size' => strlen($payload), 'status' => 'PENDING']);

    // 2.1 Validate Signature
    $sm = new SecretManager($pdo);
    $appSecret = $sm->getSecret('meta_app_secret');
    
    $adapter = new MetaAdapter();
    if (!$appSecret || !$adapter->validateSignature($payload, $signature, $appSecret)) {
        $logger->log('Meta Signature Invalid', ['status' => 'FAILED', 'payload' => substr($payload, 0, 500)]);
        $pdo->prepare("UPDATE tbl_omni_events SET status='FAILED', metadata = JSON_SET(metadata, '$.error', 'Invalid Signature') WHERE id=?")
            ->execute([$logId]);
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // 2.2 Identify Channel ID mapping (Normally we'd check DB for active Meta channels)
    // For now, we take the default active Meta channel
    $stmt = $pdo->prepare("SELECT id FROM tbl_omni_channels WHERE provider = 'meta' AND status = 'ACTIVE' LIMIT 1");
    $stmt->execute();
    $channelId = (int) $stmt->fetchColumn();

    if (!$channelId) {
        $logger->log('Meta Webhook Error', ['status' => 'FAILED', 'reason' => 'No active Meta channel found']);
        http_response_code(200); // Return 200 to prevent Meta from retrying endlessly
        exit;
    }

    
    // --- Replay Protection ---
    // Calculate a short hash of the payload to detect exact duplicates within the last few minutes
    $payloadHash = md5($payload);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_omni_events WHERE event_type = 'webhook_payload' AND metadata LIKE ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stmt->execute(['%"hash":"'.$payloadHash.'"%']);
    if ($stmt->fetchColumn() > 0) {
        $logger->log('Meta Webhook Duplicate Payload (Replay Protection)', ['hash' => $payloadHash]);
        http_response_code(200); // Ack but ignore
        exit;
    }
    // Update log metadata with hash for future replay checks
    $pdo->prepare("UPDATE tbl_omni_events SET event_type = 'webhook_payload', metadata = JSON_SET(metadata, '$.hash', ?) WHERE id=?")->execute([$payloadHash, $logId]);

    // 2.3 Parse Messages
    $messages = $adapter->parsePayload($payload, $channelId);

    if (empty($messages)) {
        $pdo->prepare("UPDATE tbl_omni_events SET status='SUCCESS', metadata = JSON_SET(metadata, '$.note', 'No actionable messages (e.g. self-echo or unsupported)') WHERE id=?")
            ->execute([$logId]);
        http_response_code(200);
        exit;
    }

    // 2.4 Route Messages
    $router = new MessageRouter($pdo);
    $routed = 0;
    foreach ($messages as $msg) {
        try {
            $router->routeIncoming($msg);
            $routed++;
        } catch (Exception $e) {
            $logger->log('Meta Routing Error', ['status' => 'FAILED', 'error' => $e->getMessage(), 'messageId' => $msg->messageId]);
        }
    }

    $pdo->prepare("UPDATE tbl_omni_events SET status='SUCCESS', metadata = JSON_SET(metadata, '$.routed_count', ?) WHERE id=?")
        ->execute([$routed, $logId]);

    http_response_code(200);
    echo json_encode(['status' => 'ok', 'routed' => $routed]);
    exit;
}

http_response_code(405);
echo 'Method not allowed';
