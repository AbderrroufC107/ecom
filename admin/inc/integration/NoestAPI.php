<?php
require_once __DIR__ . '/DeliveryIntegrationInterface.php';

class NoestAPI implements DeliveryIntegrationInterface {
    private $api_token;
    private $base_url;

    public function setCredentials(array $credentials) {
        $this->api_token = $credentials['api_token'] ?? '';
        $this->base_url = rtrim($credentials['api_base_url'] ?? 'https://api.noest.dz/v1', '/');
    }

    public function testConnection(): array {
        return ['success' => false, 'message' => 'Noest Integration stub: Not fully implemented yet.'];
    }

    public function createShipment(array $orderData): array {
        return ['success' => false, 'tracking_number' => '', 'message' => 'Not implemented yet.', 'error' => ''];
    }

    public function getShipmentStatus(string $tracking_number): array {
        return ['success' => false, 'status' => 'Unknown', 'raw_response' => []];
    }

    public function mapStatus(string $rawStatus): string {
        return 'Processing';
    }
}
?>
