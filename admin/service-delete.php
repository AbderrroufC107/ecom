<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	$statement = $dbRepo->prepare("SELECT * FROM tbl_service WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}
?>

<?php
$statement = $dbRepo->prepare("SELECT * FROM tbl_service WHERE id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$photo = $row['photo'];
}

if($photo!='') {
	delete_local_image_file($photo, '../assets/uploads');
}

$statement = $dbRepo->prepare("DELETE FROM tbl_service WHERE id=?");
$statement->execute(array($_REQUEST['id']));

header('location: service.php');
?>
