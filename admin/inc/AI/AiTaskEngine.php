<?php
namespace AI;

require_once __DIR__ . '/AiProviderInterface.php';
require_once __DIR__ . '/Providers/OpenAiProvider.php';
require_once __DIR__ . '/Providers/GeminiProvider.php';

use PDO;
use Exception;

class AiTaskEngine {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getProviders() {
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("SELECT * FROM tbl_ai_providers WHERE is_enabled = 1 ORDER BY priority DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createProviderInstance($providerData) {
        if (strpos(strtolower($providerData['name']), 'openai') !== false) {
            return new Providers\OpenAiProvider($providerData);
        }
        if (strpos(strtolower($providerData['name']), 'gemini') !== false) {
            return new Providers\GeminiProvider($providerData);
        }
        // Fallback or generic
        return null;
    }

    public function processNextTask() {
        $this->pdo->beginTransaction();

        // Find the next highest priority PENDING task
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("
            SELECT * FROM tbl_ai_tasks 
            WHERE status IN ('PENDING', 'FAILED') AND retries < 3
            ORDER BY 
                CASE priority 
                    WHEN 'URGENT' THEN 1 
                    WHEN 'HIGH' THEN 2 
                    WHEN 'NORMAL' THEN 3 
                    WHEN 'LOW' THEN 4 
                END ASC,
                created_at ASC 
            LIMIT 1 FOR UPDATE
        ");
        $task = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$task) {
            $this->pdo->rollBack();
            return false; // No tasks
        }

        // Mark as PROCESSING
        $stmtUpdate = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_ai_tasks SET status = 'PROCESSING', started_at = NOW() WHERE id = ?");
        $stmtUpdate->execute([$task['id']]);
        
        $this->pdo->commit();

        // Get Prompt Content
        $promptContent = "";
        if ($task['prompt_id']) {
            $stmtP = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT content FROM tbl_ai_prompts WHERE id = ?");
            $stmtP->execute([$task['prompt_id']]);
            $promptContent = $stmtP->fetchColumn() ?: "";
        }
        
        // Enhance prompt with payload
        $payload = json_decode($task['payload'], true);
        $finalPrompt = $promptContent . "\n\nData:\n" . json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        if ($task['task_type'] === 'omni_reply') {
            require_once __DIR__ . '/KnowledgeContextBuilder.php';
            $kb = new \AI\KnowledgeContextBuilder($this->pdo);
            $knowledge = $kb->buildContext(['platform' => 'meta', 'language' => 'ar']);
            
            // Get conversation history
            $convId = $task['entity_id'];
            $stmtHist = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT sender_type, content FROM tbl_omni_timeline WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 10");
            $stmtHist->execute([$convId]);
            $history = $stmtHist->fetchAll(\PDO::FETCH_ASSOC);
            $historyStr = "";
            foreach($history as $h) {
                $historyStr .= $h['sender_type'] . ": " . $h['content'] . "\n";
            }

            $finalPrompt = ($promptContent ?: "You are a helpful AI.") . "\n\nKNOWLEDGE BASE:\n" . $knowledge . "\n\nCONVERSATION HISTORY:\n" . $historyStr . "\n\nLATEST MESSAGE: " . ($payload['message'] ?? '') . "\n\nOUTPUT REQUIREMENT: Return a valid JSON object containing exactly one key 'reply' with your text response.";
        }

        // Try providers
        $providers = $this->getProviders();
        $success = false;
        $lastError = "";
        $usedProvider = null;
        $resultData = null;
        $metrics = [];

        foreach ($providers as $pData) {
            try {
                $provider = $this->createProviderInstance($pData);
                if (!$provider) continue;

                $startTime = microtime(true);
                $response = $provider->generate($finalPrompt, $pData['model'], [
                    'max_tokens' => $pData['max_tokens'],
                    'temperature' => $pData['temperature']
                ]);
                $endTime = microtime(true);

                $usedProvider = $pData;
                $resultData = $response['content']; // Expecting JSON string or plain text
                
                $metrics = [
                    'duration_ms' => round(($endTime - $startTime) * 1000),
                    'prompt_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $response['usage']['completion_tokens'] ?? 0,
                    'total_cost' => $response['usage']['cost'] ?? 0
                ];

                $success = true;
                break; // Stop trying if success
            } catch (Exception $e) {
                $lastError = $pData['name'] . ": " . $e->getMessage();
                // Continue to next fallback provider
            }
        }

        if ($success) {
            $stmtU = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_ai_tasks SET status = 'COMPLETED', result = ?, provider_id = ?, finished_at = NOW() WHERE id = ?");
            $stmtU->execute([$resultData, $usedProvider['id'], $task['id']]);

            $stmtM = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("INSERT INTO tbl_ai_metrics (task_id, provider_id, model, prompt_tokens, completion_tokens, total_cost, duration_ms) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtM->execute([
                $task['id'], $usedProvider['id'], $usedProvider['model'],
                $metrics['prompt_tokens'], $metrics['completion_tokens'], $metrics['total_cost'], $metrics['duration_ms']
            ]);
            
            if ($task['task_type'] === 'omni_reply') {
                $convId = $task['entity_id'];
                // Save AI reply to timeline
                $stmtT = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("INSERT INTO tbl_omni_timeline (conversation_id, type, sender_type, content) VALUES (?, 'TEXT', 'AI', ?)");
                $stmtT->execute([$convId, $resultData]);
                
                // Send via MetaAdapter
                require_once __DIR__ . '/../Omni/Adapters/AdapterInterface.php';
                require_once __DIR__ . '/../Omni/Adapters/MetaAdapter.php';
                require_once __DIR__ . '/../Security/SecretManager.php';
                $adapter = new \Omni\Adapters\MetaAdapter();
                                $parsedJSON = json_decode($resultData, true);
                if (is_array($parsedJSON) && isset($parsedJSON['reply'])) {
                    $finalReply = $parsedJSON['reply'];
                } else {
                    $finalReply = $resultData; // Fallback
                }
                $adapter->sendMessage($payload['channel_id'], $payload['platform_user_id'], $finalReply);
            }
        } else {
            $newRetries = $task['retries'] + 1;
            $newStatus = ($newRetries >= 3) ? 'FAILED' : 'PENDING';
            $stmtU = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("UPDATE tbl_ai_tasks SET status = ?, retries = ?, error_message = ? WHERE id = ?");
            $stmtU->execute([$newStatus, $newRetries, $lastError, $task['id']]);
        }

        return true;
    }
}
