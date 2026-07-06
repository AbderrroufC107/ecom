<?php
header('Location: index.php');
exit;
?>

<section class="content-header">
	<div class="content-header-left">
		<h1><?php echo $l['page_customers']; ?></h1>
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
								<th><?php echo $l['num'] ?? '#'; ?></th>
								<th><?php echo $l['customer_name']; ?></th>
								<th><?php echo $l['phone']; ?></th>
								<th><?php echo $l['full_address'] ?? ($l['nav_countries'] ?? 'العنوان'); ?></th>
								<th><?php echo $l['account_status'] ?? 'حالة الحساب'; ?></th>
								<th><?php echo $l['actions']; ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							global $customerRepo;
							$i = 0;
							$result = $customerRepo->findAll([], 'id DESC');
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
											echo '<span class="badge badge-success">' . ($l['status_active'] ?? 'نشط') . '</span>';
										} else {
											echo '<span class="badge badge-danger">' . ($l['status_disabled'] ?? 'معطل') . '</span>';
										}
										?>
									</td>
									<td>
										<a href="customer-change-status.php?id=<?php echo $row['id']; ?>" class="btn btn-success btn-xs"><?php echo $l['change_status'] ?? 'تغيير الحالة'; ?></a>
										<a href="customer-delete.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('<?php echo $l['confirm_delete_customer'] ?? 'هل أنت متأكد من حذف هذا العميل؟'; ?>');"><?php echo $l['delete']; ?></a>
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
		<?php if ($current_lang === 'fr'): ?>
		"language": {
			"search": "Rechercher :",
			"lengthMenu": "Afficher _MENU_ entrées",
			"info": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
			"paginate": { "first": "Premier", "last": "Dernier", "next": "Suivant", "previous": "Précédent" },
			"emptyTable": "Aucune donnée",
			"zeroRecords": "Aucun résultat"
		}
		<?php elseif ($current_lang === 'en'): ?>
		"language": {
			"search": "Search:",
			"lengthMenu": "Show _MENU_ entries",
			"info": "Showing _START_ to _END_ of _TOTAL_ entries",
			"paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" },
			"emptyTable": "No data available",
			"zeroRecords": "No matching records found"
		}
		<?php else: ?>
		"language": {
			"url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
		}
		<?php endif; ?>
	});
});
</script>

<?php require_once('footer.php'); ?>