<?php
require_once('header.php');

if (!isset($_REQUEST['id'])) {
    header('location: delete_trash.php');
    exit;
}

try {
    // حذف الطلب من جدول المهملات نهائيًا
    $statement = $dbRepo->prepare("DELETE FROM tbl_order_trash WHERE id=?");
    $statement->execute([$_REQUEST['id']]);

    // إعادة التوجيه إلى صفحة المهملات
    header('location: delete_trash_aff.php');
    exit;

} catch (Exception $e) {
    // في حال حدوث خطأ، أعد التوجيه مع رسالة خطأ (اختياريًا يمكن عرضها)
    header('location: delete_trash.php');
    exit;
}
?>
