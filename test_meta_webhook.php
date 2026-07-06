<?php
/**
 * Test Script: Simulates a Facebook Messenger Webhook Payload.
 * Run this from the CLI to ensure MessageRouter handles the insertion properly.
 */
require_once 'admin/inc/config.php';
require_once 'admin/inc/Security/SecretManager.php';

echo "1. Creating mock channel...\n";
$stmt = $pdo->prepare("INSERT INTO tbl_omni_channels (channel_type, provider, account_name, account_id, status) VALUES ('facebook_page', 'meta', 'Test Store Page', '1234567890', 'ACTIVE') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
$stmt->execute();
$channelId = $pdo->lastInsertId();
if (!$channelId) {
    $stmt = $pdo->query("SELECT id FROM tbl_omni_channels WHERE account_id = '1234567890'");
    $channelId = $stmt->fetchColumn();
}
echo "Channel ID: $channelId\n";

$secretManager = new \Security\SecretManager($pdo);
$secretManager->setSecret("meta_{$channelId}_webhook_secret", 'meta', 'test_secret');
$secretManager->setSecret("meta_{$channelId}_access_token", 'meta', 'mock_token_123');

$payload = [
    "object" => "page",
    "entry" => [
        [
            "id" => "1234567890", // Must match account_id in tbl_omni_channels
            "time" => time() * 1000,
            "messaging" => [
                [
                    "sender" => ["id" => "9876543210"],
                    "recipient" => ["id" => "1234567890"],
                    "timestamp" => time() * 1000,
                    "message" => [
                        "mid" => "m_" . uniqid(),
                        "text" => "مرحباً، هل قميص سويت شيرت متوفر باللون الأسود مقاس لارج؟"
                    ]
                ]
            ]
        ]
    ]
];

$jsonPayload = json_encode($payload);
$signature = 'sha256=' . hash_hmac('sha256', $jsonPayload, 'test_secret');

echo "\n2. Simulating Webhook HTTP Request...\n";
$ch = curl_init('http://localhost/ecom/api/omni/webhook.php?provider=meta');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Hub-Signature-256: ' . $signature
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

echo "\n3. Checking Database...\n";
$stmt = $pdo->query("SELECT id, first_name, journey_stage FROM tbl_omni_customers ORDER BY id DESC LIMIT 1");
$cust = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Customer: " . print_r($cust, true) . "\n";

$stmt = $pdo->query("SELECT id, ai_status, current_status FROM tbl_omni_conversations ORDER BY id DESC LIMIT 1");
$conv = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Conversation: " . print_r($conv, true) . "\n";

$stmt = $pdo->query("SELECT type, sender_type, content FROM tbl_omni_timeline ORDER BY id DESC LIMIT 1");
$timeline = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Timeline: " . print_r($timeline, true) . "\n";

echo "Simulation Complete.\n";
