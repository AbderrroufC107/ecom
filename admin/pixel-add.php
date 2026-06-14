<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	$valid = 1;

    if(empty($_POST['pixel_name'])) {
        $valid = 0;
        $error_message .= "Pixel Name can not be empty<br>";
    }
    if(empty($_POST['pixel_id'])) {
        $valid = 0;
        $error_message .= "Pixel ID can not be empty<br>";
    }
    if(empty($_POST['pixel_network'])) {
        $valid = 0;
        $error_message .= "Pixel Network can not be empty<br>";
    }

    if($valid == 1) {
		$statement = $pdo->prepare("INSERT INTO tbl_pixel (pixel_name, pixel_network, pixel_id, pixel_script) VALUES (?,?,?,?)");
		$statement->execute(array($_POST['pixel_name'], $_POST['pixel_network'], $_POST['pixel_id'], $_POST['pixel_script']));
	
    	$success_message = 'Pixel is added successfully.';
    }
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Add Pixel</h1>
	</div>
	<div class="content-header-right">
		<a href="pixel.php" class="btn btn-primary btn-sm">View All</a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<?php if($error_message): ?>
			<div class="callout callout-danger">
			<p><?php echo $error_message; ?></p>
			</div>
			<?php endif; ?>

			<?php if($success_message): ?>
			<div class="callout callout-success">
			<p><?php echo $success_message; ?></p>
			</div>
			<?php endif; ?>

			<form class="form-horizontal" action="" method="post">
				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Pixel Name <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" class="form-control" name="pixel_name" placeholder="e.g. My Meta Pixel">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Network <span>*</span></label>
							<div class="col-sm-4">
								<select name="pixel_network" class="form-control">
									<option value="Facebook">Facebook / Meta</option>
									<option value="TikTok">TikTok</option>
									<option value="Snapchat">Snapchat</option>
									<option value="Google">Google Analytics/Ads</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Pixel ID <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" class="form-control" name="pixel_id">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Pixel Script (Optional, if custom)</label>
							<div class="col-sm-8">
								<textarea class="form-control" name="pixel_script" style="height:150px;"></textarea>
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
