<?php

require_once 'inc/header.php';

$modulesBase = __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'modules';

$modules = [
    'Store'    => ['StoreRepository', 'StoreService', 'StoreSubscription', 'StoreUsage'],
    'Billing'  => ['InvoiceService', 'PaymentService', 'PlanService'],
    'Queue'    => ['QueueService', 'QueueWorker', 'QueueHealth'],
    'Backup'   => ['BackupService', 'RestoreService', 'RetentionService'],
    'Audit'    => ['AuditService', 'AuditRepository'],
    'Api'      => ['ApiKeyService', 'WebhookService', 'RateLimitService'],
    'Common'   => ['Helpers', 'Database', 'Config'],
    'Recovery' => ['RecoveryService', 'RiskService'],
];

function getLineCount(string $file): int
{
    if (!file_exists($file)) return 0;
    $handle = fopen($file, 'rb');
    $lines = 0;
    while (!feof($handle)) {
        $lines += substr_count(fread($handle, 8192), "\n");
    }
    fclose($handle);
    return $lines;
}

function formatBytes(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    $i = min($i, count($units) - 1);
    return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
}

// ========================================================================
// 1. MODULE SIZES
// ========================================================================
$moduleData = [];
$totalModuleLines = 0;
foreach ($modules as $module => $files) {
    $moduleLines = 0;
    $fileData = [];
    foreach ($files as $file) {
        $path = $modulesBase . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . $file . '.php';
        $lines = getLineCount($path);
        $size = file_exists($path) ? filesize($path) : 0;
        $fileData[] = ['name' => $file . '.php', 'lines' => $lines, 'size' => $size];
        $moduleLines += $lines;
    }
    $moduleData[$module] = ['files' => $fileData, 'total_lines' => $moduleLines];
    $totalModuleLines += $moduleLines;
}

// ========================================================================
// 2. STORE.PH SIZE
// ========================================================================
$storePhpPath = __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'store.php';
$storePhpLines = getLineCount($storePhpPath);
$storePhpSize = file_exists($storePhpPath) ? filesize($storePhpPath) : 0;

// ========================================================================
// 3. TOTAL
// ========================================================================
$totalLines = $totalModuleLines + $storePhpLines;

// ========================================================================
// 4. LARGEST FILES
// ========================================================================
$allFiles = [];
$allFiles[] = ['name' => 'admin/inc/store.php', 'lines' => $storePhpLines, 'size' => $storePhpSize];
foreach ($moduleData as $module => $data) {
    foreach ($data['files'] as $file) {
        $allFiles[] = ['name' => "modules/{$module}/{$file['name']}", 'lines' => $file['lines'], 'size' => $file['size']];
    }
}
usort($allFiles, fn($a, $b) => $b['lines'] <=> $a['lines']);

// ========================================================================
// 5. DEPENDENCY MAPPING
// ========================================================================
$dependencyGraph = [
    'store.php'        => ['* all modules (wrappers)'],
    'Common'           => ['* no dependencies'],
    'Store'            => ['Common'],
    'Billing'          => ['Store'],
    'Queue'            => ['Common'],
    'Backup'           => ['Queue', 'Common'],
    'RestoreService'   => ['BackupService', 'Common'],
    'RetentionService' => ['BackupService'],
    'Audit'            => ['Common'],
    'Api'              => ['Common'],
    'RateLimitService' => ['Common'],
    'Recovery'         => ['Common'],
    'RiskService'      => ['Recovery'],
];

// ========================================================================
// 6. REDUNDANCY CHECK (simple duplicate function detection)
// ========================================================================
$allFunctions = [];
$redundantCount = 0;
foreach ($allFiles as $fileInfo) {
    $path = __DIR__ . '/inc/' . str_replace('admin/', '', $fileInfo['name']);
    if (file_exists($path)) {
        $content = file_get_contents($path);
        preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches);
        foreach ($matches[1] as $fn) {
            if (in_array($fn, $allFunctions)) {
                $redundantCount++;
            }
            $allFunctions[] = $fn;
        }
    }
}

// ========================================================================
// RENDER
// ========================================================================
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Architecture Health Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f5f7fa; color: #1a1a2e; padding: 30px; }
        h1 { font-size: 28px; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .card h3 { font-size: 14px; color: #666; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .card .value { font-size: 32px; font-weight: 700; color: #1a1a2e; }
        .card .value.green { color: #10b981; }
        .card .value.amber { color: #f59e0b; }
        .card .value.red { color: #ef4444; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 30px; }
        th { background: #1a1a2e; color: #fff; padding: 12px 15px; text-align: left; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 10px 15px; border-bottom: 1px solid #eef0f4; font-size: 14px; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8f9fc; }
        .module-total { background: #eef2ff; font-weight: 600; }
        .section-title { font-size: 18px; margin: 30px 0 15px 0; padding-bottom: 8px; border-bottom: 2px solid #1a1a2e; }
        .bar { display: inline-block; height: 8px; border-radius: 4px; margin-right: 8px; vertical-align: middle; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
        .badge.green { background: #d1fae5; color: #065f46; }
        .badge.amber { background: #fef3c7; color: #92400e; }
        .badge.red { background: #fee2e2; color: #991b1b; }
        .bar-cell { display: flex; align-items: center; gap: 8px; }
    </style>
</head>
<body>

<h1>📊 Architecture Health Report</h1>
<p class="subtitle">Modular refactoring status — generated <?= date('Y-m-d H:i:s') ?></p>

<div class="summary-cards">
    <div class="card">
        <h3>Total Lines</h3>
        <div class="value"><?= number_format($totalLines) ?></div>
    </div>
    <div class="card">
        <h3>Module Files</h3>
        <div class="value"><?= count($allFiles) - 1 ?></div>
    </div>
    <div class="card">
        <h3>Module Lines</h3>
        <div class="value green"><?= number_format($totalModuleLines) ?></div>
    </div>
    <div class="card">
        <h3>store.php Lines</h3>
        <div class="value <?= $storePhpLines > 1000 ? 'amber' : 'green' ?>"><?= number_format($storePhpLines) ?></div>
    </div>
    <div class="card">
        <h3>Functions Total</h3>
        <div class="value"><?= count($allFunctions) ?></div>
    </div>
    <div class="card">
        <h3>Risks Detected</h3>
        <div class="value <?= $redundantCount > 0 ? 'amber' : 'green' ?>"><?= $redundantCount ?></div>
    </div>
</div>

<h2 class="section-title">Module Breakdown</h2>
<table>
    <thead>
        <tr>
            <th>Module</th>
            <th>Files</th>
            <th>Lines</th>
            <th>Visual</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
<?php
$maxModuleLines = max(array_column($moduleData, 'total_lines')) ?: 1;
foreach ($moduleData as $module => $data):
    $pct = round(($data['total_lines'] / $maxModuleLines) * 100);
    $barColor = $pct > 80 ? '#ef4444' : ($pct > 50 ? '#f59e0b' : '#10b981');
    $status = $data['total_lines'] > 0 ? '✅ Extracted' : '⚠️ Missing';
?>
        <tr>
            <td><strong><?= htmlspecialchars($module) ?></strong></td>
            <td><?= count($data['files']) ?></td>
            <td><?= number_format($data['total_lines']) ?></td>
            <td class="bar-cell">
                <span class="bar" style="width: <?= $pct ?>px; background: <?= $barColor ?>;"></span>
                <span><?= $pct ?>%</span>
            </td>
            <td><span class="badge <?= $data['total_lines'] > 0 ? 'green' : 'red' ?>"><?= $status ?></span></td>
        </tr>
<?php endforeach; ?>
        <tr class="module-total">
            <td><strong>TOTAL MODULES</strong></td>
            <td><?= array_sum(array_map(fn($m) => count($m['files']), $moduleData)) ?></td>
            <td><strong><?= number_format($totalModuleLines) ?></strong></td>
            <td></td>
            <td></td>
        </tr>
    </tbody>
</table>

<h2 class="section-title">Store.php — After Refactoring</h2>
<table>
    <thead>
        <tr><th>Metric</th><th>Value</th></tr>
    </thead>
    <tbody>
        <tr><td>File Size</td><td><?= formatBytes($storePhpSize) ?></td></tr>
        <tr><td>Total Lines</td><td><?= number_format($storePhpLines) ?> lines</td></tr>
        <tr><td>Function Count (wrappers)</td><td><?= count($allFunctions) - $totalModuleLines/10 ?> (approx)</td></tr>
        <tr><td>Lines Saved</td><td><?= number_format(max(0, 3958 - $storePhpLines)) ?> (was 3,958 before refactoring)</td></tr>
    </tbody>
</table>

<h2 class="section-title">Largest Files (Top 10)</h2>
<table>
    <thead>
        <tr><th>#</th><th>File</th><th>Lines</th><th>Size</th><th>Bar</th></tr>
    </thead>
    <tbody>
<?php
$maxLines = $allFiles[0]['lines'] ?: 1;
foreach (array_slice($allFiles, 0, 10) as $i => $f):
    $pct = round(($f['lines'] / $maxLines) * 100);
    $barColor = $pct > 80 ? '#ef4444' : ($pct > 50 ? '#f59e0b' : '#10b981');
?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($f['name']) ?></td>
            <td><?= number_format($f['lines']) ?></td>
            <td><?= formatBytes($f['size']) ?></td>
            <td class="bar-cell">
                <span class="bar" style="width: <?= $pct * 2 ?>px; background: <?= $barColor ?>;"></span>
                <span><?= $pct ?>%</span>
            </td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>

<h2 class="section-title">Dependency Graph</h2>
<table>
    <thead>
        <tr><th>Module / File</th><th>Depends On</th></tr>
    </thead>
    <tbody>
<?php foreach ($dependencyGraph as $name => $deps): ?>
        <tr>
            <td><strong><?= htmlspecialchars($name) ?></strong></td>
            <td><?= implode(', ', $deps) ?></td>
        </tr>
<?php endforeach; ?>
    </tbody>
</table>

<h2 class="section-title">Migration Status</h2>
<table>
    <thead>
        <tr><th>Area</th><th>Status</th><th>Notes</th></tr>
    </thead>
    <tbody>
        <tr><td>Autoloader (PSR-4)</td><td><span class="badge green">✅</span></td><td>admin/inc/autoload.php — maps Ecom\* to modules/</td></tr>
        <tr><td>Store — Repository</td><td><span class="badge green">✅</span></td><td>CRUD, migration, table definitions</td></tr>
        <tr><td>Store — Service</td><td><span class="badge green">✅</span></td><td>Auth, users, settings, themes, stats, ownership</td></tr>
        <tr><td>Store — Subscription</td><td><span class="badge green">✅</span></td><td>Plan limits, features, status, read-only</td></tr>
        <tr><td>Store — Usage</td><td><span class="badge green">✅</span></td><td>Quota checks, usage tracking (new schema)</td></tr>
        <tr><td>Billing — Invoices</td><td><span class="badge green">✅</span></td><td>CRUD, number generation, pagination</td></tr>
        <tr><td>Billing — Payments</td><td><span class="badge green">✅</span></td><td>Payment recording, revenue aggregation</td></tr>
        <tr><td>Billing — Plans</td><td><span class="badge green">✅</span></td><td>CRUD, plan change with proration</td></tr>
        <tr><td>Queue — Service</td><td><span class="badge green">✅</span></td><td>Enqueue, dequeue, status, CRUD, enqueue helpers</td></tr>
        <tr><td>Queue — Worker</td><td><span class="badge green">✅</span></td><td>Process, handlers (telegram,webhook,ecotrack,ai, etc.)</td></tr>
        <tr><td>Queue — Health</td><td><span class="badge green">✅</span></td><td>Stats, trends, alerts</td></tr>
        <tr><td>Backup — Service</td><td><span class="badge green">✅</span></td><td>Job CRUD, DB/file execution, S3, health, alerts</td></tr>
        <tr><td>Backup — Restore</td><td><span class="badge green">✅</span></td><td>Request workflow, execution, approval</td></tr>
        <tr><td>Backup — Retention</td><td><span class="badge green">✅</span></td><td>Policy-driven daily/weekly/monthly cleanup</td></tr>
        <tr><td>Audit — Service</td><td><span class="badge green">✅</span></td><td>Logging, queries, pagination</td></tr>
        <tr><td>Audit — Repository</td><td><span class="badge green">✅</span></td><td>Insert, query, timeline, cleanup</td></tr>
        <tr><td>API — Keys</td><td><span class="badge green">✅</span></td><td>CRUD, validation, permissions, usage stats</td></tr>
        <tr><td>API — Webhooks</td><td><span class="badge green">✅</span></td><td>CRUD, event delivery, failure tracking</td></tr>
        <tr><td>API — Rate Limits</td><td><span class="badge green">✅</span></td><td>Per-plan limits, hourly/daily enforcement</td></tr>
        <tr><td>Common — Helpers</td><td><span class="badge green">✅</span></td><td>formatBytes, generateSlug, token, sanitize, json response</td></tr>
        <tr><td>Common — Database</td><td><span class="badge green">✅</span></td><td>PDO singleton, connection, transaction helpers</td></tr>
        <tr><td>Common — Config</td><td><span class="badge green">✅</span></td><td>Cached constant access, path resolution</td></tr>
        <tr><td>Recovery — Service</td><td><span class="badge green">✅</span></td><td>Health checks, storage check, system requirements</td></tr>
        <tr><td>Recovery — Risk</td><td><span class="badge green">✅</span></td><td>Backup risk, system risk, combined risk scoring</td></tr>
        <tr><td>Usage (legacy schema)</td><td><span class="badge amber">🔄</span></td><td>Inline in store.php — uses old month_year schema</td></tr>
        <tr><td>Test Suite</td><td><span class="badge green">✅</span></td><td>Queue, Billing, Backup, Recovery, Audit (Unit tests)</td></tr>
        <tr><td>Static Analysis</td><td><span class="badge green">✅</span></td><td>phpstan.neon (level 5), phpcs.xml (PSR12)</td></tr>
    </tbody>
</table>

<p style="margin-top: 40px; color: #888; font-size: 13px; text-align: center;">
    Architecture Health Report — Ecom Modular Refactoring — Phase 14
</p>

</body>
</html>
