<?php
/**
 * نظام تشفير معرفات المنتجات
 * يوفر حماية إضافية للمعرفات من خلال تشفيرها
 */

class ProductEncryption {
    private static $secret_key = 'ecommerce_secret_key_2024_secure';
    private static $cipher_method = 'AES-256-CBC';
    
    /**
     * تشفير معرف المنتج
     * @param int $product_id معرف المنتج
     * @return string المعرف المشفر
     */
    public static function encrypt($product_id) {
        // إنشاء مفتاح تشفير من المفتاح السري
        $key = hash('sha256', self::$secret_key);
        
        // إنشاء متجه تهيئة عشوائي
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher_method));
        
        // تشفير المعرف
        $encrypted = openssl_encrypt($product_id, self::$cipher_method, $key, 0, $iv);
        
        // دمج المتجه مع النص المشفر وتشفير النتيجة بـ base64
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * فك تشفير معرف المنتج
     * @param string $encrypted_id المعرف المشفر
     * @return int|false معرف المنتج أو false في حالة الفشل
     */
    public static function decrypt($encrypted_id) {
        try {
            // فك تشفير base64
            $data = base64_decode($encrypted_id);
            
            if ($data === false) {
                return false;
            }
            
            // إنشاء مفتاح التشفير من المفتاح السري
            $key = hash('sha256', self::$secret_key);
            
            // استخراج متجه التهيئة
            $iv_length = openssl_cipher_iv_length(self::$cipher_method);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);
            
            // التحقق من طول متجه التهيئة
            if (strlen($iv) !== $iv_length) {
                return false;
            }
            
            // فك تشفير المعرف
            $decrypted = openssl_decrypt($encrypted, self::$cipher_method, $key, 0, $iv);
            
            if ($decrypted === false) {
                return false;
            }
            
            // التحقق من أن النتيجة رقم صحيح
            $product_id = intval($decrypted);
            
            if ($product_id <= 0) {
                return false;
            }
            
            return $product_id;
            
        } catch (Exception $e) {
            error_log('خطأ في فك تشفير معرف المنتج: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * التحقق من صحة المعرف المشفر
     * @param string $encrypted_id المعرف المشفر
     * @return bool true إذا كان صحيحاً، false إذا لم يكن
     */
    public static function isValid($encrypted_id) {
        return self::decrypt($encrypted_id) !== false;
    }
    
    /**
     * إنشاء رابط آمن للمنتج
     * @param int $product_id معرف المنتج
     * @param string $page الصفحة المطلوبة (افتراضي: buy-now.php)
     * @return string الرابط الآمن
     */
    public static function createSecureLink($product_id, $page = 'buy-now.php') {
        $encrypted_id = self::encrypt($product_id);
        return $page . '?id=' . urlencode($encrypted_id);
    }
    
    /**
     * الحصول على معرف المنتج من الرابط الآمن
     * @param string $encrypted_id المعرف المشفر من الرابط
     * @return int|false معرف المنتج أو false في حالة الفشل
     */
    public static function getProductIdFromLink($encrypted_id) {
        return self::decrypt($encrypted_id);
    }
}

/**
 * دوال مساعدة للاستخدام السهل
 */

/**
 * تشفير معرف المنتج
 * @param int $product_id معرف المنتج
 * @return string المعرف المشفر
 */
function encrypt_product_id($product_id) {
    return ProductEncryption::encrypt($product_id);
}

/**
 * فك تشفير معرف المنتج
 * @param string $encrypted_id المعرف المشفر
 * @return int|false معرف المنتج أو false في حالة الفشل
 */
function decrypt_product_id($encrypted_id) {
    return ProductEncryption::decrypt($encrypted_id);
}

/**
 * إنشاء رابط آمن للمنتج
 * @param int $product_id معرف المنتج
 * @param string $page الصفحة المطلوبة
 * @return string الرابط الآمن
 */
function create_secure_product_link($product_id, $page = 'buy-now.php') {
    return ProductEncryption::createSecureLink($product_id, $page);
}

/**
 * التحقق من صحة المعرف المشفر
 * @param string $encrypted_id المعرف المشفر
 * @return bool true إذا كان صحيحاً، false إذا لم يكن
 */
function is_valid_encrypted_id($encrypted_id) {
    return ProductEncryption::isValid($encrypted_id);
}
?>
