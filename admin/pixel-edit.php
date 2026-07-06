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

    if($valid == 1) {
		$statement = $dbRepo->prepare("UPDATE tbl_pixel SET pixel_name=?, pixel_network=?, pixel_id=?, pixel_script=? WHERE id=?");
		$statement->execute(array($_POST['pixel_name'], $_POST['pixel_network'], $_POST['pixel_id'], $_POST['pixel_script'], $_REQUEST['id']));
	
    	$success_message = 'Pixel is updated successfully.';
    }
}

if(!isset($_REQUEST['id']) || !ctype_digit((string) $_REQUEST['id'])) {
    header('location: pixel.php');
    exit;
} else {
    $statement = $dbRepo->prepare("SELECT * FROM tbl_pixel WHERE id=?");
    $statement->execute(array($_REQUEST['id']));
    $total = $statement->rowCount();
    $result = $statement->fetchAll(PDO::FETCH_ASSOC);
    if( $total == 0 ) {
        header('location: pixel.php');
        exit;
    }
}
foreach ($result as $row) {
    $pixel_name = $row['pixel_name'];
    $pixel_network = $row['pixel_network'];
    $pixel_id = $row['pixel_id'];
    $pixel_script = $row['pixel_script'];
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Pixel</h1>
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
								<input type="text" class="form-control" name="pixel_name" value="<?php echo htmlspecialchars($pixel_name); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Network <span>*</span></label>
							<div class="col-sm-4">
								<select name="pixel_network" class="form-control">
									<option value="Facebook" <?php if($pixel_network=='Facebook') echo 'selected'; ?>>Facebook / Meta</option>
									<option value="TikTok" <?php if($pixel_network=='TikTok') echo 'selected'; ?>>TikTok</option>
									<option value="Snapchat" <?php if($pixel_network=='Snapchat') echo 'selected'; ?>>Snapchat</option>
									<option value="Google" <?php if($pixel_network=='Google') echo 'selected'; ?>>Google Analytics/Ads</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Pixel ID <span>*</span></label>
							<div class="col-sm-4">
								<input type="text" class="form-control" name="pixel_id" value="<?php echo htmlspecialchars($pixel_id); ?>">
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Pixel Script</label>
							<div class="col-sm-8">
								<textarea class="form-control" name="pixel_script" style="height:150px;"><?php echo htmlspecialchars($pixel_script); ?></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="" class="col-sm-2 control-label"></label>
							<div class="col-sm-6">
								<button type="submit" class="btn btn-success pull-left" name="form1">Update</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>
