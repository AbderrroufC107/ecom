<?php require_once('header.php'); ?>
<?php
$store_id = $current_store_id;
$store = store_get($pdo, $store_id);

if (!$store) {
    echo '<div class="alert alert-danger">المتجر غير موجود</div>';
    require_once('footer.php');
    exit;
}

$summary = store_get_usage_summary($pdo, $store_id);
$sub_status = $summary['subscription_status'];
$invoices = store_get_invoices($pdo, $store_id);
$billing_error = $_SESSION['billing_error'] ?? '';
unset($_SESSION['billing_error']);

$plans = [
    'starter' => ['price' => 0.00, 'period' => 'شهرياً', 'btn' => 'الخطة الحالية'],
    'professional' => ['price' => 49.99, 'period' => 'شهرياً', 'btn' => 'ترقية'],
    'enterprise' => ['price' => 149.99, 'period' => 'شهرياً', 'btn' => 'اتصل بنا'],
];

function b_format_amount($amount) {
    return number_format((float)$amount, 2, '.', ' ') . ' دج';
}
?>

<style>
.billing-dash {
    font-family: 'Cairo', 'Outfit', sans-serif;
    padding: 24px;
    direction: rtl;
    text-align: right;
    color: #1b2559;
}
.b-card {
    background: rgba(255,255,255,0.95);
    border: 1px solid rgba(226,232,240,0.92);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 18px 40px rgba(112,144,176,0.12);
    margin-bottom: 24px;
    transition: all 0.3s;
}
.b-card:hover { box-shadow: 0 22px 45px rgba(112,144,176,0.18); }
.b-grid { display: grid; gap: 24px; }
.b-grid-2 { grid-template-columns: 1fr 1fr; }
.b-grid-3 { grid-template-columns: 2fr 1fr; }
.b-grid-4 { grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
@media (max-width: 991px) { .b-grid-2, .b-grid-3 { grid-template-columns: 1fr; } }
.b-title { font-size: 28px; font-weight: 800; margin: 0 0 8px; }
.b-subtitle { font-size: 15px; color: #707eae; margin: 0 0 24px; }
.b-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.b-section-head h3 { font-size: 18px; font-weight: 800; margin: 0; }
.b-plan-badge {
    display: inline-block; padding: 8px 20px; border-radius: 12px;
    font-weight: 800; font-size: 14px;
    background: linear-gradient(135deg, #4318ff, #868cff);
    color: white;
}
.b-status-badge { padding: 6px 14px; border-radius: 8px; font-size: 13px; font-weight: 700; display: inline-block; }
.b-status-active { background: #e6f9ed; color: #05cd99; }
.b-status-trial { background: #fff3e0; color: #ff9800; }
.b-status-grace { background: #fff8e1; color: #d97706; }
.b-status-expired { background: #ffebee; color: #f44336; }
.b-status-suspended { background: #fce4ec; color: #c62828; }
.b-progress { height: 10px; border-radius: 5px; background: #f4f7fe; margin: 8px 0; position: relative; overflow: hidden; }
.b-progress-bar { height: 100%; border-radius: 5px; transition: width 0.5s ease; }
.b-progress-green { background: linear-gradient(90deg, #05cd99, #4318ff); }
.b-progress-warning { background: linear-gradient(90deg, #ff9800, #ff5722); }
.b-progress-danger { background: linear-gradient(90deg, #f44336, #c62828); }
.b-table { width: 100%; border-collapse: collapse; }
.b-table th { text-align: right; padding: 12px 8px; font-size: 13px; color: #a3aed1; font-weight: 700; border-bottom: 2px solid #f4f7fe; }
.b-table td { padding: 12px 8px; font-size: 14px; border-bottom: 1px solid #f4f7fe; }
.b-table tr:hover td { background: #f8faff; }
.b-alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
.b-alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
.b-alert-warning { background: #fff8e1; color: #92400e; border: 1px solid #fde68a; }
.b-alert-info { background: #e8f4ff; color: #1e3a5f; border: 1px solid #bfdbfe; }
.b-plan-card {
    background: #f8faff; border: 2px solid #e8ecf4; border-radius: 16px; padding: 20px;
    text-align: center; transition: all 0.3s;
}
.b-plan-card:hover { border-color: #4318ff; transform: translateY(-4px); }
.b-plan-card.active { border-color: #4318ff; background: #f4f0ff; }
.b-plan-card h4 { font-size: 18px; font-weight: 800; margin: 0 0 8px; }
.b-plan-card .price { font-size: 28px; font-weight: 800; color: #4318ff; margin: 8px 0; }
.b-plan-card .period { font-size: 13px; color: #a3aed1; }
.b-plan-card .features { list-style: none; padding: 0; margin: 16px 0; text-align: right; }
.b-plan-card .features li { padding: 4px 0; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 6px; }
.b-btn { display: inline-block; padding: 10px 24px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; transition: all 0.2s; border: none; cursor: pointer; }
.b-btn-primary { background: #4318ff; color: white; }
.b-btn-primary:hover { background: #3311db; color: white; text-decoration: none; }
.b-btn-outline { background: transparent; color: #4318ff; border: 2px solid #4318ff; }
.b-btn-outline:hover { background: #4318ff; color: white; text-decoration: none; }
.b-btn-disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }
</style>

<div class="billing-dash">
    <h1 class="b-title">الفواتير والاشتراك</h1>
    <p class="b-subtitle">إدارة اشتراك المتجر والحدود والاستخدام</p>

    <?php if ($billing_error !== ''): ?>
    <div class="b-alert b-alert-error">
        <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars($billing_error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>

    <?php if ($sub_status['status'] === 'grace_period'): ?>
    <div class="b-alert b-alert-warning">
        <i class="fa fa-clock-o"></i> فترة سماح: <?php echo $sub_status['grace_days_left'] ?? 0; ?> يوم متبقي قبل الإيقاف.
        الرجاء تجديد الاشتراك.
    </div>
    <?php elseif ($sub_status['status'] === 'trial'): ?>
    <div class="b-alert b-alert-info">
        <i class="fa fa-gift"></i> الفترة التجريبية: <?php echo $sub_status['trial_days_left'] ?? 14; ?> يوم متبقي.
        قم بالترقية للاستمرار.
    </div>
    <?php elseif ($sub_status['status'] === 'expired' || $sub_status['read_only']): ?>
    <div class="b-alert b-alert-error">
        <i class="fa fa-lock"></i> الاشتراك منتهي — المتجر في وضع القراءة فقط.
    </div>
    <?php endif; ?>

    <!-- Current Plan & Status -->
    <div class="b-grid b-grid-3">
        <div class="b-card">
            <div class="b-section-head">
                <h3><i class="fa fa-credit-card" style="color: #4318ff; margin-left: 6px;"></i> الاشتراك الحالي</h3>
                <span class="b-plan-badge"><?php echo htmlspecialchars($summary['plan']['label_ar'] ?? $store['plan_type'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <table class="b-table">
                <tr>
                    <td>الحالة</td>
                    <td>
                        <?php
                        $status_classes = [
                            'active' => 'b-status-active',
                            'trial' => 'b-status-trial',
                            'grace_period' => 'b-status-grace',
                            'expired' => 'b-status-expired',
                            'suspended' => 'b-status-suspended',
                        ];
                        $status_labels = [
                            'active' => 'نشط',
                            'trial' => 'تجريبي',
                            'grace_period' => 'فترة سماح',
                            'expired' => 'منتهي',
                            'suspended' => 'موقوف',
                        ];
                        $s = $sub_status['status'] ?? 'unknown';
                        $cls = $status_classes[$s] ?? 'b-status-expired';
                        $lbl = $status_labels[$s] ?? $s;
                        ?>
                        <span class="b-status-badge <?php echo $cls; ?>"><?php echo htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                </tr>
                <tr>
                    <td>الخطة</td>
                    <td><strong><?php echo htmlspecialchars($summary['plan']['label_ar'] ?? $store['plan_type'], ENT_QUOTES, 'UTF-8'); ?></strong> — <?php echo b_format_amount($summary['plan']['monthly_price'] ?? 0); ?> / الشهر</td>
                </tr>
                <?php if (!empty($sub_status['expires_at'])): ?>
                <tr>
                    <td>تاريخ الانتهاء</td>
                    <td><?php echo htmlspecialchars($sub_status['expires_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($sub_status['trial_end'])): ?>
                <tr>
                    <td>نهاية الفترة التجريبية</td>
                    <td><?php echo htmlspecialchars($sub_status['trial_end'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (isset($sub_status['days_left'])): ?>
                <tr>
                    <td>الأيام المتبقية</td>
                    <td><strong><?php echo (int) $sub_status['days_left']; ?></strong> يوم</td>
                </tr>
                <?php endif; ?>
                <?php if (isset($sub_status['trial_days_left'])): ?>
                <tr>
                    <td>الأيام التجريبية المتبقية</td>
                    <td><strong><?php echo (int) $sub_status['trial_days_left']; ?></strong> يوم</td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="b-card">
            <div class="b-section-head">
                <h3><i class="fa fa-line-chart" style="color: #05cd99; margin-left: 6px;"></i> الاستخدام الشهري</h3>
            </div>
            <?php foreach ($summary['metrics'] as $key => $m): ?>
            <div style="margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px;">
                    <span><strong><?php echo htmlspecialchars($m['label'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
                    <span><?php echo $m['current']; ?> / <?php echo $m['unlimited'] ? '∞' : $m['max']; ?></span>
                </div>
                <?php if (!$m['unlimited']): ?>
                <?php
                $bar_class = 'b-progress-green';
                if ($m['percent'] >= 90) $bar_class = 'b-progress-danger';
                elseif ($m['percent'] >= 80) $bar_class = 'b-progress-warning';
                ?>
                <div class="b-progress">
                    <div class="b-progress-bar <?php echo $bar_class; ?>" style="width: <?php echo min(100, $m['percent']); ?>%;"></div>
                </div>
                <div style="font-size: 11px; color: #a3aed1; text-align: left;">
                    <?php echo $m['percent']; ?>% مستخدم
                    <?php if ($m['percent'] >= 100): ?>
                    <span style="color: #f44336; font-weight: 700;"> — تم تجاوز الحد</span>
                    <?php elseif ($m['percent'] >= 90): ?>
                    <span style="color: #ff9800; font-weight: 700;"> — تنبيه: وشيك</span>
                    <?php elseif ($m['percent'] >= 80): ?>
                    <span style="color: #ff9800;"> — يقترب من الحد</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Plans Comparison -->
    <div class="b-card">
        <div class="b-section-head">
            <h3><i class="fa fa-table" style="color: #ff9800; margin-left: 6px;"></i> مقارنة الخطط</h3>
        </div>
        <div class="b-grid b-grid-3">
            <?php foreach ($plans as $plan_key => $pdata):
                $plan_limits = store_get_plan_limits($plan_key);
                $is_current = $store['plan_type'] === $plan_key;
                $feature_labels = [
                    'telegram_notifications' => 'إشعارات تلغرام',
                    'basic_reports' => 'تقارير أساسية',
                    'advanced_reports' => 'تقارير متقدمة',
                    'ai_insights' => 'ذكاء الأعمال',
                    'recovery_engine' => 'محرك الاسترداد',
                    'api_access' => 'API',
                    'custom_domain' => 'نطاق مخصص',
                    'priority_support' => 'دعم ذو أولوية',
                    'white_label' => 'علامة بيضاء',
                ];
            ?>
            <div class="b-plan-card <?php echo $is_current ? 'active' : ''; ?>">
                <h4><?php echo htmlspecialchars($plan_limits['label_ar'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <div class="price"><?php echo $pdata['price'] > 0 ? b_format_amount($pdata['price']) : 'مجاني'; ?></div>
                <div class="period"><?php echo $pdata['period']; ?></div>
                <ul class="features">
                    <li><i class="fa fa-users" style="color: #4318ff;"></i> <?php echo $plan_limits['max_employees'] >= 999999 ? 'موظفين غير محدود' : $plan_limits['max_employees'] . ' موظف'; ?></li>
                    <li><i class="fa fa-shopping-cart" style="color: #4318ff;"></i> <?php echo $plan_limits['max_orders_monthly'] >= 999999 ? 'طلبات غير محدودة' : $plan_limits['max_orders_monthly'] . ' طلب/شهر'; ?></li>
                    <?php foreach ($plan_limits['features'] as $feat):
                        $flabel = $feature_labels[$feat] ?? $feat;
                    ?>
                    <li><i class="fa fa-check-circle" style="color: #05cd99;"></i> <?php echo htmlspecialchars($flabel, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($is_current): ?>
                <span class="b-btn b-btn-disabled">الخطة الحالية</span>
                <?php elseif ($plan_key === 'enterprise'): ?>
                <a href="mailto:<?php echo htmlspecialchars($store['owner_email'] ?? 'support@example.com', ENT_QUOTES, 'UTF-8'); ?>" class="b-btn b-btn-outline">اتصل بنا</a>
                <?php else: ?>
                <a href="billing.php?upgrade=<?php echo $plan_key; ?>" class="b-btn b-btn-primary" onclick="return confirm('ترقية إلى خطة <?php echo $plan_limits['label_ar']; ?>؟')">ترقية</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Invoices -->
    <div class="b-card">
        <div class="b-section-head">
            <h3><i class="fa fa-file-text-o" style="color: #0f766e; margin-left: 6px;"></i> الفواتير</h3>
            <span style="font-size: 13px; color: #a3aed1;">إجمالي: <?php echo count($invoices); ?> فاتورة</span>
        </div>
        <table class="b-table">
            <thead>
                <tr>
                    <th>رقم الفاتورة</th>
                    <th>المبلغ</th>
                    <th>المدفوع</th>
                    <th>حالة</th>
                    <th>تاريخ الإصدار</th>
                    <th>تاريخ الدفع</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                <tr><td colspan="6" style="text-align: center; color: #a3aed1; padding: 24px;">لا توجد فواتير بعد</td></tr>
                <?php else: ?>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                    <td><?php echo b_format_amount($inv['amount']); ?></td>
                    <td><?php echo b_format_amount($inv['paid_amount'] ?? 0); ?></td>
                    <td>
                        <span class="b-status-badge <?php echo $inv['status'] === 'paid' ? 'b-status-active' : 'b-status-expired'; ?>">
                            <?php echo $inv['status'] === 'paid' ? 'مدفوعة' : 'معلقة'; ?>
                        </span>
                    </td>
                    <td><?php echo $inv['issued_at'] ? date('Y-m-d', strtotime($inv['issued_at'])) : '-'; ?></td>
                    <td><?php echo $inv['paid_at'] ? date('Y-m-d', strtotime($inv['paid_at'])) : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Handle upgrade request
if (isset($_GET['upgrade'])) {
    $new_plan = trim($_GET['upgrade']);
    $valid_plans = ['starter', 'professional', 'enterprise'];
    if (in_array($new_plan, $valid_plans, true) && $new_plan !== $store['plan_type']) {
        $performer_id = isset($_SESSION['user']) ? (int)($_SESSION['user']['id'] ?? 0) : 0;
        store_change_plan($pdo, $store_id, $new_plan, $performer_id);
        echo '<script>window.location.href="billing.php";</script>';
        exit;
    }
}
?>

<?php require_once('footer.php'); ?>
