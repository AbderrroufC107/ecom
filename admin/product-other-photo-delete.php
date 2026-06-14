<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once('inc/config.php');
require_once('inc/functions.php');

if (!isset($_SESSION['user'])) {
    header('location: login.php');
    exit;
}

$photo_id = (int)($_REQUEST['id'] ?? $_REQUEST['photo_id'] ?? $_REQUEST['pp_id'] ?? 0);
$product_id = (int)($_REQUEST['id1'] ?? $_REQUEST['product_id'] ?? 0);

if ($product_id <= 0) {
    header('location: product.php');
    exit;
}

if ($photo_id <= 0) {
    header('location: product-edit.php?id=' . $product_id);
    exit;
}

$statement = $pdo->prepare("SELECT pp_id, photo, p_id FROM tbl_product_photo WHERE pp_id=?");
$statement->execute([$photo_id]);
$row = $statement->fetch(PDO::FETCH_ASSOC);
if (!$row || (int)$row['p_id'] !== $product_id) {
    header('location: product-edit.php?id=' . $product_id);
    exit;
}

$photo = $row['photo'] ?? '';
if ($photo !== '') {
    delete_local_image_file($photo, '../assets/uploads');
}

$statement = $pdo->prepare("DELETE FROM tbl_product_photo WHERE pp_id=?");
$statement->execute([$photo_id]);

header('location: product-edit.php?id=' . $product_id);
exit;
?>
