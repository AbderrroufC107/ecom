import re

with open('C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php', 'r', encoding='utf-8') as f:
    c = f.read()

# I will add a patch inside processNextTask
# Around line 74:
old_prompt_logic = """        // Enhance prompt with payload
        $payload = json_decode($task['payload'], true);
        $finalPrompt = $promptContent . "\\n\\nData:\\n" . json_encode($payload, JSON_UNESCAPED_UNICODE);"""

new_prompt_logic = """        // Enhance prompt with payload
        $payload = json_decode($task['payload'], true);
        $finalPrompt = $promptContent . "\\n\\nData:\\n" . json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        if ($task['task_type'] === 'omni_reply') {
            require_once __DIR__ . '/KnowledgeContextBuilder.php';
            $kb = new \\KnowledgeContextBuilder($this->pdo);
            $knowledge = $kb->buildContext(null, $payload['channel_id'] ?? null, 'ar');
            
            // Get conversation history
            $convId = $task['entity_id'];
            $stmtHist = $this->pdo->prepare("SELECT sender_type, content FROM tbl_omni_timeline WHERE conversation_id = ? ORDER BY created_at ASC LIMIT 10");
            $stmtHist->execute([$convId]);
            $history = $stmtHist->fetchAll(\\PDO::FETCH_ASSOC);
            $historyStr = "";
            foreach($history as $h) {
                $historyStr .= $h['sender_type'] . ": " . $h['content'] . "\\n";
            }

            $finalPrompt = "أنت مساعد مبيعات ذكي. التزم بالقواعد التالية:\\n" . $knowledge . "\\n\\nتاريخ المحادثة:\\n" . $historyStr . "\\n\\nالرسالة الأخيرة: " . ($payload['message'] ?? '') . "\\n\\nاكتب ردك مباشرة للعميل.";
        }"""

c = c.replace(old_prompt_logic, new_prompt_logic)

# Around line 122:
old_success_logic = """        if ($success) {
            $stmtU = $this->pdo->prepare("UPDATE tbl_ai_tasks SET status = 'COMPLETED', result = ?, provider_id = ?, finished_at = NOW() WHERE id = ?");
            $stmtU->execute([$resultData, $usedProvider['id'], $task['id']]);

            $stmtM = $this->pdo->prepare("INSERT INTO tbl_ai_metrics (task_id, provider_id, model, prompt_tokens, completion_tokens, total_cost, duration_ms) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtM->execute([
                $task['id'], $usedProvider['id'], $usedProvider['model'],
                $metrics['prompt_tokens'], $metrics['completion_tokens'], $metrics['total_cost'], $metrics['duration_ms']
            ]);
        } else {"""

new_success_logic = """        if ($success) {
            $stmtU = $this->pdo->prepare("UPDATE tbl_ai_tasks SET status = 'COMPLETED', result = ?, provider_id = ?, finished_at = NOW() WHERE id = ?");
            $stmtU->execute([$resultData, $usedProvider['id'], $task['id']]);

            $stmtM = $this->pdo->prepare("INSERT INTO tbl_ai_metrics (task_id, provider_id, model, prompt_tokens, completion_tokens, total_cost, duration_ms) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtM->execute([
                $task['id'], $usedProvider['id'], $usedProvider['model'],
                $metrics['prompt_tokens'], $metrics['completion_tokens'], $metrics['total_cost'], $metrics['duration_ms']
            ]);
            
            if ($task['task_type'] === 'omni_reply') {
                $convId = $task['entity_id'];
                // Save AI reply to timeline
                $stmtT = $this->pdo->prepare("INSERT INTO tbl_omni_timeline (conversation_id, type, sender_type, content) VALUES (?, 'TEXT', 'AI', ?)");
                $stmtT->execute([$convId, $resultData]);
                
                // Send via MetaAdapter
                require_once __DIR__ . '/../Omni/Adapters/AdapterInterface.php';
                require_once __DIR__ . '/../Omni/Adapters/MetaAdapter.php';
                require_once __DIR__ . '/../Security/SecretManager.php';
                $adapter = new \\Omni\\Adapters\\MetaAdapter();
                $adapter->sendMessage($payload['channel_id'], $payload['platform_user_id'], $resultData);
            }
        } else {"""

c = c.replace(old_success_logic, new_success_logic)

with open('C:/xampp/htdocs/ecom/admin/inc/AI/AiTaskEngine.php', 'w', encoding='utf-8') as f:
    f.write(c)

print('AiTaskEngine.php patched.')
