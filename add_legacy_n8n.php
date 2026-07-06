<?php
require 'admin/inc/config.php';
require 'admin/inc/integration/N8nManager.php';

use Integration\N8nManager;

// The default paths for the existing logic
$paths = [
    'ai_agent'         => '/webhook/ai-sales-agent-v2',
    'product_sync'     => '/webhook/product-sync',
    'provider_manager' => '/webhook/provider-manager',
    'customer360'      => '/webhook/customer360',
    'order_events'     => '/webhook/order-events',
    'analytics'        => '/webhook/analytics',
    'notifications'    => '/webhook/notifications',
];

// Encrypt empty API key
$encryptedKey = N8nManager::encryptApiKey('');

// Insert the legacy n8n integration into the new table
$pdo->prepare("INSERT INTO tbl_n8n_integrations (environment, label, base_url, webhook_paths, api_key, is_active) VALUES (?, ?, ?, ?, ?, ?)")
    ->execute([
        'production', 
        'ThikaStore n8n Cloud', 
        'https://thikastore.app.n8n.cloud', 
        json_encode($paths), 
        $encryptedKey, 
        1
    ]);

echo "Legacy integration added successfully.";
