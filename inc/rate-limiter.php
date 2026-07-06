<?php
declare(strict_types=1);

if (!class_exists('PublicRateLimiter')) {
    class PublicRateLimiter
    {
        private PDO $pdo;

        public function __construct(PDO $pdo)
        {
            $this->pdo = $pdo;
            $this->ensure_table();
        }

        private function ensure_table(): void
        {
            try {
                $this->pdo->exec("CREATE TABLE IF NOT EXISTS tbl_public_rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    device_id VARCHAR(128) NULL,
                    endpoint VARCHAR(60) NOT NULL,
                    attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_ip_end_time (ip_address, endpoint, attempt_time),
                    KEY idx_dev_end_time (device_id, endpoint, attempt_time)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } catch (Exception $e) {
                error_log('Failed to ensure tbl_public_rate_limits: ' . $e->getMessage());
            }
        }

        public function check(string $endpoint, int $limit, int $window_seconds, ?string $device_id = null, bool $json_response = false): void
        {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $device = $device_id;
            if ($device === null && function_exists('site_security_device_id')) {
                $device = site_security_device_id();
            }
            $device = trim((string)$device);

            // 1. Record attempt
            try {
                $stmt = $this->pdo->prepare("INSERT INTO tbl_public_rate_limits (ip_address, device_id, endpoint) VALUES (?, ?, ?)");
                $stmt->execute([$ip, $device !== '' ? $device : null, $endpoint]);
            } catch (Exception $e) {
                error_log('Failed to record rate limit attempt: ' . $e->getMessage());
            }

            // 2. Count attempts in the window
            $window_time = date('Y-m-d H:i:s', time() - $window_seconds);

            $ip_count = 0;
            $device_count = 0;

            try {
                // Check by IP
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_public_rate_limits WHERE ip_address = ? AND endpoint = ? AND attempt_time >= ?");
                $stmt->execute([$ip, $endpoint, $window_time]);
                $ip_count = (int)$stmt->fetchColumn();

                // Check by Device ID if provided
                if ($device !== '') {
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tbl_public_rate_limits WHERE device_id = ? AND endpoint = ? AND attempt_time >= ?");
                    $stmt->execute([$device, $endpoint, $window_time]);
                    $device_count = (int)$stmt->fetchColumn();
                }
            } catch (Exception $e) {
                error_log('Failed to query rate limits: ' . $e->getMessage());
                return; // fail open on database issues to prevent lockout
            }

            $max_count = max($ip_count, $device_count);

            // Progressive delays: if count is > 60% of the limit, sleep for a progressive amount of time
            if ($max_count > ($limit * 0.6) && $max_count <= $limit) {
                $delay = (int)($max_count - ($limit * 0.6)) * 2;
                if ($delay > 0) {
                    sleep(min($delay, 10));
                }
            }

            // If limit is exceeded, block the request
            if ($max_count > $limit) {
                // Log under security logs
                if (function_exists('audit_log_security')) {
                    audit_log_security($this->pdo, 0, 'rate_limit_exceeded', null, [
                        'ip' => $ip,
                        'device_id' => $device,
                        'endpoint' => $endpoint,
                        'attempts' => $max_count,
                        'limit' => $limit,
                        'window' => $window_seconds
                    ], 'rate_limiter');
                }

                http_response_code(429);
                if ($json_response) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode([
                        'success' => false,
                        'error' => 'TOO_MANY_REQUESTS',
                        'message' => 'لقد تجاوزت حد الطلبات المسموح به. يرجى المحاولة لاحقاً بعد قليل.'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    header('Content-Type: text/html; charset=UTF-8');
                    echo '<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تنبيه أمان</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: sans-serif; text-align: center; padding: 50px; background: #f8fafc; color: #334155; }
        .card { background: white; padding: 30px; border-radius: 12px; max-width: 450px; margin: auto; box-shadow: 0 4px 10px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        h1 { color: #dc2626; font-size: 24px; }
        p { font-size: 16px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="card">
        <h1>الرجاء المحاولة لاحقاً</h1>
        <p>لقد قمت بإرسال عدد كبير جداً من الطلبات في وقت قصير. يرجى الانتظار بضع دقائق ثم المحاولة مرة أخرى.</p>
    </div>
</body>
</html>';
                }
                exit;
            }
        }
    }
}
