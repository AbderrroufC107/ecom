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
            // A real customer must never receive a fabricated "yes, it's in stock" reply
            // just because the key isn't configured. Fail loudly so AiTaskEngine's provider
            // loop moves on (or marks the task FAILED for an admin to notice), the same way
            // OpenAiProvider already behaves.
            throw new Exception('Gemini API key missing');
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

    /**
     * Freeform conversational chat (plain text, not JSON).
     * Used by the admin AI assistant.
     *
     * @param string $userMessage  The latest admin message.
     * @param array  $history      Prior turns: [['role'=>'user'|'assistant','content'=>string], ...]
     * @param string $systemPrompt System instruction / persona + context.
     * @param array  $options       temperature, max_tokens overrides.
     * @return array ['content'=>string, 'usage'=>[...]]
     */
    public function chat(string $userMessage, array $history = [], string $systemPrompt = '', array $options = []) {
        $apiKey = $this->config['api_key'] ?? '';
        $model  = $options['model'] ?? ($this->config['model'] ?? 'gemini-2.5-flash');

        if (!$apiKey) {
            // Mock response so the UI stays usable before a real key is added.
            return [
                'content' => "⚠️ لم يتم ضبط مفتاح Gemini بعد. أضف المفتاح من صفحة «إدارة العملاء الآليين» ليعمل المساعد فعلياً.\n\n(هذا رد تجريبي) استلمتُ رسالتك: «" . mb_substr($userMessage, 0, 200) . "»",
                'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'cost' => 0.0],
            ];
        }

        $url = rtrim($this->config['base_url'] ?: 'https://generativelanguage.googleapis.com/v1beta/models', '/');
        $url .= '/' . $model . ':generateContent?key=' . $apiKey;

        // Build multi-turn contents. Gemini roles are 'user' and 'model'.
        $contents = [];
        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $text = trim((string)($turn['content'] ?? ''));
            if ($text === '') continue;
            $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

        $genConfig = [
            'temperature'     => (float)($options['temperature'] ?? ($this->config['temperature'] ?? 0.7)),
            'maxOutputTokens' => (int)($options['max_tokens'] ?? ($this->config['max_tokens'] ?? 2000)),
        ];
        // 2.5-series models "think" and can spend the whole token budget on hidden
        // reasoning, returning empty text. Disable thinking for direct chat replies.
        if (stripos($model, '2.5') !== false) {
            $genConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        $data = [
            'contents' => $contents,
            'generationConfig' => $genConfig,
        ];
        if ($systemPrompt !== '') {
            $data['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("تعذّر الاتصال بالشبكة: " . $error);
        }
        if ($httpCode >= 400) {
            $err  = json_decode($response, true);
            $gmsg = $err['error']['message'] ?? $response;
            // Friendly, short messages for the common cases.
            if ($httpCode === 429) {
                $retry = '';
                foreach (($err['error']['details'] ?? []) as $d) {
                    if (($d['@type'] ?? '') === 'type.googleapis.com/google.rpc.RetryInfo' && !empty($d['retryDelay'])) {
                        $retry = ' (أعد المحاولة بعد ' . $d['retryDelay'] . ')';
                    }
                }
                throw new Exception('تجاوزت الحصة المجانية لنموذج «' . $model . '» على مشروع مفتاحك (429).' . $retry
                    . ' جرّب نموذجاً آخر مثل gemini-2.5-flash، أو انتظر قليلاً، أو فعّل الفوترة على المشروع.');
            }
            if ($httpCode === 400 && stripos($gmsg, 'API key') !== false) {
                throw new Exception('مفتاح API غير صالح. تحقق من المفتاح في صفحة إدارة العملاء الآليين.');
            }
            if ($httpCode === 404) {
                throw new Exception('النموذج «' . $model . '» غير موجود/غير متاح لمفتاحك. جرّب gemini-2.5-flash أو gemini-2.0-flash.');
            }
            throw new Exception('خطأ من Gemini (' . $httpCode . '): ' . mb_substr((string)$gmsg, 0, 300));
        }

        $result  = json_decode($response, true);
        $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($content === '') {
            $blockReason = $result['promptFeedback']['blockReason'] ?? '';
            $content = $blockReason
                ? "تعذّر توليد رد (سبب الحجب: {$blockReason})."
                : 'تعذّر توليد رد. حاول إعادة صياغة سؤالك.';
        }

        $pTokens = $result['usageMetadata']['promptTokenCount'] ?? 0;
        $cTokens = $result['usageMetadata']['candidatesTokenCount'] ?? 0;
        $cost    = ($pTokens * 0.000125 / 1000) + ($cTokens * 0.000375 / 1000);

        return [
            'content' => $content,
            'usage'   => ['prompt_tokens' => $pTokens, 'completion_tokens' => $cTokens, 'cost' => $cost],
        ];
    }
}
