<?php
/**
 * SecondaryBotWebhookHandler
 *
 * Minimal handler for the secondary bots (order-status, incomplete-orders).
 * These bots exist only to deliver notifications to a personally-linked
 * chat - they don't need the main bot's full command set (/tasks,
 * /complaint, etc.), just enough to complete /start <token> linking.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Services/SecondaryBotLinkService.php';

class SecondaryBotWebhookHandler
{
    private PDO $pdo;
    private string $purpose;
    private string $botToken;

    public function __construct(PDO $pdo, string $purpose)
    {
        $this->pdo = $pdo;
        $this->purpose = $purpose;
        $this->botToken = SecondaryBotLinkService::getBotToken($pdo, $purpose);
    }

    public function handle(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));
        if ($chatId === '' || strpos($text, '/start') !== 0) {
            if ($chatId !== '') {
                $this->send($chatId, "🔒 هذا البوت مخصص للإشعارات فقط. اربط حسابك من الملف الشخصي بموقع الويب.");
            }
            return;
        }

        $parts = explode(' ', $text, 2);
        $token = trim($parts[1] ?? '');

        if ($token === '') {
            $this->send($chatId, "🔒 يرجى الضغط على زر الربط في الملف الشخصي بموقع الويب للحصول على رابط صالح.");
            return;
        }

        $username = $message['from']['username'] ?? null;
        $firstName = $message['from']['first_name'] ?? null;
        $res = SecondaryBotLinkService::verifyAndLink($this->pdo, $token, $chatId, $username, $firstName);

        if (!empty($res['success'])) {
            $this->send($chatId, "🎉 <b>تم ربط حسابك بنجاح لهذا البوت!</b>\nسوف تصلك من الآن التنبيهات المخصصة هنا.");
        } else {
            $this->send($chatId, "❌ <b>فشل الربط:</b>\n\n" . ($res['error'] ?? 'خطأ غير معروف.'));
        }
    }

    private function send(string $chatId, string $text): void
    {
        if ($this->botToken === '') {
            return;
        }
        @file_get_contents('https://api.telegram.org/bot' . $this->botToken . '/sendMessage?' . http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]));
    }
}
