<?php require_once('header.php'); ?>
<?php
if (!function_exists('admin_dashboard_status_meta')) {
    function admin_dashboard_status_meta($status) {
        $map = array(
            'Pending' => array('label' => 'معلّق', 'class' => 'is-pending', 'icon' => 'fa-clock-o'),
            'Confirmed' => array('label' => 'مؤكد', 'class' => 'is-confirmed', 'icon' => 'fa-check-circle-o'),
            'Completed' => array('label' => 'مكتمل', 'class' => 'is-completed', 'icon' => 'fa-check-circle'),
            'Cancelled' => array('label' => 'ملغي', 'class' => 'is-cancelled', 'icon' => 'fa-times-circle')
        );

        return isset($map[$status]) ? $map[$status] : array(
            'label' => 'غير محدد',
            'class' => 'is-neutral',
            'icon' => 'fa-info-circle'
        );
    }
}

if (!function_exists('admin_format_amount')) {
    function admin_format_amount($amount) {
        return number_format((float) $amount, 0, '.', ' ') . ' دج';
    }
}

$admin_name = trim((string) ($_SESSION['user']['full_name'] ?? 'المدير'));
$dashboard_refresh_time = date('d/m/Y - H:i');
$admin_auto_refresh = admin_build_live_refresh_config($pdo, 'dashboard', ['interval_ms' => 20000]);

$order_summary = array(
    'total_orders' => 0,
    'today_orders' => 0,
    'pending_orders' => 0,
    'confirmed_orders' => 0,
    'completed_orders' => 0,
    'cancelled_orders' => 0,
    'completed_revenue' => 0,
    'today_revenue' => 0,
    'registered_orders' => 0,
    'direct_orders' => 0
);

$statement = $pdo->query("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN DATE(order_date) = CURDATE() THEN 1 ELSE 0 END) AS today_orders,
        SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        COALESCE(SUM(CASE WHEN order_status = 'Completed' THEN total_price ELSE 0 END), 0) AS completed_revenue,
        COALESCE(SUM(CASE WHEN DATE(order_date) = CURDATE() AND order_status = 'Completed' THEN total_price ELSE 0 END), 0) AS today_revenue,
        SUM(CASE WHEN customer_type = 'registered' THEN 1 ELSE 0 END) AS registered_orders,
        SUM(CASE WHEN customer_type = 'direct' THEN 1 ELSE 0 END) AS direct_orders
    FROM tbl_order
");
$order_summary_raw = $statement->fetch(PDO::FETCH_ASSOC);
if ($order_summary_raw) {
    $order_summary = array_merge($order_summary, $order_summary_raw);
}

$product_summary = array(
    'total_products' => 0,
    'active_products' => 0,
    'low_stock_products' => 0
);
$statement = $pdo->query("
    SELECT
        COUNT(*) AS total_products,
        SUM(CASE WHEN p_is_active = 1 THEN 1 ELSE 0 END) AS active_products,
        SUM(CASE WHEN p_qty <= 5 THEN 1 ELSE 0 END) AS low_stock_products
    FROM tbl_product
");
$product_summary_raw = $statement->fetch(PDO::FETCH_ASSOC);
if ($product_summary_raw) {
    $product_summary = array_merge($product_summary, $product_summary_raw);
}

$customer_summary = array(
    'total_customers' => 0,
    'active_customers' => 0
);
$statement = $pdo->query("
    SELECT
        COUNT(*) AS total_customers,
        SUM(CASE WHEN cust_status = 1 THEN 1 ELSE 0 END) AS active_customers
    FROM tbl_customer
");
$customer_summary_raw = $statement->fetch(PDO::FETCH_ASSOC);
if ($customer_summary_raw) {
    $customer_summary = array_merge($customer_summary, $customer_summary_raw);
}

require_once('inc/performance_functions.php');
performance_ensure_tables($pdo);
$perf_widgets = performance_get_dashboard_widgets($pdo);

$incomplete_orders_count = 0;
$recent_incomplete_orders = array();
try {
    $statement = $pdo->query("SELECT COUNT(*) FROM incomplete_orders");
    $incomplete_orders_count = (int) $statement->fetchColumn();

    $statement = $pdo->query("
        SELECT id, customer_name, customer_phone, product_name, total_price, created_at
        FROM incomplete_orders
        ORDER BY created_at DESC, id DESC
        LIMIT 5
    ");
    $recent_incomplete_orders = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Dashboard incomplete_orders query failed: ' . $e->getMessage());
}

$statement = $pdo->query("
    SELECT id, customer_name, customer_phone, product_name, total_price, order_status, order_date, customer_type
    FROM tbl_order
    ORDER BY order_date DESC, id DESC
    LIMIT 6
");
$recent_orders = $statement->fetchAll(PDO::FETCH_ASSOC);

$statement = $pdo->query("
    SELECT p_id, p_name, p_qty, p_current_price, p_is_active
    FROM tbl_product
    WHERE p_qty <= 5
    ORDER BY p_qty ASC, p_id DESC
    LIMIT 5
");
$low_stock_items = $statement->fetchAll(PDO::FETCH_ASSOC);

$total_orders = (int) $order_summary['total_orders'];
$today_orders = (int) $order_summary['today_orders'];
$pending_orders = (int) $order_summary['pending_orders'];
$confirmed_orders = (int) $order_summary['confirmed_orders'];
$completed_orders = (int) $order_summary['completed_orders'];
$cancelled_orders = (int) $order_summary['cancelled_orders'];
$registered_orders = (int) $order_summary['registered_orders'];
$direct_orders = (int) $order_summary['direct_orders'];
$completed_revenue = (float) $order_summary['completed_revenue'];
$today_revenue = (float) $order_summary['today_revenue'];

$total_products = (int) $product_summary['total_products'];
$active_products = (int) $product_summary['active_products'];
$low_stock_products = (int) $product_summary['low_stock_products'];

$total_customers = (int) $customer_summary['total_customers'];
$active_customers = (int) $customer_summary['active_customers'];

$attention_items = $pending_orders + $incomplete_orders_count + $low_stock_products;
$completion_rate = $total_orders > 0 ? (int) round(($completed_orders / $total_orders) * 100) : 0;
$active_customer_rate = $total_customers > 0 ? (int) round(($active_customers / $total_customers) * 100) : 0;
$average_completed_order = $completed_orders > 0 ? $completed_revenue / $completed_orders : 0;

$quick_links = array(
    array('href' => 'index.php', 'icon' => 'fa-line-chart', 'title' => 'المتجر', 'text' => 'مراقبة المخزون والتنبيهات'),
    array('href' => 'order.php', 'icon' => 'fa-sticky-note', 'title' => 'إدارة الطلبات', 'text' => 'فتح الطلبات الجديدة والمؤكدة'),
    array('href' => 'product.php', 'icon' => 'fa-shopping-bag', 'title' => 'المنتجات', 'text' => 'تعديل المنتجات والمخزون'),
    array('href' => 'incomplete-orders.php', 'icon' => 'fa-exclamation-triangle', 'title' => 'طلبات غير مكتملة', 'text' => 'متابعة الطلبات المتروكة'),
    array('href' => 'customer.php', 'icon' => 'fa-users', 'title' => 'العملاء', 'text' => 'عرض الحسابات المسجلة'),
    array('href' => 'delivery_list.php', 'icon' => 'fa-truck', 'title' => 'شركات التوصيل', 'text' => 'إدارة التوصيل والتسعير'),
    array('href' => 'settings.php', 'icon' => 'fa-sliders', 'title' => 'إعدادات المتجر', 'text' => 'مراجعة الإعدادات العامة'),
    array('href' => 'employee-ranking.php', 'icon' => 'fa-trophy', 'title' => 'ترتيب الموظفين', 'text' => 'عرض أداء الموظفين ونقاطهم'),
    array('href' => 'commission-settings.php', 'icon' => 'fa-money', 'title' => 'العمولات', 'text' => 'إدارة العمولات والمدفوعات'),
);
?>

<section class="content-header admin-dashboard-header">
    <div class="admin-dashboard-header-row">
        <div>
            <span class="admin-dashboard-eyebrow">لوحة المتابعة اليومية</span>
            <h1>لوحة الإدارة</h1>
            <p>نظرة سريعة على الطلبات والمبيعات والمخزون حتى تتمكن من اتخاذ القرار من الصفحة الرئيسية مباشرة.</p>
        </div>
        <div class="admin-dashboard-header-meta">
            <span>آخر تحديث</span>
            <strong><?php echo htmlspecialchars($dashboard_refresh_time, ENT_QUOTES, 'UTF-8'); ?></strong>
            <small><?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
    </div>
</section>

<section class="content admin-dashboard">
    <div class="admin-dashboard-hero">
        <div class="admin-dashboard-hero-main">
            <span class="admin-dashboard-eyebrow is-light">ملخص تنفيذي</span>
            <h2>أهلاً <?php echo htmlspecialchars($admin_name, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p>هناك <strong><?php echo $attention_items; ?></strong> عنصر يحتاج متابعة الآن بين الطلبات المعلقة، الطلبات غير المكتملة، وتنبيهات المخزون.</p>

            <div class="admin-dashboard-actions">
                <a href="order.php" class="btn btn-primary admin-dashboard-btn">
                    <i class="fa fa-sticky-note"></i>
                    فتح الطلبات
                </a>
                <a href="product.php" class="btn btn-default admin-dashboard-btn">
                    <i class="fa fa-shopping-bag"></i>
                    إدارة المنتجات
                </a>
                <a href="incomplete-orders.php" class="btn btn-default admin-dashboard-btn">
                    <i class="fa fa-exclamation-circle"></i>
                    الطلبات غير المكتملة
                </a>
            </div>
        </div>

        <div class="admin-dashboard-hero-stats">
            <div class="admin-hero-stat">
                <span>إيراد اليوم</span>
                <strong><?php echo htmlspecialchars(admin_format_amount($today_revenue), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
            <div class="admin-hero-stat">
                <span>معدل الإنجاز</span>
                <strong><?php echo $completion_rate; ?>%</strong>
            </div>
            <div class="admin-hero-stat">
                <span>متوسط الطلب المكتمل</span>
                <strong><?php echo htmlspecialchars(admin_format_amount($average_completed_order), ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>
        </div>
    </div>

    <div class="row admin-kpi-grid">
        <div class="col-lg-3 col-sm-6">
            <div class="admin-kpi-card is-indigo">
                <div class="admin-kpi-icon"><i class="fa fa-calendar"></i></div>
                <div class="admin-kpi-copy">
                    <span>طلبات اليوم</span>
                    <strong><?php echo $today_orders; ?></strong>
                    <small>عدد الطلبات المسجلة بتاريخ اليوم</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="admin-kpi-card is-amber">
                <div class="admin-kpi-icon"><i class="fa fa-clock-o"></i></div>
                <div class="admin-kpi-copy">
                    <span>طلبات معلّقة</span>
                    <strong><?php echo $pending_orders; ?></strong>
                    <small>تحتاج مراجعة أو تأكيد</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="admin-kpi-card is-emerald">
                <div class="admin-kpi-icon"><i class="fa fa-money"></i></div>
                <div class="admin-kpi-copy">
                    <span>الإيراد المكتمل</span>
                    <strong><?php echo htmlspecialchars(admin_format_amount($completed_revenue), ENT_QUOTES, 'UTF-8'); ?></strong>
                    <small>قيمة الطلبات المكتملة فقط</small>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="admin-kpi-card is-rose">
                <div class="admin-kpi-icon"><i class="fa fa-users"></i></div>
                <div class="admin-kpi-copy">
                    <span>العملاء المسجلون</span>
                    <strong><?php echo $total_customers; ?></strong>
                    <small><?php echo $active_customers; ?> حساب نشط حالياً</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row admin-mini-grid">
        <div class="col-lg-3 col-sm-6">
            <div class="admin-mini-card">
                <span>المنتجات النشطة</span>
                <strong><?php echo $active_products; ?></strong>
                <small>من أصل <?php echo $total_products; ?> منتج</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="admin-mini-card">
                <span>طلبات مكتملة</span>
                <strong><?php echo $completed_orders; ?></strong>
                <small>تمت معالجتها بنجاح</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="admin-mini-card is-warning">
                <span>طلبات غير مكتملة</span>
                <strong><?php echo $incomplete_orders_count; ?></strong>
                <small>عمليات تحتاج استرجاع أو متابعة</small>
            </div>
        </div>

        <div class="col-lg-3 col-sm-6">
            <div class="admin-mini-card is-danger">
                <span>مخزون منخفض</span>
                <strong><?php echo $low_stock_products; ?></strong>
                <small>منتجات بكمية 5 أو أقل</small>
            </div>
        </div>
    </div>

    <div class="row admin-perf-strip">
        <div class="col-lg-3 col-sm-6">
            <div class="admin-perf-card is-leader">
                <div class="admin-perf-icon"><i class="fa fa-trophy"></i></div>
                <div class="admin-perf-body">
                    <span>أفضل موظف</span>
                    <strong><?php echo $perf_widgets['top_employee'] ? htmlspecialchars($perf_widgets['top_employee']['full_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></strong>
                    <small><?php echo $perf_widgets['top_employee'] ? ($perf_widgets['top_employee']['score'] . ' نقطة') : ''; ?></small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="admin-perf-card is-trailer">
                <div class="admin-perf-icon"><i class="fa fa-exclamation-triangle"></i></div>
                <div class="admin-perf-body">
                    <span>أسوأ موظف</span>
                    <strong><?php echo $perf_widgets['worst_employee'] ? htmlspecialchars($perf_widgets['worst_employee']['full_name'], ENT_QUOTES, 'UTF-8') : '--'; ?></strong>
                    <small><?php echo $perf_widgets['worst_employee'] ? ($perf_widgets['worst_employee']['score'] . ' نقطة') : ''; ?></small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="admin-perf-card is-rate">
                <div class="admin-perf-icon"><i class="fa fa-check-circle"></i></div>
                <div class="admin-perf-body">
                    <span>معدل التوصيل الإجمالي</span>
                    <strong><?php echo $perf_widgets['delivery_rate']; ?>%</strong>
                    <small>نسبة النجاح</small>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-sm-6">
            <div class="admin-perf-card is-revenue">
                <div class="admin-perf-icon"><i class="fa fa-money"></i></div>
                <div class="admin-perf-body">
                    <span>إيراد الشهر</span>
                    <strong><?php echo number_format($perf_widgets['monthly_revenue'], 0); ?> دج</strong>
                    <small>آخر 30 يوماً</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="admin-panel-card">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">النشاط الأخير</span>
                        <h3>آخر الطلبات</h3>
                    </div>
                    <a href="order.php" class="admin-panel-link">عرض جميع الطلبات</a>
                </div>

                <?php if (!empty($recent_orders)): ?>
                    <div class="admin-order-list">
                        <?php foreach ($recent_orders as $order): ?>
                            <?php $status = admin_dashboard_status_meta($order['order_status'] ?? ''); ?>
                            <article class="admin-order-item">
                                <div class="admin-order-main">
                                    <div class="admin-order-title-row">
                                        <strong>#<?php echo (int) $order['id']; ?> - <?php echo htmlspecialchars((string) $order['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="admin-status-badge <?php echo htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fa <?php echo htmlspecialchars($status['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                            <?php echo htmlspecialchars($status['label'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                    <p>
                                        <?php echo htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="admin-order-dot"></span>
                                        <?php echo htmlspecialchars((string) $order['customer_phone'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                    <small>
                                        <?php echo date('d/m/Y H:i', strtotime((string) $order['order_date'])); ?>
                                        <span class="admin-order-dot"></span>
                                        <?php echo ($order['customer_type'] ?? '') === 'registered' ? 'عميل مسجل' : 'طلب مباشر'; ?>
                                    </small>
                                </div>
                                <div class="admin-order-side">
                                    <strong><?php echo htmlspecialchars(admin_format_amount($order['total_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <a href="order-details.php?id=<?php echo (int) $order['id']; ?>" class="btn btn-default btn-xs">تفاصيل</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">
                        <i class="fa fa-inbox"></i>
                        <div>
                            <h4>لا توجد طلبات حتى الآن</h4>
                            <p>عند تسجيل أول طلب سيظهر هنا آخر نشاط خاص بالطلبات.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="admin-panel-card">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">روابط مباشرة</span>
                        <h3>إجراءات سريعة</h3>
                    </div>
                </div>

                <div class="admin-quick-links">
                    <?php foreach ($quick_links as $link): ?>
                        <a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>" class="admin-quick-link">
                            <i class="fa <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <div>
                                <strong><?php echo htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars($link['text'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-panel-card">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">تشغيل المتجر</span>
                        <h3>مؤشرات تنفيذية</h3>
                    </div>
                </div>

                <div class="admin-metric-stack">
                    <div class="admin-metric-row">
                        <span>معدل الإنجاز</span>
                        <strong><?php echo $completion_rate; ?>%</strong>
                    </div>
                    <div class="admin-progress"><span style="width: <?php echo $completion_rate; ?>%;"></span></div>

                    <div class="admin-metric-row">
                        <span>العملاء النشطون</span>
                        <strong><?php echo $active_customers; ?> / <?php echo $total_customers; ?></strong>
                    </div>
                    <div class="admin-progress is-teal"><span style="width: <?php echo $active_customer_rate; ?>%;"></span></div>

                    <div class="admin-metric-grid">
                        <div class="admin-metric-box">
                            <span>طلبات مباشرة</span>
                            <strong><?php echo $direct_orders; ?></strong>
                            <small>طلبات لم تسجل حساباً</small>
                        </div>
                        <div class="admin-metric-box">
                            <span>طلبات المسجلين</span>
                            <strong><?php echo $registered_orders; ?></strong>
                            <small>من حسابات مسجلة</small>
                        </div>
                        <div class="admin-metric-box">
                            <span>طلبات مؤكدة</span>
                            <strong><?php echo $confirmed_orders; ?></strong>
                            <small>بانتظار التجهيز والتوصيل</small>
                        </div>
                        <div class="admin-metric-box">
                            <span>طلبات ملغاة</span>
                            <strong><?php echo $cancelled_orders; ?></strong>
                            <small>تم إلغاؤها من العميل أو الإدارة</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="admin-panel-card">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">استرجاع العمليات</span>
                        <h3>الطلبات غير المكتملة الأخيرة</h3>
                    </div>
                    <a href="incomplete-orders.php" class="admin-panel-link">فتح القائمة</a>
                </div>

                <?php if (!empty($recent_incomplete_orders)): ?>
                    <div class="admin-compact-list">
                        <?php foreach ($recent_incomplete_orders as $order): ?>
                            <article class="admin-compact-item">
                                <div class="admin-compact-main">
                                    <strong><?php echo htmlspecialchars((string) $order['customer_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <p><?php echo htmlspecialchars((string) ($order['product_name'] ?? 'بدون منتج محدد'), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <small>
                                        <?php echo htmlspecialchars((string) $order['customer_phone'], ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="admin-order-dot"></span>
                                        <?php echo date('d/m/Y H:i', strtotime((string) $order['created_at'])); ?>
                                    </small>
                                </div>
                                <div class="admin-compact-side">
                                    <strong><?php echo htmlspecialchars(admin_format_amount($order['total_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <a href="order-add-from-incomplete.php?id=<?php echo (int) $order['id']; ?>" class="btn btn-success btn-xs">تحويل لطلب</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state is-soft">
                        <i class="fa fa-check-circle-o"></i>
                        <div>
                            <h4>لا توجد طلبات غير مكتملة</h4>
                            <p>لا توجد حالياً عمليات متروكة تحتاج متابعة إضافية.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="admin-panel-card">
                <div class="admin-panel-head">
                    <div>
                        <span class="admin-panel-kicker">تنبيهات المخزون</span>
                        <h3>منتجات تحتاج متابعة</h3>
                    </div>
                    <a href="product.php" class="admin-panel-link">إدارة المنتجات</a>
                </div>

                <?php if (!empty($low_stock_items)): ?>
                    <div class="admin-compact-list">
                        <?php foreach ($low_stock_items as $product): ?>
                            <article class="admin-compact-item">
                                <div class="admin-compact-main">
                                    <strong><?php echo htmlspecialchars((string) $product['p_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <p><?php echo ((int) ($product['p_is_active'] ?? 0) === 1) ? 'منتج ظاهر في المتجر' : 'منتج غير مفعل حالياً'; ?></p>
                                    <small>السعر الحالي: <?php echo htmlspecialchars(admin_format_amount($product['p_current_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                                <div class="admin-compact-side">
                                    <span class="admin-stock-pill <?php echo ((int) ($product['p_qty'] ?? 0) === 0) ? 'is-zero' : ''; ?>">
                                        الكمية: <?php echo (int) $product['p_qty']; ?>
                                    </span>
                                    <a href="product-edit.php?id=<?php echo (int) $product['p_id']; ?>" class="btn btn-default btn-xs">تعديل</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state is-soft">
                        <i class="fa fa-cube"></i>
                        <div>
                            <h4>المخزون في وضع جيد</h4>
                            <p>لا توجد منتجات بكمية منخفضة في الوقت الحالي.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<style>
@import url("https://fonts.googleapis.com/css2?family=Cairo:wght@500;700;800&display=swap");

.admin-dashboard-header,
.admin-dashboard {
    font-family: "Cairo", sans-serif;
}

.admin-dashboard-header {
    padding-bottom: 8px;
}

.admin-dashboard-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.admin-dashboard-eyebrow,
.admin-panel-kicker {
    display: inline-block;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: #475569;
}

.admin-dashboard-eyebrow.is-light {
    color: rgba(255, 255, 255, 0.72);
}

.admin-dashboard-header h1 {
    margin: 6px 0 10px;
    padding: 0;
    font-size: 34px;
    font-weight: 800;
    color: #0f172a;
}

.admin-dashboard-header h1:before {
    display: none;
}

.admin-dashboard-header p {
    margin: 0;
    max-width: 780px;
    color: #334155;
    font-weight: 600;
    line-height: 1.9;
    font-size: 15px;
}

.admin-dashboard-header-meta {
    min-width: 190px;
    padding: 16px 18px;
    border-radius: 18px;
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.2);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
}

.admin-dashboard-header-meta span,
.admin-dashboard-header-meta small {
    display: block;
    color: #475569;
    font-weight: 600;
}

.admin-dashboard-header-meta strong {
    display: block;
    margin: 6px 0 3px;
    color: #0f172a;
    font-size: 22px;
    font-weight: 900;
}

.admin-dashboard-hero {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
    padding: 26px;
    margin-bottom: 22px;
    border-radius: 28px;
    background: linear-gradient(135deg, #111827 0%, #1e293b 48%, #0f766e 100%);
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.16);
    color: #fff;
    overflow: hidden;
    position: relative;
}

.admin-dashboard-hero:before,
.admin-dashboard-hero:after {
    content: "";
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.06);
}

.admin-dashboard-hero:before {
    width: 220px;
    height: 220px;
    top: -90px;
    left: -40px;
}

.admin-dashboard-hero:after {
    width: 150px;
    height: 150px;
    right: 30px;
    bottom: -55px;
}

.admin-dashboard-hero-main,
.admin-dashboard-hero-stats {
    position: relative;
    z-index: 1;
}

.admin-dashboard-hero-main {
    flex: 1 1 520px;
}

.admin-dashboard-hero-main h2 {
    margin: 8px 0 12px;
    font-size: 34px;
    font-weight: 800;
}

.admin-dashboard-hero-main p {
    margin: 0;
    max-width: 700px;
    color: rgba(255, 255, 255, 0.9);
    font-size: 16px;
    font-weight: 600;
    line-height: 1.9;
}

.admin-dashboard-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.admin-dashboard-btn {
    border-radius: 999px;
    padding: 11px 18px;
    font-weight: 700;
    box-shadow: none;
}

.admin-dashboard-btn i {
    margin-left: 8px;
}

.admin-dashboard-hero-stats {
    flex: 1 1 300px;
    display: grid;
    gap: 12px;
    align-content: center;
}

.admin-hero-stat {
    padding: 18px 20px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.09);
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(8px);
}

.admin-hero-stat span {
    display: block;
    color: rgba(255, 255, 255, 0.85);
    font-size: 14px;
    font-weight: 700;
}

.admin-hero-stat strong {
    display: block;
    margin-top: 6px;
    font-size: 28px;
    font-weight: 900;
}

.admin-kpi-grid .col-lg-3,
.admin-mini-grid .col-lg-3 {
    margin-bottom: 18px;
}

.admin-kpi-card,
.admin-mini-card,
.admin-panel-card {
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 24px;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
}

.admin-kpi-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 22px;
    min-height: 146px;
}

.admin-kpi-icon {
    width: 58px;
    height: 58px;
    flex: 0 0 58px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.admin-kpi-card.is-indigo .admin-kpi-icon {
    background: #e0e7ff;
    color: #4338ca;
}

.admin-kpi-card.is-amber .admin-kpi-icon {
    background: #fef3c7;
    color: #b45309;
}

.admin-kpi-card.is-emerald .admin-kpi-icon {
    background: #dcfce7;
    color: #15803d;
}

.admin-kpi-card.is-rose .admin-kpi-icon {
    background: #ffe4e6;
    color: #be123c;
}

.admin-kpi-copy span,
.admin-mini-card span,
.admin-metric-box span,
.admin-metric-row span,
.admin-compact-main small,
.admin-order-main small {
    display: block;
    color: #334155;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.admin-kpi-copy strong,
.admin-mini-card strong {
    display: block;
    margin: 6px 0;
    color: #0f172a;
    font-size: 32px;
    font-weight: 900;
    line-height: 1.2;
}

.admin-kpi-copy small,
.admin-mini-card small {
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.8;
}

.admin-mini-card {
    padding: 18px 20px;
    min-height: 132px;
}

.admin-mini-card.is-warning {
    background: linear-gradient(180deg, #fffaf0 0%, #ffffff 100%);
}

.admin-mini-card.is-danger {
    background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%);
}

.admin-panel-card {
    padding: 22px;
    margin-bottom: 18px;
}

.admin-panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 18px;
}

.admin-panel-head h3,
.admin-empty-state h4 {
    margin: 6px 0 0;
    color: #0f172a;
    font-size: 26px;
    font-weight: 900;
}

.admin-panel-link {
    color: #0f766e;
    font-weight: 700;
}

.admin-panel-link:hover,
.admin-panel-link:focus {
    color: #115e59;
    text-decoration: none;
}

.admin-order-list,
.admin-compact-list {
    display: grid;
    gap: 14px;
}

.admin-order-item,
.admin-compact-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 18px;
    border-radius: 18px;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.14);
}

.admin-order-main,
.admin-compact-main {
    flex: 1 1 360px;
}

.admin-order-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
    justify-content: space-between;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

.admin-order-main strong,
.admin-compact-main strong {
    color: #0f172a;
    font-size: 16px;
    font-weight: 900;
}

.admin-order-main p,
.admin-compact-main p {
    margin: 0 0 6px;
    color: #334155;
    font-weight: 600;
    line-height: 1.8;
}

.admin-order-side,
.admin-compact-side {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 8px;
}

.admin-order-side strong,
.admin-compact-side strong {
    color: #0f172a;
    font-size: 20px;
    font-weight: 900;
}

.admin-status-badge,
.admin-stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 800;
}

.admin-status-badge.is-pending {
    background: #fff7ed;
    color: #c2410c;
}

.admin-status-badge.is-confirmed {
    background: #eff6ff;
    color: #1d4ed8;
}

.admin-status-badge.is-completed {
    background: #ecfdf5;
    color: #047857;
}

.admin-status-badge.is-cancelled {
    background: #fef2f2;
    color: #b91c1c;
}

.admin-status-badge.is-neutral {
    background: #f1f5f9;
    color: #334155;
}

.admin-order-dot {
    display: inline-block;
    width: 4px;
    height: 4px;
    margin: 0 8px;
    border-radius: 50%;
    background: #94a3b8;
    vertical-align: middle;
}

.admin-quick-links {
    display: grid;
    gap: 12px;
}

.admin-quick-link {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 18px;
    color: #0f172a;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.14);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
}

.admin-quick-link:hover,
.admin-quick-link:focus {
    color: #0f172a;
    text-decoration: none;
    transform: translateY(-2px);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.05);
    border-color: rgba(15, 118, 110, 0.24);
}

.admin-quick-link i {
    width: 44px;
    height: 44px;
    flex: 0 0 44px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 18px;
}

.admin-quick-link strong {
    display: block;
    margin-bottom: 4px;
    font-size: 15px;
    font-weight: 800;
}

.admin-quick-link span {
    color: #475569;
    font-size: 13px;
    font-weight: 600;
    line-height: 1.7;
}

.admin-metric-stack {
    display: grid;
    gap: 12px;
}

.admin-metric-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.admin-metric-row strong {
    color: #0f172a;
    font-size: 18px;
    font-weight: 900;
}

.admin-progress {
    width: 100%;
    height: 10px;
    overflow: hidden;
    border-radius: 999px;
    background: #e2e8f0;
}

.admin-progress span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #10b981 0%, #22c55e 100%);
}

.admin-progress.is-teal span {
    background: linear-gradient(90deg, #06b6d4 0%, #14b8a6 100%);
}

.admin-metric-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 8px;
}

.admin-metric-box {
    padding: 14px;
    border-radius: 16px;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.14);
}

.admin-metric-box strong {
    display: block;
    margin-top: 6px;
    color: #0f172a;
    font-size: 24px;
    font-weight: 900;
}

.admin-metric-box small {
    display: block;
    margin-top: 4px;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.admin-stock-pill {
    background: #fff7ed;
    color: #c2410c;
}

.admin-stock-pill.is-zero {
    background: #fef2f2;
    color: #b91c1c;
}

.admin-empty-state {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border-radius: 18px;
    background: #f8fafc;
    border: 1px dashed rgba(148, 163, 184, 0.4);
}

.admin-empty-state.is-soft {
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
}

.admin-empty-state i {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 24px;
}

.admin-empty-state p {
    margin: 6px 0 0;
    color: #475569;
    font-weight: 600;
    line-height: 1.8;
}

.admin-perf-strip {
    margin-bottom: 18px;
}

.admin-perf-strip .col-lg-3,
.admin-perf-strip .col-sm-6 {
    margin-bottom: 12px;
}

.admin-perf-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border-radius: 24px;
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.16);
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.06);
    min-height: 120px;
}

.admin-perf-icon {
    width: 52px;
    height: 52px;
    flex: 0 0 52px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.admin-perf-card.is-leader .admin-perf-icon {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #fff;
}

.admin-perf-card.is-trailer .admin-perf-icon {
    background: linear-gradient(135deg, #f87171, #ef4444);
    color: #fff;
}

.admin-perf-card.is-rate .admin-perf-icon {
    background: linear-gradient(135deg, #34d399, #10b981);
    color: #fff;
}

.admin-perf-card.is-revenue .admin-perf-icon {
    background: linear-gradient(135deg, #60a5fa, #3b82f6);
    color: #fff;
}

.admin-perf-body span {
    display: block;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.admin-perf-body strong {
    display: block;
    margin: 4px 0 2px;
    color: #0f172a;
    font-size: 20px;
    font-weight: 900;
}

.admin-perf-body small {
    color: #475569;
    font-size: 13px;
    font-weight: 600;
}

@media (max-width: 991px) {
    .admin-dashboard-header h1 {
        font-size: 30px;
    }

    .admin-dashboard-hero {
        padding: 22px;
        border-radius: 24px;
    }

    .admin-dashboard-hero-main h2 {
        font-size: 28px;
    }
}

@media (max-width: 767px) {
    .admin-dashboard-header-row,
    .admin-panel-head,
    .admin-order-item,
    .admin-compact-item {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-dashboard-header h1 {
        font-size: 26px;
    }

    .admin-dashboard-hero {
        padding: 18px;
        border-radius: 22px;
    }

    .admin-dashboard-hero-main h2 {
        font-size: 24px;
    }

    .admin-kpi-card,
    .admin-mini-card,
    .admin-panel-card {
        border-radius: 20px;
    }

    .admin-kpi-card {
        min-height: auto;
    }

    .admin-order-side,
    .admin-compact-side {
        width: 100%;
        align-items: stretch;
    }

    .admin-metric-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php Profiler::checkpoint('page_render_complete'); ?>
<?php require_once('footer.php'); ?>
