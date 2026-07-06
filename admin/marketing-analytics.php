<?php
require_once('inc/config.php');
require_once('inc/functions.php');
if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();
$days = (int)($_GET['days'] ?? 30);

$insightsRepo = new \Marketing\Repositories\InsightsRepository($pdo);
$summary = $insightsRepo->getSummary($days);
$topCampaigns = $insightsRepo->getTopCampaigns(10, $days);

// Calculate Funnel conversion rates
$impressions = $summary['total_impressions'] ?? 0;
$clicks      = $summary['total_clicks'] ?? 0;
$leads       = $summary['total_leads'] ?? 0;
$orders      = $summary['total_purchases'] ?? 0;

$ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
$cvr = $clicks > 0 ? ($orders / $clicks) * 100 : 0;
$leadRate = $clicks > 0 ? ($leads / $clicks) * 100 : 0;

require_once('header.php');
?>
<style>
.mc-wrapper { padding: 20px 24px; direction: rtl; font-family: 'Segoe UI', sans-serif; }
.mc-header { background: linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%); border-radius: 16px; padding: 24px 30px; margin-bottom: 24px; color: white; display: flex; align-items: center; justify-content: space-between; }
.mc-header h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; }
.mc-header p { margin: 0; opacity: 0.8; font-size: 0.95rem; }
.date-selector { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 12px; display: inline-flex; gap: 4px; }
.date-selector a { padding: 6px 14px; border-radius: 6px; text-decoration: none; color: #475569; font-size: 0.85rem; font-weight: 600; transition: background 0.2s; }
.date-selector a.active { background: #7c3aed; color: white; }
.date-selector a:hover:not(.active) { background: #f1f5f9; }

.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
.kpi-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
.kpi-label { color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; }
.kpi-value { font-size: 1.6rem; font-weight: 700; color: #0f172a; }

.funnel-container { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 30px; margin-bottom: 24px; display: flex; flex-direction: column; align-items: center; gap: 16px; }
.funnel-step { background: linear-gradient(90deg, #1877f2, #7c3aed); color: white; padding: 12px 24px; text-align: center; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.funnel-1 { width: 100%; border-radius: 12px 12px 4px 4px; }
.funnel-2 { width: 80%; border-radius: 4px; }
.funnel-3 { width: 60%; border-radius: 4px; }
.funnel-4 { width: 40%; border-radius: 4px 4px 12px 12px; }
.funnel-stat { font-size: 1.1rem; }
.funnel-rate { font-size: 0.8rem; color: #64748b; margin-top: -10px; }

.mc-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; font-size: 0.9rem; }
.mc-table th { background: #f8fafc; padding: 14px 16px; text-align: right; color: #475569; font-weight: 600; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }
.mc-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
.mc-table tr:hover td { background: #f8fafc; }
</style>

<div class="mc-wrapper">
    <div class="mc-header">
        <div>
            <h1><i class="fa fa-line-chart"></i> التحليلات المتقدمة (Analytics & ROI)</h1>
            <p>تحليل أداء الحملات التسويقية وقمع المبيعات</p>
        </div>
        <div class="date-selector">
            <a href="?days=7" class="<?= $days === 7 ? 'active' : '' ?>">آخر 7 أيام</a>
            <a href="?days=30" class="<?= $days === 30 ? 'active' : '' ?>">آخر 30 يوم</a>
            <a href="?days=90" class="<?= $days === 90 ? 'active' : '' ?>">آخر 90 يوم</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-money"></i> الإنفاق الإعلاني</div>
            <div class="kpi-value"><?= number_format($summary['total_spend'] ?? 0, 0) ?> دج</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-shopping-cart"></i> الإيرادات (المباشرة)</div>
            <div class="kpi-value" style="color: #16a34a"><?= number_format($summary['total_revenue'] ?? 0, 0) ?> دج</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-bar-chart"></i> ROAS</div>
            <div class="kpi-value"><?= number_format($summary['roas'] ?? 0, 2) ?>x</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-bullseye"></i> تكلفة النقر (CPC)</div>
            <div class="kpi-value"><?= number_format($summary['cpc'] ?? 0, 2) ?> دج</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-users"></i> عملاء محتملون (Leads)</div>
            <div class="kpi-value"><?= number_format($summary['total_leads'] ?? 0) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-gift"></i> طلبات مباعة</div>
            <div class="kpi-value"><?= number_format($summary['total_purchases'] ?? 0) ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-line-chart"></i> نسبة النقر (CTR)</div>
            <div class="kpi-value"><?= number_format($summary['ctr'] ?? 0, 2) ?>%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label"><i class="fa fa-calculator"></i> صافي الربح (المبدئي)</div>
            <div class="kpi-value"><?= number_format(($summary['total_revenue'] ?? 0) - ($summary['total_spend'] ?? 0), 0) ?> دج</div>
        </div>
    </div>

    <!-- Funnel -->
    <div style="font-weight: 700; font-size: 1.1rem; color: #0f172a; margin-bottom: 12px;"><i class="fa fa-filter" style="color:#7c3aed"></i> قمع التحويل (Conversion Funnel)</div>
    <div class="funnel-container">
        <div class="funnel-step funnel-1">
            <span>مرات الظهور (Impressions)</span>
            <span class="funnel-stat"><?= number_format($impressions) ?></span>
        </div>
        <div class="funnel-rate">CTR: <?= number_format($ctr, 2) ?>% (نسبة النقر للظهور)</div>
        
        <div class="funnel-step funnel-2">
            <span>النقرات (Clicks)</span>
            <span class="funnel-stat"><?= number_format($clicks) ?></span>
        </div>
        <div class="funnel-rate">نسبة تحويل العملاء المحتملين: <?= number_format($leadRate, 2) ?>%</div>
        
        <div class="funnel-step funnel-3">
            <span>العملاء المحتملون (Leads)</span>
            <span class="funnel-stat"><?= number_format($leads) ?></span>
        </div>
        <div class="funnel-rate">CVR: <?= number_format($cvr, 2) ?>% (نسبة تحويل المبيعات)</div>

        <div class="funnel-step funnel-4">
            <span>المبيعات (Orders)</span>
            <span class="funnel-stat"><?= number_format($orders) ?></span>
        </div>
    </div>

    <!-- Top Campaigns Table -->
    <div style="font-weight: 700; font-size: 1.1rem; color: #0f172a; margin-bottom: 12px;"><i class="fa fa-trophy" style="color:#f59e0b"></i> أفضل الحملات (<?= $days ?> أيام)</div>
    <?php if (empty($topCampaigns)): ?>
        <div style="padding: 30px; text-align: center; background: white; border-radius: 12px; border: 1px solid #e2e8f0; color: #94a3b8;">
            لا توجد بيانات متاحة لهذه الفترة.
        </div>
    <?php else: ?>
        <table class="mc-table">
            <thead>
                <tr>
                    <th>الحملة</th>
                    <th>الإنفاق</th>
                    <th>الإيراد</th>
                    <th>ROAS</th>
                    <th>Leads</th>
                    <th>الطلبات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topCampaigns as $c): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($c['name'] ?? 'بدون اسم') ?></strong><br>
                            <small style="color: #94a3b8;"><?= $c['meta_campaign_id'] ?></small>
                        </td>
                        <td><?= number_format($c['spend'] ?? 0, 2) ?> دج</td>
                        <td><?= number_format($c['revenue'] ?? 0, 2) ?> دج</td>
                        <td style="font-weight: bold; color: <?= ($c['roas'] ?? 0) >= 2 ? '#16a34a' : (($c['roas'] ?? 0) >= 1 ? '#f59e0b' : '#dc2626') ?>">
                            <?= number_format($c['roas'] ?? 0, 2) ?>x
                        </td>
                        <td><?= number_format($c['leads'] ?? 0) ?></td>
                        <td><?= number_format($c['orders'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once('footer.php'); ?>
