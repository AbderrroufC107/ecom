<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;
	$current_photo = $_POST['previous_photo'] ?? '';
	$remove_photo = !empty($_POST['remove_photo']);

	if(empty($_POST['caption'])) {
        $valid = 0;
        $error_message .= "Photo Caption Name can not be empty<br>";
    }

	list($image_ok, $new_image_value) = store_image_input(
		'photo',
		'photo_url',
		'photo-'.$_REQUEST['id'],
		'../assets/uploads',
		$error_message,
		false
	);

	if(!$image_ok) {
		$valid = 0;
	}

    if($valid == 1) {
		$final_photo = $current_photo;
		if($new_image_value !== '') {
			$final_photo = $new_image_value;
			if($final_photo !== $current_photo) {
				delete_local_image_file($current_photo, '../assets/uploads');
			}
		} elseif($remove_photo) {
			delete_local_image_file($current_photo, '../assets/uploads');
			$final_photo = '';
		}

		$statement = $dbRepo->prepare("UPDATE tbl_photo SET caption=?, photo=? WHERE id=?");
		$statement->execute(array($_POST['caption'],$final_photo,$_REQUEST['id']));

    	$success_message = 'Photo is updated successfully.';
    }
}
?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	$statement = $dbRepo->prepare("SELECT * FROM tbl_photo WHERE id=?");
	$statement->execute(array($_REQUEST['id']));
	$total = $statement->rowCount();
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	if( $total == 0 ) {
		header('location: logout.php');
		exit;
	}
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Photo</h1>
	</div>
	<div class="content-header-right">
		<a href="photo.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<?php
foreach ($result as $row) {
	$caption = $row['caption'];
	$photo = $row['photo'];
}
?>

<section class="content">

	<div class="row">
		<div class="col-md-12">

			<?php if($error_message): ?>
			<div class="callout callout-danger">
			<p>
			<?php echo $error_message; ?>
			</p>
			</div>
			<?php endif; ?>

			<?php if($success_message): ?>
			<div class="callout callout-success">
			<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<form class="form-horizontal" action="" method="post" enctype="multipart/form-data">
				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Photo Caption <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" class="form-control" name="caption" value="<?php echo htmlspecialchars($caption, ENT_QUOTES); ?>">
							</div>
						</div>
						<div class="form-group">
				            <label for="" class="col-sm-2 control-label">Existing Photo</label>
				            <div class="col-sm-6" style="padding-top:6px;">
				                <img src="<?php echo htmlspecialchars(get_admin_image_url($photo), ENT_QUOTES); ?>" class="existing-photo" style="width:300px;max-width:100%;">
				                <input type="hidden" name="previous_photo" value="<?php echo htmlspecialchars($photo, ENT_QUOTES); ?>">
				            </div>
				        </div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Upload New Photo</label>
							<div class="col-sm-4" style="padding-top:6px;">
								<input type="file" name="photo">
								<br><br>
								<input type="text" class="form-control" name="photo_url" placeholder="Or paste new image URL (https://...)" value="<?php echo is_external_image_url($photo) ? htmlspecialchars($photo, ENT_QUOTES) : ''; ?>">
								<br>
								<label style="font-weight:normal;">
									<input type="checkbox" name="remove_photo" value="1"> Delete existing photo
								</label>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Submit</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>
