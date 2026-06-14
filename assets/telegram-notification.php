<?php
class TelegramNotification {
    private $botToken;
    private $chatId;

    public function __construct($botToken, $chatId) {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
    }

    public function sendOrderNotification($orderData) {
        $message = $this->formatOrderMessage($orderData);
        return $this->sendMessage($message, 'HTML');
    }

    public function sendIncompleteOrderNotification($orderData) {
        $message = $this->formatIncompleteOrderMessage($orderData);
        return $this->sendMessage($message, 'HTML');
    }

    public function sendOrderStatusNotification($orderData) {
        $message = $this->formatOrderStatusMessage($orderData);
        return $this->sendMessage($message, 'HTML');
    }

    private function formatOrderMessage($orderData) {
        $message = "<b>New Order</b>

";

        $message .= "<b>Customer</b>
";
        if (!empty($orderData['customer_name'])) {
            $message .= "Name: " . $this->esc($orderData['customer_name']) . "
";
        }
        if (!empty($orderData['customer_phone'])) {
            $message .= "Phone: " . $this->esc($orderData['customer_phone']) . "
";
        }
        if (!empty($orderData['order_id'])) {
            $message .= "Order ID: " . $this->esc($orderData['order_id']) . "
";
        }
        $message .= "
";

        $hasLocation = !empty($orderData['wilaya']) || !empty($orderData['commune']) || !empty($orderData['address']) || !empty($orderData['delivery_type']);
        if ($hasLocation) {
            $message .= "<b>Delivery</b>
";
            if (!empty($orderData['wilaya'])) {
                $message .= "Wilaya: " . $this->esc($orderData['wilaya']) . "
";
            }
            if (!empty($orderData['commune'])) {
                $message .= "Commune: " . $this->esc($orderData['commune']) . "
";
            }
            if (!empty($orderData['address'])) {
                $message .= "Address: " . $this->esc($orderData['address']) . "
";
            }
            if (!empty($orderData['delivery_type'])) {
                $message .= "Delivery Type: " . $this->esc($orderData['delivery_type']) . "
";
            }
            $message .= "
";
        }

        $message .= "<b>Order Details</b>
";
        if (!empty($orderData['product_name'])) {
            $message .= "Product: " . $this->esc($orderData['product_name']) . "
";
        }
        if (!empty($orderData['quantity'])) {
            $message .= "Quantity: " . $this->esc($orderData['quantity']) . "
";
        }
        if (isset($orderData['unit_price']) && $orderData['unit_price'] !== '') {
            $message .= "Unit Price: " . $this->esc($orderData['unit_price']) . "
";
        }
        if (isset($orderData['total_price']) && $orderData['total_price'] !== '') {
            $message .= "Total: " . $this->esc($orderData['total_price']) . "
";
        }
        if (!empty($orderData['selected_size'])) {
            $message .= "Size: " . $this->esc($orderData['selected_size']) . "
";
        }
        if (!empty($orderData['selected_color'])) {
            $message .= "Color: " . $this->esc($orderData['selected_color']) . "
";
        }

        $message .= "
Timestamp: " . date('Y-m-d H:i:s');

        return $message;
    }

    private function formatIncompleteOrderMessage($orderData) {
        $status = !empty($orderData['is_update']) ? 'Update' : 'New';
        $message = "<b>Incomplete Order</b>
";
        $message .= "Status: " . $this->esc($status) . "

";

        if (!empty($orderData['incomplete_id'])) {
            $message .= "ID: " . $this->esc($orderData['incomplete_id']) . "

";
        }

        $message .= "<b>Customer</b>
";
        if (!empty($orderData['customer_name'])) {
            $message .= "Name: " . $this->esc($orderData['customer_name']) . "
";
        }
        if (!empty($orderData['customer_phone'])) {
            $message .= "Phone: " . $this->esc($orderData['customer_phone']) . "
";
        }
        $message .= "
";

        $message .= "<b>Order Details</b>
";
        if (!empty($orderData['product_name'])) {
            $message .= "Product: " . $this->esc($orderData['product_name']) . "
";
        }
        if (!empty($orderData['quantity'])) {
            $message .= "Quantity: " . $this->esc($orderData['quantity']) . "
";
        }
        if (isset($orderData['unit_price']) && $orderData['unit_price'] !== '') {
            $message .= "Unit Price: " . $this->esc($orderData['unit_price']) . "
";
        }
        if (isset($orderData['total_price']) && $orderData['total_price'] !== '') {
            $message .= "Total: " . $this->esc($orderData['total_price']) . "
";
        }
        if (!empty($orderData['selected_size'])) {
            $message .= "Size: " . $this->esc($orderData['selected_size']) . "
";
        }
        if (!empty($orderData['selected_color'])) {
            $message .= "Color: " . $this->esc($orderData['selected_color']) . "
";
        }
        $message .= "
";

        $hasLocation = !empty($orderData['wilaya']) || !empty($orderData['commune']) || !empty($orderData['delivery_type']);
        if ($hasLocation) {
            $message .= "<b>Delivery</b>
";
            if (!empty($orderData['wilaya'])) {
                $message .= "Wilaya: " . $this->esc($orderData['wilaya']) . "
";
            }
            if (!empty($orderData['commune'])) {
                $message .= "Commune: " . $this->esc($orderData['commune']) . "
";
            }
            if (!empty($orderData['delivery_type'])) {
                $message .= "Delivery Type: " . $this->esc($orderData['delivery_type']) . "
";
            }
            $message .= "
";
        }

        if (!empty($orderData['source'])) {
            $message .= "Source: " . $this->esc($orderData['source']) . "
";
        }

        $message .= "Timestamp: " . date('Y-m-d H:i:s');

        return $message;
    }

    private function formatOrderStatusMessage($orderData) {
        $message = "<b>Order Status</b>

";

        if (!empty($orderData['order_id'])) {
            $message .= "Order ID: " . $this->esc($orderData['order_id']) . "
";
        }
        if (!empty($orderData['tracking'])) {
            $message .= "Tracking: " . $this->esc($orderData['tracking']) . "
";
        }
        if (!empty($orderData['customer_name'])) {
            $message .= "Customer: " . $this->esc($orderData['customer_name']) . "
";
        }
        if (!empty($orderData['customer_phone'])) {
            $message .= "Phone: " . $this->esc($orderData['customer_phone']) . "
";
        }

        $message .= "
<b>Status Change</b>
";
        $message .= "From: " . $this->esc($orderData['old_status'] ?? '-') . "
";
        $message .= "To: " . $this->esc($orderData['new_status'] ?? '-') . "
";

        if (!empty($orderData['note'])) {
            $message .= "Note: " . $this->esc($orderData['note']) . "
";
        }
        if (!empty($orderData['remote_time'])) {
            $message .= "Remote Time: " . $this->esc($orderData['remote_time']) . "
";
        }

        if (!empty($orderData['product_name'])) {
            $message .= "
<b>Product</b>
";
            $message .= $this->esc($orderData['product_name']);
            if (!empty($orderData['quantity'])) {
                $message .= " x " . $this->esc($orderData['quantity']);
            }
            $message .= "
";
        }

        $hasLocation = !empty($orderData['wilaya']) || !empty($orderData['commune']) || !empty($orderData['delivery_type']);
        if ($hasLocation) {
            $message .= "
<b>Delivery</b>
";
            if (!empty($orderData['wilaya'])) {
                $message .= "Wilaya: " . $this->esc($orderData['wilaya']) . "
";
            }
            if (!empty($orderData['commune'])) {
                $message .= "Commune: " . $this->esc($orderData['commune']) . "
";
            }
            if (!empty($orderData['delivery_type'])) {
                $message .= "Delivery Type: " . $this->esc($orderData['delivery_type']) . "
";
            }
        }

        $message .= "
Timestamp: " . date('Y-m-d H:i:s');

        return $message;
    }

    private function esc($text) {
        return htmlspecialchars((string)$text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function sendMessage($message, $parseMode = 'HTML') {
        if (empty($this->botToken) || empty($this->chatId)) {
            return false;
        }
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $data = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => $parseMode
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded

",
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result !== false;
    }
}
?>
