<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id']) || !ctype_digit((string) $_REQUEST['id'])) {
	header('location: pixel.php');
	exit;
} else {
	// Check the id is valid or not
	$statement = $dbRepo->prepare("SELECT * FROM tbl_pixel WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: pixel.php');
		exit;
	}
}

// Delete from tbl_pixel
$statement = $dbRepo->prepare("DELETE FROM tbl_pixel WHERE id=?");
$statement->execute(array($_REQUEST['id']));

// Delete from tbl_product_pixel
$statement = $dbRepo->prepare("DELETE FROM tbl_product_pixel WHERE pixel_id=?");
$statement->execute(array($_REQUEST['id']));

header('location: pixel.php');
?>
