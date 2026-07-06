<?php

namespace Ecom\Cache;

use PDO;

class CacheService
{
    private PDO $pdo;
    private string $prefix;
    private int $defaultTtl;

    public function __construct(PDO $pdo, string $prefix = 'cache_', int $defaultTtl = 300)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
        $this->defaultTtl = $defaultTtl;
        $this->ensure_tables();
    }

    public function ensure_tables(): void
    {
        static $done = false;
        if ($done) return;
        
        $lock_file = __DIR__ . '/../../../cache/cache_tables.lock';
        if (file_exists($lock_file)) {
            $done = true;
            return;
        }

        (new \SaaS\Repositories\DatabaseRepository($this->pdo))->executeCommand("
            CREATE TABLE IF NOT EXISTS tbl_cache (
                cache_key VARCHAR(255) PRIMARY KEY,
                cache_value LONGTEXT NOT NULL,
                cache_ttl INT NOT NULL DEFAULT 300,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL,
                INDEX idx_expires (expires_at),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        (new \SaaS\Repositories\DatabaseRepository($this->pdo))->executeCommand("
            CREATE TABLE IF NOT EXISTS tbl_materialized_stats (
                stat_key VARCHAR(255) PRIMARY KEY,
                stat_value LONGTEXT NOT NULL,
                last_computed DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                refresh_interval INT NOT NULL DEFAULT 300,
                INDEX idx_last_computed (last_computed)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        @file_put_contents($lock_file, '1');
        $done = true;
    }

    public function get(string $key): ?string
    {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
            "SELECT cache_value FROM tbl_cache WHERE cache_key = ? AND expires_at > NOW()"
        );
        $stmt->execute([$this->prefix . $key]);
        $row = $stmt->fetchColumn();
        return $row !== false ? $row : null;
    }

    public function set(string $key, string $value, ?int $ttl = null): void
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $cacheKey = $this->prefix . $key;
        $expires = date('Y-m-d H:i:s', time() + $ttl);
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
            "INSERT INTO tbl_cache (cache_key, cache_value, cache_ttl, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cache_value = VALUES(cache_value), cache_ttl = VALUES(cache_ttl), expires_at = VALUES(expires_at), created_at = NOW()"
        );
        $stmt->execute([$cacheKey, $value, $ttl, $expires]);
    }

    public function delete(string $key): void
    {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("DELETE FROM tbl_cache WHERE cache_key = ?");
        $stmt->execute([$this->prefix . $key]);
    }

    public function deleteByPrefix(string $prefix): void
    {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("DELETE FROM tbl_cache WHERE cache_key LIKE ?");
        $stmt->execute([$this->prefix . $prefix . '%']);
    }

    public function flush(): void
    {
        (new \SaaS\Repositories\DatabaseRepository($this->pdo))->executeCommand("TRUNCATE tbl_cache");
    }

    public function getOrCompute(string $key, callable $fn, ?int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            $decoded = json_decode($cached, true);
            return $decoded !== null ? $decoded : $cached;
        }
        $value = $fn();
        $stringValue = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $this->set($key, $stringValue, $ttl);
        return is_string($value) ? $value : json_decode($stringValue, true);
    }

    public function getStats(string $key, callable $fn, ?int $maxAge = null): string
    {
        $maxAge = $maxAge ?? 300;
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
            "SELECT stat_value FROM tbl_materialized_stats WHERE stat_key = ? AND last_computed > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$key, $maxAge]);
        $row = $stmt->fetchColumn();
        if ($row !== false) return $row;

        $value = $fn();
        $stringValue = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
            "INSERT INTO tbl_materialized_stats (stat_key, stat_value, last_computed, refresh_interval)
             VALUES (?, ?, NOW(), ?)
             ON DUPLICATE KEY UPDATE stat_value = VALUES(stat_value), last_computed = NOW(), refresh_interval = VALUES(refresh_interval)"
        );
        $stmt->execute([$key, $stringValue, $maxAge]);
        return $stringValue;
    }

    public function invalidateStats(string $keyPattern): void
    {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("DELETE FROM tbl_materialized_stats WHERE stat_key LIKE ?");
        $stmt->execute([$keyPattern]);
    }
}
