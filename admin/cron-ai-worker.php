<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/AI/AiTaskEngine.php';

// Prevent web access if needed, or secure via cron token
if (php_sapi_name() !== 'cli' && !isset($_GET['run_worker'])) {
    http_response_code(403);
    exit('Forbidden');
}

// Ensure no timeouts for long processes
set_time_limit(0);
ini_set('memory_limit', '512M');

$engine = new \AI\AiTaskEngine($pdo);

// Process tasks until queue is empty
$processedCount = 0;
while (true) {
    // processNextTask locks a row, processes it, and updates its status
    $didWork = $engine->processNextTask();
    
    if (!$didWork) {
        break; // No more tasks
    }
    $processedCount++;
    
    // Optional: limit jobs per cron execution to avoid hanging forever
    if ($processedCount >= 50) {
        break;
    }
}

echo "AI Worker finished. Processed $processedCount tasks.\n";
