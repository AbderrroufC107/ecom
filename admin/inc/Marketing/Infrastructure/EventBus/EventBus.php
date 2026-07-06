<?php
namespace Marketing\Infrastructure\EventBus;

use Marketing\Domain\Events\DomainEventInterface;

class EventBus
{
    private static array $subscribers = [];
    
    /**
     * Subscribe a listener to an event.
     */
    public static function subscribe(string $eventName, callable $listener): void
    {
        if (!isset(self::$subscribers[$eventName])) {
            self::$subscribers[$eventName] = [];
        }
        self::$subscribers[$eventName][] = $listener;
    }
    
    /**
     * Publish an event to all subscribers.
     * In a real enterprise system, this might push to a Queue or RabbitMQ.
     */
    public static function publish(DomainEventInterface $event): void
    {
        $eventName = $event->getEventName();
        if (isset(self::$subscribers[$eventName])) {
            foreach (self::$subscribers[$eventName] as $listener) {
                call_user_func($listener, $event);
            }
        }
        
        // Also trigger wildcard subscribers if any
        if (isset(self::$subscribers['*'])) {
            foreach (self::$subscribers['*'] as $listener) {
                call_user_func($listener, $event);
            }
        }
    }
    
    /**
     * Clear all subscribers (useful for testing)
     */
    public static function clear(): void
    {
        self::$subscribers = [];
    }
}
