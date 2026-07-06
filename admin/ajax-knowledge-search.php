<?php
require_once 'inc/config.php';

$q = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';

$sql = "SELECT k.*, c.name as category_name 
        FROM tbl_ai_knowledge k 
        LEFT JOIN tbl_ai_knowledge_categories c ON k.category_id = c.id 
        WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (k.title LIKE ? OR k.content LIKE ?)";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
}

if ($category !== '') {
    $sql .= " AND c.slug = ?";
    $params[] = $category;
}

$sql .= " ORDER BY k.priority DESC LIMIT 100";

$stmt = $dbRepo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['status' => 'success', 'data' => $results]);
