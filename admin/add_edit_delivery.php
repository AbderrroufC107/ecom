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

$error_message = '';
$success_message = '';
$company_name = '';
$active = 0;
$wilaya = '';
$price = '';
$delivery_type = 'منزل';
$company_id = 0;

$is_edit_price = isset($_REQUEST['edit_price']);
$is_edit_company = isset($_REQUEST['id']) && !$is_edit_price;

if ($is_edit_company) {
    $statement = $dbRepo->prepare('SELECT * FROM tbl_delivery_company WHERE id = ?');
    $statement->execute([$_REQUEST['id']]);
    $company = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        header('location: delivery_list.php');
        exit;
    }

    $company_name = $company['name'];
    $active = (int) $company['active'];
}

if ($is_edit_price) {
    $statement = $dbRepo->prepare('
        SELECT p.*, c.name AS company_name
        FROM tbl_delivery_price p
        LEFT JOIN tbl_delivery_company c ON c.id = p.company_id
        WHERE p.id = ?
        LIMIT 1
    ');
    $statement->execute([$_REQUEST['edit_price']]);
    $price_row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$price_row) {
        header('location: delivery-company.php');
        exit;
    }

    $wilaya = $price_row['wilaya'];
    $price = $price_row['price'];
    $delivery_type = $price_row['delivery_type'];
    $company_id = (int) $price_row['company_id'];
    $company_name = $price_row['company_name'] ?? '';
}

if (isset($_POST['form1'])) {
    if ($is_edit_price) {
        $wilaya = trim($_POST['wilaya'] ?? '');
        $price = trim($_POST['price'] ?? '');
        $delivery_type = trim($_POST['delivery_type'] ?? '');
        $company_id = (int) ($_POST['company_id'] ?? 0);

        if ($wilaya === '' || $price === '' || $delivery_type === '' || $company_id <= 0) {
            $error_message = 'جميع الحقول مطلوبة.';
        } elseif (!in_array($delivery_type, ['منزل', 'مكتب'], true)) {
            $error_message = 'نوع التوصيل غير صالح.';
        } elseif (!is_numeric($price) || (float) $price < 0) {
            $error_message = 'أدخل سعراً صحيحاً.';
        } else {
            $statement = $dbRepo->prepare('UPDATE tbl_delivery_price SET wilaya = ?, price = ?, delivery_type = ?, company_id = ? WHERE id = ?');
            $statement->execute([$wilaya, $price, $delivery_type, $company_id, $_REQUEST['edit_price']]);
            $success_message = 'تم تحديث سعر التوصيل بنجاح.';

            $statement = $dbRepo->prepare('SELECT name FROM tbl_delivery_company WHERE id = ? LIMIT 1');
            $statement->execute([$company_id]);
            $company_name = $statement->fetchColumn() ?: '';
        }
    } else {
        $company_name = trim($_POST['name'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($company_name === '') {
            $error_message = 'اسم الشركة مطلوب.';
        } else {
            if ($active === 1) {
                $dbRepo->executeCommand('UPDATE tbl_delivery_company SET active = 0');
            }

            if ($is_edit_company) {
                $statement = $dbRepo->prepare('UPDATE tbl_delivery_company SET name = ?, active = ? WHERE id = ?');
                $statement->execute([$company_name, $active, $_REQUEST['id']]);
                $success_message = 'تم تحديث بيانات شركة التوصيل بنجاح.';
            } else {
                $statement = $dbRepo->prepare('INSERT INTO tbl_delivery_company (name, active) VALUES (?, ?)');
                $statement->execute([$company_name, $active]);
                $success_message = 'تم إضافة شركة التوصيل بنجاح.';
                if ($active === 1) {
                    $_SESSION['active_company_id'] = (int) $dbRepo->lastInsertId();
                }
            }
        }
    }
}

$page_title = $is_edit_price
    ? 'تعديل سعر التوصيل'
    : ($is_edit_company ? 'تعديل شركة التوصيل' : 'إضافة شركة توصيل');

$page_description = $is_edit_price
    ? 'حدّث الولاية ونوع التوصيل والسعر من واجهة أبسط وأوضح.'
    : ($is_edit_company
        ? 'حدّث اسم الشركة وحالتها داخل النظام.'
        : 'أضف شركة جديدة وحدد إن كانت الشركة النشطة حالياً.');

$back_url = $is_edit_price ? 'delivery-company.php' : 'delivery_list.php';
$back_label = $is_edit_price ? 'العودة إلى الأسعار' : 'العودة إلى الشركات';
?>

<style>
.delivery-edit-page {
    --delivery-primary: #1f6fb2;
    --delivery-primary-dark: #18598f;
    --delivery-primary-soft: #eef6fd;
    --delivery-ink: #24313f;
    --delivery-muted: #6d7986;
    --delivery-line: #dde6ee;
    --delivery-bg: #f5f8fb;
    --delivery-success: #167c59;
    --delivery-danger: #c0392b;
}

.delivery-edit-note {
    margin: 8px 0 0;
    color: var(--delivery-muted);
    font-size: 14px;
}

.delivery-edit-page .delivery-card,
.delivery-edit-page .delivery-side-card {
    border-top: 3px solid var(--delivery-primary);
    border-radius: 14px;
    box-shadow: 0 14px 30px rgba(23, 39, 56, 0.08);
    overflow: hidden;
}

.delivery-edit-page .delivery-card .box-header,
.delivery-edit-page .delivery-side-card .box-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--delivery-line);
    background: linear-gradient(180deg, #fbfdff 0%, #f4f8fc 100%);
}

.delivery-edit-page .delivery-card .box-body,
.delivery-edit-page .delivery-side-card .box-body {
    padding: 22px;
}

.delivery-edit-page .delivery-badge {
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

.delivery-edit-page .delivery-title {
    margin: 12px 0 8px;
    color: var(--delivery-ink);
    font-size: 26px;
    font-weight: 700;
}

.delivery-edit-page .delivery-text {
    margin: 0;
    color: var(--delivery-muted);
    line-height: 1.8;
}

.delivery-edit-page .delivery-form-grid {
    display: grid;
    gap: 18px;
}

.delivery-edit-page .delivery-field label {
    display: block;
    margin-bottom: 8px;
    color: var(--delivery-ink);
    font-weight: 700;
}

.delivery-edit-page .delivery-field small {
    display: block;
    margin-top: 6px;
    color: var(--delivery-muted);
}

.delivery-edit-page .form-control {
    height: 48px;
    border: 1px solid #d6dfe7;
    border-radius: 12px;
    box-shadow: none;
    font-size: 14px;
}

.delivery-edit-page textarea.form-control {
    min-height: 120px;
    height: auto;
}

.delivery-edit-page .form-control:focus {
    border-color: var(--delivery-primary);
    box-shadow: 0 0 0 3px rgba(31, 111, 178, 0.12);
}

.delivery-edit-page .delivery-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 6px;
}

.delivery-edit-page .btn {
    min-width: 150px;
    padding: 11px 18px;
    border-radius: 12px;
    font-weight: 700;
}

.delivery-edit-page .btn-primary {
    background: var(--delivery-primary);
    border-color: var(--delivery-primary);
}

.delivery-edit-page .btn-primary:hover,
.delivery-edit-page .btn-primary:focus {
    background: var(--delivery-primary-dark);
    border-color: var(--delivery-primary-dark);
}

.delivery-edit-page .btn-default {
    border-color: #d7dee6;
    background: #fff;
    color: var(--delivery-ink);
}

.delivery-edit-page .delivery-switch {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid var(--delivery-line);
    border-radius: 14px;
    background: var(--delivery-bg);
}

.delivery-edit-page .delivery-switch input {
    width: 20px;
    height: 20px;
    margin: 0;
}

.delivery-edit-page .delivery-meta {
    display: grid;
    gap: 14px;
}

.delivery-edit-page .delivery-meta-item {
    padding: 15px;
    border: 1px solid var(--delivery-line);
    border-radius: 14px;
    background: #fff;
}

.delivery-edit-page .delivery-meta-item strong {
    display: block;
    margin-bottom: 4px;
    color: var(--delivery-ink);
    font-size: 15px;
}

.delivery-edit-page .delivery-meta-item span {
    color: var(--delivery-muted);
    line-height: 1.7;
}

@media (max-width: 767px) {
    .delivery-edit-page .delivery-card .box-header,
    .delivery-edit-page .delivery-card .box-body,
    .delivery-edit-page .delivery-side-card .box-header,
    .delivery-edit-page .delivery-side-card .box-body {
        padding: 16px;
    }

    .delivery-edit-page .delivery-actions {
        flex-direction: column;
    }

    .delivery-edit-page .delivery-actions .btn {
        width: 100%;
    }
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="delivery-edit-note"><?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="content-header-right">
        <a href="<?= $back_url; ?>" class="btn btn-default">
            <i class="fa fa-arrow-right"></i> <?= htmlspecialchars($back_label, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
</section>

<section class="content delivery-edit-page">
    <?php if ($error_message !== ''): ?>
        <div class="callout callout-danger"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($success_message !== ''): ?>
        <div class="callout callout-success"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="box delivery-card">
                <div class="box-header with-border">
                    <span class="delivery-badge">
                        <i class="fa <?= $is_edit_price ? 'fa-tag' : 'fa-building-o'; ?>"></i>
                        <?= $is_edit_price ? 'إدارة سعر' : 'إدارة شركة'; ?>
                    </span>
                    <h2 class="delivery-title"><?= htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="delivery-text"><?= htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="box-body">
                    <form action="" method="post" class="delivery-form-grid">
                        <?php if ($is_edit_price): ?>
                            <div class="delivery-field">
                                <label for="company_id_view">الشركة</label>
                                <input type="text" id="company_id_view" class="form-control" value="<?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?>" disabled>
                                <input type="hidden" name="company_id" value="<?= (int) $company_id; ?>">
                            </div>

                            <div class="delivery-field">
                                <label for="wilaya">الولاية</label>
                                <input
                                    type="text"
                                    id="wilaya"
                                    name="wilaya"
                                    class="form-control"
                                    list="wilayas-list"
                                    value="<?= htmlspecialchars($wilaya, ENT_QUOTES, 'UTF-8'); ?>"
                                    placeholder="اختر أو اكتب الولاية"
                                    required
                                >
                                <datalist id="wilayas-list">
                                    <?php foreach ($wilayas as $wilaya_option): ?>
                                        <option value="<?= htmlspecialchars($wilaya_option, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </div>

                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="delivery-field">
                                        <label for="delivery_type">نوع التوصيل</label>
                                        <select name="delivery_type" id="delivery_type" class="form-control" required>
                                            <option value="منزل" <?= $delivery_type === 'منزل' ? 'selected' : ''; ?>>توصيل إلى المنزل</option>
                                            <option value="مكتب" <?= $delivery_type === 'مكتب' ? 'selected' : ''; ?>>توصيل إلى المكتب</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="delivery-field">
                                        <label for="price">السعر</label>
                                        <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثال: 600" required>
                                        <small>أدخل السعر بالدينار الجزائري.</small>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="delivery-field">
                                <label for="name">اسم الشركة</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8'); ?>" placeholder="مثال: ZR Express" required>
                                <small>استخدم اسماً واضحاً كما يظهر لموظفي الإدارة.</small>
                            </div>

                            <div class="delivery-field">
                                <label for="active">حالة الشركة</label>
                                <div class="delivery-switch">
                                    <input type="checkbox" id="active" name="active" value="1" <?= $active === 1 ? 'checked' : ''; ?>>
                                    <div>
                                        <strong>تعيينها كشركة نشطة</strong>
                                        <small>عند التفعيل ستصبح الشركة الافتراضية الحالية في النظام.</small>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="delivery-actions">
                            <button type="submit" class="btn btn-primary" name="form1">
                                <i class="fa fa-save"></i> حفظ التغييرات
                            </button>
                            <a href="<?= $back_url; ?>" class="btn btn-default">
                                <i class="fa fa-times"></i> إلغاء والعودة
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="box delivery-side-card">
                <div class="box-header with-border">
                    <h3 class="box-title">معلومات سريعة</h3>
                </div>
                <div class="box-body">
                    <div class="delivery-meta">
                        <?php if ($is_edit_price): ?>
                            <div class="delivery-meta-item">
                                <strong>الشركة المرتبطة</strong>
                                <span><?= htmlspecialchars($company_name !== '' ? $company_name : 'غير معروفة', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="delivery-meta-item">
                                <strong>نصيحة عملية</strong>
                                <span>استخدم نفس اسم الولاية دائماً لتفادي تكرار الأسعار بأسماء مختلفة لنفس المكان.</span>
                            </div>
                            <div class="delivery-meta-item">
                                <strong>بعد الحفظ</strong>
                                <span>يمكنك الرجوع إلى صفحة إدارة الأسعار لمراجعة التغييرات فوراً.</span>
                            </div>
                        <?php else: ?>
                            <div class="delivery-meta-item">
                                <strong>متى تفعّل الشركة؟</strong>
                                <span>فعّل الشركة فقط إذا كانت هي المعتمدة حالياً لاستقبال وإدارة أسعار التوصيل.</span>
                            </div>
                            <div class="delivery-meta-item">
                                <strong>تنظيم أفضل</strong>
                                <span>يفضّل استخدام اسم مختصر وواضح لتسهيل الاختيار السريع من صفحة الشركات.</span>
                            </div>
                            <div class="delivery-meta-item">
                                <strong>الخطوة التالية</strong>
                                <span>بعد حفظ الشركة، انتقل إلى إدارة الأسعار لإضافة الولايات وأسعار المنزل أو المكتب.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
