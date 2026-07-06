<?php
/**
 * Telegram Bot Module End-to-End Functional Verification Suite
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Setup Test Database Connection and Settings
require_once __DIR__ . '/../admin/inc/config.php';

// Force Telegram Bot settings to be enabled for testing
$pdo->exec("
    UPDATE tbl_settings 
    SET telegram_is_enabled = 1,
        telegram_webhook_url = 'https://example.com/admin/telegram-webhook.php',
        telegram_secret_token = 'TEST_SECRET_TOKEN',
        telegram_bot_token = '123456:TEST_BOT_TOKEN'
    WHERE id = 1
");

// Enable EventManager dispatches by requiring bootstrap
require_once __DIR__ . '/../admin/telegram/bootstrap.php';
require_once __DIR__ . '/../admin/telegram/Handlers/WebhookHandler.php';
require_once __DIR__ . '/../admin/telegram/Handlers/CallbackHandler.php';

// Clear previous queues, states and logs for testing isolation
$pdo->exec("DELETE FROM tbl_telegram_queue");
$pdo->exec("DELETE FROM tbl_telegram_logs");
$pdo->exec("DELETE FROM tbl_telegram_conversation_state");
$pdo->exec("DELETE FROM tbl_telegram_audit");

// Setup Mock Telegram Service using reflection to replace singleton
class MockTelegramService extends TelegramService
{
    public static array $calledMethods = [];
    public static array $mockResponses = [];

    public function __construct(PDO $pdo)
    {
        // Bypass private parent constructor
    }

    public function apiCall(string $method, array $data = []): array
    {
        self::$calledMethods[] = [
            'method' => $method,
            'data' => $data
        ];
        
        if (isset(self::$mockResponses[$method])) {
            return self::$mockResponses[$method];
        }
        
        return ['ok' => true, 'result' => ['message_id' => 9999]];
    }
}

$ref = new ReflectionClass('TelegramService');
$instanceProp = $ref->getProperty('instance');
$instanceProp->setAccessible(true);
$mockService = new MockTelegramService($pdo);
$instanceProp->setValue(null, $mockService);

echo "=============================================\n";
echo "TELEGRAM END-TO-END FUNCTIONAL VERIFICATION\n";
echo "=============================================\n\n";

$passCount = 0;
$failCount = 0;

function reportResult(string $testName, bool $success, array $details = [])
{
    global $passCount, $failCount;
    if ($success) {
        $passCount++;
        echo "✅ PASS: {$testName}\n";
    } else {
        $failCount++;
        echo "❌ FAIL: {$testName}\n";
    }
    foreach ($details as $k => $v) {
        echo "   - {$k}: " . (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v) . "\n";
    }
    echo "\n";
}

/**
 * Helper to simulate a POST webhook request to telegram-webhook.php in memory.
 */
function simulateWebhookRequest(PDO $pdo, string $payloadJson, array $headers)
{
    $GLOBALS['mock_post_data'] = $payloadJson;
    $GLOBALS['mock_headers'] = $headers;

    $code = file_get_contents(__DIR__ . '/../admin/telegram-webhook.php');
    
    // Remove the <?php tag
    $code = preg_replace('/^<\?php/', '', $code);
    
    // Replace the raw input reads
    $code = str_replace("file_get_contents('php://input')", "\$GLOBALS['mock_post_data']", $code);
    $code = str_replace("getallheaders()", "\$GLOBALS['mock_headers']", $code);
    
    // Catch exits and replace them with returns
    $code = str_replace("exit;", "return;", $code);
    $code = str_replace("exit (", "return (", $code);
    
    // Replace __DIR__ to resolve paths relative to admin directory
    $code = str_replace("__DIR__", "'" . addslashes(realpath(__DIR__ . '/../admin')) . "'", $code);
    
    ob_start();
    try {
        eval($code);
    } catch (Exception $e) {
        echo "Error in simulated webhook: " . $e->getMessage();
    }
    return ob_get_clean();
}

/**
 * Helper to simulate an AJAX request in memory.
 */
function simulateAjaxRequest(string $filePath)
{
    global $pdo;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $code = file_get_contents($filePath);
    $code = preg_replace('/^<\?php/', '', $code);
    $code = str_replace("exit;", "return;", $code);
    $code = str_replace("exit (", "return (", $code);

    // Resolve relative imports based on the directory of the file itself
    $dir = dirname($filePath);
    $code = str_replace("__DIR__", "'" . addslashes($dir) . "'", $code);

    ob_start();
    try {
        eval($code);
    } catch (Exception $e) {
        echo "Error in AJAX: " . $e->getMessage();
    }
    return ob_get_clean();
}

// ----------------------------------------------------
// Test 1: Webhook Security (Invalid secret token)
// ----------------------------------------------------
$output = simulateWebhookRequest($pdo, json_encode(['some' => 'payload']), [
    'X-Telegram-Bot-Api-Secret-Token' => 'INVALID_SECRET'
]);
$response = json_decode($output, true);

reportResult(
    "Webhook Security validation with invalid token",
    ($response === ['ok' => false, 'error' => 'Forbidden']),
    ['Response' => $output]
);

// ----------------------------------------------------
// Setup Employee & Manager Records for Testing
// ----------------------------------------------------
$pdo->exec("DELETE FROM tbl_employee WHERE email = 'test_employee@example.com'");
$pdo->exec("
    INSERT INTO tbl_employee (full_name, email, password_hash, is_active, telegram_is_linked) 
    VALUES ('Test Employee', 'test_employee@example.com', 'hash', 1, 0)
");
$empId = (int) $pdo->lastInsertId();

$pdo->exec("DELETE FROM tbl_user WHERE email = 'test_manager@example.com'");
$pdo->exec("
    INSERT INTO tbl_user (full_name, email, password, phone, role, status, telegram_is_linked) 
    VALUES ('Test Manager', 'test_manager@example.com', 'hash', '12345', 'Super Admin', 1, 0)
");
$mgrId = (int) $pdo->lastInsertId();


// ----------------------------------------------------
// Test 2: Deep Link Generation via AJAX (Employee)
// ----------------------------------------------------
$_SESSION['staff_employee_id'] = $empId;
$_GET['action'] = 'generate';
$_GET['user_type'] = 'employee';
unset($_GET['action'], $_GET['user_type']);
$_POST['action'] = 'generate';
$_POST['user_type'] = 'employee';

$output = simulateAjaxRequest(__DIR__ . '/../admin/telegram-link-action.php');
$res = json_decode($output, true);

$linkToken = $res['token'] ?? '';
$success = !empty($res['success']) && strlen($linkToken) === 64;

reportResult(
    "Generate Employee Link Token via Deep Link flow",
    $success,
    ['Response' => $output, 'Token' => $linkToken]
);


// ----------------------------------------------------
// Test 3: Link Account via Webhook Command (Employee)
// ----------------------------------------------------
$webhookPayload = [
    'update_id' => 10001,
    'message' => [
        'message_id' => 5001,
        'from' => [
            'id' => 987654321,
            'first_name' => 'JohnEmp',
            'username' => 'john_emp'
        ],
        'chat' => [
            'id' => 987654321,
            'type' => 'private'
        ],
        'text' => "/start {$linkToken}"
    ]
];

MockTelegramService::$calledMethods = [];
$output = simulateWebhookRequest($pdo, json_encode($webhookPayload), [
    'X-Telegram-Bot-Api-Secret-Token' => 'TEST_SECRET_TOKEN'
]);

// Check db changes
$stmt = $pdo->prepare("SELECT telegram_chat_id, telegram_is_linked, telegram_username FROM tbl_employee WHERE id = ?");
$stmt->execute([$empId]);
$empDb = $stmt->fetch(PDO::FETCH_ASSOC);

$linkedCorrectly = ($empDb['telegram_is_linked'] == 1 && $empDb['telegram_chat_id'] == 987654321 && $empDb['telegram_username'] === 'john_emp');

reportResult(
    "Verify Account Binding /start webhook command",
    $linkedCorrectly,
    ['Database Fields' => $empDb, 'Api Calls Sent' => MockTelegramService::$calledMethods]
);


// ----------------------------------------------------
// Test 4: Link Account via Webhook Command (Manager)
// ----------------------------------------------------
$_SESSION['user'] = ['id' => $mgrId, 'role' => 'Super Admin'];
$_POST['action'] = 'generate';
$_POST['user_type'] = 'manager';

$output = simulateAjaxRequest(__DIR__ . '/../admin/telegram-link-action.php');
$resMgr = json_decode($output, true);
$mgrToken = $resMgr['token'] ?? '';

$webhookPayloadMgr = [
    'update_id' => 10002,
    'message' => [
        'message_id' => 5002,
        'from' => [
            'id' => 1122334455,
            'first_name' => 'BossMan',
            'username' => 'boss_man'
        ],
        'chat' => [
            'id' => 1122334455,
            'type' => 'private'
        ],
        'text' => "/start {$mgrToken}"
    ]
];

simulateWebhookRequest($pdo, json_encode($webhookPayloadMgr), [
    'X-Telegram-Bot-Api-Secret-Token' => 'TEST_SECRET_TOKEN'
]);

$stmt = $pdo->prepare("SELECT telegram_chat_id, telegram_is_linked, telegram_username FROM tbl_user WHERE id = ?");
$stmt->execute([$mgrId]);
$mgrDb = $stmt->fetch(PDO::FETCH_ASSOC);

reportResult(
    "Verify Manager Binding /start webhook command",
    ($mgrDb['telegram_is_linked'] == 1 && $mgrDb['telegram_chat_id'] == 1122334455),
    ['Database Fields' => $mgrDb]
);


// ----------------------------------------------------
// Test 5: Assign Task & Verify Queue Delivery
// ----------------------------------------------------
// Create a fake order
$pdo->exec("
    INSERT INTO tbl_order (product_name, quantity, total_price, customer_name, customer_phone, wilaya, commune, order_status, order_date) 
    VALUES ('E2E Test Product', 2, 5000.00, 'Test Customer', '0555555555', 'Alger', 'Sidi Mhamed', 'Pending', NOW())
");
$orderId = (int) $pdo->lastInsertId();

// Assign order using functions.php
require_once __DIR__ . '/../admin/inc/employee_functions.php';
$assignmentId = employee_assign_order($pdo, $orderId, $empId, 'test_script');

// Verify that a message was pushed to the queue
$stmt = $pdo->query("SELECT * FROM tbl_telegram_queue ORDER BY id DESC LIMIT 1");
$queueItem = $stmt->fetch(PDO::FETCH_ASSOC);

$queuedCorrectly = ($queueItem && $queueItem['chat_id'] == 987654321 && $queueItem['status'] === 'pending');

reportResult(
    "Task assignment queues message with inline keyboard action buttons",
    $queuedCorrectly,
    ['Queue Record' => $queueItem]
);


// ----------------------------------------------------
// Test 6: Queue Processing execution
// ----------------------------------------------------
MockTelegramService::$calledMethods = [];
$processed = QueueService::processQueue($pdo);

// Verify queue state changed
$stmt = $pdo->prepare("SELECT status, attempts FROM tbl_telegram_queue WHERE id = ?");
$stmt->execute([$queueItem['id']]);
$queueAfter = $stmt->fetch(PDO::FETCH_ASSOC);

reportResult(
    "Forced Queue Runner execution and state transition",
    ($processed === 1 && $queueAfter['status'] === 'completed'),
    ['Queue After' => $queueAfter, 'Api Calls Made' => MockTelegramService::$calledMethods]
);


// ----------------------------------------------------
// Test 7: Inline Button Action Interactivity (Accept Task)
// ----------------------------------------------------
$callbackPayload = [
    'update_id' => 10003,
    'callback_query' => [
        'id' => 'cb_123',
        'from' => [
            'id' => 987654321,
            'first_name' => 'JohnEmp'
        ],
        'message' => [
            'message_id' => 6001,
            'chat' => [
                'id' => 987654321
            ],
            'text' => 'New Task Details'
        ],
        'data' => "emp_accept:{$orderId}"
    ]
];

MockTelegramService::$calledMethods = [];
simulateWebhookRequest($pdo, json_encode($callbackPayload), [
    'X-Telegram-Bot-Api-Secret-Token' => 'TEST_SECRET_TOKEN'
]);

// Verify order assignment status is accepted
$stmt = $pdo->prepare("SELECT status FROM tbl_order_assignment WHERE order_id = ?");
$stmt->execute([$orderId]);
$assignStatus = $stmt->fetchColumn();

// Verify that editMessageText API call was made to update Telegram UI
$calledEditMessage = false;
foreach (MockTelegramService::$calledMethods as $call) {
    if ($call['method'] === 'editMessageText') {
        $calledEditMessage = true;
        break;
    }
}

reportResult(
    "Verify Callback Query Interactive buttons (emp_accept)",
    ($assignStatus === 'active' && $calledEditMessage),
    ['Assignment Status' => $assignStatus, 'Api Calls Made' => MockTelegramService::$calledMethods]
);


// ----------------------------------------------------
// Test 8: Submit Complaint and broadcast to Managers
// ----------------------------------------------------
// Clear queue
$pdo->exec("DELETE FROM tbl_telegram_queue");

$_SESSION['staff_employee_id'] = $empId;
$_POST['submit_complaint'] = '1';
$_POST['subject'] = 'Test E2E Complaint Subject';
$_POST['message'] = 'Test E2E Complaint Message Content';

$output = simulateAjaxRequest(__DIR__ . '/../staff/complaints.php');

// Check database complaint row
$stmt = $pdo->prepare("SELECT * FROM tbl_complaints WHERE subject = 'Test E2E Complaint Subject' LIMIT 1");
$stmt->execute();
$complaintRow = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if a message was queued for manager chat
$stmt = $pdo->prepare("SELECT * FROM tbl_telegram_queue WHERE chat_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([1122334455]);
$mgrQueue = $stmt->fetch(PDO::FETCH_ASSOC);

reportResult(
    "Submit Complaint registers and queues broadcasts for linked managers",
    ($complaintRow && $mgrQueue && strpos($mgrQueue['payload'], 'Test E2E Complaint Subject') !== false),
    ['Complaint Row' => $complaintRow, 'Queued Manager Message' => $mgrQueue]
);


// ----------------------------------------------------
// Test 9: Retry Queue Worker and DLQ Behavior
// ----------------------------------------------------
// Set queue item status to pending
$pdo->exec("UPDATE tbl_telegram_queue SET status = 'pending', attempts = 0");

// Mock API failure response
MockTelegramService::$mockResponses = [
    'sendMessage' => ['ok' => false, 'description' => 'Simulated API failure']
];

// Run queue worker (fails 1st attempt)
QueueService::processQueue($pdo);

$stmt = $pdo->query("SELECT status, attempts FROM tbl_telegram_queue ORDER BY id DESC LIMIT 1");
$queueRetry = $stmt->fetch(PDO::FETCH_ASSOC);

$isRetrying = ($queueRetry['status'] === 'failed' && $queueRetry['attempts'] == 1);

// Force 2 more attempts to push to Dead Letter Queue (DLQ)
$pdo->exec("UPDATE tbl_telegram_queue SET attempts = 3, next_attempt_at = NOW()");
QueueService::processQueue($pdo);

$stmt = $pdo->query("SELECT status, attempts FROM tbl_telegram_queue ORDER BY id DESC LIMIT 1");
$queueDlq = $stmt->fetch(PDO::FETCH_ASSOC);

reportResult(
    "Verify Queue Exponential Backoff Retry & Dead-Letter Queue (DLQ) transition",
    ($isRetrying && $queueDlq['status'] === 'dead_letter'),
    ['After 1 Failure' => $queueRetry, 'After Max Failures (DLQ)' => $queueDlq]
);


// ----------------------------------------------------
// Test 10: Unlinking and Dashboard Stats updating
// ----------------------------------------------------
// Clear settings
unset($_POST['submit_complaint'], $_POST['subject'], $_POST['message']);
$_POST['action'] = 'unlink';
$_POST['user_type'] = 'employee';
$_SESSION['staff_employee_id'] = $empId;

$output = simulateAjaxRequest(__DIR__ . '/../admin/telegram-link-action.php');

// Check db changes
$stmt = $pdo->prepare("SELECT telegram_is_linked, telegram_chat_id FROM tbl_employee WHERE id = ?");
$stmt->execute([$empId]);
$empUnlinked = $stmt->fetch(PDO::FETCH_ASSOC);

$unlinkedOk = ($empUnlinked['telegram_is_linked'] == 0 && ($empUnlinked['telegram_chat_id'] === null || $empUnlinked['telegram_chat_id'] === ''));

// Clear mock responses
MockTelegramService::$mockResponses = [];

reportResult(
    "Verify portal unlink controls clear Chat IDs and reset state",
    $unlinkedOk,
    ['Database Fields after Unlink' => $empUnlinked]
);


// ----------------------------------------------------
// Cleanup testing data
// ----------------------------------------------------
$pdo->prepare("DELETE FROM tbl_employee WHERE id = ?")->execute([$empId]);
$pdo->prepare("DELETE FROM tbl_user WHERE id = ?")->execute([$mgrId]);
$pdo->prepare("DELETE FROM tbl_order WHERE id = ?")->execute([$orderId]);

echo "=============================================\n";
echo "VERIFICATION STATS: {$passCount} PASSED, {$failCount} FAILED.\n";
echo "=============================================\n";

if ($failCount > 0) {
    exit(1);
} else {
    exit(0);
}
