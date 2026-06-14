<?php

namespace Ecom\Common;

class Config
{
    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $value = defined($key) ? constant($key) : $default;
        self::$cache[$key] = $value;
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$cache[$key] = $value;
    }

    public static function has(string $key): bool
    {
        if (array_key_exists($key, self::$cache)) {
            return true;
        }
        return defined($key);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    public static function isDebug(): bool
    {
        return (bool) self::get('DEBUG_MODE', false);
    }

    public static function basePath(): string
    {
        return dirname(__DIR__, 3);
    }

    public static function adminPath(): string
    {
        return self::basePath() . DIRECTORY_SEPARATOR . 'admin';
    }

    public static function uploadsPath(): string
    {
        return self::basePath() . DIRECTORY_SEPARATOR . 'uploads';
    }
}
