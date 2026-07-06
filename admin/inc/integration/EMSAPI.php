<?php
require_once __DIR__ . '/DeliveryIntegrationInterface.php';

class EMSAPI implements DeliveryIntegrationInterface {
    private $api_username;
    private $api_password;
    private $base_url;

    public function setCredentials(array $credentials) {
        $this->api_username = $credentials['api_username'] ?? '';
        $this->api_password = $credentials['api_password'] ?? '';
        $this->base_url = rtrim($credentials['api_base_url'] ?? 'https://api.ems.dz/v1', '/');
    }

    public function testConnection(): array {
        return ['success' => false, 'message' => 'EMS Integration stub: Not fully implemented yet.'];
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
