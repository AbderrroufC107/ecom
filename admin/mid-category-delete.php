<?php require_once('header.php'); ?>
<?php
if (!isset($_REQUEST['id'])) {
    header('location: logout.php');
    exit;
}

$mid_id = (int)$_REQUEST['id'];
if ($mid_id <= 0) {
    header('location: logout.php');
    exit;
}

$statement = $pdo->prepare("SELECT mcat_id FROM tbl_mid_category WHERE mcat_id=?");
$statement->execute([$mid_id]);
if ($statement->rowCount() === 0) {
    header('location: logout.php');
    exit;
}

$delete_product = static function ($pdo, $product_id) {
    $statement = $pdo->prepare("SELECT p_featured_photo FROM tbl_product WHERE p_id=?");
    $statement->execute([$product_id]);
    $product = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return;
    }

    $featured = $product['p_featured_photo'] ?? '';
    if ($featured !== '') {
        delete_local_image_file($featured, '../assets/uploads');
    }

    $statement = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=?");
    $statement->execute([$product_id]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row_photo) {
        $photo = $row_photo['photo'] ?? '';
        if ($photo !== '') {
            delete_local_image_file($photo, '../assets/uploads');
        }
    }

    $pdo->prepare("DELETE FROM tbl_product_photo WHERE p_id=?")->execute([$product_id]);
    $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?")->execute([$product_id]);
    $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?")->execute([$product_id]);
    $pdo->prepare("DELETE FROM tbl_rating WHERE p_id=?")->execute([$product_id]);

    $pdo->prepare("DELETE FROM tbl_order WHERE product_id=?")->execute([$product_id]);
    $pdo->prepare("DELETE FROM tbl_product WHERE p_id=?")->execute([$product_id]);
};

$statement = $pdo->prepare("SELECT p.p_id
    FROM tbl_product p
    JOIN tbl_end_category e ON p.ecat_id = e.ecat_id
    WHERE e.mcat_id=?");
$statement->execute([$mid_id]);
$product_ids = $statement->fetchAll(PDO::FETCH_COLUMN);
foreach ($product_ids as $product_id) {
    $delete_product($pdo, (int)$product_id);
}

$statement = $pdo->prepare("SELECT ecat_id FROM tbl_end_category WHERE mcat_id=?");
$statement->execute([$mid_id]);
$ecat_ids = $statement->fetchAll(PDO::FETCH_COLUMN);
foreach ($ecat_ids as $ecat_id) {
    $pdo->prepare("DELETE FROM tbl_end_category WHERE ecat_id=?")->execute([(int)$ecat_id]);
}

$pdo->prepare("DELETE FROM tbl_mid_category WHERE mcat_id=?")->execute([$mid_id]);

header('location: mid-category.php');
exit;
?>
