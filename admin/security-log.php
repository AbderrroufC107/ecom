<?php
ob_start();
session_start();
require_once('inc/config.php');
require_once('inc/functions.php');
require_once('inc/audit.php');

if (!isset($_SESSION['user']) && !isset($_SESSION['store_user'])) {
    header('location: login.php');
    exit;
}

$is_admin = isset($_SESSION['user']);
$user_type = $is_admin ? 'admin' : 'employee';
$user_id = $is_admin ? $_SESSION['user']['id'] : $_SESSION['store_user']['id'];

require_once('header.php');
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>سجل الأمان (Security Log)</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-body">
                    <!-- Filters -->
                    <div class="row" style="margin-bottom: 20px;">
                        <?php if ($is_admin): ?>
                        <div class="col-md-3">
                            <label>المستخدم</label>
                            <select id="filter_user" class="form-control select2">
                                <option value="">الجميع</option>
                                <optgroup label="المدراء">
                                    <?php
                                    $stmt = $dbRepo->query("SELECT id, full_name FROM tbl_user ORDER BY full_name ASC");
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='admin_panel_{$row['id']}'>مدير: {$row['full_name']}</option>";
                                    }
                                    ?>
                                </optgroup>
                                <optgroup label="الموظفين">
                                    <?php
                                    $stmt = $dbRepo->query("SELECT id, name FROM tbl_employee ORDER BY name ASC");
                                    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='staff_portal_{$row['id']}'>موظف: {$row['name']}</option>";
                                    }
                                    ?>
                                </optgroup>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-2">
                            <label>نوع العملية</label>
                            <select id="filter_action" class="form-control">
                                <option value="">الكل</option>
                                <option value="order_created">إنشاء طلب</option>
                                <option value="order_updated">تعديل طلب</option>
                                <option value="order_deleted">حذف طلب</option>
                                <option value="order_confirmed">تأكيد طلب</option>
                                <option value="order_cancelled">إلغاء طلب</option>
                                <option value="product_updated">تعديل منتج</option>
                                <option value="stock_updated">تعديل مخزون</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label>الخطورة</label>
                            <select id="filter_risk" class="form-control">
                                <option value="">الكل</option>
                                <option value="INFO">INFO (عادي)</option>
                                <option value="WARNING">WARNING (تنبيه)</option>
                                <option value="CRITICAL">CRITICAL (حرج)</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label>النتيجة</label>
                            <select id="filter_result" class="form-control">
                                <option value="">الكل</option>
                                <option value="SUCCESS">نجاح</option>
                                <option value="FAILED">فشل</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label>من تاريخ / إلى تاريخ</label>
                            <div class="input-group">
                                <input type="date" id="filter_date_from" class="form-control" style="width: 50%;">
                                <input type="date" id="filter_date_to" class="form-control" style="width: 50%;">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table id="activityTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>رقم</th>
                                    <th>الرقم المرجعي</th>
                                    <th>التاريخ والوقت</th>
                                    <th>المستخدم</th>
                                    <th>العملية</th>
                                    <th>الكائن (Entity)</th>
                                    <th>الخطورة</th>
                                    <th>النتيجة</th>
                                    <th>IP / Agent</th>
                                    <th>التفاصيل</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">تفاصيل العملية وتغييرات البيانات</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="إغلاق"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
            <div class="col-md-6">
                <h5>البيانات القديمة (Before)</h5>
                <pre id="data-before" style="background: #fcf2f2; border-color: #dfb5b4; white-space: pre-wrap; font-size: 12px;"></pre>
            </div>
            <div class="col-md-6">
                <h5>البيانات الجديدة (After)</h5>
                <pre id="data-after" style="background: #f2fcf3; border-color: #b4dfb9; white-space: pre-wrap; font-size: 12px;"></pre>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    var table = $('#activityTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "ajax-security-log.php",
            "type": "POST",
            "data": function(d) {
                d.user = $('#filter_user').val();
                d.action = $('#filter_action').val();
                d.risk = $('#filter_risk').val();
                d.result = $('#filter_result').val();
                d.date_from = $('#filter_date_from').val();
                d.date_to = $('#filter_date_to').val();
            }
        },
        "order": [[ 0, "desc" ]],
        "columns": [
            { "data": "id" },
            { "data": "audit_ref" },
            { "data": "created_at" },
            { "data": "user_name" },
            { "data": "action_type" },
            { "data": "entity" },
            { "data": "risk_level" },
            { "data": "result" },
            { "data": "network" },
            { "data": "actions", "orderable": false, "searchable": false }
        ],
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/Arabic.json"
        },
        "dom": 'Bfrtip',
        "buttons": ['copy', 'csv', 'excel', 'pdf', 'print']
    });

    $('#filter_user, #filter_action, #filter_risk, #filter_result, #filter_date_from, #filter_date_to').on('change', function() {
        table.ajax.reload();
    });

    $('#activityTable tbody').on('click', '.view-details', function() {
        var old_data = $(this).attr('data-old') || '{}';
        var new_data = $(this).attr('data-new') || '{}';
        
        try { old_data = JSON.stringify(JSON.parse(old_data), null, 4); } catch(e){}
        try { new_data = JSON.stringify(JSON.parse(new_data), null, 4); } catch(e){}

        $('#data-before').text(old_data);
        $('#data-after').text(new_data);
        $('#detailsModal').modal('show');
    });
});
</script>

<?php require_once('footer.php'); ?>
