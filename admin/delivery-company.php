<?php
require_once('header.php');

if (!isset($_SESSION['user'])) {
    header('location: login.php');
    exit;
}

$wilayas = [
    'أدرار', 'الشلف', 'الأغواط', 'أم البواقي', 'باتنة', 'بجاية', 'بسكرة', 'بشار', 'البليدة', 'البويرة',
    'تمنراست', 'تبسة', 'تلمسان', 'تيارت', 'تيزي وزو', 'الجزائر', 'الجلفة', 'جيجل', 'سطيف', 'سعيدة',
    'سكيكدة', 'سيدي بلعباس', 'عنابة', 'قالمة', 'قسنطينة', 'المدية', 'مستغانم', 'المسيلة', 'معسكر', 'ورقلة',
    'وهران', 'البيض', 'إليزي', 'برج بوعريريج', 'بومرداس', 'الطارف', 'تندوف', 'تيسمسيلت', 'الوادي', 'خنشلة',
    'سوق أهراس', 'تيبازة', 'ميلة', 'عين الدفلى', 'النعامة', 'عين تموشنت', 'غرداية', 'غليزان', 'تيميمون',
    'برج باجي مختار', 'أولاد جلال', 'بني عباس', 'عين صالح', 'عين قزام', 'تقرت', 'جانت', 'المغير', 'المنيعة'
];

if (!function_exists('normalize_delivery_price_input')) {
    function normalize_delivery_price_input($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['،', ','], '.', $value);
        return $value;
    }
}

if (!function_exists('load_company_price_rows')) {
    function load_company_price_rows(PDO $pdo, $companyId)
    { global $dbRepo;
    global $dbRepo;

        $statement = $dbRepo->prepare('SELECT * FROM tbl_delivery_price WHERE company_id = ? ORDER BY wilaya ASC, id ASC');
        $statement->execute([$companyId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}

$error_message = '';
$flash_messages = [
    'bulk_saved' => 'تم حفظ أسعار الولايات بنجاح.'
];
$success_message = isset($_GET['msg'], $flash_messages[$_GET['msg']]) ? $flash_messages[$_GET['msg']] : '';

$statement = $dbRepo->prepare('SELECT * FROM tbl_delivery_company WHERE active = 1 LIMIT 1');
$statement->execute();
$company = $statement->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    $statement = $dbRepo->prepare('SELECT * FROM tbl_delivery_company ORDER BY id ASC LIMIT 1');
    $statement->execute();
    $company = $statement->fetch(PDO::FETCH_ASSOC);

    if ($company) {
        $statement = $dbRepo->prepare('UPDATE tbl_delivery_company SET active = 1 WHERE id = ?');
        $statement->execute([$company['id']]);
        $_SESSION['active_company_id'] = (int) $company['id'];
    }
}

$company_id = $company ? (int) $company['id'] : 0;
$submitted_home_prices = [];
$submitted_office_prices = [];

if (isset($_POST['bulk_save']) && $company_id > 0) {
    $submitted_home_prices = $_POST['home_prices'] ?? [];
    $submitted_office_prices = $_POST['office_prices'] ?? [];
    $validation_errors = [];

    foreach ($wilayas as $index => $wilaya_name) {
        $home_value = normalize_delivery_price_input($submitted_home_prices[$index] ?? '');
        $office_value = normalize_delivery_price_input($submitted_office_prices[$index] ?? '');

        if ($home_value !== '' && (!is_numeric($home_value) || (float) $home_value < 0)) {
            $validation_errors[] = 'سعر المنزل غير صالح في ولاية ' . $wilaya_name . '.';
        }

        if ($office_value !== '' && (!is_numeric($office_value) || (float) $office_value < 0)) {
            $validation_errors[] = 'سعر المكتب غير صالح في ولاية ' . $wilaya_name . '.';
        }
    }

    if (!empty($validation_errors)) {
        $error_message = implode(' ', array_slice($validation_errors, 0, 5));
    } else {
        $existing_rows = load_company_price_rows($pdo, $company_id);
        $existing_index = [];

        foreach ($existing_rows as $row) {
            $key = $row['wilaya'] . '||' . $row['delivery_type'];
            if (!isset($existing_index[$key])) {
                $existing_index[$key] = [];
            }
            $existing_index[$key][] = $row;
        }

        try {
            $pdo->beginTransaction();

            $insert_statement = $dbRepo->prepare('INSERT INTO tbl_delivery_price (company_id, wilaya, delivery_type, price) VALUES (?, ?, ?, ?)');
            $update_statement = $dbRepo->prepare('UPDATE tbl_delivery_price SET price = ?, wilaya = ?, delivery_type = ? WHERE id = ?');
            $delete_statement = $dbRepo->prepare('DELETE FROM tbl_delivery_price WHERE id = ?');

            foreach ($wilayas as $index => $wilaya_name) {
                $values_by_type = [
                    'منزل' => normalize_delivery_price_input($submitted_home_prices[$index] ?? ''),
                    'مكتب' => normalize_delivery_price_input($submitted_office_prices[$index] ?? '')
                ];

                foreach ($values_by_type as $delivery_type => $raw_value) {
                    $key = $wilaya_name . '||' . $delivery_type;
                    $rows_for_key = $existing_index[$key] ?? [];

                    if ($raw_value === '') {
                        if (!empty($rows_for_key)) {
                            foreach ($rows_for_key as $row_to_delete) {
                                $delete_statement->execute([(int) $row_to_delete['id']]);
                            }
                        }
                        continue;
                    }

                    $normalized_value = number_format((float) $raw_value, 2, '.', '');

                    if (!empty($rows_for_key)) {
                        $primary_row = array_shift($rows_for_key);
                        $update_statement->execute([
                            $normalized_value,
                            $wilaya_name,
                            $delivery_type,
                            (int) $primary_row['id']
                        ]);

                        foreach ($rows_for_key as $extra_row) {
                            $delete_statement->execute([(int) $extra_row['id']]);
                        }
                    } else {
                        $insert_statement->execute([
                            $company_id,
                            $wilaya_name,
                            $delivery_type,
                            $normalized_value
                        ]);
                    }
                }
            }

            $pdo->commit();
            header('location: delivery-company.php?msg=bulk_saved');
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'حدث خطأ أثناء حفظ الأسعار دفعة واحدة.';
            error_log('delivery-company bulk save: ' . $e->getMessage());
        }
    }
}

$prices = [];
$price_matrix = [];
$wilaya_count = 0;
$home_count = 0;
$office_count = 0;

if ($company_id > 0) {
    $prices = load_company_price_rows($pdo, $company_id);
    $seen_wilayas = [];

    foreach ($prices as $price_row) {
        $wilaya_name = $price_row['wilaya'];
        $type_name = $price_row['delivery_type'];

        if (!isset($price_matrix[$wilaya_name])) {
            $price_matrix[$wilaya_name] = ['منزل' => '', 'مكتب' => ''];
        }

        $price_matrix[$wilaya_name][$type_name] = $price_row['price'];
        $seen_wilayas[$wilaya_name] = true;

        if ($type_name === 'مكتب') {
            $office_count++;
        } else {
            $home_count++;
        }
    }

    $wilaya_count = count($seen_wilayas);
}

$grid_rows = [];
foreach ($wilayas as $index => $wilaya_name) {
    $home_value = array_key_exists($index, $submitted_home_prices)
        ? normalize_delivery_price_input($submitted_home_prices[$index])
        : ($price_matrix[$wilaya_name]['منزل'] ?? '');

    $office_value = array_key_exists($index, $submitted_office_prices)
        ? normalize_delivery_price_input($submitted_office_prices[$index])
        : ($price_matrix[$wilaya_name]['مكتب'] ?? '');

    $grid_rows[] = [
        'index' => $index,
        'wilaya' => $wilaya_name,
        'home_price' => $home_value,
        'office_price' => $office_value
    ];
}

$filled_home_rows = 0;
$filled_office_rows = 0;
foreach ($grid_rows as $row) {
    if ($row['home_price'] !== '') {
        $filled_home_rows++;
    }
    if ($row['office_price'] !== '') {
        $filled_office_rows++;
    }
}
?>

<style>
.delivery-company-page {
    --delivery-primary: #1f6fb2;
    --delivery-primary-dark: #18598f;
    --delivery-primary-soft: #eef6fd;
    --delivery-ink: #24313f;
    --delivery-muted: #6d7986;
    --delivery-line: #dde6ee;
    --delivery-bg: #f5f8fb;
}

.delivery-company-page .delivery-note {
    margin: 8px 0 0;
    color: var(--delivery-muted);
    font-size: 14px;
}

.delivery-company-page .delivery-box {
    border-top: 3px solid var(--delivery-primary);
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(23, 39, 56, 0.08);
    overflow: hidden;
}

.delivery-company-page .delivery-box .box-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--delivery-line);
    background: linear-gradient(180deg, #fbfdff 0%, #f4f8fc 100%);
}

.delivery-company-page .delivery-box .box-body {
    padding: 22px;
}

.delivery-company-page .delivery-metric {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 18px;
    border: 1px solid var(--delivery-line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 20px rgba(19, 37, 55, 0.06);
}

.delivery-company-page .delivery-metric i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: var(--delivery-primary-soft);
    color: var(--delivery-primary);
    font-size: 20px;
}

.delivery-company-page .delivery-metric small {
    display: block;
    color: var(--delivery-muted);
    font-size: 12px;
    font-weight: 700;
}

.delivery-company-page .delivery-metric strong {
    display: block;
    color: var(--delivery-ink);
    font-size: 21px;
    line-height: 1.4;
}

.delivery-company-page .delivery-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    background: var(--delivery-primary-soft);
    color: var(--delivery-primary);
    font-size: 12px;
    font-weight: 700;
}

.delivery-company-page .delivery-title {
    margin: 12px 0 8px;
    color: var(--delivery-ink);
    font-size: 26px;
    font-weight: 700;
}

.delivery-company-page .delivery-text {
    margin: 0;
    color: var(--delivery-muted);
    line-height: 1.8;
}

.delivery-company-page .delivery-helper {
    margin: 16px 0 0;
    padding: 14px 16px;
    border: 1px solid #d5e5f4;
    border-radius: 12px;
    background: #f7fbff;
    color: var(--delivery-ink);
    line-height: 1.8;
}

.delivery-company-page .delivery-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.delivery-company-page .btn {
    padding: 11px 18px;
    border-radius: 12px;
    font-weight: 700;
}

.delivery-company-page .btn-primary {
    background: var(--delivery-primary);
    border-color: var(--delivery-primary);
}

.delivery-company-page .btn-primary:hover,
.delivery-company-page .btn-primary:focus {
    background: var(--delivery-primary-dark);
    border-color: var(--delivery-primary-dark);
}

.delivery-company-page .btn-default {
    border-color: #d7dee6;
    background: #fff;
    color: var(--delivery-ink);
}

.delivery-company-page .table-wrap {
    border: 1px solid var(--delivery-line);
    border-radius: 14px;
    overflow: hidden;
}

.delivery-company-page .table-wrap .table {
    margin-bottom: 0;
}

.delivery-company-page .table-wrap thead th {
    background: #f8fafc;
    color: var(--delivery-ink);
    border-bottom: 1px solid var(--delivery-line);
    font-weight: 700;
    vertical-align: middle;
    white-space: nowrap;
}

.delivery-company-page .table-wrap tbody td {
    vertical-align: middle;
}

.delivery-company-page .bulk-price-input {
    min-width: 130px;
    height: 42px;
    border: 1px solid #d6dfe7;
    border-radius: 10px;
    box-shadow: none;
    text-align: center;
}

.delivery-company-page .bulk-price-input:focus {
    border-color: var(--delivery-primary);
    box-shadow: 0 0 0 3px rgba(31, 111, 178, 0.12);
    outline: none;
}

.delivery-company-page .delivery-side-list {
    display: grid;
    gap: 14px;
}

.delivery-company-page .delivery-side-item {
    padding: 15px;
    border: 1px solid var(--delivery-line);
    border-radius: 14px;
    background: #fff;
}

.delivery-company-page .delivery-side-item strong {
    display: block;
    margin-bottom: 4px;
    color: var(--delivery-ink);
}

.delivery-company-page .delivery-side-item span {
    color: var(--delivery-muted);
    line-height: 1.7;
}

.delivery-company-page .delivery-empty {
    padding: 28px 22px;
    border: 1px dashed #c8d6e3;
    border-radius: 16px;
    background: #fbfdff;
    text-align: center;
}

.delivery-company-page .delivery-empty i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 58px;
    height: 58px;
    margin-bottom: 14px;
    border-radius: 18px;
    background: var(--delivery-primary-soft);
    color: var(--delivery-primary);
    font-size: 24px;
}

.delivery-company-page .delivery-empty h3 {
    margin: 0 0 8px;
    color: var(--delivery-ink);
    font-size: 22px;
    font-weight: 700;
}

.delivery-company-page .delivery-empty p {
    max-width: 620px;
    margin: 0 auto 18px;
    color: var(--delivery-muted);
    line-height: 1.8;
}

@media (max-width: 767px) {
    .delivery-company-page .delivery-box .box-header,
    .delivery-company-page .delivery-box .box-body {
        padding: 16px;
    }

    .delivery-company-page .delivery-actions {
        flex-direction: column;
    }

    .delivery-company-page .delivery-actions .btn {
        width: 100%;
    }

    .delivery-company-page .bulk-price-input {
        min-width: 110px;
    }
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة أسعار التوصيل</h1>
        <p class="delivery-note">كل الولايات في صفحة واحدة، وسعر المنزل والمكتب تحت بعضهما مع حفظ جماعي.</p>
    </div>
    <div class="content-header-right">
        <a href="delivery_list.php" class="btn btn-default">
            <i class="fa fa-arrow-right"></i> العودة إلى الشركات
        </a>
    </div>
</section>

<section class="content delivery-company-page">
    <?php if ($error_message !== ''): ?>
        <div class="callout callout-danger"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($success_message !== ''): ?>
        <div class="callout callout-success"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$company): ?>
        <div class="box delivery-box">
            <div class="box-body">
                <div class="delivery-empty">
                    <i class="fa fa-truck"></i>
                    <h3>لا توجد شركة توصيل نشطة</h3>
                    <p>أضف شركة توصيل أولاً أو فعّل شركة موجودة، ثم ارجع هنا لتسعير كل الولايات دفعة واحدة.</p>
                    <div class="delivery-actions" style="justify-content:center;">
                        <a href="add_edit_delivery.php" class="btn btn-primary">
                            <i class="fa fa-plus"></i> إضافة شركة
                        </a>
                        <a href="delivery_list.php" class="btn btn-default">
                            <i class="fa fa-list"></i> صفحة الشركات
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-lg-3 col-sm-6">
                <div class="delivery-metric">
                    <i class="fa fa-building-o"></i>
                    <div>
                        <small>الشركة النشطة</small>
                        <strong><?= htmlspecialchars($company['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="delivery-metric">
                    <i class="fa fa-map-marker"></i>
                    <div>
                        <small>ولايات لديها تسعير</small>
                        <strong><?= number_format($wilaya_count); ?> / <?= number_format(count($wilayas)); ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="delivery-metric">
                    <i class="fa fa-home"></i>
                    <div>
                        <small>أسعار المنزل</small>
                        <strong><?= number_format($filled_home_rows); ?></strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="delivery-metric">
                    <i class="fa fa-briefcase"></i>
                    <div>
                        <small>أسعار المكتب</small>
                        <strong><?= number_format($filled_office_rows); ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-9">
                <div class="box delivery-box">
                    <div class="box-header with-border">
                        <span class="delivery-badge"><i class="fa fa-table"></i> تسعير كل الولايات</span>
                        <h2 class="delivery-title">أدخل كل الأسعار مرة واحدة</h2>
                        <p class="delivery-text">لكل ولاية خانتان فقط: سعر التوصيل إلى المنزل وسعر التوصيل إلى المكتب. يمكنك تعديل القيم الموجودة مباشرة ثم حفظ الكل دفعة واحدة.</p>
                        <div class="delivery-helper">
                            اترك الخانة فارغة إذا كان هذا النوع غير متوفر في تلك الولاية.
                            وإذا مسحت قيمة موجودة ثم حفظت، سيتم حذف هذا السعر لتلك الولاية.
                        </div>
                    </div>
                    <div class="box-body">
                        <form method="post" action="">
                            <div class="table-responsive table-wrap">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width:70px;">#</th>
                                            <th>الولاية</th>
                                            <th style="width:180px;">سعر المنزل</th>
                                            <th style="width:180px;">سعر المكتب</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grid_rows as $row): ?>
                                            <tr>
                                                <td><?= $row['index'] + 1; ?></td>
                                                <td><?= htmlspecialchars($row['wilaya'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="bulk-price-input"
                                                        name="home_prices[<?= $row['index']; ?>]"
                                                        value="<?= htmlspecialchars((string) $row['home_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="سعر المنزل"
                                                    >
                                                </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        class="bulk-price-input"
                                                        name="office_prices[<?= $row['index']; ?>]"
                                                        value="<?= htmlspecialchars((string) $row['office_price'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        placeholder="سعر المكتب"
                                                    >
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="delivery-actions" style="margin-top:18px;">
                                <button type="submit" class="btn btn-primary" name="bulk_save">
                                    <i class="fa fa-save"></i> حفظ كل الأسعار
                                </button>
                                <a href="delivery_list.php" class="btn btn-default">
                                    <i class="fa fa-exchange"></i> تغيير الشركة النشطة
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <div class="box delivery-box">
                    <div class="box-header with-border">
                        <h3 class="box-title">معلومات سريعة</h3>
                    </div>
                    <div class="box-body">
                        <div class="delivery-side-list">
                            <div class="delivery-side-item">
                                <strong>إجمالي الصفوف المحفوظة</strong>
                                <span><?= number_format(count($prices)); ?> سعر موزع بين المنزل والمكتب.</span>
                            </div>
                            <div class="delivery-side-item">
                                <strong>توزيع الأسعار الحالي</strong>
                                <span>منزل: <?= number_format($home_count); ?>، مكتب: <?= number_format($office_count); ?></span>
                            </div>
                            <div class="delivery-side-item">
                                <strong>طريقة العمل الآن</strong>
                                <span>لم تعد تحتاج لإضافة كل ولاية لوحدها. اكتب الجميع هنا ثم احفظ مرة واحدة.</span>
                            </div>
                            <div class="delivery-side-item">
                                <strong>تعديل شركة أخرى</strong>
                                <span>إذا أردت شركة مختلفة، بدّل الشركة النشطة من صفحة الشركات ثم ارجع لهذا الجدول.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php require_once('footer.php'); ?>
