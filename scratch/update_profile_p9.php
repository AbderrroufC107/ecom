<?php
$file = 'c:/xampp/htdocs/ecom/admin/profile-edit.php';
$content = file_get_contents($file);

// 1. Add Assignment Tab Header
if (strpos($content, '<li class="active"><a href="#tab_1"') !== false && strpos($content, '#tab_assignment') === false) {
    $content = str_replace(
        '<li><a href="#tab_telegram"',
        "<?php if (!\$is_employee): ?>\n\t\t\t\t\t\t<li><a href=\"#tab_assignment\" data-toggle=\"tab\">نظام توزيع الطلبات</a></li>\n\t\t\t\t\t\t<?php endif; ?>\n\t\t\t\t\t\t<li><a href=\"#tab_telegram\"",
        $content
    );
}

// 2. Add form handler logic for Assignment Tab
$handler_logic = <<<EOF
if(isset(\$_POST['form_assignment']) && !\$is_employee) {
    try {
        \$participate = isset(\$_POST['participate_in_assignment']) ? 1 : 0;
        \$weight = (int)\$_POST['assignment_weight'];
        \$max_orders = (int)\$_POST['max_active_orders'];
        \$status = \$_POST['availability_status'];

        if(\$weight < 1) \$weight = 1;
        if(\$max_orders < 1) \$max_orders = 1;

        // Audit Log
        if (function_exists('audit_log')) {
            audit_log(\$pdo, 'Update Profile', "Admin {$_SESSION['user']['id']} updated assignment settings. Participate: \$participate, Weight: \$weight, Status: \$status", \$_SESSION['user']['id']);
        }

        \$stmt = \$pdo->prepare("UPDATE tbl_user SET participate_in_assignment=?, assignment_weight=?, availability_status=?, max_active_orders=? WHERE id=?");
        \$stmt->execute([\$participate, \$weight, \$status, \$max_orders, \$_SESSION['user']['id']]);
        
        \$success_message = 'تم تحديث إعدادات التوزيع بنجاح';
        
        // Refresh user_data
        \$statement = \$pdo->prepare("SELECT * FROM tbl_user WHERE id=?");
        \$statement->execute([\$_SESSION['user']['id']]);
        \$user_data = \$statement->fetch(PDO::FETCH_ASSOC);

    } catch(Exception \$e) {
        \$error_message = \$e->getMessage();
    }
}
EOF;

if (strpos($content, 'if(isset($_POST[\'form1\'])) {') !== false && strpos($content, 'form_assignment') === false) {
    $content = str_replace('if(isset($_POST[\'form1\'])) {', $handler_logic . "\n\nif(isset(\$_POST['form1'])) {", $content);
}

// 3. Add Assignment Tab Body
$tab_body = <<<EOF
                        <?php if (!\$is_employee): ?>
                        <div class="tab-pane" id="tab_assignment">
                            <form class="form-horizontal" action="" method="post">
                            <div class="box box-warning">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-share-alt"></i> إعدادات استقبال الطلبات التلقائية للمدير</h3>
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">المشاركة في التوزيع</label>
                                        <div class="col-sm-4">
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" name="participate_in_assignment" <?php echo \$user_data['participate_in_assignment'] == 1 ? 'checked' : ''; ?>>
                                                    تفعيل استلام الطلبات تلقائياً كمدير
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">الحالة الحالية</label>
                                        <div class="col-sm-4">
                                            <select name="availability_status" class="form-control">
                                                <option value="Available" <?php echo \$user_data['availability_status'] == 'Available' ? 'selected' : ''; ?>>متاح (Available)</option>
                                                <option value="Busy" <?php echo \$user_data['availability_status'] == 'Busy' ? 'selected' : ''; ?>>مشغول (Busy)</option>
                                                <option value="Break" <?php echo \$user_data['availability_status'] == 'Break' ? 'selected' : ''; ?>>في استراحة (Break)</option>
                                                <option value="Vacation" <?php echo \$user_data['availability_status'] == 'Vacation' ? 'selected' : ''; ?>>في إجازة (Vacation)</option>
                                                <option value="Offline" <?php echo \$user_data['availability_status'] == 'Offline' ? 'selected' : ''; ?>>غير متصل (Offline)</option>
                                            </select>
                                            <p class="help-block">لن يتم تحويل طلبات جديدة لك إلا إذا كانت الحالة "متاح".</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">حصة التوزيع (الوزن)</label>
                                        <div class="col-sm-4">
                                            <input type="number" name="assignment_weight" class="form-control" value="<?php echo \$user_data['assignment_weight']; ?>" min="1">
                                            <p class="help-block">رقم صحيح (مثال: 1 أو 2 أو 3). كلما زاد الرقم، زادت حصتك من الطلبات مقارنة بالبقية.</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label">الحد الأقصى للطلبات المفتوحة</label>
                                        <div class="col-sm-4">
                                            <input type="number" name="max_active_orders" class="form-control" value="<?php echo \$user_data['max_active_orders']; ?>" min="1">
                                            <p class="help-block">إذا تجاوزت طلباتك قيد التنفيذ هذا الرقم، سيتوقف النظام عن تحويل طلبات جديدة لك.</p>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="col-sm-3 control-label"></label>
                                        <div class="col-sm-6">
                                            <button type="submit" class="btn btn-warning" name="form_assignment">حفظ إعدادات التوزيع</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </form>
                        </div>
                        <?php endif; ?>
EOF;

if (strpos($content, '<div class="tab-pane" id="tab_telegram">') !== false && strpos($content, 'tab_assignment') === false) {
    $content = str_replace('<div class="tab-pane" id="tab_telegram">', $tab_body . "\n\n<div class=\"tab-pane\" id=\"tab_telegram\">", $content);
}

file_put_contents($file, $content);
echo "profile-edit.php updated successfully.\n";
