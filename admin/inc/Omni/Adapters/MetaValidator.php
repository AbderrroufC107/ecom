<?php
namespace Omni\Adapters;

use Exception;

class MetaValidator {
    
    /**
     * Validates a Meta Page Access Token via the Graph API /debug_token
     * Requires the Facebook App ID and App Secret (or a valid app access token).
     * 
     * @param string $inputToken The token to validate (Page Access Token)
     * @param string $appAccessToken The app access token (app_id|app_secret)
     * @return array Status array with isValid, scopes, expiresAt, and errors
     */
    public function validateToken(string $inputToken, string $appAccessToken): array {
        $url = "https://graph.facebook.com/v19.0/debug_token?input_token={$inputToken}&access_token={$appAccessToken}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if ($httpCode !== 200 || isset($data['error'])) {
            return [
                'is_valid' => false,
                'error' => $data['error']['message'] ?? 'Unknown Error'
            ];
        }

        $info = $data['data'] ?? [];
        
        $isValid = $info['is_valid'] ?? false;
        $scopes = $info['scopes'] ?? [];
        $expiresAt = $info['data_access_expires_at'] ?? null;
        
        // Required Permissions for full OmniChannel
        $requiredScopes = ['pages_messaging', 'pages_manage_metadata', 'pages_read_engagement', 'pages_show_list'];
        $missingScopes = array_diff($requiredScopes, $scopes);

        return [
            'is_valid' => $isValid,
            'missing_scopes' => array_values($missingScopes),
            'expires_at' => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : 'Never',
            'app_id' => $info['app_id'] ?? null,
            'page_id' => $info['profile_id'] ?? null
        ];
    }
}
