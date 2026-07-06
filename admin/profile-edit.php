<?php require_once('header.php'); ?>

<?php
if(isset($_POST['form_assignment']) && !$is_employee) {
    try {
        $participate = isset($_POST['participate_in_assignment']) ? 1 : 0;
        $weight = (int)$_POST['assignment_weight'];
        $max_orders = (int)$_POST['max_active_orders'];
        $status = $_POST['availability_status'];

        if($weight < 1) $weight = 1;
        if($max_orders < 1) $max_orders = 1;

        // Audit Log
        if (function_exists('audit_log')) {
            audit_log($pdo, 'Update Profile', "Admin  updated assignment settings. Participate: $participate, Weight: $weight, Status: $status", $_SESSION['user']['id']);
        }

        $stmt = $dbRepo->prepare("UPDATE tbl_user SET participate_in_assignment=?, assignment_weight=?, availability_status=?, max_active_orders=? WHERE id=?");
        $stmt->execute([$participate, $weight, $status, $max_orders, $_SESSION['user']['id']]);
        
        $success_message = 'تم تحديث إعدادات التوزيع بنجاح';
        
        // Refresh user_data
        $statement = $dbRepo->prepare("SELECT * FROM tbl_user WHERE id=?");
        $statement->execute([$_SESSION['user']['id']]);
        $user_data = $statement->fetch(PDO::FETCH_ASSOC);

    } catch(Exception $e) {
        $error_message = $e->getMessage();
    }
}

if(isset($_POST['form1'])) {
	try {
		if(empty($_POST['full_name'])) {
			throw new Exception("الرجاء إدخال الاسم الكامل");
		}
		
		if(empty($_POST['email'])) {
			throw new Exception("الرجاء إدخال البريد الإلكتروني");
		}

		$is_employee_post = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Employee';
		
		if ($is_employee_post) {
			$emp_id = (int) str_replace('emp_', '', $_SESSION['user']['id']);
			$statement = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE email=? AND id!=?");
			$statement->execute(array($_POST['email'], $emp_id));
			$total = $statement->rowCount();
			if($total) {
				throw new Exception("البريد الإلكتروني مستخدم من قبل لموظف آخر");
			}

			$statement = $dbRepo->prepare("UPDATE tbl_employee SET full_name=?, email=? WHERE id=?");
			$statement->execute(array($_POST['full_name'], $_POST['email'], $emp_id));
			
			$_SESSION['user']['full_name'] = $_POST['full_name'];
			$_SESSION['user']['email'] = $_POST['email'];
		} else {
			// التحقق من عدم وجود نفس البريد الإلكتروني لمستخدم آخر
			$statement = $dbRepo->prepare("SELECT * FROM tbl_user WHERE email=? AND id!=?");
			$statement->execute(array($_POST['email'], $_SESSION['user']['id']));
			$total = $statement->rowCount();
			if($total) {
				throw new Exception("البريد الإلكتروني مستخدم من قبل");
			}
			
			// تحديث البيانات
			$statement = $dbRepo->prepare("UPDATE tbl_user SET 
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
		}
		
		$success_message = 'تم تحديث البيانات بنجاح';
		
	} catch(Exception $e) {
		$error_message = $e->getMessage();
	}
}

// جلب بيانات المستخدم الحالي
$is_employee = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Employee';

if ($is_employee) {
	$emp_id = (int) str_replace('emp_', '', $_SESSION['user']['id']);
	$statement = $dbRepo->prepare("SELECT * FROM tbl_employee WHERE id=?");
	$statement->execute(array($emp_id));
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	$user_data = $result[0];
	$user_data['role'] = 'Employee';
	// tbl_employee does not have phone
	$user_data['phone'] = '';
} else {
	$statement = $dbRepo->prepare("SELECT * FROM tbl_user WHERE id=?");
	$statement->execute(array($_SESSION['user']['id']));
	$result = $statement->fetchAll(PDO::FETCH_ASSOC);
	$user_data = $result[0];
}
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
						<?php if (!$is_employee): ?>
						<li><a href="#tab_assignment" data-toggle="tab">نظام توزيع الطلبات</a></li>
						<?php endif; ?>
						<li><a href="#tab_telegram" data-toggle="tab"><?php echo $is_employee ? 'Telegram Notifications' : 'Telegram Linking'; ?></a></li>
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
          				
          				<?php if (!$is_employee): ?>
          				<div class="tab-pane" id="tab_assignment">
							<div class="box box-info">
								<div class="box-body" style="padding: 20px;">
									<h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;">
										<i class="fa fa-random text-aqua"></i> نظام توزيع الطلبات
									</h3>
									<p class="text-muted" style="margin-bottom: 20px;">
										تحكم في كيفية توزيع الطلبات الجديدة على الموظفين.
									</p>
									<form action="" method="post">
										<table class="table table-bordered">
											<tr>
												<th style="width: 250px; background-color: #f9f9f9;">المشاركة في التوزيع</th>
												<td>
													<label>
														<input type="checkbox" name="participate_in_assignment" value="1" <?php echo (!empty($user_data['participate_in_assignment']) || !isset($user_data['participate_in_assignment'])) ? 'checked' : ''; ?>>
														شاركوني في توزيع الطلبات الجديدة
													</label>
												</td>
											</tr>
											<tr>
												<th style="background-color: #f9f9f9;">وزن الأولوية</th>
												<td>
													<input type="number" name="assignment_weight" class="form-control" style="width: 120px; display: inline-block;" min="1" max="100" value="<?php echo $user_data['assignment_weight'] ?? 1; ?>">
													<span class="text-muted">(1-100)</span>
												</td>
											</tr>
											<tr>
												<th style="background-color: #f9f9f9;">الحد الأقصى للطلبات النشطة</th>
												<td>
													<input type="number" name="max_active_orders" class="form-control" style="width: 120px; display: inline-block;" min="1" value="<?php echo $user_data['max_active_orders'] ?? 5; ?>">
												</td>
											</tr>
											<tr>
												<th style="background-color: #f9f9f9;">حالة التوفر</th>
												<td>
													<select name="availability_status" class="form-control" style="width: 200px;">
														<option value="available" <?php echo ($user_data['availability_status'] ?? 'available') === 'available' ? 'selected' : ''; ?>>متاح</option>
														<option value="busy" <?php echo ($user_data['availability_status'] ?? '') === 'busy' ? 'selected' : ''; ?>>مشغول</option>
														<option value="away" <?php echo ($user_data['availability_status'] ?? '') === 'away' ? 'selected' : ''; ?>>بعيد</option>
													</select>
												</td>
											</tr>
										</table>
										<div style="margin-top: 15px;">
											<button type="submit" name="form_assignment" class="btn btn-success pull-left">
												<i class="fa fa-check"></i> حفظ الإعدادات
											</button>
										</div>
									</form>
								</div>
							</div>
						</div>
						<?php endif; ?>
          				
          				<div class="tab-pane" id="tab_telegram">
							<div class="box box-info" id="telegram-link-card">
								<div class="box-body" style="padding: 20px;">
									<?php if ($is_employee): ?>
										<div class="row">
											<div class="col-sm-8">
												<h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;">
													<i class="fa fa-bell text-aqua"></i> Telegram Notifications
												</h3>
												<p class="text-muted" style="margin-bottom: 20px;">
													Link your Telegram account to receive notifications when new tasks are assigned to you or when the status of your tasks changes.
												</p>
												
												<table class="table table-bordered">
													<tr>
														<th style="width: 180px; background-color: #f9f9f9;">Connection Status</th>
														<td>
															<?php if (!empty($user_data['telegram_is_linked'])): ?>
																<span class="label label-success" style="font-size: 13px;"><i class="fa fa-check"></i> Connected</span>
															<?php else: ?>
																<span class="label label-default" style="font-size: 13px;"><i class="fa fa-chain-broken"></i> Not Connected</span>
															<?php endif; ?>
														</td>
													</tr>
													<?php if (!empty($user_data['telegram_is_linked'])): ?>
													<tr>
														<th style="background-color: #f9f9f9;">Telegram Username</th>
														<td><strong><?php echo !empty($user_data['telegram_username']) ? '@' . htmlspecialchars($user_data['telegram_username'], ENT_QUOTES, 'UTF-8') : '--'; ?></strong></td>
													</tr>
													<tr>
														<th style="background-color: #f9f9f9;">Linked Date</th>
														<td><?php echo !empty($user_data['telegram_linked_at']) ? htmlspecialchars($user_data['telegram_linked_at'], ENT_QUOTES, 'UTF-8') : '--'; ?></td>
													</tr>
													<?php endif; ?>
												</table>

												<div id="tg-initial-state" style="margin-top: 20px;">
													<?php if (!empty($user_data['telegram_is_linked'])): ?>
														<button type="button" class="btn btn-default" onclick="telegramGenerateLink()">
															<i class="fa fa-refresh"></i> Relink Account
														</button>
														<button type="button" class="btn btn-info" onclick="telegramTest()" id="btn-tg-test">
															<i class="fa fa-bell-o"></i> Send Test Notification
														</button>
														<button type="button" class="btn btn-danger" onclick="telegramUnlink()" style="margin-left: 10px;">
															<i class="fa fa-times-circle"></i> Unlink Account
														</button>
													<?php else: ?>
														<button type="button" class="btn btn-primary" onclick="telegramGenerateLink()">
															<i class="fa fa-link"></i> Link Telegram Account
														</button>
													<?php endif; ?>
												</div>

												<div id="tg-pending-state" style="display: none; margin-top: 20px; padding: 15px; border: 1px solid #bee5eb; border-radius: 8px; background-color: #d1ecf1;">
													<h4 style="color: #0c5460; margin-top:0;"><i class="icon fa fa-info"></i> Pending Link</h4>
													<p style="color: #0c5460; margin-bottom: 15px;">Please open Telegram and press the <strong>Start</strong> button to complete.</p>
													
													<div style="display:flex; gap:10px; align-items: center;">
														<a href="#" id="tg-deep-link-btn" target="_blank" class="btn btn-success">
															<i class="fa fa-telegram"></i> Open Telegram Bot
														</a>
														<button type="button" class="btn btn-default" onclick="window.location.reload()">
															<i class="fa fa-refresh"></i> I completed linking
														</button>
													</div>
													<p class="text-muted" style="font-size: 13px; margin-top: 10px; margin-bottom:0;">
														Token expires in: <span id="tg-timer" class="text-danger" style="font-weight: bold;">15:00</span>
													</p>
												</div>
											</div>
										</div>
									<?php else: ?>
										<h3 style="margin-top: 0; margin-bottom: 20px; font-size: 18px; font-weight: 600;">
											<i class="fa fa-telegram text-aqua"></i> Link Telegram Account
										</h3>
										
										<?php if (!empty($user_data['telegram_is_linked'])): ?>
											<div class="row">
												<div class="col-sm-6">
													<div class="alert alert-success" style="background-color: #d4edda !important; color: #155724 !important; border-color: #c3e6cb !important;">
														<h4><i class="icon fa fa-check"></i> Account Linked!</h4>
														Your manager account is successfully linked to the Telegram Bot.
													</div>
													
													<table class="table table-bordered">
														<tr>
															<th style="width: 150px; background-color: #f9f9f9;">Username</th>
															<td><strong><?php echo !empty($user_data['telegram_username']) ? '@' . htmlspecialchars($user_data['telegram_username'], ENT_QUOTES, 'UTF-8') : '--'; ?></strong></td>
														</tr>
														<tr>
															<th style="background-color: #f9f9f9;">Telegram Name</th>
															<td><?php echo !empty($user_data['telegram_first_name']) ? htmlspecialchars($user_data['telegram_first_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></td>
														</tr>
														<tr>
															<th style="background-color: #f9f9f9;">Linked Date</th>
															<td><?php echo !empty($user_data['telegram_linked_at']) ? htmlspecialchars($user_data['telegram_linked_at'], ENT_QUOTES, 'UTF-8') : '--'; ?></td>
														</tr>
													</table>
													
													<button type="button" class="btn btn-danger mt-3" onclick="telegramUnlink()">
														<i class="fa fa-times-circle"></i> Unlink Account
													</button>
												</div>
											</div>
										<?php else: ?>
											<div class="row">
												<div class="col-sm-6" id="tg-initial-state">
													<p class="text-muted" style="margin-bottom: 20px;">
														Link your Telegram account to start receiving enterprise notifications for orders, employee status changes, daily reports, and system alerts.
													</p>
													<button type="button" class="btn btn-primary" onclick="telegramGenerateLink()">
														<i class="fa fa-link"></i> Generate Link Token
													</button>
												</div>

												<div class="col-sm-6" id="tg-pending-state" style="display: none;">
													<div class="alert alert-info" style="background-color: #d1ecf1 !important; color: #0c5460 !important; border-color: #bee5eb !important;">
														<h4><i class="icon fa fa-info"></i> Security Token Generated</h4>
														Please click the button below to open Telegram, then press the <strong>Start</strong> button to complete the link.
													</div>
													
													<a href="#" id="tg-deep-link-btn" target="_blank" class="btn btn-success btn-lg btn-block" style="margin-bottom: 15px;">
														<i class="fa fa-telegram"></i> Open Telegram Bot
													</a>
													
													<p class="text-center text-muted" style="font-size: 13px;">
														Token expires in: <span id="tg-timer" class="text-danger font-weight-bold" style="font-weight: bold;">15:00</span>
													</p>
													
													<button type="button" class="btn btn-default btn-block" onclick="window.location.reload()">
														<i class="fa fa-refresh"></i> I completed linking, reload page
													</button>
												</div>
											</div>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</div>
          				</div>
          			</div>
				</div>			

		</div>
	</div>
</section>

<script>
let tgTimerInterval = null;

function telegramGenerateLink() {
    const btn = document.querySelector('#tg-initial-state button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

    const userType = '<?php echo $is_employee ? "employee" : "manager"; ?>';
    fetch('telegram-link-action.php?action=generate&user_type=' + userType)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('tg-initial-state').style.display = 'none';
                document.getElementById('tg-pending-state').style.display = 'block';
                
                const linkBtn = document.getElementById('tg-deep-link-btn');
                linkBtn.href = data.url;
                
                // Start 15-minute countdown
                let timeLeft = 15 * 60;
                const timerSpan = document.getElementById('tg-timer');
                
                if (tgTimerInterval) clearInterval(tgTimerInterval);
                tgTimerInterval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(tgTimerInterval);
                        timerSpan.textContent = 'Expired';
                        linkBtn.classList.add('disabled');
                        linkBtn.href = '#';
                    } else {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        timerSpan.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }, 1000);
            } else {
                alert('Error generating link token: ' + (data.error || 'Unknown error'));
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-link"></i> Generate Link Token';
            }
        })
        .catch(err => {
            alert('Failed to connect to the server: ' + err.message);
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-link"></i> Generate Link Token';
        });
}

function telegramUnlink() {
    if (!confirm('Are you sure you want to unlink your Telegram account? You will stop receiving notifications.')) {
        return;
    }
    
    const card = document.getElementById('telegram-link-card');
    card.style.opacity = '0.6';
    card.style.pointerEvents = 'none';

    const userType = '<?php echo $is_employee ? "employee" : "manager"; ?>';
    fetch('telegram-link-action.php?action=unlink&user_type=' + userType)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error unlinking account: ' + (data.error || 'Unknown error'));
                card.style.opacity = '1';
                card.style.pointerEvents = 'auto';
            }
        })
        .catch(err => {
            alert('Failed to connect to the server: ' + err.message);
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
        });
}
function telegramTest() {
    const btn = document.getElementById('btn-tg-test');
    if (!btn) return;
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending...';

    const userType = '<?php echo $is_employee ? "employee" : "manager"; ?>';
    fetch('telegram-link-action.php?action=test&user_type=' + userType)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Test notification sent successfully! Please check your Telegram app.');
            } else {
                alert('Error sending test: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Failed to connect to the server: ' + err.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabLinks = document.querySelectorAll('.nav-tabs a[data-toggle="tab"]');
    tabLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var target = this.getAttribute('href');
            if (!target || target === '#') return;
            
            document.querySelectorAll('.nav-tabs li').forEach(function(li) {
                li.classList.remove('active');
            });
            document.querySelectorAll('.tab-pane').forEach(function(pane) {
                pane.classList.remove('active');
            });
            
            this.parentElement.classList.add('active');
            var pane = document.querySelector(target);
            if (pane) pane.classList.add('active');
        });
    });
    
    var hash = window.location.hash;
    if (hash && document.querySelector('.nav-tabs a[href="' + hash + '"]')) {
        document.querySelector('.nav-tabs a[href="' + hash + '"]').click();
    }
});
</script>

<?php require_once('footer.php'); ?>