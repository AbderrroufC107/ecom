<?php

namespace Ecom\Common;

class Helpers
{
    public static function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    public static function generateSlug(string $name): string
    {
        $slug = mb_strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function sanitizeOutput(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonError(string $message, int $statusCode = 400): void
    {
        self::jsonResponse(['error' => true, 'message' => $message], $statusCode);
    }

    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
    }

    public static function truncate(string $text, int $maxLength, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength - mb_strlen($suffix)) . $suffix;
    }
}
