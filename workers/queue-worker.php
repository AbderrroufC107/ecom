<?php
/**
 * PHASE 12 — Queue Worker
 *
 * Usage:
 *   php workers/queue-worker.php                    # Run all job types
 *   php workers/queue-worker.php telegram_send      # Run only telegram jobs
 *   php workers/queue-worker.php --once             # Process one job and exit
 *   php workers/queue-worker.php --daemon           # Run as daemon (default)
 *   php workers/queue-worker.php --help             # Show help
 *
 * Run as a service/supervisor:
 *   supervisor -c /etc/supervisor/conf.d/queue-worker.conf
 *   systemd service unit recommended for production
 */

declare(strict_types=1);

// --- Bootstrap ---
$scriptDir = dirname(__DIR__);
$incDir = $scriptDir . '/admin/inc';

// Load database config
$dbConfigPath = $incDir . '/db_config.php';
if (file_exists($dbConfigPath)) {
    require_once $dbConfigPath;
} else {
    $dbConfigPath2 = $scriptDir . '/admin/db_config.php';
    if (file_exists($dbConfigPath2)) {
        require_once $dbConfigPath2;
    } else {
        fwrite(STDERR, "[FATAL] db_config.php not found\n");
        exit(1);
    }
}

// Ensure PDO connection is available
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbName = defined('DB_NAME') ? DB_NAME : 'ecom';
    $dbUser = defined('DB_USER') ? DB_USER : 'root';
    $dbPass = defined('DB_PASS') ? DB_PASS : '';
    try {
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Exception $e) {
        fwrite(STDERR, "[FATAL] DB connection failed: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Load store functions
$storePath = $incDir . '/store.php';
if (file_exists($storePath)) {
    require_once $storePath;
} else {
    fwrite(STDERR, "[FATAL] store.php not found at {$storePath}\n");
    exit(1);
}

// Load telegram functions if available
$telegramPath = $incDir . '/telegram_bot.php';
if (file_exists($telegramPath)) {
    require_once $telegramPath;
}

// Load AI functions if available
$aiPath = $incDir . '/ai_functions.php';
if (file_exists($aiPath)) {
    require_once $aiPath;
}

// Load recovery engine if available
$recoveryPath = $incDir . '/recovery_engine.php';
if (file_exists($recoveryPath)) {
    require_once $recoveryPath;
}

// Load performance functions if available
$perfPath = $incDir . '/performance_functions.php';
if (file_exists($perfPath)) {
    require_once $perfPath;
}

// Load ecotrack functions if available
$funcPath = $incDir . '/functions.php';
if (file_exists($funcPath)) {
    require_once $funcPath;
}

// --- Configuration ---
$CONFIG = [
    'poll_interval' => 2,         // seconds between polls when queue is empty
    'max_jobs_per_run' => 50,     // max jobs to process before re-checking config
    'stuck_timeout_minutes' => 30,// timeout for stuck jobs on startup
    'worker_id' => gethostname() . '-' . getmypid(),
    'shutdown_file' => sys_get_temp_dir() . '/queue_worker_shutdown',
    'health_file' => sys_get_temp_dir() . '/queue_worker_heartbeat_' . getmypid(),
];

// --- CLI Argument Parsing ---
$jobTypeFilter = null;
$onceMode = false;
$daemonMode = true;
$showHelp = false;

for ($i = 1; $i < $argc; $i++) {
    switch ($argv[$i]) {
        case '--once':
            $onceMode = true;
            $daemonMode = false;
            break;
        case '--daemon':
            $daemonMode = true;
            $onceMode = false;
            break;
        case '--help':
        case '-h':
            $showHelp = true;
            break;
        default:
            if (strpos($argv[$i], '--') !== 0) {
                $jobTypeFilter = $argv[$i];
            }
    }
}

if ($showHelp) {
    echo "Queue Worker - Ecom Background Job Processor\n";
    echo "Usage:\n";
    echo "  php workers/queue-worker.php                         # Daemon mode (all types)\n";
    echo "  php workers/queue-worker.php telegram_send            # Specific job type\n";
    echo "  php workers/queue-worker.php --once                   # Single job, then exit\n";
    echo "  php workers/queue-worker.php --daemon                 # Daemon mode (default)\n";
    echo "  php workers/queue-worker.php --help                   # This help\n\n";
    echo "Job types: telegram_send, webhook_delivery, ecotrack_sync, ai_report,\n";
    echo "           recovery_scan, risk_recalculation, email_send, invoice_generation\n";
    echo "\nCreate shutdown signal: touch " . $CONFIG['shutdown_file'] . "\n";
    exit(0);
}

// --- Signal Handling ---
$shutdown = false;

function worker_signal_handler(int $signo): void
{
    global $shutdown;
    $shutdown = true;
    fwrite(STDERR, "[WORKER] Signal {$signo} received, shutting down gracefully...\n");
}

if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'worker_signal_handler');
    pcntl_signal(SIGINT, 'worker_signal_handler');
    pcntl_signal(SIGQUIT, 'worker_signal_handler');
}

// --- Cleanup stale jobs on startup ---
$stuckCount = store_cleanup_stuck_jobs($pdo, $CONFIG['stuck_timeout_minutes']);
if ($stuckCount > 0) {
    fwrite(STDERR, "[WORKER] Cleaned {$stuckCount} stuck jobs on startup\n");
}

// --- Health heartbeat ---
function worker_write_heartbeat(): void
{
    global $CONFIG;
    $data = [
        'pid' => getmypid(),
        'host' => gethostname(),
        'time' => date('Y-m-d H:i:s'),
        'memory' => memory_get_usage(true),
    ];
    file_put_contents($CONFIG['health_file'], json_encode($data), LOCK_EX);
}

// --- Main Loop ---
$jobsProcessed = 0;
$startTime = time();
$heartbeatInterval = 30; // seconds between heartbeat writes
$lastHeartbeat = 0;

fwrite(STDERR, "[WORKER] Starting queue worker [{$CONFIG['worker_id']}]\n");
if ($jobTypeFilter) {
    fwrite(STDERR, "[WORKER] Filtering job type: {$jobTypeFilter}\n");
}
if ($onceMode) {
    fwrite(STDERR, "[WORKER] Running in single-job mode\n");
}

while (!$shutdown) {
    // Check for shutdown file
    if (file_exists($CONFIG['shutdown_file'])) {
        fwrite(STDERR, "[WORKER] Shutdown file detected, exiting\n");
        @unlink($CONFIG['shutdown_file']);
        break;
    }

    // Heartbeat
    if (time() - $lastHeartbeat > $heartbeatInterval) {
        worker_write_heartbeat();
        $lastHeartbeat = time();
    }

    // Process signals
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    // Dequeue next job
    $types = $jobTypeFilter ? [$jobTypeFilter] : null;
    $job = store_dequeue_job($pdo, $types);

    if (!$job) {
        if ($onceMode) {
            fwrite(STDERR, "[WORKER] No jobs available, exiting\n");
            break;
        }
        sleep($CONFIG['poll_interval']);
        continue;
    }

    $jobId = (int) $job['id'];
    $type = $job['job_type'];
    $storeId = (int) ($job['store_id'] ?? 0);
    $attempt = (int) ($job['attempts'] ?? 1);

    fwrite(STDERR, "[WORKER] Processing job #{$jobId} [{$type}] (attempt {$attempt}) for store {$storeId}\n");

    try {
        $result = store_process_job($pdo, $job);

        if ($result['success'] ?? false) {
            fwrite(STDERR, "[WORKER] Job #{$jobId} completed in {$result['elapsed_ms']}ms\n");
        } else {
            $error = $result['error'] ?? 'unknown error';
            if (!empty($result['retry_at'])) {
                fwrite(STDERR, "[WORKER] Job #{$jobId} failed, retry at {$result['retry_at']}: {$error}\n");
            } elseif (!empty($result['max_attempts_reached'])) {
                fwrite(STDERR, "[WORKER] Job #{$jobId} failed, max attempts reached: {$error}\n");
            } else {
                fwrite(STDERR, "[WORKER] Job #{$jobId} failed: {$error}\n");
            }
        }
    } catch (Exception $e) {
        fwrite(STDERR, "[WORKER] Job #{$jobId} exception: " . $e->getMessage() . "\n");
        // Move to failed jobs
        $maxAttempts = (int) ($job['max_attempts'] ?? 3);
        if ($attempt >= $maxAttempts) {
            store_update_job_status($pdo, $jobId, 'failed', $e->getMessage());
            store_move_to_failed_jobs($pdo, $job, $e->getMessage(), $type);
        } else {
            $backoffMinutes = store_get_backoff_minutes($attempt);
            $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$backoffMinutes} minutes"));
            $stmt = $pdo->prepare("UPDATE tbl_job_queue SET status = 'pending', scheduled_at = ?, error_message = ? WHERE id = ?");
            $stmt->execute([$scheduledAt, $e->getMessage(), $jobId]);
        }
    }

    $jobsProcessed++;

    if ($onceMode) {
        fwrite(STDERR, "[WORKER] Single-job mode, exiting after job #{$jobId}\n");
        break;
    }

    // Limit per run to prevent memory leaks
    if ($jobsProcessed >= $CONFIG['max_jobs_per_run']) {
        fwrite(STDERR, "[WORKER] Reached max jobs per run ({$CONFIG['max_jobs_per_run']}), restarting cycle\n");
        $jobsProcessed = 0;

        // Reconnect to DB to prevent timeout
        // (for very long-running workers, would need reconnect logic)
    }
}

// Final heartbeat
worker_write_heartbeat();
fwrite(STDERR, "[WORKER] Shutdown complete. Processed {$jobsProcessed} jobs in " . (time() - $startTime) . "s\n");
exit(0);
