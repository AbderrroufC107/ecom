<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;

	if($valid == 1) {
		$statement = $dbRepo->prepare("INSERT INTO tbl_slider (photo,heading,content,button_text,button_url,position) VALUES (?,?,?,?,?,?)");
		$statement->execute(array('',$_POST['heading'],$_POST['content'],$_POST['button_text'],$_POST['button_url'],$_POST['position']));
		$new_id = $dbRepo->lastInsertId();

		list($image_ok, $image_value) = store_image_input(
			'photo',
			'photo_url',
			'slider-'.$new_id,
			'../assets/uploads',
			$error_message,
			true
		);

		if(!$image_ok || $image_value === '') {
			$dbRepo->prepare("DELETE FROM tbl_slider WHERE id=?")->execute(array($new_id));
		} else {
			$statement = $dbRepo->prepare("UPDATE tbl_slider SET photo=? WHERE id=?");
			$statement->execute(array($image_value,$new_id));
			$success_message = 'Slider is added successfully!';
		}

		unset($_POST['heading']);
		unset($_POST['content']);
		unset($_POST['button_text']);
		unset($_POST['button_url']);
		unset($_POST['photo_url']);
	}
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Add Slider</h1>
	</div>
	<div class="content-header-right">
		<a href="slider.php" class="btn btn-primary btn-sm">View All</a>
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
							<label for="" class="col-sm-2 control-label">Photo <span>*</span></label>
							<div class="col-sm-9" style="padding-top:5px">
								<input type="file" name="photo">(Only jpg, jpeg, gif, png and webp are allowed)
								<br><br>
								<input type="text" class="form-control" name="photo_url" placeholder="Or paste image URL (https://...)" value="<?php if(isset($_POST['photo_url'])){echo htmlspecialchars($_POST['photo_url'], ENT_QUOTES);} ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Heading </label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="heading" value="<?php if(isset($_POST['heading'])){echo htmlspecialchars($_POST['heading'], ENT_QUOTES, 'UTF-8');} ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Content </label>
							<div class="col-sm-6">
								<textarea class="form-control" name="content" style="height:140px;"><?php if(isset($_POST['content'])){echo htmlspecialchars($_POST['content'], ENT_QUOTES, 'UTF-8');} ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Button Text </label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="button_text" value="<?php if(isset($_POST['button_text'])){echo htmlspecialchars($_POST['button_text'], ENT_QUOTES, 'UTF-8');} ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Button URL </label>
							<div class="col-sm-6">
								<input type="text" autocomplete="off" class="form-control" name="button_url" value="<?php if(isset($_POST['button_url'])){echo htmlspecialchars($_POST['button_url'], ENT_QUOTES, 'UTF-8');} ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Position </label>
							<div class="col-sm-6">
								<select name="position" class="form-control">
									<option value="Left">Left</option>
									<option value="Center">Center</option>
									<option value="Right">Right</option>
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
