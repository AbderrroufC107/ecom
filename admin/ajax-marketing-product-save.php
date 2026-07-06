<?php
require_once 'inc/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$p_id = isset($_POST['p_id']) ? (int)$_POST['p_id'] : 0;
if (!$p_id) {
    echo json_encode(['status' => 'error', 'message' => 'معرف المنتج مفقود']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Update tbl_ai_campaign
    $dbRepo->prepare("DELETE FROM tbl_ai_campaign WHERE p_id = ?")->execute([$p_id]);
    if (!empty($_POST['camp_platforms'])) {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_campaign (p_id, platform, campaign_id, ad_id, post_id, story_id, reel_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($_POST['camp_platforms'] as $idx => $platform) {
            $platform = trim($platform);
            $c_id = trim($_POST['camp_campaign_ids'][$idx] ?? '');
            $a_id = trim($_POST['camp_ad_ids'][$idx] ?? '');
            $p_id_str = trim($_POST['camp_post_ids'][$idx] ?? '');
            $s_id = trim($_POST['camp_story_ids'][$idx] ?? '');
            // We assume reel_id is the same as story_id for now, or just empty if not specified
            if ($platform !== '') {
                $stmt->execute([$p_id, $platform, $c_id, $a_id, $p_id_str, $s_id, '']);
            }
        }
    }

    // 2. Update tbl_ai_media
    $dbRepo->prepare("DELETE FROM tbl_ai_media WHERE p_id = ?")->execute([$p_id]);
    if (!empty($_POST['media_types'])) {
        $stmt = $dbRepo->prepare("INSERT INTO tbl_ai_media (p_id, media_type, media_url) VALUES (?, ?, ?)");
        foreach ($_POST['media_types'] as $idx => $mtype) {
            $mtype = trim($mtype);
            $url = trim($_POST['media_urls'][$idx] ?? '');
            if ($url !== '') {
                $stmt->execute([$p_id, $mtype, $url]);
            }
        }
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
