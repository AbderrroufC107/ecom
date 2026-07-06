<?php
/**
 * Interface DeliveryIntegrationInterface
 * Ensures all delivery company API adapters have a unified standard.
 */
interface DeliveryIntegrationInterface {
    /**
     * Set the credentials required by the API.
     */
    public function setCredentials(array $credentials);

    /**
     * Test the connection to the API.
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;

    /**
     * Create a shipment in the delivery company system.
     * @param array $orderData
     * @return array ['success' => bool, 'tracking_number' => string, 'message' => string, 'error' => string]
     */
    public function createShipment(array $orderData): array;

    /**
     * Get the latest status of a shipment.
     * @param string $tracking_number
     * @return array ['success' => bool, 'status' => string, 'raw_response' => array]
     */
    public function getShipmentStatus(string $tracking_number): array;

    /**
     * Map the company's specific status to the ERP standard status.
     * @param string $rawStatus
     * @return string (e.g. 'Delivered', 'Returned', 'Processing', 'Cancelled')
     */
    public function mapStatus(string $rawStatus): string;
}
?>
