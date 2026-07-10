<?php
/**
 * Telegram Bot Webhook Endpoint
 *
 * Endpoint: https://your-site.com/admin/telegram-webhook.php
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
if ($rawInput === false || $rawInput === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Empty request']);
    exit;
}

$update = json_decode($rawInput, true);
if (!is_array($update)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/employee_functions.php';
require_once __DIR__ . '/inc/telegram_bot.php';
require_once __DIR__ . '/inc/telegram_actions.php';

// Load new package handlers
require_once __DIR__ . '/telegram/Services/TelegramService.php';
require_once __DIR__ . '/telegram/Handlers/WebhookHandler.php';
require_once __DIR__ . '/telegram/Handlers/CallbackHandler.php';
require_once __DIR__ . '/telegram/Services/SecondaryBotLinkService.php';
require_once __DIR__ . '/telegram/Handlers/SecondaryBotWebhookHandler.php';

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(500);
    exit;
}

// 1. Secure Webhook using X-Telegram-Bot-Api-Secret-Token. Secondary bots are
// registered with Telegram's setWebhook using this same secret, so this check
// applies to every bot's requests, not just the main one.
$headers = getallheaders();
$secretToken = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? $headers['x-telegram-bot-api-secret-token'] ?? '';

try {
    $stmt = $dbRepo->query("SELECT telegram_secret_token, telegram_is_enabled FROM tbl_settings WHERE id = 1 LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($settings) {
        $dbSecret = trim((string) ($settings['telegram_secret_token'] ?? ''));
        $isEnabled = (int) ($settings['telegram_is_enabled'] ?? 0) === 1;

        if ($dbSecret !== '' && $secretToken !== $dbSecret) {
            http_response_code(403);
            error_log("Unauthorized Telegram Webhook attempt. Secret token mismatch.");
            echo json_encode(['ok' => false, 'error' => 'Forbidden']);
            exit;
        }

        if (!$isEnabled) {
            // Disabled in settings but returning 200 to acknowledge webhook
            echo json_encode(['ok' => true, 'description' => 'Telegram Bot is disabled.']);
            exit;
        }
    }
} catch (Exception $e) {
    error_log('Telegram webhook security check failed: ' . $e->getMessage());
}

// Secondary bots (order-status, incomplete-orders) each get their own
// webhook URL when registered with Telegram: .../telegram-webhook.php?purpose=order_status
// They only need /start <token> linking, not the main bot's full command set.
$__botPurpose = trim((string) ($_GET['purpose'] ?? ''));
if ($__botPurpose !== '' && SecondaryBotLinkService::isValidPurpose($__botPurpose)) {
    SecondaryBotLinkService::ensureTable($pdo);
    if (isset($update['message'])) {
        $handler = new SecondaryBotWebhookHandler($pdo, $__botPurpose);
        $handler->handle($update['message']);
    }
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// Ensure database tables exist
telegram_actions_ensure_tables($pdo);

try {
    // 2. Determine Router type
    if (isset($update['callback_query'])) {
        $data = (string) ($update['callback_query']['data'] ?? '');
        // Route new callbacks starting with "emp_" or "mgr_"
        if (strpos($data, 'emp_') === 0 || strpos($data, 'mgr_') === 0) {
            $handler = new CallbackHandler($pdo);
            $handler->handle($update['callback_query']);
        } else {
            // Fallback to legacy callback processing
            telegram_process_update($pdo, $update);
        }
    } elseif (isset($update['message'])) {
        $text = trim((string) ($update['message']['text'] ?? ''));
        // Route new commands or state replies
        $isLegacyCommand = (strpos($text, '/start') === 0 && (strpos($text, 'STAFF_') !== false || strpos($text, 'ADMIN_') !== false));
        
        if (strpos($text, '/') === 0 && !$isLegacyCommand) {
            $handler = new WebhookHandler($pdo);
            $handler->handle($update['message']);
        } elseif ($text !== '' && strpos($text, '/') !== 0) {
            // Check if there is an active new state session
            $chatId = (string) ($update['message']['chat']['id'] ?? '');
            $stateStmt = $dbRepo->prepare("SELECT id FROM tbl_telegram_conversation_state WHERE chat_id = ? AND expires_at >= NOW() LIMIT 1");
            $stateStmt->execute([$chatId]);
            
            if ($stateStmt->fetch()) {
                $handler = new WebhookHandler($pdo);
                $handler->handle($update['message']);
            } else {
                // Fallback to legacy messages/sessions
                telegram_process_update($pdo, $update);
            }
        } else {
            // Fallback to legacy commands
            telegram_process_update($pdo, $update);
        }
    }
} catch (Exception $e) {
    error_log('Telegram Webhook Route Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
}

http_response_code(200);
echo json_encode(['ok' => true]);
