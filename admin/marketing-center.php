<?php
require_once('inc/config.php');
require_once('inc/functions.php');

if (session_status() === PHP_SESSION_NONE) session_start();

$tenantId = \SaaS\TenantContext::getTenantId();

// Fetch summary data from insights
$insightsRepo = new \Marketing\Repositories\InsightsRepository($pdo);
$summary30    = $insightsRepo->getSummary(30);
$summary7     = $insightsRepo->getSummary(7);
$topCampaigns = $insightsRepo->getTopCampaigns(5, 30);

// Fetch active campaigns count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_meta_campaigns WHERE tenant_id = ? AND status = 'ACTIVE' AND is_deleted = 0");
$stmt->execute([$tenantId]);
$activeCampaigns = $stmt->fetchColumn();

// Fetch recent sync logs
$syncRepo  = new \Marketing\Repositories\SyncLogRepository($pdo);
$syncLogs  = $syncRepo->getRecentLogs(5);

// Fetch ad accounts
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM tbl_meta_ad_accounts WHERE tenant_id = ? AND status = 'ACTIVE'");
$stmt2->execute([$tenantId]);
$adAccountsCount = $stmt2->fetchColumn();

// Attribution stats
$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM tbl_meta_attribution WHERE tenant_id = ? AND first_touch_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt3->execute([$tenantId]);
$attributedLeads = $stmt3->fetchColumn();

require_once('header.php');
?>

<style>
/* Marketing Center - Premium Dashboard */
:root {
    --mc-blue:   #1877f2;
    --mc-green:  #00c875;
    --mc-purple: #7c3aed;
    --mc-orange: #f59e0b;
    --mc-red:    #ef4444;
    --mc-dark:   #0f172a;
    --mc-card:   #ffffff;
    --mc-border: #e2e8f0;
}

.mc-wrapper {
    padding: 0 24px 32px;
    font-family: 'Segoe UI', Tahoma, sans-serif;
    direction: rtl;
}

/* Header */
.mc-header {
    background: linear-gradient(135deg, #1877f2 0%, #0d5dbf 50%, #7c3aed 100%);
    border-radius: 20px;
    padding: 32px 36px;
    margin-bottom: 28px;
    color: white;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}
.mc-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -20%;
    width: 400px;
    height: 400px;
    background: rgba(255,255,255,0.06);
    border-radius: 50%;
}
.mc-header-title h1 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 6px;
    letter-spacing: -0.5px;
}
.mc-header-title p {
    margin: 0;
    opacity: 0.8;
    font-size: 0.95rem;
}
.mc-header-actions {
    display: flex;
    gap: 12px;
    position: relative;
    z-index: 1;
}
.btn-mc {
    padding: 10px 22px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    border: none;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-mc-primary {
    background: rgba(255,255,255,0.2);
    color: white;
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(4px);
}
.btn-mc-primary:hover { background: rgba(255,255,255,0.35); color: white; }
.btn-mc-white {
    background: white;
    color: var(--mc-blue);
}
.btn-mc-white:hover { background: #f0f4ff; color: var(--mc-blue); }

/* Quick Nav */
.mc-quick-nav {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.mc-nav-item {
    padding: 9px 18px;
    background: white;
    border: 1px solid var(--mc-border);
    border-radius: 10px;
    font-size: 0.88rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 7px;
    transition: all 0.2s;
}
.mc-nav-item:hover, .mc-nav-item.active {
    background: var(--mc-blue);
    color: white;
    border-color: var(--mc-blue);
}
.mc-nav-item .badge {
    background: #ef4444;
    color: white;
    border-radius: 20px;
    font-size: 0.7rem;
    padding: 1px 6px;
    font-weight: 700;
}

/* KPI Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
.kpi-card {
    background: white;
    border-radius: 16px;
    padding: 22px 20px;
    border: 1px solid var(--mc-border);
    position: relative;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}
.kpi-card::before {
    content: '';
    position: absolute;
    top: 0; right: 0;
    width: 70px; height: 70px;
    border-radius: 0 16px 0 70px;
    opacity: 0.1;
}
.kpi-blue::before   { background: var(--mc-blue); }
.kpi-green::before  { background: var(--mc-green); }
.kpi-purple::before { background: var(--mc-purple); }
.kpi-orange::before { background: var(--mc-orange); }
.kpi-red::before    { background: var(--mc-red); }

.kpi-icon {
    width: 42px; height: 42px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem;
    margin-bottom: 14px;
}
.kpi-blue .kpi-icon   { background: rgba(24,119,242,0.12); color: var(--mc-blue); }
.kpi-green .kpi-icon  { background: rgba(0,200,117,0.12);  color: var(--mc-green); }
.kpi-purple .kpi-icon { background: rgba(124,58,237,0.12); color: var(--mc-purple); }
.kpi-orange .kpi-icon { background: rgba(245,158,11,0.12); color: var(--mc-orange); }
.kpi-red .kpi-icon    { background: rgba(239,68,68,0.12);  color: var(--mc-red); }

.kpi-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #0f172a;
    line-height: 1;
    margin-bottom: 4px;
}
.kpi-label { font-size: 0.82rem; color: #64748b; font-weight: 500; }
.kpi-trend {
    font-size: 0.78rem;
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
}
.kpi-trend.up   { color: var(--mc-green); }
.kpi-trend.down { color: var(--mc-red); }

/* Content Grid */
.mc-content-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}
@media (max-width: 1100px) {
    .mc-content-grid { grid-template-columns: 1fr; }
}

/* Cards */
.mc-card {
    background: white;
    border: 1px solid var(--mc-border);
    border-radius: 16px;
    overflow: hidden;
}
.mc-card-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--mc-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.mc-card-header h3 {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 9px;
}
.mc-card-body { padding: 20px 22px; }

/* Campaign Table */
.mc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
}
.mc-table th {
    text-align: right;
    color: #64748b;
    font-weight: 600;
    font-size: 0.78rem;
    padding: 8px 12px;
    border-bottom: 1px solid var(--mc-border);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.mc-table td {
    padding: 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
    vertical-align: middle;
}
.mc-table tr:hover td { background: #f8fafc; }

.status-badge {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-block;
}
.status-active   { background: #dcfce7; color: #16a34a; }
.status-paused   { background: #fef3c7; color: #d97706; }
.status-deleted  { background: #fee2e2; color: #dc2626; }

.roas-good  { color: var(--mc-green); font-weight: 700; }
.roas-ok    { color: var(--mc-orange); font-weight: 700; }
.roas-bad   { color: var(--mc-red); font-weight: 700; }

/* Sync Log */
.sync-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.85rem;
}
.sync-item:last-child { border: none; }
.sync-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.sync-success { background: var(--mc-green); }
.sync-failed  { background: var(--mc-red); }
.sync-running { background: var(--mc-blue); animation: pulse 1s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

/* Module Grid */
.modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 24px;
}
.module-card {
    background: white;
    border: 1px solid var(--mc-border);
    border-radius: 14px;
    padding: 22px 16px;
    text-align: center;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
    transition: all 0.25s;
    display: block;
}
.module-card:hover {
    border-color: var(--mc-blue);
    box-shadow: 0 0 0 3px rgba(24,119,242,0.1);
    transform: translateY(-2px);
}
.module-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem;
    margin: 0 auto 12px;
}
.module-name {
    font-weight: 700;
    font-size: 0.88rem;
    color: #0f172a;
    margin-bottom: 4px;
}
.module-desc {
    font-size: 0.75rem;
    color: #94a3b8;
}

/* Chart container */
.chart-container {
    height: 240px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    flex-direction: column;
    gap: 8px;
}
.chart-placeholder {
    font-size: 3rem;
    opacity: 0.3;
}
</style>

<div class="mc-wrapper">

    <!-- Header -->
    <div class="mc-header">
        <div class="mc-header-title">
            <h1><i class="fa fa-rocket"></i> Marketing Center</h1>
            <p>منصة الإعلانات الذكية — Meta · Instagram · WhatsApp</p>
        </div>
        <div class="mc-header-actions">
            <a href="marketing-accounts.php" class="btn-mc btn-mc-primary">
                <i class="fa fa-link"></i> ربط حساب
            </a>
            <a href="marketing-campaign-wizard.php" class="btn-mc btn-mc-white">
                <i class="fa fa-plus"></i> حملة جديدة
            </a>
        </div>
    </div>

    <!-- Quick Nav -->
    <div class="mc-quick-nav">
        <a href="marketing-center.php" class="mc-nav-item active">
            <i class="fa fa-dashboard"></i> Dashboard
        </a>
        <a href="marketing-campaigns.php" class="mc-nav-item">
            <i class="fa fa-bullhorn"></i> الحملات
            <?php if ($activeCampaigns > 0): ?>
            <span class="badge"><?= $activeCampaigns ?></span>
            <?php endif; ?>
        </a>
        <a href="marketing-adsets.php" class="mc-nav-item">
            <i class="fa fa-object-group"></i> Ad Sets
        </a>
        <a href="marketing-ads.php" class="mc-nav-item">
            <i class="fa fa-image"></i> الإعلانات
        </a>
        <a href="marketing-creatives.php" class="mc-nav-item">
            <i class="fa fa-paint-brush"></i> Creative Studio
        </a>
        <a href="marketing-audience.php" class="mc-nav-item">
            <i class="fa fa-users"></i> Audiences
        </a>
        <a href="marketing-leads.php" class="mc-nav-item">
            <i class="fa fa-user-plus"></i> Leads
        </a>
        <a href="marketing-analytics.php" class="mc-nav-item">
            <i class="fa fa-line-chart"></i> Analytics
        </a>
        <a href="marketing-automation.php" class="mc-nav-item">
            <i class="fa fa-bolt"></i> Automation
        </a>
        <a href="marketing-ab-testing.php" class="mc-nav-item">
            <i class="fa fa-flask"></i> A/B Testing
        </a>
        <a href="marketing-pixel-center.php" class="mc-nav-item">
            <i class="fa fa-circle"></i> Pixel Center
        </a>
        <a href="marketing-settings.php" class="mc-nav-item">
            <i class="fa fa-cog"></i> الإعدادات
        </a>
    </div>

    <!-- KPI Grid (30 days) -->
    <div class="kpi-grid">
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="fa fa-dollar"></i></div>
            <div class="kpi-value"><?= number_format($summary30['total_spend'] ?? 0, 0) ?> <small style="font-size:0.6em">دج</small></div>
            <div class="kpi-label">الإنفاق الإعلاني (30 يوم)</div>
            <div class="kpi-trend up"><i class="fa fa-arrow-up"></i> هذا الشهر</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="fa fa-money"></i></div>
            <div class="kpi-value"><?= number_format($summary30['total_revenue'] ?? 0, 0) ?> <small style="font-size:0.6em">دج</small></div>
            <div class="kpi-label">إيراد الحملات (30 يوم)</div>
            <div class="kpi-trend up"><i class="fa fa-arrow-up"></i> من الإعلانات</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="fa fa-bar-chart"></i></div>
            <?php $roas = $summary30['roas'] ?? 0; ?>
            <div class="kpi-value <?= $roas >= 2 ? 'roas-good' : ($roas >= 1 ? 'roas-ok' : 'roas-bad') ?>"><?= number_format($roas, 2) ?>x</div>
            <div class="kpi-label">ROAS (30 يوم)</div>
            <div class="kpi-trend <?= $roas >= 2 ? 'up' : 'down' ?>">
                <i class="fa fa-<?= $roas >= 2 ? 'arrow-up' : 'arrow-down' ?>"></i>
                <?= $roas >= 2 ? 'ممتاز' : ($roas >= 1 ? 'مقبول' : 'خسارة') ?>
            </div>
        </div>
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="fa fa-user-plus"></i></div>
            <div class="kpi-value"><?= number_format($summary30['total_leads'] ?? 0) ?></div>
            <div class="kpi-label">Leads (30 يوم)</div>
            <?php $cpl = ($summary30['total_leads'] ?? 0) > 0 ? ($summary30['total_spend'] ?? 0) / ($summary30['total_leads']) : 0; ?>
            <div class="kpi-trend up">CPL: <?= number_format($cpl, 0) ?> دج</div>
        </div>
        <div class="kpi-card kpi-green">
            <div class="kpi-icon"><i class="fa fa-shopping-cart"></i></div>
            <div class="kpi-value"><?= number_format($summary30['total_purchases'] ?? 0) ?></div>
            <div class="kpi-label">طلبات من إعلانات</div>
            <div class="kpi-trend up"><i class="fa fa-arrow-up"></i> إجمالي</div>
        </div>
        <div class="kpi-card kpi-blue">
            <div class="kpi-icon"><i class="fa fa-eye"></i></div>
            <div class="kpi-value"><?= number_format(($summary30['total_impressions'] ?? 0) / 1000, 1) ?>K</div>
            <div class="kpi-label">Impressions (30 يوم)</div>
            <?php $ctr = $summary30['ctr'] ?? 0; ?>
            <div class="kpi-trend <?= $ctr >= 1 ? 'up' : 'down' ?>">CTR: <?= number_format($ctr, 2) ?>%</div>
        </div>
        <div class="kpi-card kpi-purple">
            <div class="kpi-icon"><i class="fa fa-mouse-pointer"></i></div>
            <div class="kpi-value"><?= number_format($summary30['total_clicks'] ?? 0) ?></div>
            <div class="kpi-label">Clicks (30 يوم)</div>
            <?php $cpc = $summary30['cpc'] ?? 0; ?>
            <div class="kpi-trend <?= $cpc < 30 ? 'up' : 'down' ?>">CPC: <?= number_format($cpc, 1) ?> دج</div>
        </div>
        <div class="kpi-card kpi-orange">
            <div class="kpi-icon"><i class="fa fa-link"></i></div>
            <div class="kpi-value"><?= $activeCampaigns ?></div>
            <div class="kpi-label">حملات نشطة</div>
            <div class="kpi-trend up"><?= $adAccountsCount ?> حساب إعلاني</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="mc-content-grid">

        <!-- Top Campaigns Table -->
        <div class="mc-card">
            <div class="mc-card-header">
                <h3><i class="fa fa-trophy" style="color:var(--mc-orange)"></i> أفضل الحملات (30 يوم)</h3>
                <a href="marketing-campaigns.php" class="btn-mc btn-mc-primary" style="font-size:0.8rem;padding:6px 14px;">عرض الكل</a>
            </div>
            <div class="mc-card-body" style="padding:0;">
                <?php if (empty($topCampaigns)): ?>
                <div class="chart-container">
                    <div class="chart-placeholder"><i class="fa fa-rocket"></i></div>
                    <p>لا توجد حملات بعد</p>
                    <a href="marketing-campaign-wizard.php" class="btn-mc btn-mc-white" style="color:var(--mc-blue);border:1px solid var(--mc-border);">
                        <i class="fa fa-plus"></i> أنشئ حملتك الأولى
                    </a>
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
                            <th>طلبات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topCampaigns as $camp): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($camp['name'] ?? 'غير محدد') ?></strong><br>
                                <small style="color:#94a3b8"><?= $camp['meta_campaign_id'] ?></small>
                            </td>
                            <td><?= number_format($camp['spend'] ?? 0, 0) ?> دج</td>
                            <td><?= number_format($camp['revenue'] ?? 0, 0) ?> دج</td>
                            <td>
                                <?php $r = (float)($camp['roas'] ?? 0); ?>
                                <span class="<?= $r >= 2 ? 'roas-good' : ($r >= 1 ? 'roas-ok' : 'roas-bad') ?>">
                                    <?= number_format($r, 2) ?>x
                                </span>
                            </td>
                            <td><?= number_format($camp['leads'] ?? 0) ?></td>
                            <td><?= number_format($camp['orders'] ?? 0) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sync Logs + Quick Actions -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Quick Sync -->
            <div class="mc-card">
                <div class="mc-card-header">
                    <h3><i class="fa fa-refresh" style="color:var(--mc-blue)"></i> المزامنة</h3>
                </div>
                <div class="mc-card-body">
                    <p style="font-size:0.85rem;color:#64748b;margin:0 0 14px">مزامنة البيانات من Meta بشكل يدوي أو تلقائي.</p>
                    <button onclick="runSync('full')" class="btn-mc btn-mc-white" style="width:100%;justify-content:center;background:var(--mc-blue);color:white;border:none;margin-bottom:8px;">
                        <i class="fa fa-refresh"></i> مزامنة شاملة
                    </button>
                    <button onclick="runSync('insights')" class="btn-mc btn-mc-white" style="width:100%;justify-content:center;margin-bottom:8px;">
                        <i class="fa fa-bar-chart"></i> مزامنة Insights
                    </button>
                    <button onclick="runSync('leads')" class="btn-mc btn-mc-white" style="width:100%;justify-content:center;">
                        <i class="fa fa-user-plus"></i> استيراد Leads
                    </button>
                    <div id="sync-status" style="margin-top:12px;display:none;padding:10px;border-radius:8px;background:#f0f9ff;color:#0369a1;font-size:0.85rem;">
                        <i class="fa fa-spinner fa-spin"></i> جاري المزامنة...
                    </div>
                </div>
            </div>

            <!-- Recent Sync Logs -->
            <div class="mc-card">
                <div class="mc-card-header">
                    <h3><i class="fa fa-history" style="color:#64748b"></i> آخر العمليات</h3>
                </div>
                <div class="mc-card-body" style="padding:12px 22px;">
                    <?php if (empty($syncLogs)): ?>
                    <p style="color:#94a3b8;font-size:0.85rem;text-align:center;padding:16px 0;">لا توجد عمليات مزامنة بعد</p>
                    <?php else: ?>
                    <?php foreach ($syncLogs as $log): ?>
                    <div class="sync-item">
                        <div class="sync-dot sync-<?= strtolower($log['status']) ?>"></div>
                        <div style="flex:1;">
                            <div style="font-weight:600;color:#0f172a;"><?= $log['sync_type'] ?></div>
                            <div style="color:#94a3b8;font-size:0.78rem;"><?= $log['records_synced'] ?> سجل • <?= date('H:i d/m', strtotime($log['started_at'])) ?></div>
                        </div>
                        <span class="status-badge status-<?= strtolower($log['status'] === 'SUCCESS' ? 'active' : ($log['status'] === 'FAILED' ? 'deleted' : 'paused')) ?>">
                            <?= $log['status'] ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modules Grid -->
    <div class="mc-card" style="margin-bottom:24px;">
        <div class="mc-card-header">
            <h3><i class="fa fa-th"></i> الوحدات</h3>
        </div>
        <div class="mc-card-body">
            <div class="modules-grid">
                <a href="marketing-campaign-wizard.php" class="module-card">
                    <div class="module-icon" style="background:#eff6ff;color:var(--mc-blue)">🚀</div>
                    <div class="module-name">Campaign Wizard</div>
                    <div class="module-desc">إنشاء حملة خطوة بخطوة</div>
                </a>
                <a href="marketing-audience.php" class="module-card">
                    <div class="module-icon" style="background:#f5f3ff;color:var(--mc-purple)">👥</div>
                    <div class="module-name">Audience Manager</div>
                    <div class="module-desc">إدارة الجمهور المستهدف</div>
                </a>
                <a href="marketing-creatives.php" class="module-card">
                    <div class="module-icon" style="background:#fff7ed;color:var(--mc-orange)">🎨</div>
                    <div class="module-name">Creative Studio</div>
                    <div class="module-desc">تصميم وإدارة الإعلانات</div>
                </a>
                <a href="marketing-leads.php" class="module-card">
                    <div class="module-icon" style="background:#f0fdf4;color:var(--mc-green)">📋</div>
                    <div class="module-name">Lead Forms</div>
                    <div class="module-desc">نماذج جذب العملاء</div>
                </a>
                <a href="marketing-analytics.php" class="module-card">
                    <div class="module-icon" style="background:#eff6ff;color:var(--mc-blue)">📊</div>
                    <div class="module-name">Analytics</div>
                    <div class="module-desc">تقارير وتحليلات متقدمة</div>
                </a>
                <a href="marketing-automation.php" class="module-card">
                    <div class="module-icon" style="background:#fff7ed;color:var(--mc-orange)">⚡</div>
                    <div class="module-name">Automation</div>
                    <div class="module-desc">قواعد التنبيه التلقائية</div>
                </a>
                <a href="marketing-ab-testing.php" class="module-card">
                    <div class="module-icon" style="background:#f5f3ff;color:var(--mc-purple)">🔬</div>
                    <div class="module-name">A/B Testing</div>
                    <div class="module-desc">اختبار البدائل الإعلانية</div>
                </a>
                <a href="marketing-pixel-center.php" class="module-card">
                    <div class="module-icon" style="background:#fef2f2;color:var(--mc-red)">🎯</div>
                    <div class="module-name">Pixel Center</div>
                    <div class="module-desc">إدارة بكسلات التتبع</div>
                </a>
                <a href="marketing-settings.php" class="module-card">
                    <div class="module-icon" style="background:#f8fafc;color:#64748b">⚙️</div>
                    <div class="module-name">الإعدادات</div>
                    <div class="module-desc">إعداد الـ API والاتصالات</div>
                </a>
            </div>
        </div>
    </div>

</div>

<script>
function runSync(type) {
    document.getElementById('sync-status').style.display = 'block';
    fetch('api/marketing-sync.php?action=' + type, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('sync-status');
            if (data.success) {
                el.style.background = '#f0fdf4';
                el.style.color = '#16a34a';
                el.innerHTML = '<i class="fa fa-check"></i> تمت المزامنة: ' + JSON.stringify(data.result);
                setTimeout(() => location.reload(), 2000);
            } else {
                el.style.background = '#fef2f2';
                el.style.color = '#dc2626';
                el.innerHTML = '<i class="fa fa-times"></i> خطأ: ' + (data.error || 'Unknown error');
            }
        })
        .catch(() => {
            document.getElementById('sync-status').innerHTML = '<i class="fa fa-times"></i> فشل الاتصال بالخادم';
        });
}
</script>

<?php require_once('footer.php'); ?>
