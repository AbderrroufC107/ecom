<?php
require_once('header.php');
require_once('inc/config.php');

// Fetch Metrics Summary
$stmt = $dbRepo->query("
    SELECT 
        COUNT(*) as total_requests,
        SUM(prompt_tokens) as total_prompt_tokens,
        SUM(completion_tokens) as total_completion_tokens,
        SUM(total_cost) as total_cost,
        AVG(total_cost) as avg_cost_per_request
    FROM tbl_ai_metrics
");
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Provider Metrics
$stmt = $dbRepo->query("
    SELECT 
        p.name as provider_name,
        COUNT(m.id) as req_count,
        SUM(m.prompt_tokens + m.completion_tokens) as total_tokens,
        SUM(m.total_cost) as provider_cost
    FROM tbl_ai_metrics m
    LEFT JOIN tbl_ai_providers p ON m.provider_id = p.id
    GROUP BY m.provider_id
");
$providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Task Types
$stmt = $dbRepo->query("
    SELECT 
        t.task_type,
        COUNT(t.id) as task_count,
        SUM(m.total_cost) as task_cost
    FROM tbl_ai_tasks t
    LEFT JOIN tbl_ai_metrics m ON t.id = m.task_id
    GROUP BY t.task_type
    ORDER BY task_cost DESC
");
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<section class="content-header">
    <div class="content-header-left">
        <h1>تحليلات الذكاء الاصطناعي والتكاليف (AI Cost Dashboard)</h1>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?= number_format($summary['total_requests']) ?></h3>
                    <p>إجمالي الطلبات (Requests)</p>
                </div>
                <div class="icon"><i class="fa fa-cogs"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3><?= number_format($summary['total_prompt_tokens'] + $summary['total_completion_tokens']) ?></h3>
                    <p>إجمالي التوكنز (Tokens)</p>
                </div>
                <div class="icon"><i class="fa fa-database"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3>$<?= number_format($summary['total_cost'], 4) ?></h3>
                    <p>التكلفة الإجمالية (Total Cost)</p>
                </div>
                <div class="icon"><i class="fa fa-dollar"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3>$<?= number_format($summary['avg_cost_per_request'], 5) ?></h3>
                    <p>متوسط التكلفة للطلب الواحد</p>
                </div>
                <div class="icon"><i class="fa fa-line-chart"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Providers -->
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">استهلاك المزودين (Providers Usage)</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>المزود</th>
                                <th>عدد الطلبات</th>
                                <th>إجمالي التوكنز</th>
                                <th>التكلفة ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($providers as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['provider_name'] ?? 'Unknown') ?></td>
                                <td><?= number_format($p['req_count']) ?></td>
                                <td><?= number_format($p['total_tokens']) ?></td>
                                <td>$<?= number_format($p['provider_cost'], 4) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Task Types -->
        <div class="col-md-6">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">استهلاك المهام (Most Consuming Tasks)</h3>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>نوع المهمة (Task Type)</th>
                                <th>عدد المهام</th>
                                <th>التكلفة ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['task_type']) ?></td>
                                <td><?= number_format($t['task_count']) ?></td>
                                <td>$<?= number_format($t['task_cost'], 4) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once('footer.php'); ?>
