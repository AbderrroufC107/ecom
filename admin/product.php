<?php
require_once('header.php');
if (isset($_GET['pid']) && ctype_digit((string) $_GET['pid'])) {
    header('Location: product-edit.php?id=' . (int) $_GET['pid']);
    exit;
}
?>

<section class="content-header">
	<div class="content-header-left">
		<h1><?php echo $l['page_products']; ?></h1>
	</div>
	<div class="content-header-right">
		<a href="product-add.php" class="btn btn-primary btn-sm"><?php echo $l['add_product']; ?></a>
	</div>
</section>

<section class="content">
	<div class="row">
		<div class="col-md-12">
			<div class="box box-info">
				<div class="box-body table-responsive">
					<table id="example1" class="table table-bordered table-hover table-striped">
					<thead class="thead-dark">
						<tr>
							<th width="10"><?php echo $l['num']; ?></th>
							<th><?php echo $l['photo']; ?></th>
							<th width="160"><?php echo $l['product_name']; ?></th>
							<th width="60"><?php echo $l['old_price']; ?></th>
							<th width="60"><?php echo $l['current_price']; ?></th>
							<th width="60"><?php echo $l['quantity']; ?></th>
							<th><?php echo $l['featured']; ?></th>
							<th><?php echo $l['active_col']; ?></th>
							<th width="80"><?php echo $l['actions']; ?></th>
						</tr>
					</thead>
					<thead>
					</thead>
					<tbody>
						<?php
						global $productRepo;
						$i=0;
						$result = $productRepo->findAll([], 'p_id DESC');
						foreach ($result as $row) {
							$i++;
							?>
							<tr>
								<td><?php echo $i; ?></td>
								<td style="width:82px;"><img src="<?php echo htmlspecialchars(get_admin_image_url($row['p_featured_photo']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($row['p_name'], ENT_QUOTES, 'UTF-8'); ?>" style="width:80px;"></td>
								<td><?php echo $row['p_name']; ?></td>
								<td>$<?php echo $row['p_old_price']; ?></td>
								<td>$<?php echo $row['p_current_price']; ?></td>
								<td><?php echo $row['p_qty']; ?></td>
								<td>
									<?php if($row['p_is_featured'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">' . $l['yes'] . '</span>';} else {echo '<span class="badge badge-success" style="background-color:red;">' . $l['no'] . '</span>';} ?>
								</td>
								<td>
									<?php if($row['p_is_active'] == 1) {echo '<span class="badge badge-success" style="background-color:green;">' . $l['yes'] . '</span>';} else {echo '<span class="badge badge-danger" style="background-color:red;">' . $l['no'] . '</span>';} ?>
								</td>
								<td style="white-space: nowrap;">								
									<a href="product-edit.php?id=<?php echo $row['p_id']; ?>" class="btn btn-primary btn-xs"><i class="fa fa-edit"></i> <?php echo $l['edit']; ?></a>
									<a href="#" class="btn btn-danger btn-xs" data-href="product-delete.php?id=<?php echo $row['p_id']; ?>" data-toggle="modal" data-target="#confirm-delete"><i class="fa fa-trash"></i> <?php echo $l['delete']; ?></a>  
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


<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="myModalLabel"><?php echo $l['del_modal_title']; ?></h4>
            </div>
            <div class="modal-body">
                <p><?php echo $l['del_modal_body']; ?></p>
                <p style="color:red;"><?php echo $l['del_modal_warning']; ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo $l['cancel']; ?></button>
                <a class="btn btn-danger btn-ok"><?php echo $l['delete']; ?></a>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
