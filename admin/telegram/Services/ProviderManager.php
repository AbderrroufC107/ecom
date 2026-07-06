<?php
/**
 * ProviderManager Class
 *
 * Registers and retrieves active notification channel providers.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Providers/NotificationProviderInterface.php';

class ProviderManager
{
    private static array $providers = [];

    /**
     * Register a notification channel provider.
     */
    public static function registerProvider(string $name, NotificationProviderInterface $provider): void
    {
        self::$providers[$name] = $provider;
    }

    /**
     * Get a specific registered provider.
     */
    public static function getProvider(string $name): ?NotificationProviderInterface
    {
        return self::$providers[$name] ?? null;
    }

    /**
     * Get all registered active providers.
     */
    public static function getActiveProviders(): array
    {
        return self::$providers;
    }
}
