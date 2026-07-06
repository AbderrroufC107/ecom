<?php
namespace Marketing\Infrastructure\Logging;

class CorrelationTracker
{
    private static ?string $correlationId = null;

    /**
     * Get or generate the current correlation ID.
     */
    public static function getId(): string
    {
        if (self::$correlationId === null) {
            self::$correlationId = bin2hex(random_bytes(16));
        }
        return self::$correlationId;
    }

    /**
     * Set a specific correlation ID (e.g. from an incoming queue job).
     */
    public static function setId(string $id): void
    {
        self::$correlationId = $id;
    }
    
    /**
     * Clear the correlation ID (useful for long-running workers).
     */
    public static function clear(): void
    {
        self::$correlationId = null;
    }
}
