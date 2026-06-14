<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $pdo->prepare("SELECT * FROM tbl_pixel WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}

// Delete from tbl_pixel
$statement = $pdo->prepare("DELETE FROM tbl_pixel WHERE id=?");
$statement->execute(array($_REQUEST['id']));

// Delete from tbl_product_pixel
$statement = $pdo->prepare("DELETE FROM tbl_product_pixel WHERE pixel_id=?");
$statement->execute(array($_REQUEST['id']));

header('location: pixel.php');
?>
