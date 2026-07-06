<?php
require_once('header.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('location: login.php');
    exit;
}

require_once(__DIR__ . '/../inc/delivery_cache_functions.php');

if (isset($_POST['sync_company'])) {
    $code = $_POST['sync_company'];
    if ($code === 'all') {
        delivery_cache_sync_all($pdo);
    } else {
        delivery_cache_sync_company($pdo, $code);
    }
    header('location: delivery-cache.php?msg=sync_done');
    exit;
}

if (isset($_POST['rebuild_all'])) {
    $dbRepo->executeCommand("TRUNCATE TABLE tbl_delivery_cache_locations");
    $dbRepo->executeCommand("TRUNCATE TABLE tbl_delivery_cache_desks");
    $dbRepo->executeCommand("TRUNCATE TABLE tbl_delivery_cache_logs");
    delivery_cache_sync_all($pdo);
    header('location: delivery-cache.php?msg=rebuild_done');
    exit;
}

$adapters = delivery_cache_get_adapters();
$stats = [];

foreach ($adapters as $code => $adapter) {
    $company_id_query = $dbRepo->prepare("SELECT id FROM tbl_delivery_company WHERE name LIKE ? LIMIT 1");
    $company_id_query->execute(['%' . $adapter['name'] . '%']);
    $cid = $company_id_query->fetchColumn();

    if ($cid) {
        $meta_file = __DIR__ . '/cache/delivery/delivery_cache_meta_' . $cid . '.json';
        $json_file = __DIR__ . '/cache/delivery/delivery_cache_' . $cid . '.json';
        
        $has_json = file_exists($json_file);
        $has_meta = file_exists($meta_file);
        
        $meta_data = [];
        if ($has_meta) {
            $meta_data = json_decode(file_get_contents($meta_file), true);
        }
        
        $stats[$code] = [
            'id' => $cid,
            'name' => $adapter['name'],
            'has_json' => $has_json,
            'has_meta' => $has_meta,
            'version' => $meta_data['version'] ?? 'غير متوفر',
            'last_sync' => $meta_data['last_sync_time'] ?? 'لم تتم المزامنة بعد',
            'wilayas' => $meta_data['total_wilayas'] ?? 0,
            'communes' => $meta_data['total_communes'] ?? 0,
            'desks' => $meta_data['total_desks'] ?? 0,
            'prices' => $meta_data['total_prices'] ?? 0,
            'file_size' => isset($meta_data['file_size']) ? round($meta_data['file_size'] / 1024, 2) . ' KB' : '0 KB',
            'is_valid' => $has_json && $has_meta ? 'Valid' : 'Invalid / Missing',
            'is_configured' => call_user_func($adapter['is_configured'], $pdo)
        ];
    }
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>إدارة المزامنة (Delivery Cache)</h1>
    </div>
</section>

<section class="content">
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'sync_done'): ?>
        <div class="alert alert-success">تمت عملية المزامنة بنجاح!</div>
    <?php endif; ?>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'rebuild_done'): ?>
        <div class="alert alert-success">تم حذف الكاش وإعادة بناء البيانات بالكامل بنجاح!</div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($stats as $code => $st): ?>
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><?php echo htmlspecialchars($st['name']); ?></h3>
                        <?php if (!$st['is_configured']): ?>
                            <span class="label label-danger pull-right">غير مُعد بشكل كامل</span>
                        <?php elseif ($st['is_valid'] === 'Valid'): ?>
                            <span class="label label-success pull-right">JSON Valid</span>
                        <?php else: ?>
                            <span class="label label-warning pull-right">JSON Missing</span>
                        <?php endif; ?>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered">
                            <tr><th>حالة الملف</th><td>
                                <?php if ($st['is_valid'] === 'Valid'): ?>
                                    <span class="text-success"><i class="fa fa-check-circle"></i> صالح (Valid)</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fa fa-times-circle"></i> غير صالح أو مفقود</span>
                                <?php endif; ?>
                            </td></tr>
                            <tr><th>وقت الإنشاء (Version)</th><td><?php echo is_numeric($st['version']) ? date('Y-m-d H:i:s', $st['version']) : $st['version']; ?></td></tr>
                            <tr><th>حجم ملف JSON</th><td><?php echo $st['file_size']; ?></td></tr>
                            <tr><th>آخر وقت مزامنة للبيانات</th><td><?php echo $st['last_sync']; ?></td></tr>
                            <tr><th>عدد الولايات المدعومة</th><td><?php echo $st['wilayas']; ?></td></tr>
                            <tr><th>عدد البلديات المدعومة</th><td><?php echo $st['communes']; ?></td></tr>
                            <tr><th>عدد المكاتب (Desks)</th><td><?php echo $st['desks']; ?></td></tr>
                            <tr><th>عدد الأسعار المخزنة</th><td><?php echo $st['prices']; ?></td></tr>
                        </table>
                    </div>
                    <div class="box-footer">
                        <form method="post" style="display:inline-block; margin-bottom: 5px;">
                            <input type="hidden" name="sync_company" value="<?php echo $code; ?>">
                            <button type="submit" class="btn btn-primary btn-sm" <?php echo !$st['is_configured'] ? 'disabled' : ''; ?>><i class="fa fa-refresh"></i> مزامنة API وإعادة بناء JSON</button>
                        </form>
                        
                        <?php if ($st['has_json']): ?>
                        <a href="cache/delivery/delivery_cache_<?php echo $st['id']; ?>.json" download class="btn btn-info btn-sm"><i class="fa fa-download"></i> تنزيل JSON</a>
                        <a href="cache/delivery/delivery_cache_<?php echo $st['id']; ?>.json" target="_blank" class="btn btn-default btn-sm"><i class="fa fa-eye"></i> عرض المحتوى</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require_once('footer.php'); ?>
