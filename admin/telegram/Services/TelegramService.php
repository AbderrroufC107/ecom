<?php
/**
 * TelegramService Class
 *
 * Central HTTP client wrapping Telegram Bot API calls.
 */

declare(strict_types=1);

class TelegramService
{
    private static ?TelegramService $instance = null;
    private string $token = '';
    private bool $isEnabled = false;
    private ?PDO $pdo = null;

    /**
     * Constructor loads configuration settings from tbl_settings.
     */
    private function __construct(PDO $pdo)
    { global $dbRepo;
    global $dbRepo;

        $this->pdo = $pdo;
        try {
            $stmt = $dbRepo->query("
                SELECT telegram_bot_token, telegram_is_enabled 
                FROM tbl_settings 
                WHERE id = 1 
                LIMIT 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->token = trim((string) ($row['telegram_bot_token'] ?? ''));
                $this->isEnabled = (int) ($row['telegram_is_enabled'] ?? 0) === 1;
            }
        } catch (Exception $e) {
            $this->token = '';
            $this->isEnabled = false;
        }
    }

    /**
     * Singleton instance accessor.
     */
    public static function getInstance(PDO $pdo): TelegramService
    { global $dbRepo;
        if (self::$instance === null) {
            self::$instance = new self($pdo);
        }
        return self::$instance;
    }

    /**
     * Check if Telegram Bot integration is enabled.
     */
    public function isEnabled(): bool
    { global $dbRepo;
        return $this->isEnabled;
    }

    /**
     * Make API call directly to Telegram API.
     */
    public function apiCall(string $method, array $data = []): array
    { global $dbRepo;
        if ($this->token === '') {
            return ['ok' => false, 'description' => 'Bot token is not configured.'];
        }

        $url = "https://api.telegram.org/bot{$this->token}/{$method}";
        
        $allowInsecureSsl = filter_var(getenv('TELEGRAM_ALLOW_INSECURE_SSL') ?: '', FILTER_VALIDATE_BOOLEAN);
        if (!$allowInsecureSsl) {
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $allowInsecureSsl = $host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || strpos($host, 'localhost') !== false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$allowInsecureSsl);
        if ($allowInsecureSsl) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok' => false, 
                'description' => "CURL Error: {$curlError}",
                'latency_ms' => $latencyMs
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false, 
                'description' => 'Invalid JSON response from Telegram API.',
                'latency_ms' => $latencyMs
            ];
        }

        $decoded['latency_ms'] = $latencyMs;

        // Check for rate limit throttling
        if ($httpCode === 429 && isset($decoded['parameters']['retry_after'])) {
            $decoded['rate_limited'] = true;
            $decoded['retry_after'] = (int) $decoded['parameters']['retry_after'];
        }

        return $decoded;
    }

    /**
     * Sends a text message.
     */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendMessage', $data);
    }

    /**
     * Sends a photo message.
     */
    public function sendPhoto(string $chatId, string $photo, string $caption = '', ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendPhoto', $data);
    }

    /**
     * Sends a document file.
     */
    public function sendDocument(string $chatId, string $document, string $caption = '', ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendDocument', $data);
    }

    /**
     * Sends an audio file.
     */
    public function sendAudio(string $chatId, string $audio, string $caption = '', ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'audio' => $audio,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendAudio', $data);
    }

    /**
     * Sends a video file.
     */
    public function sendVideo(string $chatId, string $video, string $caption = '', ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendVideo', $data);
    }

    /**
     * Sends location details.
     */
    public function sendLocation(string $chatId, float $latitude, float $longitude, ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendLocation', $data);
    }

    /**
     * Sends a contact.
     */
    public function sendContact(string $chatId, string $phoneNumber, string $firstName, string $lastName = '', ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'phone_number' => $phoneNumber,
            'first_name' => $firstName,
            'last_name' => $lastName
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('sendContact', $data);
    }

    /**
     * Edits a text message.
     */
    public function editMessage(string $chatId, int $messageId, string $text, ?array $replyMarkup = null): array
    { global $dbRepo;
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        if ($replyMarkup !== null) {
            $data['reply_markup'] = $replyMarkup;
        }

        return $this->apiCall('editMessageText', $data);
    }

    /**
     * Deletes a message.
     */
    public function deleteMessage(string $chatId, int $messageId): array
    { global $dbRepo;
        return $this->apiCall('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    /**
     * Answers callback query from inline buttons.
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array
    { global $dbRepo;
        return $this->apiCall('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }

    /**
     * Get the Bot username, using local PHP file cache if available.
     */
    public function getBotUsername(): string
    { global $dbRepo;
        $cacheFile = __DIR__ . '/../../cache/telegram_bot_username.cache';
        
        if (file_exists($cacheFile)) {
            $cacheContent = @file_get_contents($cacheFile);
            if (!empty($cacheContent)) {
                return trim($cacheContent);
            }
        }

        // Fetch from API
        $res = $this->apiCall('getMe');
        if (!empty($res['ok']) && !empty($res['result']['username'])) {
            $botUsername = trim($res['result']['username']);
            // Ensure folder exists and write cache
            @file_put_contents($cacheFile, $botUsername);
            return $botUsername;
        }

        return 'YourBotUsername';
    }
}
