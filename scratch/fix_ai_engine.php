<?php
require 'C:/xampp/htdocs/ecom/admin/inc/config.php';

$file = 'C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php';
$content = file_get_contents($file);

// Fix 1: No Business Logic in Prompt / Context from Knowledge Hub only
$bad_prompt_logic = '$finalPrompt = "أنت مساعد مبيعات ذكي. التزم بالقواعد التالية:\n" . $knowledge . "\n\nتاريخ المحادثة:\n" . $historyStr . "\n\nالرسالة الأخيرة: " . ($payload[\'message\'] ?? \'\') . "\n\nاكتب ردك مباشرة للعميل.";';

$good_prompt_logic = '$finalPrompt = ($promptContent ?: "You are a helpful AI.") . "\n\nKNOWLEDGE BASE:\n" . $knowledge . "\n\nCONVERSATION HISTORY:\n" . $historyStr . "\n\nLATEST MESSAGE: " . ($payload[\'message\'] ?? \'\') . "\n\nOUTPUT REQUIREMENT: Return a valid JSON object containing exactly one key \'reply\' with your text response.";';

$content = str_replace($bad_prompt_logic, $good_prompt_logic, $content);

// Fix 2: Structured JSON parsing
$send_message_bad = '$adapter->sendMessage($payload[\'channel_id\'], $payload[\'platform_user_id\'], $resultData);';

$send_message_good = <<<'EOD'
                $parsedJSON = json_decode($resultData, true);
                if (is_array($parsedJSON) && isset($parsedJSON['reply'])) {
                    $finalReply = $parsedJSON['reply'];
                } else {
                    $finalReply = $resultData; // Fallback
                }
                $adapter->sendMessage($payload['channel_id'], $payload['platform_user_id'], $finalReply);
EOD;

$content = str_replace($send_message_bad, $send_message_good, $content);

file_put_contents($file, $content);
echo "AiTaskEngine fixed: Removed business logic from prompt, enforced structured JSON output, integrated Knowledge Hub correctly.\n";
