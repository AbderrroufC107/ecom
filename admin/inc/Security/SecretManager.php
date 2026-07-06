<?php
namespace Security;

use PDO;
use Exception;

class SecretManager {
    private $pdo;
    private $key;
    private $cipher = 'aes-256-gcm';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        if (!defined('APP_SECRET_KEY')) {
            throw new Exception("APP_SECRET_KEY is not defined in config.");
        }
        // Decode the base64 key back to raw bytes for encryption
        $this->key = base64_decode(APP_SECRET_KEY);
    }

    /**
     * Encrypts a plaintext string using AES-256-GCM
     */
    private function encrypt(string $plaintext): string {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $tag = '';
        
        $ciphertext = openssl_encrypt($plaintext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        
        // Return base64 encoded format: iv::tag::ciphertext
        return base64_encode($iv . '::' . $tag . '::' . $ciphertext);
    }

    /**
     * Decrypts a ciphertext string using AES-256-GCM
     */
    private function decrypt(string $encryptedData): string {
        $decoded = base64_decode($encryptedData);
        $parts = explode('::', $decoded, 3);
        
        if (count($parts) !== 3) {
            throw new Exception("Invalid encrypted data format.");
        }
        
        $iv = $parts[0];
        $tag = $parts[1];
        $ciphertext = $parts[2];
        
        $plaintext = openssl_decrypt($ciphertext, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($plaintext === false) {
            throw new Exception("Decryption failed.");
        }
        
        return $plaintext;
    }

    /**
     * Stores a secret in the database
     */
    public function setSecret(string $secretName, string $provider, string $value): bool {
        if (empty($value)) return false;

        $encryptedValue = $this->encrypt($value);
        
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            INSERT INTO tbl_secrets (secret_name, provider, encrypted_value) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE encrypted_value = ?, updated_at = NOW()
        ");
        
        return $stmt->execute([$secretName, $provider, $encryptedValue, $encryptedValue]);
    }

    /**
     * Retrieves and decrypts a secret from the database
     */
    public function getSecret(string $secretName): ?string {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT encrypted_value FROM tbl_secrets WHERE secret_name = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$secretName]);
        $result = $stmt->fetchColumn();
        
        if (!$result) return null;
        
        try {
            return $this->decrypt($result);
        } catch (Exception $e) {
            error_log("SecretManager Decryption Error for {$secretName}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Deletes a secret (soft delete or hard delete)
     */
    public function removeSecret(string $secretName): bool {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("DELETE FROM tbl_secrets WHERE secret_name = ?");
        return $stmt->execute([$secretName]);
    }
}
