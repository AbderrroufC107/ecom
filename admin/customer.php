<?php require_once('header.php'); ?>

<section class="content-header">
	<div class="content-header-left">
		<h1>View Customers</h1>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="customers_table" class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>رقم</th>
								<th>الاسم واللقب</th>
								<th>البريد الإلكتروني</th>
								<th>رقم الهاتف</th>
								<th>العنوان الكامل</th>
								<th>حالة الحساب</th>
								<th>تعديل</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i = 0;
							$statement = $pdo->prepare("SELECT * FROM tbl_customer ORDER BY id DESC");
							$statement->execute();
							$result = $statement->fetchAll(PDO::FETCH_ASSOC);
							foreach ($result as $row) {
								$i++;
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td><?php echo $row['cust_name']; ?></td>
									<td><?php echo $row['cust_phone']; ?></td>
									<td>
										<?php 
										echo $row['wilaya'] . ' - ' . $row['commune'];
										if(!empty($row['address'])) {
											echo '<br>' . $row['address'];
										}
										?>
									</td>
									<td>
										<?php 
										if($row['cust_status'] == 1) {
											echo '<span class="badge badge-success">نشط</span>';
										} else {
											echo '<span class="badge badge-danger">معطل</span>';
										}
										?>
									</td>
									<td>
										<a href="customer-change-status.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-xs">تغيير الحالة</a>
										<a href="customer-delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد من حذف هذا العميل؟');">حذف</a>
									</td>
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

<style>
.badge {
	padding: 8px 12px;
	font-size: 12px;
}
.badge-success {
	background-color: #28a745;
}
.badge-danger {
	background-color: #dc3545;
}
</style>

<script>
$(document).ready(function() {
	$('#customers_table').DataTable({
		"order": [[ 0, "desc" ]],
		"language": {
			"url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
		}
	});
});
</script>

<?php require_once('footer.php'); ?>