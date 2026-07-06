<?php
/**
 * EventManager Class
 *
 * Facilitates loose coupling using an Event Dispatcher (Observer Pattern).
 */

declare(strict_types=1);

class EventManager
{
    private static array $listeners = [];

    /**
     * Subscribe a callback listener to a specific system event.
     */
    public static function subscribe(string $event, callable $callback): void
    {
        self::$listeners[$event][] = $callback;
    }

    /**
     * Dispatch an event to all registered listeners.
     */
    public static function dispatch(string $event, ...$args): void
    {
        if (isset(self::$listeners[$event]) && is_array(self::$listeners[$event])) {
            foreach (self::$listeners[$event] as $callback) {
                try {
                    call_user_func_array($callback, $args);
                } catch (Exception $e) {
                    error_log("Error in EventManager listener for event '{$event}': " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Get all listeners subscribed to a specific event.
     */
    public static function getListeners(string $event): array
    {
        return self::$listeners[$event] ?? [];
    }
}
