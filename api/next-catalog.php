<?php
declare(strict_types=1);

require_once __DIR__ . '/next-common.php';

next_api_headers();

if (isset($pdo)) { next_api_rate_limit($pdo, basename(__FILE__)); }

try {
    $mode = trim((string) ($_GET['mode'] ?? 'home'));
    $settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];

    $tops = $pdo->query("SELECT * FROM tbl_top_category ORDER BY tcat_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $mids = $pdo->query("SELECT * FROM tbl_mid_category ORDER BY mcat_id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $ends = $pdo->query("SELECT * FROM tbl_end_category ORDER BY ecat_id ASC")->fetchAll(PDO::FETCH_ASSOC);

    $topCategories = [];
    foreach ($tops as $topRow) {
        $topCategories[] = [
            'id' => (int) $topRow['tcat_id'],
            'name' => next_text($topRow['tcat_name'] ?? ''),
            'url' => '/category?id=' . (int) $topRow['tcat_id'] . '&type=top-category',
        ];
    }

    if ($mode === 'category') {
        $categoryId = (int) ($_GET['id'] ?? 0);
        $categoryType = (string) ($_GET['type'] ?? '');
        if ($categoryId <= 0 || !in_array($categoryType, ['top-category', 'mid-category', 'end-category'], true)) {
            next_json(['success' => false, 'message' => 'تصنيف غير صحيح.'], 400);
        }

        $topById = [];
        $midById = [];
        $endById = [];
        foreach ($tops as $row) {
            $topById[(int) $row['tcat_id']] = $row;
        }
        foreach ($mids as $row) {
            $midById[(int) $row['mcat_id']] = $row;
        }
        foreach ($ends as $row) {
            $endById[(int) $row['ecat_id']] = $row;
        }

        $title = '';
        $finalIds = [];
        if ($categoryType === 'top-category' && isset($topById[$categoryId])) {
            $title = next_text($topById[$categoryId]['tcat_name'] ?? '');
            $midIds = [];
            foreach ($mids as $mid) {
                if ((int) $mid['tcat_id'] === $categoryId) {
                    $midIds[] = (int) $mid['mcat_id'];
                }
            }
            foreach ($ends as $end) {
                if (in_array((int) $end['mcat_id'], $midIds, true)) {
                    $finalIds[] = (int) $end['ecat_id'];
                }
            }
        } elseif ($categoryType === 'mid-category' && isset($midById[$categoryId])) {
            $title = next_text($midById[$categoryId]['mcat_name'] ?? '');
            foreach ($ends as $end) {
                if ((int) $end['mcat_id'] === $categoryId) {
                    $finalIds[] = (int) $end['ecat_id'];
                }
            }
        } elseif ($categoryType === 'end-category' && isset($endById[$categoryId])) {
            $title = next_text($endById[$categoryId]['ecat_name'] ?? '');
            $finalIds[] = $categoryId;
        }

        if ($title === '') {
            next_json(['success' => false, 'message' => 'التصنيف غير موجود.'], 404);
        }

        $products = [];
        if (!empty($finalIds)) {
            $placeholders = implode(',', array_fill(0, count($finalIds), '?'));
            $stmt = $pdo->prepare("SELECT * FROM tbl_product WHERE ecat_id IN ($placeholders) AND p_is_active = 1 ORDER BY p_id DESC");
            $stmt->execute($finalIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $products[] = next_product_card($row);
            }
        }

        next_json([
            'success' => true,
            'store' => [
                'name' => next_text($settings['meta_title_home'] ?? 'متجر الثقة'),
                'logo' => next_asset_url($settings['logo'] ?? ''),
            ],
            'category' => [
                'title' => $title,
                'type' => $categoryType,
                'count' => count($products),
            ],
            'categories' => $topCategories,
            'products' => $products,
        ]);
    }

    $sections = [];
    $queries = [
        ['key' => 'featured', 'title' => 'مختارات المتجر', 'sql' => "SELECT * FROM tbl_product WHERE p_is_featured = 1 AND p_is_active = 1 ORDER BY p_id DESC LIMIT 8"],
        ['key' => 'latest', 'title' => 'أحدث المنتجات', 'sql' => "SELECT * FROM tbl_product WHERE p_is_active = 1 ORDER BY p_id DESC LIMIT 8"],
        ['key' => 'popular', 'title' => 'الأكثر طلبا', 'sql' => "SELECT * FROM tbl_product WHERE p_is_active = 1 ORDER BY p_total_view DESC, p_id DESC LIMIT 8"],
    ];
    foreach ($queries as $query) {
        $items = [];
        try {
            $stmt = $pdo->query($query['sql']);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $items[] = next_product_card($row);
            }
        } catch (Throwable $e) {
            $items = [];
        }
        $sections[] = ['key' => $query['key'], 'title' => $query['title'], 'products' => $items];
    }

    next_json([
        'success' => true,
        'store' => [
            'name' => next_text($settings['meta_title_home'] ?? 'متجر الثقة'),
            'logo' => next_asset_url($settings['logo'] ?? ''),
        ],
        'hero' => [
            'title' => next_text($settings['cta_title'] ?? 'متجر الثقة'),
            'subtitle' => next_text($settings['cta_content'] ?? 'واجهة تسوق حديثة وسريعة مبنية بـ Next.js.'),
            'image' => next_asset_url($settings['cta_photo'] ?? ''),
        ],
        'categories' => $topCategories,
        'sections' => $sections,
    ]);
} catch (Throwable $e) {
    error_log('next-catalog failed: ' . $e->getMessage());
    next_json(['success' => false, 'message' => 'تعذر تحميل بيانات المتجر.'], 500);
}
