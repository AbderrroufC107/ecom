<?php
session_start();
if (!isset($_SESSION['super_admin'])) {
    header('location: login.php');
    exit;
}
require_once __DIR__ . '/../admin/inc/config.php';
require_once __DIR__ . '/../admin/inc/store.php';
store_ensure_tables($pdo);

define('SUPER_ADMIN', true);

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

$stats = store_get_global_stats($pdo);
$stores = store_get_all($pdo);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>Super Admin - لوحة التحكم</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../admin/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: 'Segoe UI', Tahoma, sans-serif; }
        .sa-header { background: linear-gradient(135deg, #1e293b, #0f172a); border-bottom: 1px solid #334155; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .sa-header h1 { margin: 0; font-size: 20px; font-weight: 800; color: #f8fafc; }
        .sa-header a { color: #94a3b8; text-decoration: none; }
        .sa-container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .sa-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .sa-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 20px; }
        .sa-card h3 { margin: 0 0 8px; font-size: 28px; font-weight: 800; color: #f8fafc; }
        .sa-card p { margin: 0; color: #94a3b8; font-size: 13px; }
        .sa-table { width: 100%; border-collapse: collapse; background: #1e293b; border-radius: 12px; overflow: hidden; }
        .sa-table th { background: #334155; color: #94a3b8; font-size: 12px; font-weight: 700; text-transform: uppercase; padding: 12px 16px; text-align: right; }
        .sa-table td { padding: 12px 16px; border-bottom: 1px solid #334155; color: #cbd5e1; font-size: 14px; }
        .sa-table tr:hover td { background: #1a2332; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-success { background: #166534; color: #bbf7d0; }
        .badge-danger { background: #991b1b; color: #fecaca; }
        .badge-warning { background: #92400e; color: #fde68a; }
        .badge-info { background: #1e3a5f; color: #bfdbfe; }
        .btn-sm { padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-warning { background: #d97706; color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-default { background: #334155; color: #e2e8f0; }
        a.btn-sm:hover { opacity: 0.85; }
        .sa-actions { display: flex; gap: 6px; }
        .sa-nav { display: flex; gap: 16px; align-items: center; }
    </style>
</head>
<body>
    <div class="sa-header">
        <h1><i class="fa fa-dashboard"></i> Super Admin</h1>
        <div class="sa-nav">
            <a href="index.php">المتاجر</a>
            <a href="store-create.php">إضافة متجر</a>
            <a href="logout.php">تسجيل الخروج</a>
        </div>
    </div>
    <div class="sa-container">
        <div class="sa-grid">
            <div class="sa-card">
                <h3><?php echo number_format($stats['total_stores']); ?></h3>
                <p>إجمالي المتاجر</p>
            </div>
            <div class="sa-card">
                <h3><?php echo number_format($stats['active_stores']); ?></h3>
                <p>المتاجر النشطة</p>
            </div>
            <div class="sa-card">
                <h3><?php echo number_format($stats['total_orders']); ?></h3>
                <p>إجمالي الطلبات</p>
            </div>
            <div class="sa-card">
                <h3><?php echo number_format($stats['total_employees']); ?></h3>
                <p>إجمالي الموظفين</p>
            </div>
            <div class="sa-card">
                <h3><?php echo number_format($stats['total_revenue'], 2); ?> دج</h3>
                <p>إجمالي الإيرادات</p>
            </div>
        </div>

        <h4 style="color:#f8fafc;margin-bottom:12px;"><i class="fa fa-list"></i> جميع المتاجر</h4>
        <table class="sa-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>المتجر</th>
                    <th>المالك</th>
                    <th>البريد</th>
                    <th>الخطة</th>
                    <th>الحالة</th>
                    <th>التاريخ</th>
                    <th>إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stores as $store): ?>
                <tr>
                    <td><?php echo (int) $store['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($store['store_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small style="color:#64748b;"><?php echo htmlspecialchars($store['store_slug'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                    <td><?php echo htmlspecialchars($store['owner_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($store['owner_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge badge-info"><?php echo htmlspecialchars($store['plan_type'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><span class="badge <?php echo $store['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $store['is_active'] ? 'نشط' : 'موقوف'; ?></span></td>
                    <td><?php echo date('Y-m-d', strtotime($store['created_at'])); ?></td>
                    <td>
                        <div class="sa-actions">
                            <a href="store-edit.php?id=<?php echo (int) $store['id']; ?>" class="btn-sm btn-primary"><i class="fa fa-pencil"></i></a>
                            <?php if ((int) $store['id'] > 1): ?>
                            <a href="store-edit.php?id=<?php echo (int) $store['id']; ?>&action=delete" class="btn-sm btn-danger" onclick="return confirm('حذف هذا المتجر وكافة بياناته؟')"><i class="fa fa-trash"></i></a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>

