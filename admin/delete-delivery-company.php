<?php
require_once('header.php');
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // حذف أسعار التوصيل المرتبطة
    $stmt1 = $dbRepo->prepare("DELETE FROM tbl_delivery_price WHERE company_id=?");
    $stmt1->execute([$id]);
    // حذف الشركة
    $stmt2 = $dbRepo->prepare("DELETE FROM tbl_delivery_company WHERE id=?");
    $stmt2->execute([$id]);
}
header('Location: delivery_list.php?msg=company_deleted');
exit; 