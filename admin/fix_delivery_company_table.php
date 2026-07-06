<?php
require_once('header.php');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user'])) {
    header('location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// تنفيذ إصلاح قاعدة البيانات
if (isset($_POST['fix_database'])) {
    try {
        // إضافة عمود active إذا لم يكن موجوداً
        $pdo->exec("ALTER TABLE `tbl_delivery_company` 
                    ADD COLUMN IF NOT EXISTS `active` TINYINT(1) NOT NULL DEFAULT 0 
                    COMMENT 'حالة الشركة (1 = نشطة، 0 = غير نشطة)'");
        
        // إضافة فهرس على عمود active
        $pdo->exec("ALTER TABLE `tbl_delivery_company` 
                    ADD INDEX IF NOT EXISTS `idx_active` (`active`)");
        
        // تحديث الشركة الأولى لتكون نشطة إذا لم تكن هناك شركة نشطة
        $pdo->exec("UPDATE `tbl_delivery_company` 
                    SET `active` = 1 
                    WHERE `id` = (
                        SELECT `id` FROM (
                            SELECT `id` FROM `tbl_delivery_company` 
                            ORDER BY `id` ASC 
                            LIMIT 1
                        ) AS temp
                    ) 
                    AND NOT EXISTS (
                        SELECT 1 FROM `tbl_delivery_company` WHERE `active` = 1
                    )");
        
        $success_message = 'تم إصلاح جدول شركات التوصيل بنجاح!';
        
    } catch (Exception $e) {
        $error_message = 'خطأ أثناء إصلاح قاعدة البيانات: ' . $e->getMessage();
    }
}

// فحص حالة الجدول
$table_status = '';
try {
    $stmt = $pdo->query("DESCRIBE tbl_delivery_company");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_active_column = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'active') {
            $has_active_column = true;
            break;
        }
    }
    
    if ($has_active_column) {
        $table_status = '<div class="alert alert-success">عمود active موجود في الجدول</div>';
    } else {
        $table_status = '<div class="alert alert-warning">عمود active غير موجود في الجدول - يرجى تشغيل الإصلاح</div>';
    }
} catch (Exception $e) {
    $table_status = '<div class="alert alert-danger">خطأ في فحص الجدول: ' . $e->getMessage() . '</div>';
}
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">إصلاح جدول شركات التوصيل</h3>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?= $success_message ?></div>
                <?php endif; ?>
                
                <?= $table_status ?>
                
                <p>هذا الملف يصلح جدول شركات التوصيل لإضافة عمود <code>active</code> إذا لم يكن موجوداً.</p>
                
                <form method="post">
                    <button type="submit" name="fix_database" class="btn btn-primary" 
                            onclick="return confirm('هل أنت متأكد من تشغيل إصلاح قاعدة البيانات؟')">
                        تشغيل الإصلاح
                    </button>
                    <a href="delivery_list.php" class="btn btn-secondary">عودة إلى قائمة الشركات</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>
