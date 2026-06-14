<?php

class LoginThrottle {
    private PDO $pdo;
    private int $max_attempts = 5;
    private int $lockout_minutes = 15;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensure_tables();
    }

    public function ensure_tables(): void {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS tbl_login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(500) DEFAULT '',
            login_identifier VARCHAR(255) NOT NULL,
            attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_ip_time (ip_address, attempt_time),
            INDEX idx_login_time (login_identifier, attempt_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function is_locked_out(string $ip, string $login): bool {
        $window = date('Y-m-d H:i:s', strtotime("-{$this->lockout_minutes} minutes"));
        $sql = "SELECT COUNT(*) FROM tbl_login_attempts
                WHERE (ip_address = ? OR login_identifier = ?)
                AND attempt_time >= ? AND success = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ip, $login, $window]);
        return $stmt->fetchColumn() >= $this->max_attempts;
    }

    public function record_attempt(string $ip, string $login, string $user_agent, bool $success): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tbl_login_attempts (ip_address, user_agent, login_identifier, success) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$ip, $login, $user_agent, $success ? 1 : 0]);
    }

    public function clear_attempts(string $ip, string $login): void {
        $stmt = $this->pdo->prepare(
            "DELETE FROM tbl_login_attempts WHERE (ip_address = ? OR login_identifier = ?)"
        );
        $stmt->execute([$ip, $login]);
    }

    public function get_remaining_lockout_time(string $ip, string $login): int {
        $window = date('Y-m-d H:i:s', strtotime("-{$this->lockout_minutes} minutes"));
        $sql = "SELECT MAX(attempt_time) FROM tbl_login_attempts
                WHERE (ip_address = ? OR login_identifier = ?)
                AND attempt_time >= ? AND success = 0";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ip, $login, $window]);
        $last_attempt = $stmt->fetchColumn();
        if (!$last_attempt) return 0;
        $lockout_until = strtotime($last_attempt) + ($this->lockout_minutes * 60);
        return max(0, $lockout_until - time());
    }
}
