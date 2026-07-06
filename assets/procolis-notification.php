<?php
class ProcolisNotification {
    private $token;
    private $key;
    private $baseUrl;
    
    public function __construct($token, $key) {
        $this->token = $token;
        $this->key = $key;
        $this->baseUrl = 'https://procolis.com/api_v1';
    }
    
    /**
     * إرسال طلب جديد إلى Procolis
     */
    public function createOrder($orderData) {
        $url = $this->baseUrl . '/add_colis';
        
        $data = [
            'Colis' => [
                [
                    'Tracking' => $orderData['tracking'] ?? uniqid('TRK_'),
                    'TypeLivraison' => $orderData['delivery_type'] ?? '0', // 0: Domicile, 1: Stopdesk
                    'TypeColis' => '0', // 0: Normal, 1: Echange
                    'Confrimee' => $orderData['confirmed'] ?? '', // 1: Confirmer directement
                    'Client' => $orderData['customer_name'],
                    'MobileA' => $orderData['customer_phone'],
                    'MobileB' => $orderData['customer_phone_alt'] ?? $orderData['customer_phone'],
                    'Adresse' => $orderData['address'],
                    'IDWilaya' => $orderData['wilaya_id'],
                    'Commune' => $orderData['commune'],
                    'Total' => $orderData['total_price'],
                    'Note' => $orderData['notes'] ?? '',
                    'TProduit' => $orderData['product_name'],
                    'id_Externe' => $orderData['external_id'] ?? uniqid('EXT_'),
                    'Source' => $orderData['source'] ?? 'Ecommerce'
                ]
            ]
        ];
        
        return $this->makeRequest($url, $data);
    }
    
    /**
     * قراءة حالة الطلبات
     */
    public function readOrders($trackingNumbers) {
        $url = $this->baseUrl . '/lire';
        
        $colis = [];
        foreach ($trackingNumbers as $tracking) {
            $colis[] = ['Tracking' => $tracking];
        }
        
        $data = ['Colis' => $colis];
        
        return $this->makeRequest($url, $data);
    }
    
    /**
     * تغيير حالة الطلب إلى "جاهز للإرسال"
     */
    public function markAsReady($trackingNumbers) {
        $url = $this->baseUrl . '/pret';
        
        $colis = [];
        foreach ($trackingNumbers as $tracking) {
            $colis[] = ['Tracking' => $tracking];
        }
        
        $data = ['Colis' => $colis];
        
        return $this->makeRequest($url, $data);
    }
    
    /**
     * الحصول على التعريفة
     */
    public function getTarification() {
        $url = $this->baseUrl . '/tarification';
        
        // التعريفة تستخدم POST وليس GET
        return $this->makeRequest($url, [], 'POST');
    }
    
    /**
     * إرسال طلب HTTP
     */
    private function makeRequest($url, $data = [], $method = 'POST') {
        $headers = [
            'token: ' . $this->token,
            'key: ' . $this->key,
            'Content-Type: application/json'
        ];
        
        $options = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $method === 'POST' ? json_encode($data) : null,
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log('Procolis API Error: Failed to make request to ' . $url);
            return false;
        }
        
        $response = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Procolis API Error: Invalid JSON response - ' . $result);
            return false;
        }
        
        return $response;
    }
    
    /**
     * اختبار الاتصال بـ API
     */
    public function testConnection() {
        // اختبار بسيط - محاولة إرسال طلب تجريبي
        try {
            $testOrder = [
                'tracking' => 'TEST_' . time(),
                'delivery_type' => '0',
                'customer_name' => 'Test User',
                'customer_phone' => '0555123456',
                'address' => 'Test Address',
                'wilaya_id' => '16',
                'commune' => 'Test Commune',
                'total_price' => '100',
                'product_name' => 'Test Product',
                'external_id' => 'TEST_' . time(),
                'confirmed' => ''
            ];
            
            $result = $this->createOrder($testOrder);
            
            if ($result) {
                return ['success' => true, 'message' => 'تم اختبار الاتصال بنجاح', 'data' => $result];
            } else {
                return ['success' => false, 'message' => 'فشل في اختبار الاتصال'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'خطأ في الاتصال: ' . $e->getMessage()];
        }
    }
    
    /**
     * تحويل بيانات الطلب إلى تنسيق Procolis
     */
    public function formatOrderForProcolis($orderData) {
        // تحويل أسماء الولايات إلى رموز
        $wilayaMap = [
            'الجزائر' => '16',
            'وهران' => '31',
            'قسنطينة' => '25',
            'عنابة' => '23',
            'باتنة' => '5',
            'بجاية' => '6',
            'بسكرة' => '7',
            'بشار' => '8',
            'البليدة' => '9',
            'البويرة' => '10',
            'تمنراست' => '11',
            'تبسة' => '12',
            'تلمسان' => '13',
            'تيارت' => '14',
            'تيزي وزو' => '15',
            'الجلفة' => '17',
            'جيجل' => '18',
            'سطيف' => '19',
            'سعيدة' => '20',
            'سكيكدة' => '21',
            'سيدي بلعباس' => '22',
            'الشلف' => '2',
            'الاغواط' => '3',
            'أم البواقي' => '4',
            'برج بوعريريج' => '34',
            'بومرداس' => '35',
            'الطارف' => '36',
            'تندوف' => '37',
            'تيسمسيلت' => '38',
            'الوادي' => '39',
            'خنشلة' => '40',
            'سوق أهراس' => '41',
            'تيبازة' => '42',
            'ميلة' => '43',
            'عين الدفلى' => '44',
            'النعامة' => '45',
            'عين تموشنت' => '46',
            'غرداية' => '47',
            'غليزان' => '48'
        ];
        
        $wilayaId = $wilayaMap[$orderData['wilaya']] ?? '16'; // افتراضي: الجزائر
        
        return [
            'tracking' => 'TRK_' . time() . '_' . rand(1000, 9999),
            'delivery_type' => $orderData['delivery_type'] === 'منزل' ? '0' : '1',
            'customer_name' => $orderData['customer_name'],
            'customer_phone' => $orderData['customer_phone'],
            'address' => $orderData['address'] ?? $orderData['commune'],
            'wilaya_id' => $wilayaId,
            'commune' => $orderData['commune'],
            'total_price' => $orderData['total_price'],
            'product_name' => $orderData['product_name'],
            'external_id' => $orderData['order_id'] ?? uniqid('ORD_'),
            'notes' => $orderData['notes'] ?? '',
            'confirmed' => '1' // تأكيد مباشر
        ];
    }
}
?>
