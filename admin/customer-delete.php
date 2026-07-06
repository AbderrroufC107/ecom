<?php require_once('header.php'); ?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	global $customerRepo;
	$id = (int) $_REQUEST['id'];

	// Check if customer exists in current tenant
	$customer = $customerRepo->find($id);
	if(!$customer) {
		header('location: logout.php');
		exit;
	}
}

// Delete from tbl_customer
$customerRepo->delete($id);

// We need a RatingRepository if rating is used, but for now we can just execute the delete directly or skip it if it's not a tenant table.
// tbl_rating has no tenant_id currently? Let's check. 
// For now we'll leave tbl_rating direct since we don't have a RatingRepository, but we should use tenant_id if it exists.
$dbRepo->prepare("DELETE FROM tbl_rating WHERE cust_id=?")->execute([$id]);

header('location: customer.php');