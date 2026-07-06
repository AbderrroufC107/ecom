<?php
require_once __DIR__ . '/DeliveryIntegrationInterface.php';

class ZRExpressAPI implements DeliveryIntegrationInterface {
    private $api_key;
    private $base_url;

    public function setCredentials(array $credentials) {
        $this->api_key = $credentials['api_key'] ?? '';
        $this->base_url = rtrim($credentials['api_base_url'] ?? 'https://api.zrexpress.com/v1', '/');
    }

    public function testConnection(): array {
        return ['success' => false, 'message' => 'ZR Express Integration stub: Not fully implemented yet.'];
    }

    public function createShipment(array $orderData): array {
        return ['success' => false, 'tracking_number' => '', 'message' => 'Not implemented yet.', 'error' => ''];
    }

    public function getShipmentStatus(string $tracking_number): array {
        // Return dummy pending status for now until real API endpoints are provided
        return ['success' => false, 'status' => 'Unknown', 'raw_response' => []];
    }

    public function mapStatus(string $rawStatus): string {
        return 'Processing';
    }
}
?>
