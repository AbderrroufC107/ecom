<?php require_once('header.php'); ?>

<?php
if (!isset($_REQUEST['id']) || !isset($_REQUEST['id1'])) {
    header('location: logout.php');
    exit;
}

$photo_id = (int)$_REQUEST['id'];
$product_id = (int)$_REQUEST['id1'];

$statement = $pdo->prepare("SELECT photo, p_id FROM tbl_product_photo WHERE pp_id = ? AND is_additional = 1");
$statement->execute([$photo_id]);
$row = $statement->fetch(PDO::FETCH_ASSOC);

if (!$row || (int)$row['p_id'] !== $product_id) {
    header('location: logout.php');
    exit;
}

$photo = $row['photo'] ?? '';
if ($photo !== '') {
    delete_local_image_file($photo, '../assets/uploads');
}

$statement = $pdo->prepare("DELETE FROM tbl_product_photo WHERE pp_id = ? AND is_additional = 1");
$statement->execute([$photo_id]);

header('location: product-edit.php?id=' . $product_id);
exit;
?>
