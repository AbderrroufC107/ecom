<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;

    if(empty($_POST['caption'])) {
        $valid = 0;
        $error_message .= "Photo Caption Name can not be empty<br>";
    }

    if($valid == 1) {
    	$statement = $pdo->prepare("SHOW TABLE STATUS LIKE 'tbl_photo'");
		$statement->execute();
		$result = $statement->fetchAll();
		foreach($result as $row) {
			$ai_id=$row[10];
		}

		list($image_ok, $image_value) = store_image_input(
			'photo',
			'photo_url',
			'photo-'.$ai_id,
			'../assets/uploads',
			$error_message,
			true
		);

		if($image_ok && $image_value !== '') {
			$statement = $pdo->prepare("INSERT INTO tbl_photo (caption,photo) VALUES (?,?)");
			$statement->execute(array($_POST['caption'],$image_value));
			$success_message = 'Photo is added successfully.';

			unset($_POST['caption']);
			unset($_POST['photo_url']);
		}
    }
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Add Photo</h1>
	</div>
	<div class="content-header-right">
		<a href="photo.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>


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
								<input type="text" class="form-control" name="caption" value="<?php echo isset($_POST['caption']) ? htmlspecialchars($_POST['caption'], ENT_QUOTES) : ''; ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Upload Photo <span>*</span></label>
							<div class="col-sm-4" style="padding-top:6px;">
								<input type="file" name="photo">
								<br><br>
								<input type="text" class="form-control" name="photo_url" placeholder="Or paste image URL (https://...)" value="<?php echo isset($_POST['photo_url']) ? htmlspecialchars($_POST['photo_url'], ENT_QUOTES) : ''; ?>">
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
