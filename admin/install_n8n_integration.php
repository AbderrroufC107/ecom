<?php
// Bootstrap N8n tables and return manager instance
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/integration/N8nManager.php';

use Integration\N8nManager;

N8nManager::ensureTables($pdo);
echo json_encode(['status' => 'ok', 'message' => 'Tables created successfully.']);
