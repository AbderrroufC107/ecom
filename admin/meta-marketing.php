<?php require_once('header.php'); ?>
<?php require_once __DIR__ . '/../inc/meta-marketing.php'; ?>
<?php
meta_marketing_ensure_settings_columns($pdo);

$settings = function_exists('front_get_settings') ? front_get_settings($pdo) : [];
$datePreset = isset($_GET['date_preset']) ? trim((string) $_GET['date_preset']) : 'last_30d';
$allowedPresets = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'last_7d' => 'Last 7 days',
    'last_14d' => 'Last 14 days',
    'last_30d' => 'Last 30 days',
    'this_month' => 'This month',
    'last_month' => 'Last month',
];
if (!isset($allowedPresets[$datePreset])) {
    $datePreset = 'last_30d';
}

$configured = trim((string) ($settings['meta_marketing_access_token'] ?? '')) !== ''
    && trim((string) ($settings['meta_marketing_ad_account_id'] ?? '')) !== '';

$insightsResponse = null;
$campaignsResponse = null;
$insight = [];
$campaigns = [];

if ($configured) {
    $insightsResponse = meta_marketing_fetch_insights($settings, $datePreset);
    if (!empty($insightsResponse['ok'])) {
        $rows = $insightsResponse['data']['data'] ?? [];
        $insight = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
    }

    $campaignsResponse = meta_marketing_fetch_campaigns($settings, $datePreset, 25);
    if (!empty($campaignsResponse['ok'])) {
        $campaigns = $campaignsResponse['data']['data'] ?? [];
        if (!is_array($campaigns)) {
            $campaigns = [];
        }
    }
}

function meta_admin_money($value): string
{
    return number_format((float) $value, 2, '.', ' ');
}

function meta_admin_int($value): string
{
    return number_format((float) $value, 0, '.', ' ');
}

function meta_admin_campaign_insight(array $campaign): array
{
    $rows = $campaign['insights']['data'] ?? [];
    return is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : [];
}

function meta_admin_purchases(array $insight): float
{
    return meta_marketing_metric_from_actions($insight['actions'] ?? [], [
        'purchase',
        'offsite_conversion.fb_pixel_purchase',
        'onsite_conversion.purchase',
    ]);
}

function meta_admin_roas(array $insight): string
{
    $rows = $insight['purchase_roas'] ?? [];
    if (is_array($rows) && isset($rows[0]['value'])) {
        return meta_admin_money($rows[0]['value']);
    }
    return '-';
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>Meta Marketing API</h1>
    </div>
    <div class="content-header-right">
        <a href="settings.php#tab_pixels" class="btn btn-primary btn-sm"><i class="fa fa-cog"></i> Settings</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <?php if (!$configured): ?>
                <div class="callout callout-warning">
                    <p>Meta Marketing API is not configured. Add Access Token and Ad Account ID from Settings &gt; Pixels.</p>
                </div>
            <?php endif; ?>

            <?php if ($configured && $insightsResponse && empty($insightsResponse['ok'])): ?>
                <div class="callout callout-danger">
                    <p>Could not load account insights: <?php echo htmlspecialchars((string) ($insightsResponse['error'] ?? 'Unknown error'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($configured && $campaignsResponse && empty($campaignsResponse['ok'])): ?>
                <div class="callout callout-danger">
                    <p>Could not load campaigns: <?php echo htmlspecialchars((string) ($campaignsResponse['error'] ?? 'Unknown error'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">Connection</h3>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Status</strong><br>
                    <?php if ($configured): ?>
                        <span class="label label-success">Configured</span>
                    <?php else: ?>
                        <span class="label label-warning">Missing settings</span>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <strong>Ad Account</strong><br>
                    <?php echo htmlspecialchars(meta_marketing_normalize_ad_account_id($settings['meta_marketing_ad_account_id'] ?? '') ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="col-md-3">
                    <strong>Graph Version</strong><br>
                    <?php echo htmlspecialchars(meta_marketing_normalize_graph_version($settings['meta_marketing_graph_version'] ?? 'v25.0'), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="col-md-3">
                    <strong>Conversions API</strong><br>
                    <?php echo !empty($settings['meta_marketing_enabled']) ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Disabled</span>'; ?>
                </div>
            </div>
        </div>
    </div>

    <form method="get" class="form-inline" style="margin-bottom:15px;">
        <label>Date range&nbsp;</label>
        <select name="date_preset" class="form-control">
            <?php foreach ($allowedPresets as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $datePreset === $key ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-default" type="submit"><i class="fa fa-refresh"></i> Refresh</button>
    </form>

    <div class="row">
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-aqua">
                <div class="inner"><h3><?php echo meta_admin_money($insight['spend'] ?? 0); ?></h3><p>Spend</p></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-green">
                <div class="inner"><h3><?php echo meta_admin_int($insight['clicks'] ?? 0); ?></h3><p>Clicks</p></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-yellow">
                <div class="inner"><h3><?php echo meta_admin_int($insight['impressions'] ?? 0); ?></h3><p>Impressions</p></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-purple">
                <div class="inner"><h3><?php echo meta_admin_money($insight['ctr'] ?? 0); ?>%</h3><p>CTR</p></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-red">
                <div class="inner"><h3><?php echo meta_admin_int(meta_admin_purchases($insight)); ?></h3><p>Purchases</p></div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="small-box bg-blue">
                <div class="inner"><h3><?php echo meta_admin_roas($insight); ?></h3><p>Purchase ROAS</p></div>
            </div>
        </div>
    </div>

    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title">Campaigns</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Objective</th>
                        <th>Spend</th>
                        <th>Clicks</th>
                        <th>Impressions</th>
                        <th>Purchases</th>
                        <th>ROAS</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)): ?>
                        <tr><td colspan="9" class="text-center text-muted">No campaigns found or API data unavailable.</td></tr>
                    <?php else: ?>
                        <?php foreach ($campaigns as $campaign): ?>
                            <?php $ci = is_array($campaign) ? meta_admin_campaign_insight($campaign) : []; ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars((string) ($campaign['name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars((string) ($campaign['id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                </td>
                                <td>
                                    <span class="label label-<?php echo in_array(($campaign['effective_status'] ?? ''), ['ACTIVE'], true) ? 'success' : 'default'; ?>">
                                        <?php echo htmlspecialchars((string) ($campaign['effective_status'] ?? $campaign['status'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($campaign['objective'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo meta_admin_money($ci['spend'] ?? 0); ?></td>
                                <td><?php echo meta_admin_int($ci['clicks'] ?? 0); ?></td>
                                <td><?php echo meta_admin_int($ci['impressions'] ?? 0); ?></td>
                                <td><?php echo meta_admin_int(meta_admin_purchases($ci)); ?></td>
                                <td><?php echo meta_admin_roas($ci); ?></td>
                                <td><?php echo htmlspecialchars((string) ($campaign['updated_time'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
