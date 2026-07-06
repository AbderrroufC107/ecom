<?php
require_once 'inc/config.php';
require_once 'inc/Omni/EventLogger.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eventId = (int)$_POST['event_id'];
    if ($eventId) {
        $logger = new \Omni\EventLogger($pdo);
        try {
            $logger->replay($eventId);
            $_SESSION['success'] = "Event #{$eventId} replayed successfully!";
        } catch (Exception $e) {
            $_SESSION['error'] = "Failed to replay event: " . $e->getMessage();
        }
    }
}
header('Location: omni-meta-monitor.php');
exit;
