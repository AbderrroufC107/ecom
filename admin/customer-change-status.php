<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	global $customerRepo;
	$id = (int) $_REQUEST['id'];
	
	// Check if customer belongs to current tenant
	$customer = $customerRepo->find($id);
	if(!$customer) {
		header('location: logout.php');
		exit;
	}

	$cust_status = $customer['cust_status'];
}
?>

<?php
$final = ($cust_status == 0) ? 1 : 0;
$customerRepo->update($id, ['cust_status' => $final]);

header('location: customer.php');
?>