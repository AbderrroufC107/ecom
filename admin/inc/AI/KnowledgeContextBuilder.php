<?php
namespace AI;

use PDO;

class KnowledgeContextBuilder {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Builds the AI knowledge context based on given parameters
     * 
     * @param array $params [
     *   'product_id' => 123,
     *   'category_id' => 12, // Product category
     *   'brand_id' => 5,
     *   'language' => 'ar',
     *   'platform' => 'facebook',
     *   'task_type' => 'FAQ' // Or any specific type of generation task
     * ]
     * @return string
     */
    public function buildContext(array $params): string {
        $language = $params['language'] ?? 'ar';
        $currentTime = date('Y-m-d H:i:s');
        
        $knowledgeItems = [];

        // Base Query targeting active knowledge with valid dates
        $baseQuery = "
            SELECT k.title, k.knowledge_type, k.content, k.priority 
            FROM tbl_ai_knowledge k
            WHERE k.is_active = 1 
            AND k.language = ?
            AND (k.valid_from IS NULL OR k.valid_from <= ?)
            AND (k.valid_until IS NULL OR k.valid_until >= ?)
        ";

        $baseParams = [$language, $currentTime, $currentTime];

        // 1. Fetch Global Company Knowledge (High priority usually, but specific overrides exist)
        // Let's assume categories with slugs 'company', 'sales', 'shipping', 'returns', 'payments' are global if no relations exist.
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
            SELECT k.title, k.knowledge_type, k.content, k.priority 
            FROM tbl_ai_knowledge k
            LEFT JOIN tbl_ai_knowledge_relations kr ON k.id = kr.knowledge_id
            WHERE k.is_active = 1 
            AND k.language = ?
            AND (k.valid_from IS NULL OR k.valid_from <= ?)
            AND (k.valid_until IS NULL OR k.valid_until >= ?)
            AND kr.id IS NULL -- Means it's global, not tied to a specific entity
            ORDER BY k.priority DESC
        ");
        $stmt->execute($baseParams);
        $globalKnowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($globalKnowledge as $k) {
            $knowledgeItems[$k['title']] = $k;
        }

        // 2. Fetch Specific Entity Knowledge
        $entitiesToSearch = [];
        if (!empty($params['product_id'])) {
            $entitiesToSearch[] = ['type' => 'product', 'id' => $params['product_id']];
        }
        if (!empty($params['category_id'])) {
            $entitiesToSearch[] = ['type' => 'category', 'id' => $params['category_id']];
        }
        if (!empty($params['brand_id'])) {
            $entitiesToSearch[] = ['type' => 'brand', 'id' => $params['brand_id']];
        }
        if (!empty($params['platform'])) {
            $entitiesToSearch[] = ['type' => 'platform', 'id' => 0, 'platform_name' => $params['platform']]; // Platform can be handled via tags or custom relation logic
        }

        foreach ($entitiesToSearch as $entity) {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                SELECT k.title, k.knowledge_type, k.content, k.priority 
                FROM tbl_ai_knowledge k
                JOIN tbl_ai_knowledge_relations kr ON k.id = kr.knowledge_id
                WHERE k.is_active = 1 
                AND k.language = ?
                AND (k.valid_from IS NULL OR k.valid_from <= ?)
                AND (k.valid_until IS NULL OR k.valid_until >= ?)
                AND kr.entity_type = ? AND kr.entity_id = ?
                ORDER BY k.priority DESC
            ");
            $paramsArray = array_merge($baseParams, [$entity['type'], $entity['id']]);
            $stmt->execute($paramsArray);
            $entityKnowledge = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Entity specific knowledge overwrites global if titles match, or just appends
            foreach ($entityKnowledge as $k) {
                // If there's a priority conflict or identical title, higher priority wins
                if (isset($knowledgeItems[$k['title']])) {
                    if ($k['priority'] > $knowledgeItems[$k['title']]['priority']) {
                        $knowledgeItems[$k['title']] = $k;
                    }
                } else {
                    $knowledgeItems[$k['title']] = $k;
                }
            }
        }

        // 3. Filter by Task Type if necessary (e.g. if task_type is FAQ, maybe we emphasize FAQ knowledge, but generally we feed everything relevant)
        
        // 4. Build Context String
        $contextStr = "### COMPANY & PRODUCT KNOWLEDGE HUB ###\n";
        $contextStr .= "Use the following rules, policies, and information as the absolute truth for this task.\n\n";

        // Sort by priority before outputting
        usort($knowledgeItems, function($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        foreach ($knowledgeItems as $item) {
            $contextStr .= "--- " . strtoupper($item['knowledge_type']) . ": " . $item['title'] . " ---\n";
            $contextStr .= $item['content'] . "\n\n";
        }

        return $contextStr;
    }
}
