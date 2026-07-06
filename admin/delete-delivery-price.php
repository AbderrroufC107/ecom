<?php
require_once('header.php');
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $dbRepo->prepare("DELETE FROM tbl_delivery_price WHERE id = ?");
    $stmt->execute([$id]);
}
header('Location: delivery-company.php');
exit; 