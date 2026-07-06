<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>Select Customer</h1>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<form class="form-horizontal" action="" method="post">

				<div class="box box-info">
					<div class="box-body">
						<div class="form-group">
							<label for="" class="col-sm-2 control-label">Select a Customer <span>*</span></label>
							<div class="col-sm-4">
								<select name="cust_id" class="form-control select2">
								<?php
								global $customerRepo;
								$result = $customerRepo->findAll([], 'cust_id ASC');
								foreach ($result as $row) {
									?>
									<option value="<?php echo $row['cust_id']; ?>"><?php echo htmlspecialchars($row['cust_name']); ?> - <?php echo htmlspecialchars($row['cust_email'] ?? ''); ?></option>
									<?php
								}
								?>
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


<?php if(isset($_POST['form1'])): ?>
<section class="content-header">
	<div class="content-header-left">
		<h1>View All Customer Messages</h1>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-striped">
						<thead>
							<tr>
								<th width="30">#</th>
								<th width="100">Subject</th>
								<th width="200">Message</th>
								<th width="200">Order Details</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i=0;
							$messageRepo = new \SaaS\Repositories\CustomerMessageRepository($pdo);
							$result = $messageRepo->findAll(['cust_id' => $_POST['cust_id']]);
							foreach ($result as $row) {
								$i++;
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td><?php echo $row['subject']; ?></td>
									<td><?php echo nl2br($row['message']); ?></td>
									<td><?php echo $row['order_detail']; ?></td>
								</tr>
								<?php
							}
							?>							
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<?php require_once('footer.php'); ?>