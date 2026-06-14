<?php require_once('header.php'); ?>

<?php
if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
	header('location: product.php?msg=invalid');
	exit;
}
$id = (int)$_REQUEST['id'];
if ($id <= 0) {
	header('location: product.php?msg=invalid');
	exit;
}

// Check the product exists
$statement = $pdo->prepare("SELECT p_featured_photo FROM tbl_product WHERE p_id=?");
$statement->execute([$id]);
$product = $statement->fetch(PDO::FETCH_ASSOC);
if (!$product) {
	header('location: product.php?msg=not_found');
	exit;
}
?>

<?php
	// Remove featured photo if exists (local files only)
	$p_featured_photo = $product['p_featured_photo'] ?? '';
	if (!empty($p_featured_photo)) {
		delete_local_image_file($p_featured_photo, '../assets/uploads');
	}

	// Remove additional/color photos if exist (local files only)
	$statement = $pdo->prepare("SELECT photo FROM tbl_product_photo WHERE p_id=?");
	$statement->execute([$id]);
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	foreach ($result as $row) {
		$photo = $row['photo'] ?? '';
		if (empty($photo)) {
			continue;
		}
		delete_local_image_file($photo, '../assets/uploads');
	}

	// Delete from tbl_product
	$statement = $pdo->prepare("DELETE FROM tbl_product WHERE p_id=?");
	$statement->execute([$id]);

	// Delete from tbl_product_photo
	$statement = $pdo->prepare("DELETE FROM tbl_product_photo WHERE p_id=?");
	$statement->execute([$id]);

	// Delete from tbl_product_size
	$statement = $pdo->prepare("DELETE FROM tbl_product_size WHERE p_id=?");
	$statement->execute([$id]);

	// Delete from tbl_product_color
	$statement = $pdo->prepare("DELETE FROM tbl_product_color WHERE p_id=?");
	$statement->execute([$id]);

	// Delete from tbl_rating
	$statement = $pdo->prepare("DELETE FROM tbl_rating WHERE p_id=?");
	$statement->execute([$id]);

	// Delete from tbl_order
	$statement = $pdo->prepare("DELETE FROM tbl_order WHERE product_id=?");
	$statement->execute([$id]);

	header('location: product.php?msg=deleted');
?>
