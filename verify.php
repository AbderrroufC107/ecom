<?php require_once('header.php'); ?>

<?php
if (!isset($_GET['email'], $_GET['token']) || $_GET['email'] === '' || $_GET['token'] === '') {
    header('location: ' . BASE_URL);
    exit;
}

$supports_email_verification = db_table_has_columns($pdo, 'tbl_customer', ['cust_email', 'cust_token', 'cust_status']);
if (!$supports_email_verification) {
    $error_message = '<div class="alert alert-danger">ميزة التحقق عبر البريد الإلكتروني غير مفعلة في بنية قاعدة البيانات الحالية.</div>';
} else {
    $statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE cust_email=? LIMIT 1");
    $statement->execute([trim((string)$_GET['email'])]);
    $customer = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$customer || trim((string)($customer['cust_token'] ?? '')) === '' || trim((string)$_GET['token']) !== trim((string)$customer['cust_token'])) {
        $error_message = '<div class="alert alert-danger">رابط التحقق غير صالح أو منتهي الصلاحية.</div>';
    } else {
        $statement = $pdo->prepare("UPDATE tbl_customer SET cust_token=?, cust_status=? WHERE cust_email=?");
        $statement->execute(['', 1, trim((string)$_GET['email'])]);

        $success_message = '<div class="alert alert-success">تم تفعيل الحساب بنجاح. يمكنك الآن تسجيل الدخول.</div>'
            . '<p><a href="' . BASE_URL . 'login.php" style="font-weight:bold;">الانتقال إلى صفحة الدخول</a></p>';
    }
}
?>

<div class="page-banner" style="background-color:#444;">
    <div class="inner">
        <h1>تأكيد الحساب</h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="user-content">
                    <?php 
                        echo $error_message;
                        echo $success_message;
                    ?>
                </div>                
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
