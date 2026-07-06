<?php
require_once __DIR__ . '/inc/next-customer-bridge.php';
next_customer_redirect('/category', [
    'id' => $_GET['id'] ?? $_REQUEST['id'] ?? '',
    'type' => $_GET['type'] ?? $_REQUEST['type'] ?? '',
]);

$hide_auth_links = true;
$body_class = 'catalog-react';
$catalog_css = 'assets/css/catalog-react.css';
$page_stylesheet = $catalog_css;
$css_version = @filemtime(__DIR__ . '/' . $catalog_css);
if ($css_version) {
    $page_stylesheet .= '?v=' . $css_version;
}

require_once('header.php');
require_once('inc/encryption.php');

if (!isset($_REQUEST['id'], $_REQUEST['type'])) {
    header('location: index.php');
    exit;
}

$category_id = (int)$_REQUEST['id'];
$category_type = (string)$_REQUEST['type'];
if (!in_array($category_type, ['top-category', 'mid-category', 'end-category'], true) || $category_id <= 0) {
    header('location: index.php');
    exit;
}

if (!function_exists('catalog_image_url')) {
    function catalog_image_url($image)
    {
        $image = trim((string)$image);
        if ($image === '') {
            return '';
        }
        return trim((string)get_front_image_url($image));
    }
}

if (!function_exists('catalog_product_badge')) {
    function catalog_product_badge($row)
    {
        $price = (float)($row['p_current_price'] ?? 0);
        $old = (float)($row['p_old_price'] ?? 0);
        if ($old > $price && $price > 0) {
            $percent = (int)round((($old - $price) / $old) * 100);
            if ($percent > 0) {
                return 'خصم ' . $percent . '%';
            }
        }
        return ((int)($row['p_qty'] ?? 0) === 0) ? 'نفد المخزون' : 'متاح';
    }
}

$top_categories_rows = [];
$mid_categories_rows = [];
$end_categories_rows = [];

$statement = $pdo->prepare("SELECT * FROM tbl_top_category ORDER BY tcat_id ASC");
$statement->execute();
$top_categories_rows = $statement->fetchAll(PDO::FETCH_ASSOC);

$statement = $pdo->prepare("SELECT * FROM tbl_mid_category ORDER BY mcat_id ASC");
$statement->execute();
$mid_categories_rows = $statement->fetchAll(PDO::FETCH_ASSOC);

$statement = $pdo->prepare("SELECT * FROM tbl_end_category ORDER BY ecat_id ASC");
$statement->execute();
$end_categories_rows = $statement->fetchAll(PDO::FETCH_ASSOC);

$top_by_id = [];
$mid_by_id = [];
$end_by_id = [];
foreach ($top_categories_rows as $row) {
    $top_by_id[(int)$row['tcat_id']] = $row;
}
foreach ($mid_categories_rows as $row) {
    $mid_by_id[(int)$row['mcat_id']] = $row;
}
foreach ($end_categories_rows as $row) {
    $end_by_id[(int)$row['ecat_id']] = $row;
}

$title = '';
$final_ecat_ids = [];
$breadcrumb = [];

if ($category_type === 'top-category') {
    if (!isset($top_by_id[$category_id])) {
        header('location: index.php');
        exit;
    }
    $title = (string)$top_by_id[$category_id]['tcat_name'];
    $breadcrumb[] = ['label' => $title, 'url' => 'product-category.php?id=' . $category_id . '&type=top-category'];
    $mid_ids = [];
    foreach ($mid_categories_rows as $mid) {
        if ((int)$mid['tcat_id'] === $category_id) {
            $mid_ids[] = (int)$mid['mcat_id'];
        }
    }
    foreach ($end_categories_rows as $end) {
        if (in_array((int)$end['mcat_id'], $mid_ids, true)) {
            $final_ecat_ids[] = (int)$end['ecat_id'];
        }
    }
}

if ($category_type === 'mid-category') {
    if (!isset($mid_by_id[$category_id])) {
        header('location: index.php');
        exit;
    }
    $mid_row = $mid_by_id[$category_id];
    $title = (string)$mid_row['mcat_name'];
    $top_row = $top_by_id[(int)$mid_row['tcat_id']] ?? null;
    if ($top_row) {
        $breadcrumb[] = ['label' => (string)$top_row['tcat_name'], 'url' => 'product-category.php?id=' . (int)$top_row['tcat_id'] . '&type=top-category'];
    }
    $breadcrumb[] = ['label' => $title, 'url' => 'product-category.php?id=' . $category_id . '&type=mid-category'];
    foreach ($end_categories_rows as $end) {
        if ((int)$end['mcat_id'] === $category_id) {
            $final_ecat_ids[] = (int)$end['ecat_id'];
        }
    }
}

if ($category_type === 'end-category') {
    if (!isset($end_by_id[$category_id])) {
        header('location: index.php');
        exit;
    }
    $end_row = $end_by_id[$category_id];
    $title = (string)$end_row['ecat_name'];
    $mid_row = $mid_by_id[(int)$end_row['mcat_id']] ?? null;
    $top_row = $mid_row ? ($top_by_id[(int)$mid_row['tcat_id']] ?? null) : null;
    if ($top_row) {
        $breadcrumb[] = ['label' => (string)$top_row['tcat_name'], 'url' => 'product-category.php?id=' . (int)$top_row['tcat_id'] . '&type=top-category'];
    }
    if ($mid_row) {
        $breadcrumb[] = ['label' => (string)$mid_row['mcat_name'], 'url' => 'product-category.php?id=' . (int)$mid_row['mcat_id'] . '&type=mid-category'];
    }
    $breadcrumb[] = ['label' => $title, 'url' => 'product-category.php?id=' . $category_id . '&type=end-category'];
    $final_ecat_ids[] = $category_id;
}

$products = [];
if (!empty($final_ecat_ids)) {
    $placeholders = implode(',', array_fill(0, count($final_ecat_ids), '?'));
    $statement = $pdo->prepare("SELECT * FROM tbl_product WHERE ecat_id IN ($placeholders) AND p_is_active=1 ORDER BY p_id DESC");
    $statement->execute($final_ecat_ids);
    $products = $statement->fetchAll(PDO::FETCH_ASSOC);
}

$rating_map = front_get_product_rating_map($pdo, array_column($products, 'p_id'));
$product_payload = [];
foreach ($products as $row) {
    $product_id = (int)$row['p_id'];
    $price = (float)($row['p_current_price'] ?? 0);
    $old_price = (float)($row['p_old_price'] ?? 0);
    $product_payload[] = [
        'id' => $product_id,
        'name' => (string)($row['p_name'] ?? ''),
        'price' => $price,
        'oldPrice' => $old_price,
        'image' => catalog_image_url($row['p_featured_photo'] ?? ''),
        'url' => create_secure_product_link($product_id, $row['product_template'] ?? ''),
        'badge' => catalog_product_badge($row),
        'soldOut' => (int)($row['p_qty'] ?? 0) === 0,
        'rating' => (float)($rating_map[$product_id]['avg_rating'] ?? 0),
        'views' => (int)($row['p_total_view'] ?? 0),
        'saving' => ($old_price > $price && $price > 0) ? ($old_price - $price) : 0
    ];
}

$top_categories = [];
foreach (array_slice($top_categories_rows, 0, 8) as $cat) {
    $top_categories[] = [
        'id' => (int)$cat['tcat_id'],
        'name' => (string)$cat['tcat_name'],
        'url' => 'product-category.php?id=' . (int)$cat['tcat_id'] . '&type=top-category'
    ];
}

$side_categories = [];
foreach ($mid_categories_rows as $mid) {
    $top_id = (int)$mid['tcat_id'];
    if (!isset($top_by_id[$top_id])) {
        continue;
    }
    if (!isset($side_categories[$top_id])) {
        $side_categories[$top_id] = [
            'id' => $top_id,
            'name' => (string)$top_by_id[$top_id]['tcat_name'],
            'url' => 'product-category.php?id=' . $top_id . '&type=top-category',
            'children' => []
        ];
    }
    $side_categories[$top_id]['children'][] = [
        'id' => (int)$mid['mcat_id'],
        'name' => (string)$mid['mcat_name'],
        'url' => 'product-category.php?id=' . (int)$mid['mcat_id'] . '&type=mid-category'
    ];
}

$settings = front_get_settings($pdo);
$banner = catalog_image_url($settings['banner_product_category'] ?? '');
$catalog_payload = [
    'store' => [
        'name' => trim((string)($meta_title_home ?: 'متجر الثقة')),
        'logo' => catalog_image_url($logo ?? ''),
        'fallbackLogo' => trim((string)($meta_title_home ?: 'MT')),
        'searchAction' => 'search-result.php'
    ],
    'category' => [
        'title' => $title,
        'type' => $category_type,
        'count' => count($product_payload),
        'banner' => $banner,
        'breadcrumb' => $breadcrumb
    ],
    'topCategories' => $top_categories,
    'sideCategories' => array_values($side_categories),
    'products' => $product_payload
];
?>

<div id="react-category-root" dir="rtl"></div>
<script id="react-category-data" type="application/json"><?php echo json_encode($catalog_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?></script>
<?php if (!is_file(__DIR__ . '/assets/dist/app.min.js')): ?>
<script>
(function () {
    var root = document.getElementById('react-category-root');
    if (root) {
        root.innerHTML = '<div class="catalog-boot">جاري تحميل التصنيف...</div>';
    }
    function loadScript(src, done) {
        var script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = done;
        script.onerror = function () {
            if (root && !root.getAttribute('data-react-ready')) {
                root.innerHTML = '<div class="catalog-boot catalog-boot-error">تعذر تحميل React. أعد تحديث الصفحة.</div>';
            }
        };
        document.head.appendChild(script);
    }
    loadScript('assets/js/react.production.min.js', function () {
        loadScript('assets/js/react-dom.production.min.js', function () {
            loadScript('assets/js/category-react-page.js?v=20260516-1', function () {});
        });
    });
})();
</script>
<?php endif; ?>

<?php require_once('footer.php'); ?>
