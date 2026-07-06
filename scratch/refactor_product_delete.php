<?php
$content = file_get_contents('C:/xampp/htdocs/ecom/admin/product-delete.php');

$search1 = <<<PHP
	// Check the product exists
	\$statement = \$pdo->prepare("SELECT p_featured_photo FROM tbl_product WHERE p_id=?");
	\$statement->execute([\$id]);
	\$product = \$statement->fetch(PDO::FETCH_ASSOC);
PHP;

$replace1 = <<<PHP
	global \$productRepo;
	// Check the product exists for the current tenant
	\$product = \$productRepo->find(\$id);
PHP;

$search2 = <<<PHP
	// Delete from tbl_product
	\$statement = \$pdo->prepare("DELETE FROM tbl_product WHERE p_id=?");
	\$statement->execute([\$id]);
PHP;

$replace2 = <<<PHP
	// Delete from tbl_product (tenant-safe)
	\$productRepo->delete(\$id);
PHP;

$content = str_replace($search1, $replace1, $content);
$content = str_replace($search2, $replace2, $content);

file_put_contents('C:/xampp/htdocs/ecom/admin/product-delete.php', $content);
echo "Updated product-delete.php\n";
