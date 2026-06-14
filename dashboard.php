<?php
$page_meta_title = 'لوحة التحكم | الحساب الشخصي';
$page_meta_description = 'تابع بيانات الحساب وملخص الطلبات من لوحة تحكم أوضح وأكثر احترافية.';
$page_google_fonts = 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap';
$body_class = 'account-dashboard-page';

require_once('header.php');

if(!isset($_SESSION['customer'])) {
    header('location: login.php');
    exit;
}

if (!function_exists('dashboard_status_meta')) {
    function dashboard_status_meta($status) {
        $map = array(
            'Pending' => array(
                'label' => 'قيد المراجعة',
                'class' => 'is-pending',
                'icon' => 'fa-clock-o'
            ),
            'Confirmed' => array(
                'label' => 'مؤكد',
                'class' => 'is-confirmed',
                'icon' => 'fa-check-circle-o'
            ),
            'Completed' => array(
                'label' => 'مكتمل',
                'class' => 'is-completed',
                'icon' => 'fa-check-circle'
            ),
            'Cancelled' => array(
                'label' => 'ملغي',
                'class' => 'is-cancelled',
                'icon' => 'fa-times-circle'
            )
        );

        return isset($map[$status]) ? $map[$status] : array(
            'label' => 'غير محدد',
            'class' => 'is-neutral',
            'icon' => 'fa-info-circle'
        );
    }
}

$statement = $pdo->prepare("SELECT * FROM tbl_customer WHERE id=? LIMIT 1");
$statement->execute(array($_SESSION['customer']['id']));
$customer = $statement->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('location: logout.php');
    exit;
}

$cust_name = trim((string) ($customer['cust_name'] ?? ''));
$cust_phone = trim((string) ($customer['cust_phone'] ?? ''));
$wilaya = trim((string) ($customer['wilaya'] ?? ''));
$commune = trim((string) ($customer['commune'] ?? ''));
$cust_address = trim((string) ($customer['cust_address'] ?? ''));
$cust_status = (int) ($customer['cust_status'] ?? 0);

$profile_fields = array($cust_name, $cust_phone, $wilaya, $commune, $cust_address);
$filled_fields = 0;
foreach ($profile_fields as $field_value) {
    if (trim((string) $field_value) !== '') {
        $filled_fields++;
    }
}

$profile_completion = (int) round(($filled_fields / count($profile_fields)) * 100);
$profile_completion_text = $profile_completion === 100
    ? 'ملفك الشخصي مكتمل وجاهز لتسريع الطلب والتوصيل.'
    : 'أكمل بياناتك لتسهيل تأكيد الطلبات وتسريع عملية التوصيل.';

$account_status_label = $cust_status === 1 ? 'الحساب نشط' : 'قيد التفعيل';
$account_status_text = $cust_status === 1
    ? 'يمكنك متابعة الطلبات وتحديث بياناتك في أي وقت.'
    : 'راجع بياناتك الشخصية للتأكد من جاهزية الحساب بالكامل.';
$account_status_class = $cust_status === 1 ? 'is-active' : 'is-review';

$statement = $pdo->prepare("
    SELECT
        COUNT(*) AS total_orders,
        SUM(CASE WHEN order_status = 'Pending' THEN 1 ELSE 0 END) AS pending_orders,
        SUM(CASE WHEN order_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed_orders,
        SUM(CASE WHEN order_status = 'Completed' THEN 1 ELSE 0 END) AS completed_orders,
        SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
        COALESCE(SUM(total_price), 0) AS total_spent
    FROM tbl_order
    WHERE customer_name = ?
");
$statement->execute(array($cust_name));
$order_summary = $statement->fetch(PDO::FETCH_ASSOC);

$total_orders = (int) ($order_summary['total_orders'] ?? 0);
$pending_orders = (int) ($order_summary['pending_orders'] ?? 0);
$confirmed_orders = (int) ($order_summary['confirmed_orders'] ?? 0);
$completed_orders = (int) ($order_summary['completed_orders'] ?? 0);
$cancelled_orders = (int) ($order_summary['cancelled_orders'] ?? 0);
$processing_orders = $pending_orders + $confirmed_orders;
$total_spent = (float) ($order_summary['total_spent'] ?? 0);

$statement = $pdo->prepare("
    SELECT id, product_name, total_price, order_status, order_date
    FROM tbl_order
    WHERE customer_name = ?
    ORDER BY order_date DESC, id DESC
    LIMIT 1
");
$statement->execute(array($cust_name));
$last_order = $statement->fetch(PDO::FETCH_ASSOC);

$welcome_name = 'عميلنا';
if ($cust_name !== '') {
    $name_parts = preg_split('/\s+/u', $cust_name, -1, PREG_SPLIT_NO_EMPTY);
    $welcome_name = !empty($name_parts[0]) ? $name_parts[0] : $cust_name;
}

$avatar_initial = 'ع';
if ($cust_name !== '') {
    if (function_exists('mb_substr')) {
        $avatar_initial = mb_substr($cust_name, 0, 1, 'UTF-8');
        if (function_exists('mb_strtoupper')) {
            $avatar_initial = mb_strtoupper($avatar_initial, 'UTF-8');
        }
    } else {
        $avatar_initial = strtoupper(substr($cust_name, 0, 1));
    }
}

$formatted_total_spent = number_format($total_spent, 0, '.', ' ') . ' دج';
$formatted_last_order_date = 'لا توجد طلبات بعد';
if (!empty($last_order['order_date'])) {
    $formatted_last_order_date = date('d/m/Y - h:i A', strtotime($last_order['order_date']));
}

$latest_order_status = dashboard_status_meta($last_order['order_status'] ?? '');

$cta_title = 'أكمل الصورة العامة لحسابك';
$cta_text = 'يمكنك تحديث بياناتك أو مراجعة الطلبات الأخيرة من الروابط السريعة في هذه الصفحة.';
$cta_link = 'edit-profile.php';
$cta_link_text = 'تحديث معلوماتي';

if ($profile_completion < 100) {
    $cta_title = 'بياناتك تحتاج استكمال';
    $cta_text = 'إضافة الولاية، البلدية، والعنوان بشكل دقيق يساعد على تسريع التوصيل وتأكيد الطلب.';
} elseif ($total_orders === 0) {
    $cta_title = 'لا توجد طلبات بعد';
    $cta_text = 'بعد إتمام أول طلب سيظهر هنا ملخص واضح لحالة الطلبات والإنفاق الكلي.';
    $cta_link = 'index.php';
    $cta_link_text = 'تصفح المنتجات';
} else {
    $cta_title = 'الحساب جاهز';
    $cta_text = 'بياناتك مكتملة ويمكنك متابعة الطلبات الحالية أو مراجعة النشاط الأخير مباشرة.';
    $cta_link = 'customer-order.php';
    $cta_link_text = 'عرض كل الطلبات';
}
?>

<div class="page dashboard-page">
    <div class="container">
        <div class="dashboard-shell">
            <section class="dashboard-hero">
                <div class="dashboard-hero-main">
                    <div class="dashboard-avatar"><?php echo htmlspecialchars($avatar_initial, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="dashboard-hero-copy">
                        <span class="dashboard-kicker">لوحة الحساب الشخصي</span>
                        <h1>مرحباً <?php echo htmlspecialchars($welcome_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <p>تابع معلوماتك الأساسية، حالة حسابك، وآخر نشاط على المتجر من صفحة واحدة أكثر وضوحاً وتنظيماً.</p>

                        <div class="dashboard-hero-actions">
                            <a href="edit-profile.php" class="dashboard-btn dashboard-btn-primary">
                                <i class="fa fa-pencil"></i>
                                تحديث البيانات
                            </a>
                            <a href="customer-order.php" class="dashboard-btn dashboard-btn-secondary">
                                <i class="fa fa-shopping-bag"></i>
                                عرض الطلبات
                            </a>
                        </div>
                    </div>
                </div>

                <div class="dashboard-hero-side">
                    <div class="dashboard-mini-card <?php echo $account_status_class; ?>">
                        <span class="dashboard-mini-label">حالة الحساب</span>
                        <strong><?php echo htmlspecialchars($account_status_label, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <p><?php echo htmlspecialchars($account_status_text, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="dashboard-mini-card">
                        <div class="dashboard-mini-head">
                            <span>اكتمال الملف</span>
                            <strong><?php echo $profile_completion; ?>%</strong>
                        </div>
                        <div class="dashboard-progress">
                            <span style="width: <?php echo $profile_completion; ?>%;"></span>
                        </div>
                        <p><?php echo htmlspecialchars($profile_completion_text, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                    <div class="dashboard-mini-card">
                        <span class="dashboard-mini-label">آخر نشاط</span>
                        <?php if ($last_order): ?>
                            <strong>#<?php echo (int) $last_order['id']; ?> - <?php echo htmlspecialchars($latest_order_status['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?php echo htmlspecialchars((string) $last_order['product_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <small><?php echo htmlspecialchars($formatted_last_order_date, ENT_QUOTES, 'UTF-8'); ?></small>
                        <?php else: ?>
                            <strong>لا يوجد نشاط بعد</strong>
                            <p>ابدأ بطلبك الأول لتظهر هنا آخر العمليات الخاصة بحسابك.</p>
                            <small>سيتم تحديث هذا القسم تلقائياً.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <div class="row dashboard-layout">
                <div class="col-md-4">
                    <aside class="dashboard-card dashboard-nav-card">
                        <div class="dashboard-card-head">
                            <div>
                                <span class="dashboard-card-kicker">التنقل السريع</span>
                                <h2>إدارة الحساب</h2>
                            </div>
                        </div>

                        <a href="dashboard.php" class="dashboard-nav-item is-active">
                            <div class="dashboard-nav-icon"><i class="fa fa-user-circle-o"></i></div>
                            <div class="dashboard-nav-copy">
                                <strong>معلوماتي</strong>
                                <span>عرض بيانات الحساب الحالية</span>
                            </div>
                        </a>

                        <a href="customer-order.php" class="dashboard-nav-item">
                            <div class="dashboard-nav-icon"><i class="fa fa-shopping-bag"></i></div>
                            <div class="dashboard-nav-copy">
                                <strong>طلباتي</strong>
                                <span>متابعة حالة الطلبات السابقة والحالية</span>
                            </div>
                            <span class="dashboard-nav-badge"><?php echo $total_orders; ?></span>
                        </a>

                        <a href="edit-profile.php" class="dashboard-nav-item">
                            <div class="dashboard-nav-icon"><i class="fa fa-pencil-square-o"></i></div>
                            <div class="dashboard-nav-copy">
                                <strong>تعديل معلوماتي</strong>
                                <span>تحديث بيانات التوصيل والاتصال</span>
                            </div>
                            <span class="dashboard-nav-badge"><?php echo $profile_completion; ?>%</span>
                        </a>

                        <a href="logout.php" class="dashboard-nav-item is-danger">
                            <div class="dashboard-nav-icon"><i class="fa fa-sign-out"></i></div>
                            <div class="dashboard-nav-copy">
                                <strong>تسجيل الخروج</strong>
                                <span>إنهاء الجلسة الحالية بأمان</span>
                            </div>
                        </a>
                    </aside>

                    <aside class="dashboard-card dashboard-note-card">
                        <span class="dashboard-card-kicker">ملاحظة سريعة</span>
                        <h3><?php echo htmlspecialchars($cta_title, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($cta_text, ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo htmlspecialchars($cta_link, ENT_QUOTES, 'UTF-8'); ?>" class="dashboard-text-link">
                            <?php echo htmlspecialchars($cta_link_text, ENT_QUOTES, 'UTF-8'); ?>
                            <i class="fa fa-angle-left"></i>
                        </a>
                    </aside>
                </div>
                <div class="col-md-8">
                    <div class="row dashboard-stats">
                        <div class="col-sm-6">
                            <div class="dashboard-card dashboard-stat-card">
                                <div class="dashboard-stat-icon is-slate"><i class="fa fa-list-alt"></i></div>
                                <div class="dashboard-stat-copy">
                                    <span>إجمالي الطلبات</span>
                                    <strong><?php echo $total_orders; ?></strong>
                                    <small>كل الطلبات المسجلة على الحساب</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dashboard-card dashboard-stat-card">
                                <div class="dashboard-stat-icon is-amber"><i class="fa fa-refresh"></i></div>
                                <div class="dashboard-stat-copy">
                                    <span>طلبات قيد المعالجة</span>
                                    <strong><?php echo $processing_orders; ?></strong>
                                    <small>تشمل الطلبات قيد المراجعة والمؤكدة</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dashboard-card dashboard-stat-card">
                                <div class="dashboard-stat-icon is-green"><i class="fa fa-check"></i></div>
                                <div class="dashboard-stat-copy">
                                    <span>طلبات مكتملة</span>
                                    <strong><?php echo $completed_orders; ?></strong>
                                    <small>طلبات تم إنهاؤها بنجاح</small>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6">
                            <div class="dashboard-card dashboard-stat-card">
                                <div class="dashboard-stat-icon is-blue"><i class="fa fa-money"></i></div>
                                <div class="dashboard-stat-copy">
                                    <span>إجمالي الإنفاق</span>
                                    <strong><?php echo htmlspecialchars($formatted_total_spent, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small>إجمالي قيمة الطلبات المسجلة</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="dashboard-card">
                        <div class="dashboard-card-head">
                            <div>
                                <span class="dashboard-card-kicker">البيانات الأساسية</span>
                                <h2>معلومات الحساب</h2>
                            </div>
                            <a href="edit-profile.php" class="dashboard-inline-link">تعديل</a>
                        </div>

                        <div class="dashboard-info-grid">
                            <div class="dashboard-info-item">
                                <span>الاسم الكامل</span>
                                <strong><?php echo htmlspecialchars($cust_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>

                            <div class="dashboard-info-item">
                                <span>رقم الهاتف</span>
                                <strong><?php echo htmlspecialchars($cust_phone, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>

                            <div class="dashboard-info-item">
                                <span>الولاية</span>
                                <strong><?php echo htmlspecialchars($wilaya, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>

                            <div class="dashboard-info-item">
                                <span>البلدية</span>
                                <strong><?php echo htmlspecialchars($commune, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>

                            <div class="dashboard-info-item is-wide">
                                <span>العنوان</span>
                                <strong><?php echo nl2br(htmlspecialchars($cust_address, ENT_QUOTES, 'UTF-8')); ?></strong>
                            </div>
                        </div>
                    </section>

                    <section class="dashboard-card">
                        <div class="dashboard-card-head">
                            <div>
                                <span class="dashboard-card-kicker">ملخص النشاط</span>
                                <h2>حالة الطلبات</h2>
                            </div>
                            <a href="customer-order.php" class="dashboard-inline-link">فتح صفحة الطلبات</a>
                        </div>

                        <div class="dashboard-status-list">
                            <div class="dashboard-status-chip is-pending">
                                <span>قيد المراجعة</span>
                                <strong><?php echo $pending_orders; ?></strong>
                            </div>

                            <div class="dashboard-status-chip is-confirmed">
                                <span>مؤكدة</span>
                                <strong><?php echo $confirmed_orders; ?></strong>
                            </div>

                            <div class="dashboard-status-chip is-completed">
                                <span>مكتملة</span>
                                <strong><?php echo $completed_orders; ?></strong>
                            </div>

                            <div class="dashboard-status-chip is-cancelled">
                                <span>ملغاة</span>
                                <strong><?php echo $cancelled_orders; ?></strong>
                            </div>
                        </div>

                        <?php if ($last_order): ?>
                            <div class="dashboard-last-order">
                                <div class="dashboard-last-order-main">
                                    <span>آخر طلب مسجل</span>
                                    <strong>#<?php echo (int) $last_order['id']; ?> - <?php echo htmlspecialchars((string) $last_order['product_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <small><?php echo htmlspecialchars($formatted_last_order_date, ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>

                                <div class="dashboard-last-order-price">
                                    <span>القيمة</span>
                                    <strong><?php echo htmlspecialchars(number_format((float) $last_order['total_price'], 0, '.', ' ') . ' دج', ENT_QUOTES, 'UTF-8'); ?></strong>
                                </div>

                                <div class="dashboard-last-order-status <?php echo htmlspecialchars($latest_order_status['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fa <?php echo htmlspecialchars($latest_order_status['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    <?php echo htmlspecialchars($latest_order_status['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa fa-shopping-basket"></i></div>
                                <div>
                                    <h3>لا توجد طلبات حتى الآن</h3>
                                    <p>عند تنفيذ أول طلب سيظهر هنا آخر طلب وحالته بشكل مختصر وسهل المتابعة.</p>
                                </div>
                                <a href="index.php" class="dashboard-btn dashboard-btn-primary">تصفح المنتجات</a>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-page {
    background:
        radial-gradient(circle at top right, rgba(14, 165, 233, 0.12), transparent 32%),
        linear-gradient(180deg, #f7fafc 0%, #edf4fa 100%);
    font-family: "Cairo", sans-serif;
}

.dashboard-shell {
    background: rgba(255, 255, 255, 0.86);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 30px;
    padding: 28px;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(10px);
}

.dashboard-hero {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 24px;
}

.dashboard-hero-main {
    flex: 1 1 520px;
    display: flex;
    align-items: center;
    gap: 24px;
    padding: 32px;
    border-radius: 26px;
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 45%, #0f766e 100%);
    color: #fff;
}

.dashboard-hero-main:before,
.dashboard-hero-main:after {
    content: "";
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}

.dashboard-hero-main:before {
    width: 220px;
    height: 220px;
    top: -80px;
    left: -40px;
}

.dashboard-hero-main:after {
    width: 140px;
    height: 140px;
    bottom: -45px;
    right: 20px;
}

.dashboard-avatar,
.dashboard-hero-copy {
    position: relative;
    z-index: 1;
}

.dashboard-avatar {
    width: 84px;
    height: 84px;
    border-radius: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 800;
    color: #082f49;
    background: linear-gradient(135deg, #f8fafc 0%, #dbeafe 100%);
    box-shadow: 0 18px 36px rgba(8, 47, 73, 0.2);
}

.dashboard-kicker,
.dashboard-card-kicker,
.dashboard-mini-label {
    display: inline-block;
    color: #6b7280;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
}

.dashboard-kicker {
    color: rgba(255, 255, 255, 0.72);
    margin-bottom: 10px;
}

.dashboard-hero-copy h1 {
    margin: 0 0 12px;
    font-size: 36px;
    font-weight: 800;
    line-height: 1.2;
}

.dashboard-hero-copy p {
    margin: 0;
    max-width: 620px;
    color: rgba(255, 255, 255, 0.82);
    line-height: 1.9;
    font-size: 15px;
}

.dashboard-hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
}

.dashboard-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    padding: 12px 20px;
    font-weight: 700;
    transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
}

.dashboard-btn:hover,
.dashboard-btn:focus {
    text-decoration: none;
    transform: translateY(-2px);
}

.dashboard-btn-primary {
    background: #f8fafc;
    color: #0f172a;
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
}

.dashboard-btn-primary:hover,
.dashboard-btn-primary:focus {
    color: #0f172a;
}

.dashboard-btn-secondary {
    background: rgba(255, 255, 255, 0.08);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.18);
}

.dashboard-btn-secondary:hover,
.dashboard-btn-secondary:focus {
    color: #fff;
    background: rgba(255, 255, 255, 0.14);
}

.dashboard-hero-side {
    flex: 1 1 320px;
    display: grid;
    gap: 18px;
}

.dashboard-mini-card,
.dashboard-card {
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 22px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.05);
}

.dashboard-mini-card {
    padding: 20px 22px;
}

.dashboard-mini-card strong {
    display: block;
    color: #0f172a;
    font-size: 20px;
    margin: 8px 0 10px;
}

.dashboard-mini-card p,
.dashboard-mini-card small {
    color: #64748b;
    line-height: 1.8;
}

.dashboard-mini-card.is-active {
    background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 100%);
}

.dashboard-mini-card.is-review {
    background: linear-gradient(180deg, #fff7ed 0%, #ffffff 100%);
}

.dashboard-mini-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    color: #0f172a;
    font-weight: 700;
}

.dashboard-mini-head strong {
    margin: 0;
    font-size: 24px;
}

.dashboard-progress {
    width: 100%;
    height: 10px;
    margin: 14px 0 14px;
    overflow: hidden;
    border-radius: 999px;
    background: #e2e8f0;
}

.dashboard-progress span {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #0ea5e9 0%, #14b8a6 100%);
}

.dashboard-card {
    padding: 24px;
    margin-bottom: 22px;
}

.dashboard-card-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 20px;
}

.dashboard-card-head h2,
.dashboard-note-card h3,
.dashboard-empty-state h3 {
    margin: 6px 0 0;
    color: #0f172a;
    font-size: 24px;
    font-weight: 800;
}

.dashboard-nav-card {
    padding: 20px;
}

.dashboard-nav-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 18px;
    margin-bottom: 12px;
    color: #0f172a;
    border: 1px solid transparent;
    transition: transform 0.25s ease, border-color 0.25s ease, box-shadow 0.25s ease;
}

.dashboard-nav-item:hover,
.dashboard-nav-item:focus {
    text-decoration: none;
    color: #0f172a;
    transform: translateY(-2px);
    border-color: rgba(14, 165, 233, 0.18);
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.06);
}

.dashboard-nav-item.is-active {
    background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%);
    border-color: rgba(59, 130, 246, 0.2);
}

.dashboard-nav-item.is-danger {
    color: #b91c1c;
    background: #fff7f7;
}

.dashboard-nav-item.is-danger:hover,
.dashboard-nav-item.is-danger:focus {
    color: #991b1b;
    border-color: rgba(220, 38, 38, 0.16);
}

.dashboard-nav-icon {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #0f172a;
    font-size: 18px;
}

.dashboard-nav-copy {
    flex: 1 1 auto;
}

.dashboard-nav-copy strong,
.dashboard-info-item strong {
    display: block;
    color: #0f172a;
    font-size: 16px;
    font-weight: 700;
}

.dashboard-nav-copy span,
.dashboard-info-item span,
.dashboard-stat-copy span,
.dashboard-last-order span {
    display: block;
    color: #64748b;
    font-size: 13px;
    line-height: 1.7;
}

.dashboard-nav-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 52px;
    padding: 6px 12px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #0f172a;
    font-size: 12px;
    font-weight: 800;
}

.dashboard-note-card p,
.dashboard-empty-state p {
    margin: 14px 0 0;
    color: #64748b;
    line-height: 1.9;
}

.dashboard-text-link,
.dashboard-inline-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0f766e;
    font-weight: 700;
}

.dashboard-text-link {
    margin-top: 14px;
}

.dashboard-text-link:hover,
.dashboard-inline-link:hover,
.dashboard-text-link:focus,
.dashboard-inline-link:focus {
    color: #115e59;
    text-decoration: none;
}

.dashboard-stats .col-sm-6 {
    margin-bottom: 20px;
}

.dashboard-stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 0;
}

.dashboard-stat-icon {
    width: 60px;
    height: 60px;
    flex: 0 0 60px;
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.dashboard-stat-icon.is-slate {
    background: #e2e8f0;
    color: #1e293b;
}

.dashboard-stat-icon.is-amber {
    background: #fef3c7;
    color: #b45309;
}

.dashboard-stat-icon.is-green {
    background: #dcfce7;
    color: #15803d;
}

.dashboard-stat-icon.is-blue {
    background: #dbeafe;
    color: #1d4ed8;
}

.dashboard-stat-copy strong {
    display: block;
    margin: 4px 0;
    color: #0f172a;
    font-size: 28px;
    font-weight: 800;
}

.dashboard-stat-copy small,
.dashboard-last-order small {
    color: #64748b;
    line-height: 1.8;
}

.dashboard-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.dashboard-info-item {
    padding: 18px;
    border-radius: 18px;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.dashboard-info-item.is-wide {
    grid-column: 1 / -1;
}

.dashboard-status-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.dashboard-status-chip,
.dashboard-last-order-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    font-weight: 700;
}

.dashboard-status-chip span {
    display: inline;
    font-size: 13px;
}

.dashboard-status-chip strong {
    font-size: 14px;
}

.dashboard-status-chip.is-pending,
.dashboard-last-order-status.is-pending {
    background: #fff7ed;
    color: #c2410c;
}

.dashboard-status-chip.is-confirmed,
.dashboard-last-order-status.is-confirmed {
    background: #eff6ff;
    color: #1d4ed8;
}

.dashboard-status-chip.is-completed,
.dashboard-last-order-status.is-completed {
    background: #ecfdf5;
    color: #047857;
}

.dashboard-status-chip.is-cancelled,
.dashboard-last-order-status.is-cancelled {
    background: #fef2f2;
    color: #b91c1c;
}

.dashboard-last-order-status.is-neutral {
    background: #f1f5f9;
    color: #334155;
}

.dashboard-last-order {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    padding: 20px;
    border-radius: 20px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    border: 1px solid rgba(148, 163, 184, 0.16);
}

.dashboard-last-order-main strong,
.dashboard-last-order-price strong {
    display: block;
    color: #0f172a;
    font-size: 18px;
    font-weight: 800;
    margin-top: 4px;
}

.dashboard-empty-state {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    padding: 24px;
    border-radius: 22px;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    border: 1px dashed rgba(148, 163, 184, 0.5);
}

.dashboard-empty-icon {
    width: 70px;
    height: 70px;
    border-radius: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #0f766e;
    background: #ecfeff;
}

@media (max-width: 991px) {
    .dashboard-shell {
        padding: 22px;
        border-radius: 24px;
    }

    .dashboard-hero-main {
        padding: 24px;
    }

    .dashboard-hero-copy h1 {
        font-size: 30px;
    }
}

@media (max-width: 767px) {
    .dashboard-page {
        padding-top: 36px;
        padding-bottom: 36px;
    }

    .dashboard-shell {
        padding: 16px;
        border-radius: 22px;
    }

    .dashboard-hero-main {
        gap: 18px;
        padding: 20px;
        border-radius: 22px;
    }

    .dashboard-avatar {
        width: 68px;
        height: 68px;
        font-size: 26px;
    }

    .dashboard-hero-copy h1 {
        font-size: 26px;
    }

    .dashboard-card,
    .dashboard-mini-card {
        border-radius: 18px;
    }

    .dashboard-card {
        padding: 18px;
    }

    .dashboard-card-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .dashboard-info-grid {
        grid-template-columns: 1fr;
    }

    .dashboard-info-item.is-wide {
        grid-column: auto;
    }

    .dashboard-status-list {
        gap: 10px;
    }

    .dashboard-status-chip,
    .dashboard-last-order-status {
        width: 100%;
        justify-content: space-between;
    }

    .dashboard-stat-copy strong {
        font-size: 24px;
    }
}
</style>

<?php require_once('footer.php'); ?>
