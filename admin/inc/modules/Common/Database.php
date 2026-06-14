<?php

namespace Ecom\Common;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function configure(string $host, string $name, string $user, string $pass, int $port = 3306): void
    {
        self::$config = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass,
            'port' => $port,
        ];
    }

    public static function connect(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        if (empty(self::$config)) {
            self::$config = [
                'host'     => defined('DB_HOST') ? DB_HOST : 'localhost',
                'name'     => defined('DB_NAME') ? DB_NAME : 'ecom',
                'user'     => defined('DB_USER') ? DB_USER : 'root',
                'pass'     => defined('DB_PASS') ? DB_PASS : '',
                'port'     => defined('DB_PORT') ? (int) DB_PORT : 3306,
            ];
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            self::$config['host'],
            self::$config['port'],
            self::$config['name']
        );

        try {
            self::$instance = new PDO($dsn, self::$config['user'], self::$config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage());
        }

        return self::$instance;
    }

    public static function getInstance(): ?PDO
    {
        return self::$instance;
    }

    public static function disconnect(): void
    {
        self::$instance = null;
    }

    public static function beginTransaction(): bool
    {
        return self::connect()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    public static function rollback(): bool
    {
        return self::connect()->rollBack();
    }
}
