<?php require_once('header.php'); ?>

<?php
$statement = $pdo->prepare("SELECT * FROM tbl_settings WHERE id=1");
$statement->execute();
$result = $statement->fetchAll(PDO::FETCH_ASSOC);                           
foreach ($result as $row) {
    $banner_forget_password = $row['banner_forget_password'];
}
$banner_forget_password_url = trim((string)get_front_image_url($banner_forget_password));
?>

<div class="page-banner" style="background-color:#444;<?php if ($banner_forget_password_url !== ''): ?>background-image: url('<?php echo htmlspecialchars($banner_forget_password_url, ENT_QUOTES, 'UTF-8'); ?>');<?php endif; ?>">
    <div class="inner">
        <h1><?php echo LANG_VALUE_149; ?></h1>
    </div>
</div>

<div class="page">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="user-content">
                    <?php echo LANG_VALUE_146; ?><br><br>
                    <a href="<?php echo BASE_URL; ?>login.php" style="color:#e4144d;font-weight:bold;"><?php echo LANG_VALUE_11; ?></a>
                </div>                
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
