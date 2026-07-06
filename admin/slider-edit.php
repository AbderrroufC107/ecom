<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;
	$current_photo = $_POST['current_photo'] ?? '';
	$remove_photo = !empty($_POST['remove_photo']);

	list($image_ok, $new_image_value) = store_image_input(
		'photo',
		'photo_url',
		'slider-'.$_REQUEST['id'],
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

		$statement = $dbRepo->prepare("UPDATE tbl_slider SET photo=?, heading=?, content=?, button_text=?, button_url=?, position=? WHERE id=?");
		$statement->execute(array($final_photo,$_POST['heading'],$_POST['content'],$_POST['button_text'],$_POST['button_url'],$_POST['position'],$_REQUEST['id']));

	    $success_message = 'Slider is updated successfully!';
	}
}
?>

<?php
if(!isset($_REQUEST['id'])) {
	header('location: logout.php');
	exit;
} else {
	$statement = $dbRepo->prepare("SELECT * FROM tbl_slider WHERE id=?");
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
		<h1>Edit Slider</h1>
	</div>
	<div class="content-header-right">
		<a href="slider.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<?php
$statement = $dbRepo->prepare("SELECT * FROM tbl_slider WHERE id=?");
$statement->execute(array($_REQUEST['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
foreach ($result as $row) {
	$photo       = $row['photo'];
	$heading     = $row['heading'];
	$content     = $row['content'];
	$button_text = $row['button_text'];
	$button_url  = $row['button_url'];
	$position    = $row['position'];
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
							<label for="" class="col-sm-2 control-label">Existing Photo</label>
							<div class="col-sm-9" style="padding-top:5px">
								<img src="<?php echo htmlspecialchars(get_admin_image_url($photo), ENT_QUOTES); ?>" alt="Slider Photo" style="width:400px;max-width:100%;">
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
							<label for="" class="col-sm-2 control-label">Heading </label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="heading" value="<?php echo htmlspecialchars($heading, ENT_QUOTES); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Content </label>
							<div class="col-sm-6">
								<textarea class="form-control" name="content" style="height:140px;"><?php echo htmlspecialchars($content); ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Button Text </label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="button_text" value="<?php echo htmlspecialchars($button_text, ENT_QUOTES); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Button URL </label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="button_url" value="<?php echo htmlspecialchars($button_url, ENT_QUOTES); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Position </label>
							<div class="col-sm-6">
								<select name="position" class="form-control">
									<option value="Left" <?php if($position == 'Left') {echo 'selected';} ?>>Left</option>
									<option value="Center" <?php if($position == 'Center') {echo 'selected';} ?>>Center</option>
									<option value="Right" <?php if($position == 'Right') {echo 'selected';} ?>>Right</option>
								</select>
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
