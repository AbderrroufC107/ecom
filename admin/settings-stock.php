<?php
require_once('header.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Super Admin') {
    echo '<script>window.location.href="login.php";</script>';
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_POST['form1'])) {
    $valid = 1;

    $stock_low_threshold = (int)$_POST['stock_low_threshold'];
    $stock_critical_threshold = (int)$_POST['stock_critical_threshold'];
    $stock_alarm_enabled = isset($_POST['stock_alarm_enabled']) ? 1 : 0;
    $stock_sound_enabled = isset($_POST['stock_sound_enabled']) ? 1 : 0;

    if ($stock_low_threshold < 0 || $stock_critical_threshold < 0) {
        $valid = 0;
        $error_message = 'يجب أن تكون القيم أرقاماً موجبة.';
    }

    if ($valid == 1) {
        $statement = $dbRepo->prepare("UPDATE tbl_settings SET stock_low_threshold=?, stock_critical_threshold=?, stock_alarm_enabled=?, stock_sound_enabled=? WHERE id=1");
        $statement->execute([$stock_low_threshold, $stock_critical_threshold, $stock_alarm_enabled, $stock_sound_enabled]);

        $success_message = 'تم تحديث إعدادات المخزون بنجاح.';
    }
}

$statement = $dbRepo->prepare("SELECT stock_low_threshold, stock_critical_threshold, stock_alarm_enabled, stock_sound_enabled FROM tbl_settings WHERE id=1");
$statement->execute();
$settings = $statement->fetch(PDO::FETCH_ASSOC);

$low = $settings['stock_low_threshold'] ?? 5;
$critical = $settings['stock_critical_threshold'] ?? 2;
$alarm_enabled = $settings['stock_alarm_enabled'] ?? 1;
$sound_enabled = $settings['stock_sound_enabled'] ?? 1;
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إعدادات تنبيهات المخزون</h1>
    </div>
    <div class="content-header-right">
        <a href="stock.php" class="btn btn-primary btn-sm">العودة إلى المخزون</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if($error_message): ?>
            <div class="callout callout-danger"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>
            <?php if($success_message): ?>
            <div class="callout callout-success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>

            <form class="form-horizontal" action="" method="post">
                <div class="box box-info">
                    <div class="box-body">
                        
                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">الحد الأدنى للمخزون (Low Stock) <span>*</span></label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="stock_low_threshold" value="<?php echo $low; ?>" min="0">
                                <small>سيتم إصدار تنبيه أصفر إذا وصلت الكمية إلى هذا الحد أو أقل.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">الحد الحرج للمخزون (Critical/Out) <span>*</span></label>
                            <div class="col-sm-4">
                                <input type="number" class="form-control" name="stock_critical_threshold" value="<?php echo $critical; ?>" min="0">
                                <small>يجب أن يكون أقل من الحد الأدنى.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">تفعيل التنبيهات المنبثقة</label>
                            <div class="col-sm-4">
                                <label class="switch">
                                    <input type="checkbox" name="stock_alarm_enabled" <?php if($alarm_enabled == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label">تفعيل صوت الإنذار</label>
                            <div class="col-sm-4">
                                <label class="switch">
                                    <input type="checkbox" name="stock_sound_enabled" <?php if($sound_enabled == 1) echo 'checked'; ?>>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="" class="col-sm-3 control-label"></label>
                            <div class="col-sm-6">
                                <button type="submit" class="btn btn-success pull-left" name="form1">حفظ الإعدادات</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<style>
.switch { position: relative; display: inline-block; width: 50px; height: 24px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 4px; bottom: 4px; background-color: white; transition: .4s; }
input:checked + .slider { background-color: #2196F3; }
input:focus + .slider { box-shadow: 0 0 1px #2196F3; }
input:checked + .slider:before { transform: translateX(26px); }
.slider.round { border-radius: 34px; }
.slider.round:before { border-radius: 50%; }
</style>

<?php require_once('footer.php'); ?>
