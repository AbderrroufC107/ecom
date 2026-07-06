<?php
require_once __DIR__ . '/DeliveryIntegrationInterface.php';

class YalidineAPI implements DeliveryIntegrationInterface {
    private $api_id;
    private $api_token;
    private $base_url;

    public function setCredentials(array $credentials) {
        $this->api_id = $credentials['api_key'] ?? '';
        $this->api_token = $credentials['api_token'] ?? '';
        $this->base_url = rtrim($credentials['api_base_url'] ?? 'https://api.yalidine.com/v1', '/');
    }

    private function request($endpoint, $method = 'GET', $data = null) {
        $ch = curl_init();
        $url = $this->base_url . $endpoint;
        
        $headers = [
            'X-API-ID: ' . $this->api_id,
            'X-API-TOKEN: ' . $this->api_token,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return ['response' => json_decode($response, true), 'code' => $httpcode, 'error' => $error];
    }

    public function testConnection(): array {
        $result = $this->request('/histories');
        if ($result['code'] === 200) {
            return ['success' => true, 'message' => 'Connected successfully to Yalidine API.'];
        }
        return ['success' => false, 'message' => 'Failed to connect. HTTP ' . $result['code'] . ' ' . $result['error']];
    }

    public function createShipment(array $orderData): array {
        // Stub for creating a shipment
        return ['success' => false, 'tracking_number' => '', 'message' => 'Not implemented yet.', 'error' => ''];
    }

    public function getShipmentStatus(string $tracking_number): array {
        $result = $this->request('/histories?tracking=' . urlencode($tracking_number));
        if ($result['code'] === 200 && isset($result['response']['data'][0])) {
            return [
                'success' => true,
                'status' => $result['response']['data'][0]['status'] ?? 'Unknown',
                'raw_response' => $result['response']['data'][0]
            ];
        }
        return ['success' => false, 'status' => 'Unknown', 'raw_response' => $result];
    }

    public function mapStatus(string $rawStatus): string {
        // Map Yalidine statuses to our ERP statuses
        $rawStatus = strtolower(trim($rawStatus));
        switch ($rawStatus) {
            case 'delivered':
            case 'livré':
                return 'Delivered';
            case 'returned':
            case 'retourné':
                return 'Returned';
            case 'in_transit':
            case 'vers la wilaya':
            case 'reçu à la wilaya':
            case 'en préparation':
            case 'en route':
                return 'Shipped';
            case 'cancelled':
            case 'annulé':
                return 'Cancelled';
            case 'failed':
            case 'échoué':
                return 'Failed';
            default:
                return 'Processing';
        }
    }
}
?>
