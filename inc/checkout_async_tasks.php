<?php

if (!function_exists('checkout_dispatch_order_post_tasks')) {
    function checkout_dispatch_order_post_tasks(array $payload) {
        $order_id = (int)($payload['order_id'] ?? ($payload['context']['id'] ?? 0));
        if ($order_id <= 0) {
            return false;
        }

        $dir = __DIR__ . '/../cache/checkout-post-tasks';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log('Checkout async task directory is not writable: ' . $dir);
            return false;
        }

        $job_file = $dir . '/order_' . $order_id . '_' . bin2hex(random_bytes(6)) . '.json';
        $payload['created_at'] = date('c');
        $payload['order_id'] = $order_id;

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($job_file, $json, LOCK_EX) === false) {
            error_log('Failed to write checkout async job for order #' . $order_id);
            return false;
        }

        $script = realpath(__DIR__ . '/../checkout-post-order-task.php');
        if (!$script || !is_file($script)) {
            error_log('Checkout async worker is missing for order #' . $order_id);
            return false;
        }

        $php = PHP_BINARY ?: 'php';
        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            $command = 'cmd /C start "" /B ' . escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($job_file);
            @pclose(@popen($command, 'r'));
        } else {
            $command = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($job_file) . ' > /dev/null 2>&1 &';
            @exec($command);
        }

        return true;
    }
}
