<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

// 1. Add Rate Limiting & API Logging to next-common.php
$common_file = 'C:/xampp/htdocs/ecom/api/next-common.php';
$common_content = file_get_contents($common_file);

if (strpos($common_content, 'next_api_rate_limit') === false) {
    $security_block = <<<'EOD'

if (!function_exists('next_api_rate_limit')) {
    function next_api_rate_limit(PDO $pdo, string $endpoint, int $maxRequests = 60, int $timeWindowSeconds = 60): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Cleanup old
        $pdo->exec("DELETE FROM tbl_api_log WHERE created_at < DATE_SUB(NOW(), INTERVAL $timeWindowSeconds SECOND)");
        // Count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_api_log WHERE ip_address = ? AND endpoint = ? AND created_at >= DATE_SUB(NOW(), INTERVAL $timeWindowSeconds SECOND)");
        $stmt->execute([$ip, $endpoint]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= $maxRequests) {
            next_json(['success' => false, 'error' => 'Too Many Requests', 'code' => 'RATE_LIMIT_EXCEEDED'], 429);
        }
        
        // Log request
        $logStmt = $pdo->prepare("INSERT INTO tbl_api_log (ip_address, endpoint, method, user_agent, created_at, tenant_id) VALUES (?, ?, ?, ?, NOW(), 1)");
        $logStmt->execute([
            $ip, 
            $endpoint, 
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    }
}
EOD;

    // Inject after next_api_headers
    $common_content = preg_replace('/(function next_api_headers\(\): void\s*\{.*?\n\s*\})/s', "$1\n$security_block", $common_content);
    file_put_contents($common_file, $common_content);
    echo "Added Rate Limiting and Logging to next-common.php\n";
}

// 2. Add Replay Protection to Meta Webhook
$webhook_file = 'C:/xampp/htdocs/ecom/api/meta_webhook.php';
$webhook_content = file_get_contents($webhook_file);

if (strpos($webhook_content, 'Replay Protection') === false) {
    $replay_block = <<<'EOD'

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
EOD;

    // Inject before parsing messages
    $webhook_content = str_replace('// 2.3 Parse Messages', $replay_block . "\n\n    // 2.3 Parse Messages", $webhook_content);
    file_put_contents($webhook_file, $webhook_content);
    echo "Added Replay Protection to meta_webhook.php\n";
}

// 3. Inject Rate Limiting call in all API endpoints
$api_files = glob('C:/xampp/htdocs/ecom/api/*.php');
foreach ($api_files as $file) {
    if (basename($file) === 'next-common.php' || basename($file) === 'meta_webhook.php') continue;
    $content = file_get_contents($file);
    if (strpos($content, 'next_api_rate_limit(') === false && strpos($content, 'next_api_headers()') !== false) {
        $content = str_replace('next_api_headers();', "next_api_headers();\n\nif (isset(\$pdo)) { next_api_rate_limit(\$pdo, basename(__FILE__)); }", $content);
        file_put_contents($file, $content);
        echo "Enabled Rate Limiting in " . basename($file) . "\n";
    }
}

echo "API & Meta Audits Fixed!\n";

