<?php
namespace AI\Providers;

use AI\AiProviderInterface;
use Exception;

class GeminiProvider implements AiProviderInterface {
    private $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function getName() {
        return 'Gemini';
    }

    public function generate(string $prompt, string $model, array $options = []) {
        $apiKey = $this->config['api_key'] ?? '';
        if (!$apiKey) {
            // MOCK RESPONSE FOR TESTING PURPOSES WHEN NO API KEY IS PROVIDED
            return [
                'content' => json_encode(['text' => 'مرحباً بك! نعم، السويت شيرت متوفر باللون الأسود ومقاس لارج. يمكنك إتمام الطلب الآن.']),
                'usage' => [
                    'prompt_tokens' => 50,
                    'completion_tokens' => 20,
                    'cost' => 0.0001
                ]
            ];
        }

        $url = rtrim($this->config['base_url'] ?: 'https://generativelanguage.googleapis.com/v1beta/models', '/');
        $url .= '/' . $model . ':generateContent?key=' . $apiKey;

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'You are an AI assistant. Output JSON strictly when requested.']
                    ]
                ],
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => (float)($options['temperature'] ?? 0.7),
                'maxOutputTokens' => (int)($options['max_tokens'] ?? 2000),
                'responseMimeType' => 'application/json'
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("CURL Error: " . $error);
        }

        if ($httpCode >= 400) {
            throw new Exception("Gemini API Error ($httpCode): " . $response);
        }

        $result = json_decode($response, true);
        $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        // Very rough cost estimate
        $pTokens = $result['usageMetadata']['promptTokenCount'] ?? 0;
        $cTokens = $result['usageMetadata']['candidatesTokenCount'] ?? 0;
        $cost = ($pTokens * 0.000125 / 1000) + ($cTokens * 0.000375 / 1000);

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $pTokens,
                'completion_tokens' => $cTokens,
                'cost' => $cost
            ]
        ];
    }

    public function isHealthy(): bool {
        return !empty($this->config['api_key']);
    }
}
