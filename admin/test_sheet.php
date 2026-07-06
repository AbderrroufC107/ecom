<?php
require_once('includes/order_sheet.php');

if(testGoogleSheet()) {
    echo "تم إرسال البيانات التجريبية بنجاح!";
} else {
    echo "حدث خطأ في إرسال البيانات";
}
?> 