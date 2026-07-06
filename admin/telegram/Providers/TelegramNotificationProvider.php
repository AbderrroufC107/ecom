<?php
/**
 * TelegramNotificationProvider Class
 *
 * Implements NotificationProviderInterface delivering messages via Telegram Bot Queue.
 */

declare(strict_types=1);

require_once __DIR__ . '/NotificationProviderInterface.php';
require_once __DIR__ . '/../Services/QueueService.php';

class TelegramNotificationProvider implements NotificationProviderInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private function formatMessage(string $templateKey, string $lang, array $data): string
    {
        $lang = in_array($lang, ['ar', 'en', 'fr'], true) ? $lang : 'ar';
        $templateFile = __DIR__ . "/../Templates/{$lang}.php";
        $templates = file_exists($templateFile) ? (require $templateFile) : [];
        $template = $templates[$templateKey] ?? ($templates['emergency_notification'] ?? 'Notification: {message}');

        $replacements = [];
        foreach ($data as $key => $val) {
            $replacements['{' . $key . '}'] = htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
        }

        return strtr($template, $replacements);
    }

    public function sendTaskNotification(array $employee, array $order, string $templateKey, array $data = []): bool
    {
        $chatId = trim((string) ($employee['telegram_chat_id'] ?? ''));
        if ($chatId === '' || empty($employee['telegram_is_linked'])) {
            return false;
        }

        $lang = trim((string) ($employee['telegram_lang'] ?? 'ar'));
        $mergedData = array_merge([
            'order_id' => $order['id'] ?? '',
            'product_name' => $order['product_name'] ?? '',
            'customer_name' => $order['customer_name'] ?? '',
            'customer_phone' => $order['customer_phone'] ?? '',
            'wilaya' => $order['wilaya'] ?? '',
            'commune' => $order['commune'] ?? '',
            'total_price' => $order['total_price'] ?? '0'
        ], $data);

        $orderId = (int) ($order['id'] ?? 0);
        $status = $order['order_status'] ?? 'Pending';
        $buttons = [];

        if ($status === 'Pending') {
            $buttons = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تأكيد الطلب', 'callback_data' => "confirm:{$orderId}"],
                        ['text' => '✏️ تعديل الطلب', 'callback_data' => "edit:{$orderId}"],
                    ],
                    [
                        ['text' => '🚚 إرسال للشركة', 'callback_data' => "ship:{$orderId}"],
                        ['text' => '📝 ملاحظة', 'callback_data' => "note:{$orderId}"],
                    ],
                    [
                        ['text' => '❌ إلغاء الطلب', 'callback_data' => "cancel:{$orderId}"],
                        ['text' => '🗑 حذف الطلب', 'callback_data' => "delete:{$orderId}"],
                    ],
                ]
            ];
        } elseif ($status === 'Confirmed') {
            $buttons = [
                'inline_keyboard' => [
                    [
                        ['text' => '🚚 إرسال للشركة', 'callback_data' => "ship:{$orderId}"],
                        ['text' => '🏁 إنهاء المهمة', 'callback_data' => "emp_complete:{$orderId}"],
                    ],
                    [
                        ['text' => '📝 ملاحظة', 'callback_data' => "note:{$orderId}"],
                        ['text' => '🗑 حذف الطلب', 'callback_data' => "delete:{$orderId}"],
                    ],
                ]
            ];
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $this->formatMessage($templateKey, $lang, $mergedData),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        if (!empty($buttons)) {
            $payload['reply_markup'] = $buttons;
        }

        return QueueService::push($this->pdo, $chatId, 'sendMessage', $payload) > 0;
    }

    public function sendAdminNotification(array $manager, string $templateKey, array $data = []): bool
    {
        $chatId = trim((string) ($manager['telegram_chat_id'] ?? ''));
        if ($chatId === '' || empty($manager['telegram_is_linked'])) {
            return false;
        }

        $permissions = json_decode((string) ($manager['telegram_notifications'] ?? ''), true) ?: [];
        $category = $this->getCategoryByTemplate($templateKey);
        if ($category !== null && isset($permissions[$category]) && !$permissions[$category]) {
            return false;
        }

        $lang = trim((string) ($manager['telegram_lang'] ?? 'ar'));
        $payload = [
            'chat_id' => $chatId,
            'text' => $this->formatMessage($templateKey, $lang, $data),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        if (isset($data['order_id']) && in_array($category, ['orders', 'status'], true)) {
            $orderId = (int) $data['order_id'];
            $payload['reply_markup'] = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تأكيد', 'callback_data' => "mgr_approve:{$orderId}"],
                        ['text' => '🚚 إرسال للشركة', 'callback_data' => "mgr_ship:{$orderId}"],
                    ],
                    [
                        ['text' => '📝 ملاحظة', 'callback_data' => "mgr_note:{$orderId}"],
                        ['text' => '❌ إلغاء', 'callback_data' => "mgr_cancel:{$orderId}"],
                    ],
                    [
                        ['text' => '🔄 تحويل لموظف', 'callback_data' => "mgr_reassign:{$orderId}"],
                        ['text' => '🔒 إغلاق', 'callback_data' => "mgr_close:{$orderId}"],
                    ],
                    [
                        ['text' => '🗑 حذف الطلب', 'callback_data' => "mgr_delete:{$orderId}"],
                    ],
                ]
            ];
        }

        return QueueService::push($this->pdo, $chatId, 'sendMessage', $payload) > 0;
    }

    public function broadcastNotification(array $managers, string $templateKey, array $data = []): bool
    {
        $success = true;
        foreach ($managers as $manager) {
            if (!$this->sendAdminNotification($manager, $templateKey, $data)) {
                $success = false;
            }
        }
        return $success;
    }

    private function getCategoryByTemplate(string $templateKey): ?string
    {
        $map = [
            'new_order' => 'orders',
            'new_complaint' => 'complaints',
            'employee_status_change' => 'status',
            'task_accepted' => 'status',
            'task_rejected' => 'status',
            'task_started' => 'status',
            'task_completed' => 'status',
            'employee_registered' => 'status',
            'daily_summary' => 'reports',
            'emergency_notification' => 'emergency'
        ];

        return $map[$templateKey] ?? 'system';
    }
}
