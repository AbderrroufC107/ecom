<?php require_once('header.php'); ?>
<?php
require_once('inc/performance_functions.php');

performance_ensure_tables($pdo);

$error_message = '';
$success_message = '';

$config_keys = [
    'score_completed' => 'نقاط الطلب المكتمل',
    'score_confirmed' => 'نقاط الطلب المؤكد',
    'score_cancelled' => 'نقاط الطلب الملغي (قيمة سالبة)',
    'score_returned' => 'نقاط الطلب المرتجع (قيمة سالبة)',
    'score_late_processing' => 'نقاط التأخير في المعالجة (قيمة سالبة)',
    'late_processing_hours' => 'عدد الساعات قبل اعتبار الطلب متأخراً',
    'ranking_period' => 'فترة الترتيب (all/today/week)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        foreach ($config_keys as $key => $label) {
            $val = trim((string) ($_POST[$key] ?? ''));
            performance_set_setting($pdo, $key, $val);
        }
        $success_message = 'تم حفظ الإعدادات بنجاح.';
    } catch (PDOException $e) {
        $error_message = 'خطأ في الحفظ: ' . $e->getMessage();
    }
}

$settings = [];
foreach ($config_keys as $key => $label) {
    $settings[$key] = performance_get_setting($pdo, $key);
}
?>
<section class="content-header">
    <h1>إعدادات الأداء</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">نقاط التقييم والخصومات</h3>
                </div>
                <form method="post" class="form-horizontal">
                    <div class="box-body">
                        <?php foreach ($config_keys as $key => $label): ?>
                            <div class="form-group">
                                <label for="<?php echo $key; ?>" class="col-sm-3 control-label"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></label>
                                <div class="col-sm-9">
                                    <input type="text" name="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"
                                        id="<?php echo $key; ?>"
                                        value="<?php echo htmlspecialchars($settings[$key], ENT_QUOTES, 'UTF-8'); ?>"
                                        class="form-control">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="save_settings" class="btn btn-primary pull-right">حفظ الإعدادات</button>
                    </div>
                </form>
            </div>

            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">كيفية احتساب النقاط</h3>
                </div>
                <div class="box-body">
                    <p>يتم احتساب النقاط لكل موظف بناءً على أدائه في الطلبات المسندة إليه:</p>
                    <ul>
                        <li><strong>الطلب المكتمل:</strong> يتم إضافة نقاط إيجابية.</li>
                        <li><strong>الطلب المؤكد:</strong> يتم إضافة نقاط إيجابية (أقل من المكتمل).</li>
                        <li><strong>الطلب الملغي:</strong> يتم خصم نقاط (قيمة سالبة).</li>
                        <li><strong>الطلب المرتجع:</strong> يتم خصم نقاط كبيرة (قيمة سالبة).</li>
                        <li><strong>التأخير:</strong> يتم خصم نقاط إذا تجاوز وقت المعالجة الحد المحدد.</li>
                    </ul>
                    <p>يتم تحديث الترتيب تلقائياً بناءً على مجموع النقاط.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once('footer.php'); ?>
