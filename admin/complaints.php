<?php require_once('header.php'); ?>
<?php
if (file_exists('inc/telegram_bot.php')) { require_once('inc/telegram_bot.php'); }

// Provision table if not exists
telegram_ensure_complaints_table($pdo);

$success_message = '';
$error_message = '';

$action = $_POST['action'] ?? ($_GET['action'] ?? '');
$complaint_id = (int) ($_POST['complaint_id'] ?? ($_GET['id'] ?? 0));

if ($action === 'delete' && $complaint_id > 0) {
    try {
        $stmt = $dbRepo->prepare("DELETE FROM tbl_complaints WHERE id = ?");
        $stmt->execute([$complaint_id]);
        $success_message = 'تم حذف الشكوى/الملاحظة بنجاح.';
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all complaints with employee names
$stmt = $dbRepo->query("
    SELECT c.*, e.full_name AS employee_name, e.email AS employee_email
    FROM tbl_complaints c
    LEFT JOIN tbl_employee e ON e.id = c.employee_id
    ORDER BY c.created_at DESC
");
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="content-header">
	<div class="content-header-left">
		<h1>الشكاوى والملاحظات الواردة</h1>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
            <?php if ($success_message !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($error_message !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="complaints_table" class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>#</th>
								<th>الموظف</th>
								<th>الموضوع</th>
								<th>الشكوى / الملاحظة</th>
								<th>التاريخ</th>
								<th>حالة تيليجرام</th>
								<th>العمليات</th>
							</tr>
						</thead>
						<tbody>
							<?php
							$i = 0;
							foreach ($complaints as $row) {
								$i++;
								?>
								<tr>
									<td><?php echo $i; ?></td>
									<td>
                                        <strong><?php echo htmlspecialchars($row['employee_name'] ?? 'غير معروف', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['employee_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
                                    </td>
									<td><strong><?php echo htmlspecialchars($row['subject'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
									<td style="white-space: normal; min-width: 280px; max-width: 450px;">
                                        <div style="font-size: 13px; white-space: pre-wrap; line-height: 1.5;">
                                            <?php echo htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
									<td><?php echo htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
									<td>
										<?php if ($row['telegram_status'] === 'sent'): ?>
											<span class="badge bg-success" style="background-color: #28a745;">وصلت للمدير</span>
										<?php elseif ($row['telegram_status'] === 'failed'): ?>
											<span class="badge bg-danger" style="background-color: #dc3545;">فشل الإرسال</span>
										<?php else: ?>
											<span class="badge bg-warning text-dark" style="background-color: #ffc107; color: #212529;">معلقة</span>
										<?php endif; ?>
									</td>
									<td>
										<a href="complaints.php?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد من حذف هذه الشكوى؟');">حذف</a>
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

<script>
$(document).ready(function() {
	$('#complaints_table').DataTable({
		"order": [[ 0, "asc" ]],
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
			"search": "بحث سريع:",
			"lengthMenu": "عرض _MENU_ سجلات",
			"info": "عرض من _START_ إلى _END_ من أصل _TOTAL_ سجل",
			"paginate": { "first": "الأول", "last": "الأخير", "next": "التالي", "previous": "السابق" },
			"emptyTable": "لا توجد شكاوى أو ملاحظات واردة.",
			"zeroRecords": "لم يتم العثور على أي نتائج مطابقة"
		}
		<?php endif; ?>
	});
});
</script>

<?php require_once('footer.php'); ?>
