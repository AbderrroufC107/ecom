<?php
namespace Omni\Adapters;

use Omni\UnifiedMessage;

interface AdapterInterface {
    /**
     * Validates the incoming webhook signature based on the platform's mechanism.
     */
    public function validateSignature(string $payload, string $signature, string $secret): bool;
    
    /**
     * Parses the raw payload into an array of UnifiedMessage objects.
     */
    public function parsePayload(string $payload, int $channelId): array;

    /**
     * Sends a message back to the platform.
     */
    public function sendMessage(int $channelId, string $platformUserId, string $text, array $options = []): bool;
}
