<?php require_once('header.php'); ?>
<?php
require_once('inc/employee_functions.php');
require_once('inc/performance_functions.php');

performance_ensure_tables($pdo);

$current_manager_id = (int)($_SESSION['user']['id'] ?? 0);
$is_super_admin = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin');
$effective_manager_id = $is_super_admin ? null : $current_manager_id;

$period = trim((string) ($_GET['period'] ?? 'all'));
$limit = 50;
$ranking = performance_get_ranking($pdo, $limit, $period, $effective_manager_id);

$widgets = performance_get_dashboard_widgets($pdo, $effective_manager_id);
?>
<section class="content-header">
    <h1>ترتيب الموظفين</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3><?php echo $widgets['top_employee'] ? htmlspecialchars($widgets['top_employee']['full_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></h3>
                    <p>أفضل موظف</p>
                </div>
                <div class="icon"><i class="fa fa-trophy"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3><?php echo $widgets['worst_employee'] ? htmlspecialchars($widgets['worst_employee']['full_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></h3>
                    <p>أسوأ موظف</p>
                </div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3><?php echo $widgets['delivery_rate']; ?>%</h3>
                    <p>معدل التوصيل الإجمالي</p>
                </div>
                <div class="icon"><i class="fa fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?php echo $widgets['pending_orders']; ?></h3>
                    <p>الطلبات المعلقة</p>
                </div>
                <div class="icon"><i class="fa fa-clock-o"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xs-12">
            <div class="box">
                <div class="box-header">
                    <h3 class="box-title">قائمة الترتيب</h3>
                    <div class="box-tools pull-right">
                        <a href="?period=all" class="btn btn-default btn-sm <?php echo $period === 'all' ? 'active' : ''; ?>">الكل</a>
                        <a href="?period=today" class="btn btn-default btn-sm <?php echo $period === 'today' ? 'active' : ''; ?>">اليوم</a>
                        <a href="?period=week" class="btn btn-default btn-sm <?php echo $period === 'week' ? 'active' : ''; ?>">هذا الأسبوع</a>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الاسم</th>
                                <th>النقاط</th>
                                <th>مكتمل</th>
                                <th>مؤكد</th>
                                <th>ملغي</th>
                                <th>مرتجع</th>
                                <th>نسبة التوصيل</th>
                                <th>نسبة الإلغاء</th>
                                <th>متوسط الوقت</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ranking)): ?>
                                <tr><td colspan="11" class="text-center">لا توجد بيانات</td></tr>
                            <?php else: ?>
                                <?php foreach ($ranking as $i => $emp): ?>
                                    <?php
                                        $pos = $i + 1;
                                        $medal = '';
                                        if ($pos === 1) $medal = '<i class="fa fa-trophy" style="color:#f39c12;"></i>';
                                        elseif ($pos === 2) $medal = '<i class="fa fa-trophy" style="color:#bdc3c7;"></i>';
                                        elseif ($pos === 3) $medal = '<i class="fa fa-trophy" style="color:#cd7f32;"></i>';
                                    ?>
                                    <tr>
                                        <td><?php echo $pos; ?></td>
                                        <td>
                                            <?php echo $medal; ?>
                                            <a href="employee-details.php?id=<?php echo (int) $emp['id']; ?>">
                                                <?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </td>
                                        <td><strong><?php echo (int) $emp['score']; ?></strong></td>
                                        <td><span class="label label-success"><?php echo (int) $emp['completed']; ?></span></td>
                                        <td><span class="label label-info"><?php echo (int) $emp['confirmed']; ?></span></td>
                                        <td><span class="label label-danger"><?php echo (int) $emp['cancelled']; ?></span></td>
                                        <td><span class="label label-warning"><?php echo (int) $emp['returned']; ?></span></td>
                                        <td><?php echo $emp['delivery_success_rate']; ?>%</td>
                                        <td><?php echo $emp['cancellation_rate']; ?>%</td>
                                        <td><?php echo $emp['avg_processing_hours']; ?> ساعة</td>
                                        <td><a href="employee-details.php?id=<?php echo (int) $emp['id']; ?>" class="btn btn-default btn-xs"><i class="fa fa-eye"></i> عرض</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once('footer.php'); ?>
