<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;
	$current_photo = $_POST['current_photo'] ?? '';
	$remove_photo = !empty($_POST['remove_photo']);

	if(empty($_POST['title'])) {
		$valid = 0;
		$error_message .= 'Title can not be empty<br>';
	}

	if(empty($_POST['content'])) {
		$valid = 0;
		$error_message .= 'Content can not be empty<br>';
	}

	list($image_ok, $new_image_value) = store_image_input(
		'photo',
		'photo_url',
		'service-'.$_REQUEST['id'],
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

		$statement = $pdo->prepare("UPDATE tbl_service SET title=?, content=?, photo=? WHERE id=?");
		$statement->execute(array($_POST['title'],$_POST['content'],$final_photo,$_REQUEST['id']));

	    $success_message = 'Service is updated successfully!';
	}
}
?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	$statement = $pdo->prepare("SELECT * FROM tbl_service WHERE id=?");
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
		<h1>Edit Service</h1>
	</div>
	<div class="content-header-right">
		<a href="service.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_service WHERE id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$title = $row['title'];
	$content = $row['content'];
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
				<input type="hidden" name="current_photo" value="<?php echo htmlspecialchars($photo, ENT_QUOTES); ?>">
				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Title <span>*</span></label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="title" value="<?php echo htmlspecialchars($title, ENT_QUOTES); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Content <span>*</span></label>
							<div class="col-sm-6">
								<textarea class="form-control" name="content" style="height:140px;"><?php echo htmlspecialchars($content); ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Existing Photo</label>
							<div class="col-sm-9" style="padding-top:5px">
								<img src="<?php echo htmlspecialchars(get_admin_image_url($photo), ENT_QUOTES); ?>" alt="Service Photo" style="width:180px;">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Photo </label>
							<div class="col-sm-6" style="padding-top:5px">
								<input type="file" name="photo">(Only jpg, jpeg, gif, png and webp are allowed)
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
