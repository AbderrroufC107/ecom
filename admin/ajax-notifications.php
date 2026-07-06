<?php
session_start();
require_once('inc/config.php');

if (!isset($_SESSION['user'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$user_id = (int)$_SESSION['user']['id'];

if (isset($_POST['action']) && $_POST['action'] == 'mark_read') {
    $stmt = $dbRepo->prepare("UPDATE tbl_notification SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    echo json_encode(["status" => "success"]);
    exit;
}

// Fetch unread notifications
$stmt = $dbRepo->prepare("SELECT * FROM tbl_notification WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user settings
$stmt = $dbRepo->prepare("SELECT notification_sound, polling_interval FROM tbl_user WHERE id = ?");
$stmt->execute([$user_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    "status" => "success",
    "count" => count($notifications),
    "notifications" => $notifications,
    "sound" => $settings['notification_sound'] ?? 1,
    "interval" => $settings['polling_interval'] ?? 30
]);
