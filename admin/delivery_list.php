<?php
require_once('header.php');

if (!isset($pdo)) {
    die('خطأ: لم يتم إنشاء اتصال قاعدة البيانات.');
}

if (!function_exists('delivery_list_redirect')) {
    function delivery_list_redirect(array $params = [])
    { global $dbRepo;
    global $dbRepo;

        $url = 'delivery_list.php';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('delivery_money')) {
    function delivery_money($value)
    { global $dbRepo;
    global $dbRepo;

        return number_format((float) $value, 2) . ' دج';
    }
}

if (!function_exists('delivery_range')) {
    function delivery_range($minPrice, $maxPrice)
    { global $dbRepo;
    global $dbRepo;

        if ($minPrice === null || $maxPrice === null) {
            return 'لا توجد أسعار بعد';
        }

        $minPrice = (float) $minPrice;
        $maxPrice = (float) $maxPrice;

        if (abs($minPrice - $maxPrice) < 0.00001) {
            return delivery_money($minPrice);
        }

        return delivery_money($minPrice) . ' - ' . delivery_money($maxPrice);
    }
}

$error_message = '';
$flash_messages = [
    'company_activated' => 'تم تعيين شركة التوصيل النشطة بنجاح.',
    'company_deleted' => 'تم حذف شركة التوصيل وكل الأسعار التابعة لها.',
    'price_deleted' => 'تم حذف سعر التوصيل بنجاح.'
];
$success_message = isset($_GET['msg'], $flash_messages[$_GET['msg']]) ? $flash_messages[$_GET['msg']] : '';
$allowed_delivery_types = ['منزل', 'مكتب'];
$admin_auto_refresh = admin_build_live_refresh_config($pdo, 'delivery', ['interval_ms' => 25000]);

if (isset($_GET['active_company'])) {
    $active_company_id = (int) $_GET['active_company'];

    if ($active_company_id <= 0) {
        $error_message = 'معرّف الشركة غير صالح.';
    } else {
        try {
            $statement = $dbRepo->prepare('SELECT id FROM tbl_delivery_company WHERE id = ? LIMIT 1');
            $statement->execute([$active_company_id]);

            if (!$statement->fetch(PDO::FETCH_ASSOC)) {
                $error_message = 'الشركة المحددة غير موجودة.';
            } else {
                $pdo->beginTransaction();
                $dbRepo->executeCommand('UPDATE tbl_delivery_company SET active = 0');
                $statement = $dbRepo->prepare('UPDATE tbl_delivery_company SET active = 1 WHERE id = ?');
                $statement->execute([$active_company_id]);
                $pdo->commit();

                $_SESSION['active_company_id'] = $active_company_id;

                if (isset($_GET['next']) && $_GET['next'] === 'manage') {
                    header('Location: delivery-company.php');
                    exit;
                }

                delivery_list_redirect(['msg' => 'company_activated']);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'تعذر تحديث الشركة النشطة حالياً.';
            error_log('delivery_list.php active_company: ' . $e->getMessage());
        }
    }
}

if (isset($_GET['delete_company'])) {
    $delete_company_id = (int) $_GET['delete_company'];

    if ($delete_company_id <= 0) {
        $error_message = 'معرّف الشركة المطلوب حذفها غير صالح.';
    } else {
        try {
            $statement = $dbRepo->prepare('SELECT id FROM tbl_delivery_company WHERE id = ? LIMIT 1');
            $statement->execute([$delete_company_id]);

            if (!$statement->fetch(PDO::FETCH_ASSOC)) {
                $error_message = 'الشركة المطلوب حذفها غير موجودة.';
            } else {
                $pdo->beginTransaction();

                $statement = $dbRepo->prepare('DELETE FROM tbl_delivery_price WHERE company_id = ?');
                $statement->execute([$delete_company_id]);

                $statement = $dbRepo->prepare('DELETE FROM tbl_delivery_company WHERE id = ?');
                $statement->execute([$delete_company_id]);

                $statement = $dbRepo->query('SELECT id FROM tbl_delivery_company WHERE active = 1 LIMIT 1');
                $remaining_active = $statement->fetch(PDO::FETCH_ASSOC);

                if (!$remaining_active) {
                    $statement = $dbRepo->query('SELECT id FROM tbl_delivery_company ORDER BY id ASC LIMIT 1');
                    $fallback_company = $statement->fetch(PDO::FETCH_ASSOC);

                    if ($fallback_company) {
                        $dbRepo->executeCommand('UPDATE tbl_delivery_company SET active = 0');
                        $statement = $dbRepo->prepare('UPDATE tbl_delivery_company SET active = 1 WHERE id = ?');
                        $statement->execute([(int) $fallback_company['id']]);
                        $_SESSION['active_company_id'] = (int) $fallback_company['id'];
                    } else {
                        unset($_SESSION['active_company_id']);
                    }
                } else {
                    $_SESSION['active_company_id'] = (int) $remaining_active['id'];
                }

                $pdo->commit();
                delivery_list_redirect(['msg' => 'company_deleted']);
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'تعذر حذف الشركة المطلوبة.';
            error_log('delivery_list.php delete_company: ' . $e->getMessage());
        }
    }
}

if (isset($_GET['delete_price'])) {
    $delete_price_id = (int) $_GET['delete_price'];

    if ($delete_price_id <= 0) {
        $error_message = 'معرّف السعر غير صالح.';
    } else {
        try {
            $statement = $dbRepo->prepare('DELETE FROM tbl_delivery_price WHERE id = ?');
            $statement->execute([$delete_price_id]);
            delivery_list_redirect(['msg' => 'price_deleted']);
        } catch (Exception $e) {
            $error_message = 'تعذر حذف سعر التوصيل المطلوب.';
            error_log('delivery_list.php delete_price: ' . $e->getMessage());
        }
    }
}

$statement = $dbRepo->query('SELECT id FROM tbl_delivery_company WHERE active = 1 LIMIT 1');
$active_company_row = $statement->fetch(PDO::FETCH_ASSOC);

if (!$active_company_row) {
    $statement = $dbRepo->query('SELECT id FROM tbl_delivery_company ORDER BY id ASC LIMIT 1');
    $first_company_row = $statement->fetch(PDO::FETCH_ASSOC);

    if ($first_company_row) {
        $statement = $dbRepo->prepare('UPDATE tbl_delivery_company SET active = 1 WHERE id = ?');
        $statement->execute([(int) $first_company_row['id']]);
        $_SESSION['active_company_id'] = (int) $first_company_row['id'];
    }
}

$statement = $dbRepo->query("\n    SELECT\n        c.id,\n        c.name,\n        c.active,\n        COUNT(p.id) AS price_count,\n        COUNT(DISTINCT p.wilaya) AS wilaya_count,\n        SUM(CASE WHEN p.delivery_type = 'منزل' THEN 1 ELSE 0 END) AS home_count,\n        SUM(CASE WHEN p.delivery_type = 'مكتب' THEN 1 ELSE 0 END) AS office_count,\n        MIN(p.price) AS min_price,\n        MAX(p.price) AS max_price\n    FROM tbl_delivery_company c\n    LEFT JOIN tbl_delivery_price p ON p.company_id = c.id\n    GROUP BY c.id, c.name, c.active\n    ORDER BY c.active DESC, c.name ASC, c.id ASC\n");
$companies = $statement->fetchAll(PDO::FETCH_ASSOC);

$active_company = null;
$total_prices_all = 0;

foreach ($companies as $index => $company) {
    $companies[$index]['price_count'] = (int) $company['price_count'];
    $companies[$index]['wilaya_count'] = (int) $company['wilaya_count'];
    $companies[$index]['home_count'] = (int) $company['home_count'];
    $companies[$index]['office_count'] = (int) $company['office_count'];
    $companies[$index]['active'] = (int) $company['active'];
    $total_prices_all += $companies[$index]['price_count'];

    if ($companies[$index]['active'] === 1 && $active_company === null) {
        $active_company = $companies[$index];
    }
}

$active_company_id = $active_company ? (int) $active_company['id'] : null;
$price_search = trim($_GET['price_search'] ?? '');
$delivery_type_filter = $_GET['delivery_type'] ?? '';

if (!in_array($delivery_type_filter, $allowed_delivery_types, true)) {
    $delivery_type_filter = '';
}

$filtered_prices = [];
$filtered_prices_count = 0;

if ($active_company_id !== null) {
    $price_query = 'SELECT id, wilaya, delivery_type, price FROM tbl_delivery_price WHERE company_id = ?';
    $price_params = [$active_company_id];

    if ($price_search !== '') {
        $price_query .= ' AND wilaya LIKE ?';
        $price_params[] = '%' . $price_search . '%';
    }

    if ($delivery_type_filter !== '') {
        $price_query .= ' AND delivery_type = ?';
        $price_params[] = $delivery_type_filter;
    }

    $price_query .= " ORDER BY wilaya ASC, CASE WHEN delivery_type = 'منزل' THEN 0 ELSE 1 END ASC, price ASC";
    $statement = $dbRepo->prepare($price_query);
    $statement->execute($price_params);
    $filtered_prices = $statement->fetchAll(PDO::FETCH_ASSOC);
    $filtered_prices_count = count($filtered_prices);
}

$has_filters = ($price_search !== '' || $delivery_type_filter !== '');
?>

<style>
.page-note{margin:8px 0 0;color:#6c7a89;font-size:14px}
.delivery-admin .panel-box{border-top:3px solid #1f6fb2;border-radius:12px;box-shadow:0 10px 24px rgba(18,35,52,.08);overflow:hidden}
.delivery-admin .panel-box .box-header{padding:18px 20px;border-bottom:1px solid #e3ebf2;background:#f8fbfe}
.delivery-admin .panel-box .box-body{padding:20px}
.delivery-admin .metric{display:flex;gap:10px;align-items:center;margin-bottom:16px;padding:14px;border:1px solid #dde5ec;border-radius:12px;background:#fff;box-shadow:0 4px 12px rgba(19,37,55,.05)}
.delivery-admin .metric i{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:10px;background:#eef6fd;color:#1f6fb2;font-size:16px}
.delivery-admin .metric small{display:block;color:#6c7a89;font-size:11px;font-weight:700}
.delivery-admin .metric strong{display:block;color:#263442;font-size:18px;line-height:1.3}
.delivery-admin .filters,.delivery-admin .card-actions,.delivery-admin .empty-actions{display:flex;flex-wrap:wrap;gap:8px}
.delivery-admin .filters label,.delivery-admin .search-wrap label{display:block;margin-bottom:4px;color:#263442;font-weight:700}
.delivery-admin .muted{margin:6px 0 0;color:#6c7a89;font-size:13px}
.delivery-admin .search-wrap{max-width:280px;margin-bottom:14px}
.delivery-admin .company-card{height:100%;margin-bottom:16px;padding:14px;border:1px solid #dde5ec;border-radius:12px;background:#fff;box-shadow:0 4px 12px rgba(19,37,55,.05);transition:.15s ease}
.delivery-admin .company-card:hover{box-shadow:0 8px 18px rgba(19,37,55,.08)}
.delivery-admin .company-card.active{border-color:rgba(31,111,178,.30);background:linear-gradient(180deg,#fff 0,#f7fbff 100%)}
.delivery-admin .company-head{display:flex;gap:8px;justify-content:space-between;align-items:center;margin-bottom:10px}
.delivery-admin .company-head h3{margin:0;color:#263442;font-size:15px;font-weight:700}
.delivery-admin .state-pill,.delivery-admin .type-pill{display:inline-flex;align-items:center;justify-content:center;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}
.delivery-admin .state-pill.active{background:#e7f7f0;color:#1e8a63}
.delivery-admin .state-pill.inactive{background:#f3f5f7;color:#7c8a98}
.delivery-admin .mini-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;margin-bottom:10px}
.delivery-admin .mini-stats div{padding:8px;border-radius:8px;background:#f5f8fb}
.delivery-admin .mini-stats strong{display:block;color:#263442;font-size:14px}
.delivery-admin .mini-stats span{display:block;margin-top:2px;color:#6c7a89;font-size:10px}
.delivery-admin .company-range{margin:0 0 10px;color:#6c7a89;font-size:12px;text-align:center}
.delivery-admin .filters{align-items:flex-end;margin-bottom:14px;padding:14px;border:1px solid #dde5ec;border-radius:12px;background:#f5f8fb}
.delivery-admin .filter-field{flex:1 1 200px}
.delivery-admin .type-pill.home{background:#e7f2fd;color:#1f6fb2;min-width:68px}
.delivery-admin .type-pill.office{background:#fff2df;color:#d97706;min-width:68px}
.delivery-admin .empty-state{padding:28px 22px;border:1px dashed #c8d6e3;border-radius:16px;background:#fbfdff;text-align:center}
.delivery-admin .empty-state i{display:inline-flex;align-items:center;justify-content:center;width:58px;height:58px;margin-bottom:14px;border-radius:18px;background:#eef6fd;color:#1f6fb2;font-size:24px}
.delivery-admin .empty-state h3{margin:0 0 8px;color:#263442;font-size:22px;font-weight:700}
.delivery-admin .empty-state p{max-width:620px;margin:0 auto 18px;color:#6c7a89;line-height:1.8}
.delivery-admin .price-card{padding:14px;border-radius:12px;background:#fff;border:1px solid #dde5ec;box-shadow:0 4px 12px rgba(19,37,55,.05);transition:.15s ease;margin-bottom:14px;display:flex;flex-direction:column;gap:8px}
.delivery-admin .price-card:hover{box-shadow:0 8px 18px rgba(19,37,55,.08)}
.delivery-admin .price-card.home{border-right:3px solid #1f6fb2}
.delivery-admin .price-card.office{border-right:3px solid #d97706}
.delivery-admin .price-card-head{display:flex;justify-content:space-between;align-items:center}
.delivery-admin .price-wilaya{font-weight:700;color:#263442;font-size:14px}
.delivery-admin .price-amount{font-size:18px;font-weight:800;color:#1f6fb2}
.delivery-admin .price-card.office .price-amount{color:#d97706}
.delivery-admin .price-card-actions{display:flex;gap:6px;padding-top:4px;border-top:1px solid #eef2f6}
.delivery-admin .inline-company-form{display:flex;align-items:center;gap:4px;font-size:13px;color:#6c7a89}
@media (max-width:767px){.delivery-admin .panel-box .box-header,.delivery-admin .panel-box .box-body{padding:14px}.delivery-admin .filters,.delivery-admin .card-actions,.delivery-admin .empty-actions{flex-direction:column}.delivery-admin .filters .btn,.delivery-admin .card-actions .btn,.delivery-admin .empty-actions .btn{width:100%}.delivery-admin .mini-stats{grid-template-columns:1fr}.delivery-admin .price-cards .price-card{margin-bottom:10px}}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>شركات التوصيل</h1>
        <p class="page-note">واجهة أوضح لإدارة الشركات، تحديد الشركة النشطة، ومراجعة أسعار الولايات بسرعة.</p>
    </div>
    <div class="content-header-right">
        <a href="add_edit_delivery.php" class="btn btn-success"><i class="fa fa-plus"></i> إضافة شركة جديدة</a>
    </div>
</section>

<section class="content delivery-admin">
    <?php if ($error_message !== ''): ?>
        <div class="callout callout-danger"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($success_message !== ''): ?>
        <div class="callout callout-success"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (empty($companies)): ?>
        <div class="box panel-box">
            <div class="box-body">
                <div class="empty-state">
                    <i class="fa fa-truck"></i>
                    <h3>لا توجد شركات توصيل بعد</h3>
                    <p>ابدأ بإضافة أول شركة توصيل، وبعدها يمكنك تفعيل الشركة المناسبة وإدارة الأسعار لكل ولاية من نفس القسم.</p>
                    <div class="empty-actions">
                        <a href="add_edit_delivery.php" class="btn btn-success"><i class="fa fa-plus"></i> إضافة شركة توصيل</a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-3 col-sm-6"><div class="metric"><i class="fa fa-building-o"></i><div><small>إجمالي الشركات</small><strong><?= number_format(count($companies)); ?></strong></div></div></div>
            <div class="col-lg-3 col-sm-6"><div class="metric"><i class="fa fa-check-circle"></i><div><small>الشركة النشطة</small><strong><?= htmlspecialchars($active_company['name'], ENT_QUOTES, 'UTF-8'); ?></strong></div></div></div>
            <div class="col-lg-3 col-sm-6"><div class="metric"><i class="fa fa-map-marker"></i><div><small>تغطية الشركة النشطة</small><strong><?= number_format((int) $active_company['wilaya_count']); ?> ولاية</strong></div></div></div>
            <div class="col-lg-3 col-sm-6"><div class="metric"><i class="fa fa-tags"></i><div><small>إجمالي التسعيرات</small><strong><?= number_format($total_prices_all); ?></strong></div></div></div>
        </div>

        <div class="box panel-box">
            <div class="box-header with-border">
                <h3 class="box-title">قائمة الشركات</h3>
                <form class="inline-company-form" method="get" action="delivery_list.php">
                    <label for="active_company">النشطة:</label>
                    <select name="active_company" id="active_company" class="form-control input-sm" style="width:auto;display:inline-block;margin:0 6px;">
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= (int) $company['id']; ?>" <?= ((int) $company['id'] === $active_company_id) ? 'selected' : ''; ?>><?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-xs"><i class="fa fa-check"></i></button>
                </form>
            </div>
            <div class="box-body">
                <div class="search-wrap">
                    <label for="company_search">بحث سريع داخل الشركات</label>
                    <input type="text" id="company_search" class="form-control" placeholder="ابحث باسم الشركة...">
                </div>

                <div class="row" id="company_cards">
                    <?php foreach ($companies as $company): ?>
                        <?php $company_id = (int) $company['id']; $is_active = ((int) $company['active'] === 1); ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 company-card-col" data-company-name="<?= htmlspecialchars(mb_strtolower($company['name'], 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="company-card<?= $is_active ? ' active' : ''; ?>">
                                <div class="company-head">
                                    <div>
                                        <h3><?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    </div>
                                    <span class="state-pill <?= $is_active ? 'active' : 'inactive'; ?>"><?= $is_active ? 'نشطة' : 'غير نشطة'; ?></span>
                                </div>

                                <div class="mini-stats">
                                    <div><strong><?= number_format((int) $company['price_count']); ?></strong><span>سعر</span></div>
                                    <div><strong><?= number_format((int) $company['wilaya_count']); ?></strong><span>ولاية</span></div>
                                    <div><strong><?= number_format((int) $company['home_count']); ?></strong><span>منزل</span></div>
                                    <div><strong><?= number_format((int) $company['office_count']); ?></strong><span>مكتب</span></div>
                                </div>

                                <div class="company-range"><?= htmlspecialchars(delivery_range($company['min_price'], $company['max_price']), ENT_QUOTES, 'UTF-8'); ?></div>

                                <div class="card-actions">
                                    <?php if ($is_active): ?>
                                        <a href="delivery-company.php" class="btn btn-primary btn-xs"><i class="fa fa-cog"></i> الأسعار</a>
                                    <?php else: ?>
                                        <a href="delivery_list.php?active_company=<?= $company_id; ?>" class="btn btn-primary btn-xs"><i class="fa fa-check"></i> تفعيل</a>
                                        <a href="delivery_list.php?active_company=<?= $company_id; ?>&next=manage" class="btn btn-info btn-xs"><i class="fa fa-sliders"></i> تفعيل وإدارة</a>
                                    <?php endif; ?>
                                    <a href="add_edit_delivery.php?id=<?= $company_id; ?>" class="btn btn-default btn-xs"><i class="fa fa-pencil"></i></a>
                                    <a href="delivery_list.php?delete_company=<?= $company_id; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد من حذف الشركة وجميع أسعارها؟');"><i class="fa fa-trash"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="box panel-box">
            <div class="box-header with-border">
                <h3 class="box-title">أسعار الشركة النشطة: <?= htmlspecialchars($active_company['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <span class="state-pill active"><?= number_format((int) $active_company['price_count']); ?> سعر · <?= number_format((int) $active_company['wilaya_count']); ?> ولاية</span>
            </div>
            <div class="box-body">
                <form method="get" action="delivery_list.php" class="filters">
                    <div class="filter-field">
                        <label for="price_search">بحث بالولاية</label>
                        <input type="text" id="price_search" name="price_search" class="form-control" value="<?= htmlspecialchars($price_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثال: الجزائر أو وهران">
                    </div>
                    <div class="filter-field">
                        <label for="delivery_type">نوع التوصيل</label>
                        <select name="delivery_type" id="delivery_type" class="form-control">
                            <option value="">كل الأنواع</option>
                            <option value="منزل" <?= $delivery_type_filter === 'منزل' ? 'selected' : ''; ?>>منزل</option>
                            <option value="مكتب" <?= $delivery_type_filter === 'مكتب' ? 'selected' : ''; ?>>مكتب</option>
                        </select>
                    </div>
                    <div class="card-actions">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> تطبيق</button>
                        <a href="delivery_list.php" class="btn btn-default"><i class="fa fa-refresh"></i> إعادة ضبط</a>
                        <a href="delivery-company.php" class="btn btn-success"><i class="fa fa-plus"></i> إضافة أو تعديل الأسعار</a>
                        <a href="delivery-cache.php" class="btn btn-warning"><i class="fa fa-database"></i> إدارة المزامنة (Cache)</a>
                    </div>
                </form>

                <?php if ((int) $active_company['price_count'] > 0): ?>
                    <p class="muted"><?= $has_filters ? 'نتيجة التصفية الحالية: ' : 'المعروض حالياً: '; ?><strong><?= number_format($filtered_prices_count); ?></strong> من أصل <strong><?= number_format((int) $active_company['price_count']); ?></strong> سعر.</p>
                    <?php if ($filtered_prices_count > 0): ?>
                        <div class="row price-cards">
                            <?php foreach ($filtered_prices as $idx => $price): ?>
                                <?php $is_office = ($price['delivery_type'] === 'مكتب'); ?>
                                <div class="col-lg-3 col-md-4 col-sm-6">
                                    <div class="price-card <?= $is_office ? 'office' : 'home'; ?>">
                                        <div class="price-card-head">
                                            <span class="price-wilaya"><?= htmlspecialchars($price['wilaya'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="type-pill <?= $is_office ? 'office' : 'home'; ?>"><?= $is_office ? 'مكتب' : 'منزل'; ?></span>
                                        </div>
                                        <div class="price-amount"><?= htmlspecialchars(delivery_money($price['price']), ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="price-card-actions">
                                            <a href="add_edit_delivery.php?edit_price=<?= (int) $price['id']; ?>&active_company=<?= $active_company_id; ?>" class="btn btn-primary btn-xs"><i class="fa fa-pencil"></i></a>
                                            <a href="delivery_list.php?delete_price=<?= (int) $price['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل تريد حذف سعر هذه الولاية؟');"><i class="fa fa-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fa fa-search"></i>
                            <h3>لا توجد نتائج مطابقة</h3>
                            <p>غيّر كلمات البحث أو نوع التوصيل، أو أعد ضبط الفلاتر لعرض كل الأسعار المسجلة للشركة النشطة.</p>
                            <div class="empty-actions"><a href="delivery_list.php" class="btn btn-default"><i class="fa fa-refresh"></i> إزالة الفلاتر</a></div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-map-o"></i>
                        <h3>لا توجد أسعار مسجلة لهذه الشركة</h3>
                        <p>الشركة النشطة موجودة، لكن لم يتم إدخال أسعار الولايات بعد. انتقل إلى صفحة إدارة الأسعار لإضافة الأسعار المطلوبة حسب نوع التوصيل.</p>
                        <div class="empty-actions">
                            <a href="delivery-company.php" class="btn btn-success"><i class="fa fa-plus"></i> إضافة أول سعر</a>
                            <a href="add_edit_delivery.php?id=<?= $active_company_id; ?>" class="btn btn-default"><i class="fa fa-pencil"></i> تعديل بيانات الشركة</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('company_search');
    if (!searchInput) {
        return;
    }

    var cards = Array.prototype.slice.call(document.querySelectorAll('.company-card-col'));
    searchInput.addEventListener('input', function () {
        var keyword = searchInput.value.toLowerCase().trim();
        cards.forEach(function (card) {
            var name = card.getAttribute('data-company-name') || '';
            card.style.display = name.indexOf(keyword) !== -1 ? '' : 'none';
        });
    });
});
</script>

<?php require_once('footer.php'); ?>
