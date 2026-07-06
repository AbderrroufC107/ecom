<?php require_once('header.php'); ?>
<?php
require_once('inc/CSRF_Protect.php');

$csrf = new CSRF_Protect();
admin_ensure_zrexpress_setting_columns($pdo);

if (!function_exists('admin_zrexpress_diag_preview')) {
    function admin_zrexpress_diag_preview($value, $limit = 1200)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > $limit) {
                return mb_substr($value, 0, $limit, 'UTF-8') . PHP_EOL . '...';
            }
            return $value;
        }

        if (strlen($value) > $limit) {
            return substr($value, 0, $limit) . PHP_EOL . '...';
        }

        return $value;
    }
}

if (!function_exists('admin_zrexpress_diag_status_meta')) {
    function admin_zrexpress_diag_status_meta($state)
    {
        switch ($state) {
            case 'pass':
                return ['class' => 'label label-success', 'text' => 'PASS'];
            case 'fail':
                return ['class' => 'label label-danger', 'text' => 'FAIL'];
            default:
                return ['class' => 'label label-warning', 'text' => 'SKIP'];
        }
    }
}

if (!function_exists('admin_zrexpress_diag_add_result')) {
    function admin_zrexpress_diag_add_result(array &$results, $name, $method, $endpoint, $state, $summary, $preview = '', $http_status = 0)
    {
        $results[] = [
            'name' => $name,
            'method' => $method,
            'endpoint' => $endpoint,
            'state' => $state,
            'summary' => trim((string) $summary),
            'preview' => admin_zrexpress_diag_preview($preview),
            'http_status' => (int) $http_status
        ];
    }
}

$settings = zrexpress_normalize_settings(front_get_settings($pdo));
$results = [];

if (isset($_POST['run_zrexpress_diagnostics'])) {
    if ($csrf->validateRequest()) {
        $settings = zrexpress_normalize_settings(front_get_settings($pdo));

        if (!zrexpress_is_configured($settings)) {
            admin_zrexpress_diag_add_result(
                $results,
                'ZRexpress configuration',
                '-',
                '-',
                'fail',
                'ZRexpress integration is not configured or disabled in settings.',
                json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } else {
            // Check 1: Test token/key API
            $test_token = zrexpress_api_request($pdo, $settings, 'GET', '/token');
            if ($test_token['success']) {
                admin_zrexpress_diag_add_result(
                    $results,
                    'Test credentials (/token)',
                    'GET',
                    '/token',
                    'pass',
                    'Credentials are valid and active.',
                    $test_token['response'],
                    $test_token['status_code']
                );
            } else {
                admin_zrexpress_diag_add_result(
                    $results,
                    'Test credentials (/token)',
                    'GET',
                    '/token',
                    'fail',
                    'Invalid credentials or server error: ' . ($test_token['error'] ?: 'API error'),
                    $test_token['response'],
                    $test_token['status_code']
                );
            }

            // Check 2: Pricing
            $test_tarification = zrexpress_api_request($pdo, $settings, 'POST', '/tarification');
            if ($test_tarification['success']) {
                admin_zrexpress_diag_add_result(
                    $results,
                    'Check Tarification (/tarification)',
                    'POST',
                    '/tarification',
                    'pass',
                    'Tarification list retrieved successfully.',
                    $test_tarification['response'],
                    $test_tarification['status_code']
                );
            } else {
                admin_zrexpress_diag_add_result(
                    $results,
                    'Check Tarification (/tarification)',
                    'POST',
                    '/tarification',
                    'fail',
                    'Error calling pricing: ' . ($test_tarification['error'] ?: 'API error'),
                    $test_tarification['response'],
                    $test_tarification['status_code']
                );
            }

            // Check 3: Latest updates
            $test_updates = zrexpress_api_request($pdo, $settings, 'GET', '/tarification');
            if ($test_updates['success']) {
                admin_zrexpress_diag_add_result(
                    $results,
                    'Get Updates (/tarification)',
                    'GET',
                    '/tarification',
                    'pass',
                    'Latest updated parcels retrieved successfully.',
                    $test_updates['response'],
                    $test_updates['status_code']
                );
            } else {
                admin_zrexpress_diag_add_result(
                    $results,
                    'Get Updates (/tarification)',
                    'GET',
                    '/tarification',
                    'fail',
                    'Error calling updates: ' . ($test_updates['error'] ?: 'API error'),
                    $test_updates['response'],
                    $test_updates['status_code']
                );
            }
        }
    } else {
        admin_zrexpress_diag_add_result($results, 'CSRF verification', '-', '-', 'fail', 'CSRF verification failed.');
    }
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>ZRexpress diagnostics & status checker</h1>
    </div>
    <div class="content-header-right">
        <a href="settings.php#tab_zrexpress" class="btn btn-primary btn-sm">تعديل الإعدادات</a>
    </div>
</section>

<section class="content" style="font-family: 'Cairo', 'Outfit', sans-serif;">
    <div class="row">
        <div class="col-md-4">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">ZRexpress Credentials</h3>
                </div>
                <div class="box-body">
                    <table class="table table-condensed table-striped" style="margin-bottom:0;">
                        <tbody>
                            <tr>
                                <th style="width:140px;">Active status</th>
                                <td>
                                    <?php if (!empty($settings['zrexpress_enabled'])): ?>
                                        <span class="label label-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="label label-default">Disabled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>API Token</th>
                                <td>
                                    <?php if ($settings['zrexpress_token'] !== ''): ?>
                                        <span class="text-success"><i class="fa fa-check"></i> Filled</span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fa fa-times"></i> Empty</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>API Key</th>
                                <td>
                                    <?php if ($settings['zrexpress_key'] !== ''): ?>
                                        <span class="text-success"><i class="fa fa-check"></i> Filled</span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="fa fa-times"></i> Empty</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Base URL</th>
                                <td style="font-family: monospace; font-size:11px;">
                                    <?php echo htmlspecialchars($settings['zrexpress_base_url']); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Run Diagnostics</h3>
                </div>
                <form method="post">
                    <?php $csrf->echoInputField(); ?>
                    <div class="box-body">
                        <p class="text-muted">Test connection and API features using endpoints provided by ZRexpress (Procolis API).</p>
                    </div>
                    <div class="box-footer">
                        <button type="submit" name="run_zrexpress_diagnostics" class="btn btn-primary btn-block"><i class="fa fa-play-circle"></i> Run Diagnostics</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-8">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Diagnostic results</h3>
                </div>
                <div class="box-body">
                    <?php if (empty($results)): ?>
                        <p class="text-muted text-center" style="padding:40px;">No results. Click "Run Diagnostics" to verify settings.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Check Name</th>
                                        <th>Method</th>
                                        <th>Endpoint</th>
                                        <th>Status</th>
                                        <th>HTTP Code</th>
                                        <th>Summary</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results as $index => $res): ?>
                                        <?php $meta = admin_zrexpress_diag_status_meta($res['state']); ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($res['name']); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($res['method']); ?></code></td>
                                            <td><code><?php echo htmlspecialchars($res['endpoint']); ?></code></td>
                                            <td><span class="<?php echo $meta['class']; ?>"><?php echo $meta['text']; ?></span></td>
                                            <td><code><?php echo $res['http_status']; ?></code></td>
                                            <td><?php echo htmlspecialchars($res['summary']); ?></td>
                                        </tr>
                                        <?php if ($res['preview'] !== ''): ?>
                                            <tr>
                                                <td colspan="6" style="padding:0;">
                                                    <details style="background:#f9f9f9; padding:8px 12px; border-top:1px solid #eee;">
                                                        <summary style="cursor:pointer; font-size:11px; font-weight:bold; color:#777;">Show Response details</summary>
                                                        <pre style="margin-top:5px; font-size:11px; font-family:monospace; background:#fff; border:1px solid #ddd; max-height:250px; overflow-y:auto;"><?php echo htmlspecialchars($res['preview']); ?></pre>
                                                    </details>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
