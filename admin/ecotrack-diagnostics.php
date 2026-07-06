<?php require_once('header.php'); ?>
<?php
require_once('inc/CSRF_Protect.php');

$csrf = new CSRF_Protect();
admin_ensure_ecotrack_setting_columns($pdo);

if (!function_exists('admin_ecotrack_diag_preview')) {
    function admin_ecotrack_diag_preview($value, $limit = 1200)
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

if (!function_exists('admin_ecotrack_diag_status_meta')) {
    function admin_ecotrack_diag_status_meta($state)
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

if (!function_exists('admin_ecotrack_diag_add_result')) {
    function admin_ecotrack_diag_add_result(array &$results, $name, $method, $endpoint, $state, $summary, $preview = '', $http_status = 0)
    {
        $results[] = [
            'name' => $name,
            'method' => $method,
            'endpoint' => $endpoint,
            'state' => $state,
            'summary' => trim((string) $summary),
            'preview' => admin_ecotrack_diag_preview($preview),
            'http_status' => (int) $http_status
        ];
    }
}

$settings = ecotrack_normalize_settings(front_get_settings($pdo));
$sample_tracking = trim((string) ($_POST['sample_tracking'] ?? ''));
$sample_wilaya_id = isset($_POST['sample_wilaya_id']) ? (int) $_POST['sample_wilaya_id'] : 16;
if ($sample_wilaya_id < 1 || $sample_wilaya_id > 58) {
    $sample_wilaya_id = 16;
}
$sample_status = trim((string) ($_POST['sample_status'] ?? 'en_livraison'));
$order_id_to_open = isset($_POST['order_id_to_open']) ? (int) $_POST['order_id_to_open'] : 0;

$available_checks = [
    'resolve_base' => 'Resolve base URL',
    'validate_token' => 'Validate token',
    'wilayas' => 'Get wilayas',
    'communes' => 'Get communes',
    'fees' => 'Get fees',
    'products' => 'Get products',
    'orders' => 'Get recent orders',
    'tracking_info' => 'Get single tracking info',
    'trackings_info' => 'Get multiple tracking history',
    'updates' => 'Get tracking updates',
    'orders_status' => 'Filter orders by status'
];
$selected_checks = isset($_POST['checks']) && is_array($_POST['checks']) ? array_values(array_intersect(array_keys($available_checks), $_POST['checks'])) : array_keys($available_checks);
$results = [];

if (isset($_POST['open_order_id']) && $order_id_to_open > 0) {
    header('Location: order-details.php?id=' . $order_id_to_open);
    exit;
}

if (isset($_POST['run_ecotrack_diagnostics'])) {
    $csrf->verifyRequest();
    $settings = ecotrack_normalize_settings(front_get_settings($pdo));

    if (!ecotrack_is_configured($settings)) {
        admin_ecotrack_diag_add_result(
            $results,
            'ECOTRACK configuration',
            '-',
            '-',
            'fail',
            'ECOTRACK is not configured yet. Save the token first, and add the Base URL as well if auto-discovery fails.',
            ''
        );
    } else {
        $base_ok = false;
        $base_url = trim((string) ($settings['ecotrack_base_url'] ?? ''));
        $base_error = '';

        if (in_array('resolve_base', $selected_checks, true)) {
            list($base_ok, $base_url, $base_error) = ecotrack_resolve_base_url($pdo, $settings);
            if (!$base_ok && trim((string) ($settings['ecotrack_base_url'] ?? '')) === '') {
                $base_error = 'Auto-discovery failed. Save the exact {{url}} value manually in ECOTRACK settings, then rerun diagnostics.';
            }
            admin_ecotrack_diag_add_result(
                $results,
                'Resolve base URL',
                'AUTO',
                '/api/v1/validate/token',
                $base_ok ? 'pass' : 'fail',
                $base_ok ? ('Resolved successfully: ' . $base_url) : ($base_error !== '' ? $base_error : 'Failed to resolve ECOTRACK base URL'),
                $base_url
            );
            $settings = ecotrack_normalize_settings(front_get_settings($pdo));
        } else {
            list($base_ok, $base_url, $base_error) = ecotrack_resolve_base_url($pdo, $settings);
            if (!$base_ok && trim((string) ($settings['ecotrack_base_url'] ?? '')) === '') {
                $base_error = 'Auto-discovery failed. Save the exact {{url}} value manually in ECOTRACK settings, then rerun diagnostics.';
            }
        }

        if (in_array('validate_token', $selected_checks, true)) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/validate/token', ['api_token' => $settings['ecotrack_api_token']], null, 'none');
            $message = trim((string) ($request['json']['message'] ?? $request['error'] ?? ''));
            $state = (!empty($request['success']) && strtoupper($message) === 'VALID_TOKEN') ? 'pass' : 'fail';
            admin_ecotrack_diag_add_result(
                $results,
                'Validate token',
                'GET',
                '/api/v1/validate/token',
                $state,
                $message !== '' ? $message : 'No message returned',
                ecotrack_response_to_text($request['response'] ?? '', $request['json'] ?? null),
                (int) ($request['status_code'] ?? 0)
            );
        }

        if (in_array('wilayas', $selected_checks, true)) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/wilayas', [], null, 'bearer');
            $payload = $request['json'];
            $count = is_array($payload) ? count($payload) : 0;
            $state = (!empty($request['success']) && $count > 0) ? 'pass' : 'fail';
            admin_ecotrack_diag_add_result(
                $results,
                'Get wilayas',
                'GET',
                '/api/v1/get/wilayas',
                $state,
                $count > 0 ? ('Returned ' . $count . ' wilaya rows') : trim((string) ($request['error'] ?? 'No data returned')),
                ecotrack_response_to_text($request['response'] ?? '', $payload),
                (int) ($request['status_code'] ?? 0)
            );
        }

        if (in_array('communes', $selected_checks, true)) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/communes', ['wilaya_id' => $sample_wilaya_id], null, 'bearer');
            $payload = $request['json'];
            $count = is_array($payload) ? count($payload) : 0;
            $state = (!empty($request['success']) && $count > 0) ? 'pass' : 'fail';
            admin_ecotrack_diag_add_result(
                $results,
                'Get communes',
                'GET',
                '/api/v1/get/communes?wilaya_id=' . $sample_wilaya_id,
                $state,
                $count > 0 ? ('Returned ' . $count . ' commune rows for wilaya ' . $sample_wilaya_id) : trim((string) ($request['error'] ?? 'No data returned')),
                ecotrack_response_to_text($request['response'] ?? '', $payload),
                (int) ($request['status_code'] ?? 0)
            );
        }

        if (in_array('fees', $selected_checks, true)) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/fees', [], null, 'bearer');
            $payload = $request['json'];
            $count = is_array($payload) ? count($payload) : 0;
            $state = (!empty($request['success']) && $count > 0) ? 'pass' : 'fail';
            admin_ecotrack_diag_add_result(
                $results,
                'Get fees',
                'GET',
                '/api/v1/get/fees',
                $state,
                $count > 0 ? 'Fees payload returned successfully' : trim((string) ($request['error'] ?? 'No data returned')),
                ecotrack_response_to_text($request['response'] ?? '', $payload),
                (int) ($request['status_code'] ?? 0)
            );
        }

        if (in_array('products', $selected_checks, true)) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/products/list', [], null, 'bearer');
            $payload = $request['json'];
            $count = !empty($payload['products']) && is_array($payload['products']) ? count($payload['products']) : 0;
            $state = !empty($request['success']) ? 'pass' : 'fail';
            admin_ecotrack_diag_add_result(
                $results,
                'Get products',
                'GET',
                '/api/v1/get/products/list',
                $state,
                !empty($request['success']) ? ('Products endpoint responded. Count: ' . $count) : trim((string) ($request['error'] ?? 'Request failed')),
                ecotrack_response_to_text($request['response'] ?? '', $payload),
                (int) ($request['status_code'] ?? 0)
            );
        }

        if (in_array('orders', $selected_checks, true)) {
            $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/orders', ['page' => 1], null, 'bearer');
            $payload = $request['json'];
            $count = !empty($payload['data']) && is_array($payload['data']) ? count($payload['data']) : 0;
            $state = !empty($request['success']) ? 'pass' : 'fail';
            admin_ecotrack_diag_add_result(
                $results,
                'Get recent orders',
                'GET',
                '/api/v1/get/orders?page=1',
                $state,
                !empty($request['success']) ? ('Orders endpoint responded. Rows in page: ' . $count) : trim((string) ($request['error'] ?? 'Request failed')),
                ecotrack_response_to_text($request['response'] ?? '', $payload),
                (int) ($request['status_code'] ?? 0)
            );
        }

        if (in_array('tracking_info', $selected_checks, true)) {
            if ($sample_tracking === '') {
                admin_ecotrack_diag_add_result($results, 'Get single tracking info', 'GET', '/api/v1/get/tracking/info', 'skip', 'Enter a sample tracking first.');
            } else {
                $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/tracking/info', ['tracking' => $sample_tracking], null, 'bearer');
                $payload = $request['json'];
                $state = !empty($request['success']) ? 'pass' : 'fail';
                $summary = !empty($request['success'])
                    ? ('Tracking info returned for ' . $sample_tracking)
                    : trim((string) (ecotrack_messages_to_text($payload['errors'] ?? []) ?: ($payload['message'] ?? $request['error'] ?? 'Request failed')));
                admin_ecotrack_diag_add_result(
                    $results,
                    'Get single tracking info',
                    'GET',
                    '/api/v1/get/tracking/info?tracking=' . $sample_tracking,
                    $state,
                    $summary,
                    ecotrack_response_to_text($request['response'] ?? '', $payload),
                    (int) ($request['status_code'] ?? 0)
                );
            }
        }

        if (in_array('trackings_info', $selected_checks, true)) {
            if ($sample_tracking === '') {
                admin_ecotrack_diag_add_result($results, 'Get multiple tracking history', 'GET', '/api/v1/get/trackings/info', 'skip', 'Enter a sample tracking first.');
            } else {
                $query = ['trackings[]' => $sample_tracking];
                $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/trackings/info', $query, null, 'bearer');
                $payload = $request['json'];
                $state = !empty($request['success']) ? 'pass' : 'fail';
                $summary = !empty($request['success'])
                    ? ('Tracking history returned for ' . $sample_tracking)
                    : trim((string) (ecotrack_messages_to_text($payload['errors'] ?? []) ?: ($payload['message'] ?? $request['error'] ?? 'Request failed')));
                admin_ecotrack_diag_add_result(
                    $results,
                    'Get multiple tracking history',
                    'GET',
                    '/api/v1/get/trackings/info',
                    $state,
                    $summary,
                    ecotrack_response_to_text($request['response'] ?? '', $payload),
                    (int) ($request['status_code'] ?? 0)
                );
            }
        }

        if (in_array('updates', $selected_checks, true)) {
            if ($sample_tracking === '') {
                admin_ecotrack_diag_add_result($results, 'Get tracking updates', 'GET', '/api/v1/get/maj', 'skip', 'Enter a sample tracking first.');
            } else {
                $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/maj', ['tracking' => $sample_tracking], null, 'bearer');
                $payload = $request['json'];
                $count = is_array($payload) ? count($payload) : 0;
                $state = !empty($request['success']) ? 'pass' : 'fail';
                $summary = !empty($request['success'])
                    ? ('Updates endpoint responded. Rows: ' . $count)
                    : trim((string) ($request['error'] ?? 'Request failed'));
                admin_ecotrack_diag_add_result(
                    $results,
                    'Get tracking updates',
                    'GET',
                    '/api/v1/get/maj?tracking=' . $sample_tracking,
                    $state,
                    $summary,
                    ecotrack_response_to_text($request['response'] ?? '', $payload),
                    (int) ($request['status_code'] ?? 0)
                );
            }
        }

        if (in_array('orders_status', $selected_checks, true)) {
            if ($sample_tracking === '') {
                admin_ecotrack_diag_add_result($results, 'Filter orders by status', 'GET', '/api/v1/get/orders/status', 'skip', 'Enter a sample tracking first.');
            } else {
                $query = [
                    'api_token' => $settings['ecotrack_api_token'],
                    'trackings' => $sample_tracking,
                    'status' => $sample_status
                ];
                $request = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/get/orders/status', $query, null, 'none');
                $payload = $request['json'];
                $count = !empty($payload['data']) && is_array($payload['data']) ? count($payload['data']) : 0;
                $state = !empty($request['success']) ? 'pass' : 'fail';
                $summary = !empty($request['success'])
                    ? ('Status filter responded. Matching rows: ' . $count)
                    : trim((string) (ecotrack_messages_to_text($payload['errors'] ?? []) ?: ($payload['message'] ?? $request['error'] ?? 'Request failed')));
                admin_ecotrack_diag_add_result(
                    $results,
                    'Filter orders by status',
                    'GET',
                    '/api/v1/get/orders/status',
                    $state,
                    $summary,
                    ecotrack_response_to_text($request['response'] ?? '', $payload),
                    (int) ($request['status_code'] ?? 0)
                );
            }
        }
    }
}
?>

<section class="content-header">
    <div class="content-header-left">
        <h1>ECOTRACK Diagnostics</h1>
    </div>
    <div class="content-header-right">
            <a href="settings.php#tab_ecotrack" class="btn btn-default btn-sm">Back To Settings</a>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">Current Configuration</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Integration:</strong>
                            <?php echo !empty($settings['ecotrack_enabled']) ? '<span class="label label-success">Enabled</span>' : '<span class="label label-default">Disabled</span>'; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Token:</strong>
                            <?php echo trim((string) ($settings['ecotrack_api_token'] ?? '')) !== '' ? '<span class="label label-success">Saved</span>' : '<span class="label label-danger">Missing</span>'; ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Cached base URL:</strong>
                            <span class="text-muted"><?php echo htmlspecialchars((string) ($settings['ecotrack_base_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" action="">
                <?php $csrf->echoInputField(); ?>
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Read-Only API Checks</h3>
                    </div>
                    <div class="box-body">
                        <div class="callout callout-info">
                            These checks do not create, update, ship, or delete real ECOTRACK orders. For write operations, open an actual order and use its ECOTRACK controls.
                        </div>

                        <div class="row">
                            <div class="col-md-8">
                                <div class="row">
                                    <?php foreach ($available_checks as $check_key => $check_label): ?>
                                        <div class="col-md-4">
                                            <div class="checkbox">
                                                <label>
                                                    <input type="checkbox" name="checks[]" value="<?php echo htmlspecialchars($check_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($check_key, $selected_checks, true) ? 'checked' : ''; ?>>
                                                    <?php echo htmlspecialchars($check_label, ENT_QUOTES, 'UTF-8'); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Sample Tracking</label>
                                    <input type="text" class="form-control" name="sample_tracking" value="<?php echo htmlspecialchars($sample_tracking, ENT_QUOTES, 'UTF-8'); ?>" placeholder="ECQFLD...">
                                    <small class="text-muted">Used only for tracking-related checks.</small>
                                </div>
                                <div class="form-group">
                                    <label>Sample Wilaya ID</label>
                                    <input type="number" class="form-control" name="sample_wilaya_id" min="1" max="58" value="<?php echo (int) $sample_wilaya_id; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Sample Status</label>
                                    <input type="text" class="form-control" name="sample_status" value="<?php echo htmlspecialchars($sample_status, ENT_QUOTES, 'UTF-8'); ?>" placeholder="en_livraison">
                                </div>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:0;">
                            <button type="submit" name="run_ecotrack_diagnostics" class="btn btn-primary">
                                <i class="fa fa-stethoscope"></i> Run Diagnostics
                            </button>
                            <a href="order.php" class="btn btn-default">Open Orders</a>
                        </div>
                    </div>
                </div>
            </form>

            <form method="post" action="">
                <?php $csrf->echoInputField(); ?>
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">Write Endpoint Checks</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">Writable ECOTRACK actions already exist on each order page: create, update, sync, ship, delete, add note, request return, label download, tracking info, and updates.</p>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Order ID</label>
                                    <input type="number" class="form-control" name="order_id_to_open" min="1" value="<?php echo $order_id_to_open > 0 ? (int) $order_id_to_open : ''; ?>" placeholder="123">
                                </div>
                            </div>
                            <div class="col-md-8" style="padding-top:25px;">
                                <button type="submit" name="open_order_id" class="btn btn-warning">
                                    <i class="fa fa-external-link"></i> Open Order ECOTRACK Controls
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <?php
            $speed_results = [];
            if (isset($_POST['run_speed_test'])) {
                $csrf->verifyRequest();
                $settings = ecotrack_normalize_settings(front_get_settings($pdo));
                if (!ecotrack_is_configured($settings)) {
                    $speed_results['error'] = 'ECOTRACK is not configured.';
                } else {
                    $times = [];
                    $successes = 0;
                    $failures = 0;
                    for ($i = 1; $i <= 3; $i++) {
                        $t0 = microtime(true);
                        $req = ecotrack_api_request($pdo, $settings, 'GET', '/api/v1/validate/token', ['api_token' => $settings['ecotrack_api_token']], null, 'none');
                        $elapsed = round((microtime(true) - $t0) * 1000);
                        $times[] = $elapsed;
                        if (!empty($req['success'])) {
                            $successes++;
                        } else {
                            $failures++;
                        }
                    }
                    $avg = round(array_sum($times) / count($times));
                    $min = min($times);
                    $max = max($times);
                    $speed_results = [
                        'times' => $times,
                        'avg' => $avg,
                        'min' => $min,
                        'max' => $max,
                        'successes' => $successes,
                        'failures' => $failures,
                    ];
                }
            }
            ?>

            <form method="post" action="">
                <?php $csrf->echoInputField(); ?>
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-tachometer"></i> اختبار سرعة ECOTRACK</h3>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">يقوم باستدعاء 3 طلبات إلى ECOTRACK ويقيس متوسط وسطى وأقصى وقت استجابة.</p>
                        <button type="submit" name="run_speed_test" class="btn btn-success">
                            <i class="fa fa-bolt"></i> تشغيل اختبار السرعة
                        </button>
                    </div>
                </div>
            </form>

            <?php if (!empty($speed_results) && !isset($speed_results['error'])): ?>
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">نتائج اختبار السرعة</h3>
                    </div>
                    <div class="box-body">
                        <div class="row" style="text-align:center;">
                            <div class="col-md-3">
                                <div class="description-block border-right">
                                    <h5 class="description-header text-muted">المتوسط</h5>
                                    <h3 class="description-text" style="color:<?php echo $speed_results['avg'] < 1000 ? '#00a65a' : ($speed_results['avg'] < 3000 ? '#f39c12' : '#dd4b39'); ?>;"><?php echo $speed_results['avg']; ?> ms</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="description-block border-right">
                                    <h5 class="description-header text-muted">الأسرع</h5>
                                    <h3 class="description-text text-green;"><?php echo $speed_results['min']; ?> ms</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="description-block border-right">
                                    <h5 class="description-header text-muted">الأبطأ</h5>
                                    <h3 class="description-text text-red;"><?php echo $speed_results['max']; ?> ms</h3>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="description-block">
                                    <h5 class="description-header text-muted">النجاح / الفشل</h5>
                                    <h3 class="description-text"><span class="text-green"><?php echo $speed_results['successes']; ?></span> / <span class="text-red"><?php echo $speed_results['failures']; ?></span></h3>
                                </div>
                            </div>
                        </div>
                        <br>
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>المحاولة</th><th>الوقت (مللي ثانية)</th><th>التقييم</th></tr></thead>
                            <tbody>
                                <?php foreach ($speed_results['times'] as $idx => $ms): ?>
                                    <tr>
                                        <td>المحاولة #<?php echo $idx + 1; ?></td>
                                        <td><strong><?php echo $ms; ?> ms</strong></td>
                                        <td>
                                            <?php if ($ms < 1000): ?>
                                                <span class="label label-success">ممتاز</span>
                                            <?php elseif ($ms < 3000): ?>
                                                <span class="label label-warning">مقبول</span>
                                            <?php else: ?>
                                                <span class="label label-danger">بطيء</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php elseif (!empty($speed_results['error'])): ?>
                <div class="callout callout-danger">
                    <p><?php echo htmlspecialchars($speed_results['error'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($results)): ?>
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Diagnostic Results</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width:140px;">Check</th>
                                    <th style="width:90px;">Method</th>
                                    <th style="width:180px;">Status</th>
                                    <th style="width:90px;">HTTP</th>
                                    <th>Endpoint</th>
                                    <th>Summary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <?php $status_meta = admin_ecotrack_diag_status_meta($result['state']); ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($result['method'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="<?php echo $status_meta['class']; ?>"><?php echo $status_meta['text']; ?></span></td>
                                        <td><?php echo $result['http_status'] > 0 ? (int) $result['http_status'] : '-'; ?></td>
                                        <td><code><?php echo htmlspecialchars($result['endpoint'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td><?php echo htmlspecialchars($result['summary'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <?php if ($result['preview'] !== ''): ?>
                                        <tr>
                                            <td colspan="6">
                                                <textarea class="form-control" rows="6" readonly><?php echo htmlspecialchars($result['preview'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
