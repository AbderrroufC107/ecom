<?php
/**
 * Telegram Account Linking AJAX Controller
 *
 * Handles generating start link tokens and unlinking accounts for employees and managers.
 */

declare(strict_types=1);

// We need session context to identify the logged-in user
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/telegram/Services/TelegramService.php';
require_once __DIR__ . '/telegram/Services/TokenService.php';
require_once __DIR__ . '/telegram/Services/AuditService.php';
require_once __DIR__ . '/telegram/Services/SecondaryBotLinkService.php';

$action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
$userType = trim((string) ($_POST['user_type'] ?? $_GET['user_type'] ?? ''));
$botPurpose = trim((string) ($_POST['bot_purpose'] ?? $_GET['bot_purpose'] ?? ''));

if ($action === '' || $userType === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
    exit;
}

if (!in_array($userType, ['employee', 'manager'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid user type.']);
    exit;
}

// 1. Session Authentications
$userId = 0;
if ($userType === 'employee') {
    $userId = 0;
    if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Employee') {
        $userId = (int) str_replace('emp_', '', $_SESSION['user']['id']);
    }
    
    if ($userId === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized Employee Session.']);
        exit;
    }
} else {
    if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized Manager Session.']);
        exit;
    }
    $userId = (int) $_SESSION['user']['id'];
}

// 1b. Secondary bots (order-status, incomplete-orders) route to their own
// service entirely - separate table, separate per-bot chat_id, independent
// of the main bot's tbl_user/tbl_employee linking above.
if ($botPurpose !== '') {
    if (!SecondaryBotLinkService::isValidPurpose($botPurpose)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid bot purpose.']);
        exit;
    }

    SecondaryBotLinkService::ensureTable($pdo);
    $ownerType = $userType === 'employee' ? 'employee' : 'manager';

    if ($action === 'generate') {
        try {
            if (!SecondaryBotLinkService::hasDedicatedBot($pdo, $botPurpose)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'لا يوجد بوت منفصل مضبوط لهذا الغرض بعد.']);
                exit;
            }
            $token = SecondaryBotLinkService::generateLinkToken($pdo, $ownerType, $userId, $botPurpose);
            $botUsername = SecondaryBotLinkService::getBotUsername($pdo, $botPurpose);
            if ($botUsername === '') {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'تعذّر التحقق من هذا البوت. تأكد من صحة التوكن.']);
                exit;
            }
            echo json_encode(['success' => true, 'token' => $token, 'url' => "https://t.me/{$botUsername}?start={$token}"]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate token: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'unlink') {
        SecondaryBotLinkService::unlink($pdo, $ownerType, $userId, $botPurpose);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'test') {
        $chatId = SecondaryBotLinkService::getLinkedChatId($pdo, $ownerType, $userId, $botPurpose);
        if ($chatId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Account is not linked to this bot.']);
            exit;
        }
        $botToken = SecondaryBotLinkService::getBotToken($pdo, $botPurpose);
        $result = @file_get_contents('https://api.telegram.org/bot' . $botToken . '/sendMessage?' . http_build_query([
            'chat_id' => $chatId,
            'text' => "🔔 Test notification - this channel is linked correctly.",
        ]));
        $decoded = $result !== false ? json_decode($result, true) : null;
        if (!empty($decoded['ok'])) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send test message.']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
    exit;
}

// 2. Action Routing
if ($action === 'generate') {
    try {
        $token = TokenService::generateLinkToken($pdo, $userId, $userType);
        $telegramService = TelegramService::getInstance($pdo);
        $botUsername = $telegramService->getBotUsername();
        
        $deepLink = "https://t.me/{$botUsername}?start={$token}";
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'url' => $deepLink
        ]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to generate token: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'unlink') {
    try {
        // Log Audit Trail if Manager
        if ($userType === 'manager') {
            // Fetch username
            $stmt = $dbRepo->prepare("SELECT telegram_username FROM tbl_user WHERE id = ?");
            $stmt->execute([$userId]);
            $prevUsername = $stmt->fetchColumn() ?: 'None';
            AuditService::logAudit($pdo, $userId, 'unlink_telegram', "Telegram Username: {$prevUsername}", "Unlinked");
        }

        $ok = TokenService::unlink($pdo, $userId, $userType);
        if ($ok) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to unlink account.']);
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error unlinking account: ' . $e->getMessage()]);
        exit;
    }
}

if ($action === 'test') {
    try {
        $telegramService = TelegramService::getInstance($pdo);
        $chatId = null;

        if ($userType === 'manager') {
            $stmt = $dbRepo->prepare("SELECT telegram_chat_id FROM tbl_user WHERE id = ? AND telegram_is_linked = 1");
            $stmt->execute([$userId]);
            $chatId = $stmt->fetchColumn();
        } else {
            $stmt = $dbRepo->prepare("SELECT telegram_chat_id FROM tbl_employee WHERE id = ? AND telegram_is_linked = 1");
            $stmt->execute([$userId]);
            $chatId = $stmt->fetchColumn();
        }

        if (!$chatId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Account is not linked to Telegram.']);
            exit;
        }

        $message = "🔔 <b>Test Notification</b>\n\nYour Telegram account is successfully linked and you are ready to receive notifications.";
        $result = $telegramService->sendMessage((int)$chatId, $message);

        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to send test message. Check bot configuration.']);
        }
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unsupported action.']);
exit;
