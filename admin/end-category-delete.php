<?php require_once('header.php'); ?>
<?php
if (!isset($_REQUEST['id'])) {
    header('location: logout.php');
    exit;
}

$end_id = (int)$_REQUEST['id'];
if ($end_id <= 0) {
    header('location: logout.php');
    exit;
}

$statement = $dbRepo->prepare("SELECT ecat_id FROM tbl_end_category WHERE ecat_id=?");
$statement->execute([$end_id]);
if ($statement->rowCount() === 0) {
    header('location: logout.php');
    exit;
}

$delete_product = static function ($pdo, $product_id) {
    $statement = $dbRepo->prepare("SELECT p_featured_photo FROM tbl_product WHERE p_id=?");
    $statement->execute([$product_id]);
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return;
    }

    $featured = $product['p_featured_photo'] ?? '';
    if ($featured !== '') {
        delete_local_image_file($featured, '../assets/uploads');
    }

    $statement = $dbRepo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=?");
    $statement->execute([$product_id]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row_photo) {
        $photo = $row_photo['photo'] ?? '';
        if ($photo !== '') {
            delete_local_image_file($photo, '../assets/uploads');
        }
    }

    $dbRepo->prepare("DELETE FROM tbl_product_photo WHERE p_id=?")->execute([$product_id]);
    $dbRepo->prepare("DELETE FROM tbl_product_size WHERE p_id=?")->execute([$product_id]);
    $dbRepo->prepare("DELETE FROM tbl_product_color WHERE p_id=?")->execute([$product_id]);
    $dbRepo->prepare("DELETE FROM tbl_rating WHERE p_id=?")->execute([$product_id]);

    $dbRepo->prepare("DELETE FROM tbl_order WHERE product_id=?")->execute([$product_id]);
    $dbRepo->prepare("DELETE FROM tbl_product WHERE p_id=?")->execute([$product_id]);
};

$statement = $dbRepo->prepare("SELECT p_id FROM tbl_product WHERE ecat_id=?");
$statement->execute([$end_id]);
$product_ids = $statement->fetchAll(PDO::FETCH_COLUMN);
foreach ($product_ids as $product_id) {
    $delete_product($pdo, (int)$product_id);
}

$dbRepo->prepare("DELETE FROM tbl_end_category WHERE ecat_id=?")->execute([$end_id]);

header('location: end-category.php');
exit;
?>
