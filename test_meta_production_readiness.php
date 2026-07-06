<?php
/**
 * Test Suite for Meta Production Readiness
 * Simulates payloads hitting the webhook logic directly (or via HTTP if needed).
 */
require_once __DIR__ . '/admin/inc/config.php';

// Check if we have an active Meta channel
$stmt = $pdo->query("SELECT id, account_id FROM tbl_omni_channels WHERE provider = 'meta' AND status = 'ACTIVE' LIMIT 1");
$channel = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$channel) {
    die("No active Meta channel found for testing.\n");
}

$pageId = $channel['account_id'];
$channelId = $channel['id'];

$tests = [
    'Text Message' => [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => time(),
            'messaging' => [[
                'sender' => ['id' => 'USER_123'],
                'recipient' => ['id' => $pageId],
                'timestamp' => time() * 1000,
                'message' => [
                    'mid' => 'mid.' . time(),
                    'text' => 'Hello, I want to buy a shirt.'
                ]
            ]]
        ]]
    ],
    'Image Attachment' => [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => time(),
            'messaging' => [[
                'sender' => ['id' => 'USER_123'],
                'recipient' => ['id' => $pageId],
                'timestamp' => time() * 1000,
                'message' => [
                    'mid' => 'mid.' . time(),
                    'attachments' => [[
                        'type' => 'image',
                        'payload' => ['url' => 'https://example.com/image.jpg']
                    ]]
                ]
            ]]
        ]]
    ],
    'Location Attachment' => [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => time(),
            'messaging' => [[
                'sender' => ['id' => 'USER_123'],
                'recipient' => ['id' => $pageId],
                'timestamp' => time() * 1000,
                'message' => [
                    'mid' => 'mid.' . time(),
                    'attachments' => [[
                        'type' => 'location',
                        'payload' => ['coordinates' => ['lat' => 36.75, 'long' => 3.05]]
                    ]]
                ]
            ]]
        ]]
    ],
    'Comment - Price Request (Private Reply)' => [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => time(),
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => 'comment_' . time(),
                    'post_id' => 'post_' . time(),
                    'from' => ['id' => 'USER_456', 'name' => 'John Doe'],
                    'message' => 'بكم السعر؟',
                    'created_time' => time()
                ]
            ]]
        ]]
    ],
    'Comment - Availability (Public Reply)' => [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId,
            'time' => time(),
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => 'comment_' . (time()+1),
                    'post_id' => 'post_' . time(),
                    'from' => ['id' => 'USER_789', 'name' => 'Jane Doe'],
                    'message' => 'هل اللون الأحمر متوفر؟',
                    'created_time' => time()
                ]
            ]]
        ]]
    ],
    'Comment - Self Ignored' => [
        'object' => 'page',
        'entry' => [[
            'id' => $pageId, // Same as page ID
            'time' => time(),
            'changes' => [[
                'field' => 'feed',
                'value' => [
                    'item' => 'comment',
                    'verb' => 'add',
                    'comment_id' => 'comment_self',
                    'post_id' => 'post_' . time(),
                    'from' => ['id' => $pageId, 'name' => 'My Page'],
                    'message' => 'Thank you all!',
                    'created_time' => time()
                ]
            ]]
        ]]
    ],
];

echo "--- Running Meta Production Readiness Tests ---\n\n";

// We will simulate posting to the webhook locally
$webhookUrl = 'http://localhost/ecom/api/omni/webhook.php?provider=meta';

// Fetch the webhook secret to sign the requests
require_once __DIR__ . '/admin/inc/Security/SecretManager.php';
$secretManager = new \Security\SecretManager($pdo);
$webhookSecret = $secretManager->getSecret("meta_{$channelId}_webhook_secret");

$results = [];

foreach ($tests as $name => $payloadData) {
    $payloadJson = json_encode($payloadData);
    $signature = 'sha256=' . hash_hmac('sha256', $payloadJson, $webhookSecret ?? 'test_secret');
    
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Hub-Signature-256: ' . $signature
    ]);
    
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $passed = ($code === 200 && $response === 'EVENT_RECEIVED');
    $results[$name] = $passed ? "PASS" : "FAIL (Code: $code)";
    
    echo "Test [$name]: " . $results[$name] . "\n";
}

echo "\n--- Test Summary ---\n";
$allPassed = true;
foreach ($results as $r) {
    if (strpos($r, 'FAIL') !== false) $allPassed = false;
}

if ($allPassed) {
    echo "✅ ALL META TESTS PASSED. SYSTEM IS READY FOR AI PROCESSING.\n";
} else {
    echo "❌ SOME TESTS FAILED. CHECK LOGS.\n";
}
