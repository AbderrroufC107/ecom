<?php
require_once('header.php');
?>
<?php
admin_ensure_order_call_log_table($pdo);
admin_ensure_order_status_log_table($pdo);
admin_ensure_ecotrack_setting_columns($pdo);
admin_ensure_order_ecotrack_columns($pdo);
if (function_exists('admin_ensure_zrexpress_setting_columns')) {
    admin_ensure_zrexpress_setting_columns($pdo);
}
if (function_exists('admin_ensure_order_zrexpress_columns')) {
    admin_ensure_order_zrexpress_columns($pdo);
}
require_once('inc/employee_functions.php');
employee_ensure_tables($pdo);
employee_auto_assign_unassigned($pdo, 100);

$settings = front_get_settings($pdo);
$ecotrack_settings = ecotrack_normalize_settings($settings);
$ecotrack_ready = ecotrack_is_configured($ecotrack_settings);

$all_employees = employee_get_all($pdo, true);

$is_admin = ($_SESSION['user']['role'] === 'Admin' || $_SESSION['user']['role'] === 'Super Admin' || $_SESSION['user']['role'] === 'Manager');

$order_summary = [
    'total' => 0,
    'pending' => 0,
    'completed' => 0,
    'issues' => 0,
];
try {
    $summary_stmt = $dbRepo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN order_status IN ('Returned', 'Cancelled', 'Failed') THEN 1 ELSE 0 END) AS issues
        FROM tbl_order
    ");
    $summary_row = $summary_stmt ? $summary_stmt->fetch(PDO::FETCH_ASSOC) : [];
    foreach ($order_summary as $key => $value) {
        $order_summary[$key] = (int)($summary_row[$key] ?? 0);
    }
} catch (Exception $e) {
    error_log('Order summary cards failed: ' . $e->getMessage());
}

?>

<!-- Workspace Styling -->
<style>
.workspace-tabs {
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
}
.workspace-tabs .nav-tabs {
    border-bottom: none;
}
.workspace-tabs .nav-tabs > li > a {
    color: #555;
    font-weight: 600;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 15px;
    border-radius: 0;
}
.workspace-tabs .nav-tabs > li.active > a,
.workspace-tabs .nav-tabs > li.active > a:hover,
.workspace-tabs .nav-tabs > li.active > a:focus {
    color: #3c8dbc;
    border: none;
    border-bottom: 3px solid #3c8dbc;
    background: transparent;
}
.workspace-table-container {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 8px 22px rgba(15,23,42,.04);
    padding: 12px;
    overflow-x: auto;
}
#ordersTable {
    width: 100% !important;
    margin-top: 10px;
}
#ordersTable th {
    background-color: #f9fafb;
    color: #4b5563;
    font-weight: 600;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
}
#ordersTable td {
    vertical-align: middle;
    border-top: 1px solid #e5e7eb;
}
.workspace-actions {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex-wrap: nowrap;
    white-space: nowrap;
}
.workspace-actions .btn {
    border-radius: 7px;
    font-weight: 700;
}
.workspace-actions .btn-xs {
    padding: 5px 8px;
}
.workspace-actions .btn .fa {
    margin-left: 4px;
}
body.admin-react-ready .workspace-table-container {
    border-radius: var(--admin-radius, 8px);
    box-shadow: 0 10px 24px rgba(15,23,42,.04);
}
body.admin-react-ready #ordersTable {
    min-width: 980px;
}
.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    line-height: 32px;
    text-align: center;
    border-radius: 4px;
    margin-left: 5px;
}
.table-hover > tbody > tr:hover {
    background-color: #f3f4f6;
}
.dropdown-menu {
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}
.dataTables_wrapper .dataTables_processing {
    background: rgba(255,255,255,0.9);
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.orders-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 15px;
}
.order-summary-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px;
    box-shadow: 0 8px 22px rgba(15,23,42,.04);
}
.order-summary-card small {
    display: block;
    color: #64748b;
    font-weight: 700;
    margin-bottom: 6px;
}
.order-summary-card strong {
    display: block;
    color: #0f172a;
    font-size: 24px;
    line-height: 1.2;
}
.order-summary-card span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #64748b;
    font-size: 12px;
    margin-top: 7px;
}
.order-summary-card.is-primary { border-top: 3px solid #4f46e5; }
.order-summary-card.is-warning { border-top: 3px solid #d97706; }
.order-summary-card.is-success { border-top: 3px solid #14b8a6; }
.order-summary-card.is-danger { border-top: 3px solid #dc2626; }
@media (max-width: 991px) {
    .orders-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 560px) {
    .orders-summary-grid {
        grid-template-columns: 1fr;
    }
}
.row-other-manager {
    background-color: #f5f5f5 !important;
    opacity: 0.65;
}
.row-other-manager td {
    color: #999 !important;
}
.row-other-manager:hover {
    opacity: 0.8;
    background-color: #f0f0f0 !important;
}
.row-other-manager .workspace-actions .btn {
    display: none;
}
.row-other-manager .workspace-actions .label {
    display: inline-block !important;
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة الطلبات <small>مساحة العمل الاحترافية</small></h1>
    </div>
</section>

<section class="content">

    <div class="orders-summary-grid">
        <div class="order-summary-card is-primary">
            <small>إجمالي الطلبات</small>
            <strong><?php echo (int)$order_summary['total']; ?></strong>
            <span><i class="fa fa-shopping-cart"></i> كل السجلات</span>
        </div>
        <div class="order-summary-card is-warning">
            <small>قيد المراجعة</small>
            <strong><?php echo (int)$order_summary['pending']; ?></strong>
            <span><i class="fa fa-clock-o"></i> تحتاج متابعة</span>
        </div>
        <div class="order-summary-card is-success">
            <small>مؤكدة</small>
            <strong><?php echo (int)$order_summary['completed']; ?></strong>
            <span><i class="fa fa-check"></i> مكتملة أو مؤكدة</span>
        </div>
        <div class="order-summary-card is-danger">
            <small>مشاكل ومرتجعات</small>
            <strong><?php echo (int)$order_summary['issues']; ?></strong>
            <span><i class="fa fa-exclamation-triangle"></i> تحتاج انتباه</span>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="row" style="margin-bottom: 15px;">
        <?php if ($is_admin): ?>
        <div class="col-md-3">
            <select id="employeeFilter" class="form-control">
                <option value="">جميع الموظفين</option>
                <option value="unassigned">غير موزعة</option>
                <?php foreach ($all_employees as $emp): ?>
                    <option value="<?php echo (int)$emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-9 text-right">
            <button type="button" class="btn btn-default js-refresh-orders"><i class="fa fa-refresh"></i> تحديث</button>
        </div>
    </div>

    <!-- Workspace Tabs -->
    <div class="nav-tabs-custom workspace-tabs" style="box-shadow: none; background: transparent; margin-bottom: 15px;">
        <ul class="nav nav-tabs" id="statusTabs" style="border-bottom: 1px solid #ddd; list-style: none; padding: 0; margin: 0; display: flex; flex-wrap: wrap; gap: 5px;">
            <li class="active" data-tab="all" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">الكل (نشطة)</a></li>
            <li data-tab="new" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">جديدة</a></li>
            <li data-tab="needs_conf" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">تحتاج تأكيد</a></li>
            <li data-tab="follow_up" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">قيد المتابعة</a></li>
            <li data-tab="waiting_dispatch" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">بانتظار الإرسال</a></li>
            <li data-tab="dispatched" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">مرسلة</a></li>
            <li data-tab="completed" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">مؤكدة</a></li>
            <li data-tab="issues" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">مشاكل</a></li>
            <li data-tab="returns" style="list-style:none;"><a href="#" style="text-decoration:none; display:block;">مرتجعات</a></li>
        </ul>
    </div>

    <!-- DataTables Container -->
    <div class="workspace-table-container table-responsive">
        <table id="ordersTable" class="table table-hover">
            <thead>
                <tr>
                    <th style="width: 80px;">الطلب</th>
                    <th style="width: 150px;">العميل</th>
                    <th>المنتج</th>
                    <th style="width: 100px;">الموظف</th>
                    <th style="width: 120px;">التوصيل</th>
                    <th style="width: 100px;">الحالة</th>
                    <th style="width: 160px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by AJAX -->
            </tbody>
        </table>
    </div>

</section>

<!-- Professional Drawer for Order Details -->
<div id="orderDrawer" class="professional-drawer" tabindex="-1" role="dialog">
    <div class="drawer-dialog" role="document">
        <div class="drawer-content" id="drawerContent">
            <!-- Loading state initially -->
            <div class="drawer-body" style="display:flex; flex-direction:column; height:100%;">
                <div class="text-center" style="padding: 50px; flex:1;"><i class="fa fa-spinner fa-spin fa-3x text-muted"></i><br><br>جاري التحميل...</div>
            </div>
        </div>
    </div>
</div>

<!-- Iframe Drawer for Actions (Edit, Follow-up, Confirm) -->
<div id="iframeDrawer" class="professional-drawer" tabindex="-1" role="dialog">
    <div class="drawer-dialog" role="document" style="width: 1200px; max-width: 95vw;">
        <div class="drawer-content" style="display: flex; flex-direction: column; height: 100%;">
            <div class="drawer-header" style="flex: 0 0 auto;">
                <h4 class="drawer-title" id="iframeDrawerTitle" style="margin:0; font-size:18px; font-weight:700; color:var(--e-ui-text-main);">إجراء</h4>
                <button type="button" class="close-btn" onclick="closeIframeDrawer()"><i class="fa fa-times"></i></button>
            </div>
            <div class="drawer-body" style="padding: 0; background: var(--e-ui-bg-body); overflow: hidden; display: flex; flex-direction: column; flex: 1;">
                <iframe id="iframeDrawerContent" src="about:blank" style="width: 100%; height: 100%; border: none; flex: 1;"></iframe>
            </div>
            <div class="drawer-footer" style="flex: 0 0 auto; padding: 15px 20px; border-top: 1px solid var(--e-ui-border); display: flex; justify-content: flex-end; gap: 10px; background: var(--e-ui-bg-panel);">
                <button type="button" class="btn btn-default" onclick="closeIframeDrawer()" style="border-radius: 8px; font-weight: 600; padding: 8px 20px;">إغلاق</button>
                <button type="button" id="iframeDrawerSaveBtn" class="btn btn-primary" onclick="submitIframeForm()" style="border-radius: 8px; font-weight: 600; padding: 8px 20px; background: var(--e-ui-primary); border-color: var(--e-ui-primary); color: #fff;">تأكيد / حفظ التعديلات</button>
            </div>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>

<!-- DataTables Scripts -->
<script>
var ordersTable;
var currentTab = 'all';

$(document).ready(function() {

    // Initialize DataTable
    ordersTable = $('#ordersTable').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": {
            "url": "ajax/orders_workspace.php",
            "type": "POST",
            "data": function ( d ) {
                d.tab = currentTab;
                d.employee = $('#employeeFilter').val() || '';
            }
        },
        "order": [[ 0, "desc" ]],
        "pageLength": 25,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
        },
        "columnDefs": [
            { "orderable": false, "targets": 6 } // Actions column
        ]
    });

    // Handle Tabs Click
    $('#statusTabs li').on('click', function(e) {
        e.preventDefault();
        $('#statusTabs li').removeClass('active');
        $(this).addClass('active');
        currentTab = $(this).data('tab');
        ordersTable.ajax.reload();
    });

    // Handle Employee Filter Change
    $('#employeeFilter').on('change', function() {
        ordersTable.ajax.reload();
    });

    $(document).on('click', '.js-refresh-orders', function(e) {
        e.preventDefault();
        if (ordersTable) {
            ordersTable.ajax.reload(null, false);
        }
    });

    $(document).on('click', '.js-close-iframe-modal', function(e) {
        e.preventDefault();
        closeIframeDrawer();
    });

    $(document).on('click', '.js-close-order-drawer', function(e) {
        e.preventDefault();
        closeOrderDrawer();
    });

    $(document).on('click', '.js-open-iframe-modal', function(e) {
        e.preventDefault();
        var $button = $(this);
        openIframeDrawer($button.data('title') || 'إدارة الطلب', $button.data('url') || '#');
    });

    $(document).on('click', '.js-order-action', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.data('action');
        var id = parseInt($button.data('id'), 10);

        if (!id) return;

        if (action === 'view') {
            openOrderDrawer(id);
            return;
        }

        if(action === 'manage' || action === 'edit' || action === 'confirm' || action === 'follow') {
            openIframeDrawer($button.data('title') || ('إدارة الطلب #' + id), $button.data('url') || ('order-details.php?id=' + id));
        } else if (action === 'delete') {
            if (confirm($button.data('confirm') || 'هل أنت متأكد من حذف هذا الطلب؟')) {
                window.location.href = 'order-delete.php?id=' + id;
            }
        }
    });

});

// Actions
function openOrderDrawer(id) {

    $('#drawerContent').html('<div class="drawer-body" style="display:flex; flex-direction:column; height:100%;"><div class="text-center" style="padding: 50px; flex:1;"><i class="fa fa-spinner fa-spin fa-3x text-muted"></i><br><br>جاري التحميل...</div></div>');

    // Open new drawer
    $('#orderDrawer').show();
    setTimeout(function() {
        $('#orderDrawer').addClass('in');
    }, 10);

    $.post('ajax/order_details_ajax.php', { id: id }, function(response) {
        $('#drawerContent').html(response);
    }).fail(function() {
        $('#drawerContent').html('<div class="drawer-body"><div class="alert alert-danger" style="margin:20px;">حدث خطأ أثناء تحميل التفاصيل.</div></div>');
    });
}

function closeOrderDrawer() {

    $('#orderDrawer').removeClass('in').delay(300).queue(function(next){
        $(this).hide();
        next();
    });
}

function openIframeDrawer(title, url) {

    $('#iframeDrawerTitle').text(title);
    $('#iframeDrawerContent').attr('src', url + (url.indexOf('?') !== -1 ? '&' : '?') + 'iframe=1');
    $('#iframeDrawer').show();
    setTimeout(function() {
        $('#iframeDrawer').addClass('in');
    }, 10);
}

function closeIframeDrawer() {

    $('#iframeDrawer').removeClass('in');
    setTimeout(function() {
        $('#iframeDrawer').hide();
        $('#iframeDrawerContent').attr('src', 'about:blank');
        ordersTable.ajax.reload(null, false); // Reload table just in case they edited/confirmed something
    }, 300);
}

function submitIframeForm() {

    var iframe = document.getElementById('iframeDrawerContent');
    if (iframe && iframe.contentWindow) {
        var form = iframe.contentWindow.document.querySelector('form');
        if (form) {
            form.submit();
        } else {
            alert('لا توجد استمارة قابلة للحفظ في هذه الصفحة.');
        }
    }
}


</script>
