<?php
namespace AI\Providers;

use AI\AiProviderInterface;
use Exception;

class OpenAiProvider implements AiProviderInterface {
    private $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function getName() {
        return 'OpenAI';
    }

    public function generate(string $prompt, string $model, array $options = []) {
        $apiKey = $this->config['api_key'] ?? '';
        if (!$apiKey) throw new Exception("OpenAI API key missing");

        $url = rtrim($this->config['base_url'] ?: 'https://api.openai.com/v1', '/') . '/chat/completions';

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an AI assistant. Output JSON strictly when requested.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => $options['max_tokens'] ?? 2000,
            'temperature' => $options['temperature'] ?? 0.7,
            'response_format' => ['type' => 'json_object']
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
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
            throw new Exception("OpenAI API Error ($httpCode): " . $response);
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? '';
        
        // Very rough cost estimate for gpt-4o
        $pTokens = $result['usage']['prompt_tokens'] ?? 0;
        $cTokens = $result['usage']['completion_tokens'] ?? 0;
        $cost = ($pTokens * 0.005 / 1000) + ($cTokens * 0.015 / 1000);

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
