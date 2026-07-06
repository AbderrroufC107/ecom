<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {

	$statement = $dbRepo->prepare("UPDATE tbl_social SET social_url=? WHERE social_name=?");
	$statement->execute(array($_POST['facebook'],'Facebook'));

	$statement = $dbRepo->prepare("UPDATE tbl_social SET social_url=? WHERE social_name=?");
	$statement->execute(array($_POST['TikTok'],'TikTok'));

	$statement = $dbRepo->prepare("UPDATE tbl_social SET social_url=? WHERE social_name=?");
	$statement->execute(array($_POST['instagram'],'Instagram'));

	$statement = $dbRepo->prepare("UPDATE tbl_social SET social_url=? WHERE social_name=?");
	$statement->execute(array($_POST['whatsapp'],'WhatsApp'));

	$success_message = 'Social Media URLs are updated successfully.';

}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Social Media</h1>
	</div>
</section>

<?php
$statement = $dbRepo->prepare("SELECT * FROM tbl_social");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);							
foreach ($result as $row) {
	if($row['social_name'] == 'Facebook') {
		$facebook = $row['social_url'];
	}

	if($row['social_name'] == 'Instagram') {
		$instagram = $row['social_url'];
	}

	if($row['social_name'] == 'TikTok') {
		$TikTok = $row['social_url'];
	}
	if($row['social_name'] == 'WhatsApp') {
		$whatsapp = $row['social_url'];
	}
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
			
			<form class="form-horizontal" action="" method="post">
    <div class="box box-info">
        <div class="box-body">						
            <p style="padding-bottom: 20px;">إذا كنت لا تريد عرض الوسائط الاجتماعية في صفحتك الرئيسية، فقط اترك حقل الإدخال فارغًا.</p>

            <div class="form-group">
                <label class="col-sm-2 control-label">Facebook </label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" name="facebook" value="<?php echo $facebook; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">TikTok </label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" name="TikTok" value="<?php echo $TikTok; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">Instagram </label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" name="instagram" value="<?php echo $instagram; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="col-sm-2 control-label">WhatsApp </label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" name="whatsapp" value="<?php echo $whatsapp; ?>">
                </div>
            </div>

            <!-- زر التحديث -->
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-4">
                    <button type="submit" class="btn btn-primary" name="form1">تحديث</button>
                </div>
            </div>

        </div>
    </div>
</form>

		</div>
	</div>

</section>

<?php require_once('footer.php'); ?>