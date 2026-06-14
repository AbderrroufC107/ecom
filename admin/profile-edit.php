<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form1'])) {
	try {
		if(empty($_POST['full_name'])) {
			throw new Exception("الرجاء إدخال الاسم الكامل");
		}
		
		if(empty($_POST['email'])) {
			throw new Exception("الرجاء إدخال البريد الإلكتروني");
		}

		// التحقق من عدم وجود نفس البريد الإلكتروني لمستخدم آخر
		$statement = $pdo->prepare("SELECT * FROM tbl_user WHERE email=? AND id!=?");
		$statement->execute(array($_POST['email'], $_SESSION['user']['id']));
		$total = $statement->rowCount();
		if($total) {
			throw new Exception("البريد الإلكتروني مستخدم من قبل");
		}
		
		// تحديث البيانات
		$statement = $pdo->prepare("UPDATE tbl_user SET 
			full_name=?, 
			email=?,
			phone=?
			WHERE id=?");
		$statement->execute(array(
			$_POST['full_name'],
			$_POST['email'],
			$_POST['phone'],
			$_SESSION['user']['id']
		));
		
		// تحديث بيانات الجلسة
		$_SESSION['user']['full_name'] = $_POST['full_name'];
		$_SESSION['user']['email'] = $_POST['email'];
		$_SESSION['user']['phone'] = $_POST['phone'];
		
		$success_message = 'تم تحديث البيانات بنجاح';
		
	} catch(Exception $e) {
		$error_message = $e->getMessage();
	}
}

// جلب بيانات المستخدم الحالي
$statement = $pdo->prepare("SELECT * FROM tbl_user WHERE id=?");
$statement->execute(array($_SESSION['user']['id']));
$result = $statement->fetchAll(PDO::FETCH_ASSOC);
$user_data = $result[0];
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Edit Profile</h1>
	</div>
</section>

<section class="content">

	<div class="row">
		<div class="col-md-12">
				
				<div class="nav-tabs-custom">
					<ul class="nav nav-tabs">
						<li class="active"><a href="#tab_1" data-toggle="tab">Update Information</a></li>
					</ul>
					<div class="tab-content">
          				<div class="tab-pane active" id="tab_1">
							
							<form class="form-horizontal" action="" method="post">
							<div class="box box-info">
								<div class="box-body">
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Name <span>*</span></label>
										<?php
										if($_SESSION['user']['role'] == 'Super Admin') {
											?>
												<div class="col-sm-4">
													<input type="text" class="form-control" name="full_name" value="<?php echo $user_data['full_name']; ?>">
												</div>
											<?php
										} else {
											?>
												<div class="col-sm-4" style="padding-top:7px;">
													<?php echo $user_data['full_name']; ?>
												</div>
											<?php
										}
										?>
										
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Email Address <span>*</span></label>
										<?php
										if($_SESSION['user']['role'] == 'Super Admin') {
											?>
												<div class="col-sm-4">
													<input type="email" class="form-control" name="email" value="<?php echo $user_data['email']; ?>">
												</div>
											<?php
										} else {
											?>
											<div class="col-sm-4" style="padding-top:7px;">
												<?php echo $user_data['email']; ?>
											</div>
											<?php
										}
										?>
										
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Phone </label>
										<div class="col-sm-4">
											<input type="text" class="form-control" name="phone" value="<?php echo $user_data['phone']; ?>">
										</div>
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label">Role <span>*</span></label>
										<div class="col-sm-4" style="padding-top:7px;">
											<?php echo $user_data['role']; ?>
										</div>
									</div>
									<div class="form-group">
										<label for="" class="col-sm-2 control-label"></label>
										<div class="col-sm-6">
											<button type="submit" class="btn btn-success pull-left" name="form1">Update Information</button>
										</div>
									</div>
								</div>
							</div>
							</form>
          				</div>
          			</div>
				</div>			

		</div>
	</div>
</section>

<?php require_once('footer.php'); ?>