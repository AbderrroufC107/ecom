<?php
require_once 'inc/config.php';
require_once 'header.php';

// Production Operations Center Dashboard
// Reading exclusively from tbl_omni_events

// Statistics
$stmt = $dbRepo->query("SELECT status, COUNT(*) as count FROM tbl_omni_events GROUP BY status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $dbRepo->query("SELECT event_type, COUNT(*) as count FROM tbl_omni_events GROUP BY event_type");
$eventCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt = $dbRepo->query("SELECT AVG(duration_ms) FROM tbl_omni_events WHERE duration_ms > 0 AND status = 'SUCCESS'");
$avgLatency = round((float)$stmt->fetchColumn(), 2);

// Recent Live Events
$stmt = $dbRepo->query("SELECT * FROM tbl_omni_events ORDER BY id DESC LIMIT 50");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<style>
/* Enterprise styling overrides */
.monitor-title { font-size: 2rem; font-weight: 800; color: #1e293b; font-family: 'InterLocal', 'CairoLocal', sans-serif; margin-bottom: 10px; }
.monitor-subtitle { color: #64748b; font-size: 1.1rem; margin-bottom: 30px; }
.premium-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px; }
.premium-card-wrapper { flex: 1 1 calc(25% - 20px); min-width: 240px; }
.stat-card { border-radius: 12px; padding: 25px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: none; height: 100%; display: flex; flex-direction: column; justify-content: center; }
.stat-card-title { text-transform: uppercase; margin-bottom: 15px; font-size: 0.95rem; opacity: 0.85; font-weight: 600; }
.stat-card-value { font-size: 2.5rem; font-weight: 700; margin: 0; line-height: 1; }
.bg-success-custom { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
.bg-danger-custom { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
.bg-warning-custom { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
.bg-info-custom { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; }
.table-monitor { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
.table-monitor thead th { background: #f8fafc; color: #475569; font-weight: 600; padding: 15px; border-bottom: 2px solid #e2e8f0; }
.table-monitor tbody td { padding: 15px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; color: #334155; }
.table-header { background: white; padding: 20px 25px; border-bottom: 1px solid #e2e8f0; }
.table-header h5 { margin: 0; font-weight: 700; color: #1e293b; font-size: 1.25rem; }
.badge-custom { padding: 6px 12px; border-radius: 50px; font-weight: 600; font-size: 0.85rem; display: inline-block; }
.badge-success { background: #dcfce7; color: #166534; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-secondary { background: #f1f5f9; color: #475569; }
</style>

<section class="content">
    <div style="direction: rtl; text-align: right; font-family: 'InterLocal', 'CairoLocal', sans-serif;">
        <div style="margin-bottom: 25px;">
            <h2 class="monitor-title"><i class="fa fa-heartbeat text-danger"></i> مركز مراقبة العمليات (Meta Readiness)</h2>
            <p class="monitor-subtitle">مراقبة حية لجميع أحداث OmniChannel، الأخطاء، مهام الذكاء الاصطناعي وزمن الاستجابة.</p>
        </div>

    <!-- Metrics row -->
    <div class="premium-grid">
        <div class="premium-card-wrapper">
            <div class="stat-card bg-success-custom">
                <div class="stat-card-title">العمليات الناجحة (Successful)</div>
                <div class="stat-card-value"><?= $statusCounts['SUCCESS'] ?? 0 ?></div>
            </div>
        </div>
        <div class="premium-card-wrapper">
            <div class="stat-card bg-danger-custom">
                <div class="stat-card-title">الأخطاء (Failed Events)</div>
                <div class="stat-card-value"><?= $statusCounts['FAILED'] ?? 0 ?></div>
            </div>
        </div>
        <div class="premium-card-wrapper">
            <div class="stat-card bg-warning-custom">
                <div class="stat-card-title">إعادات المحاولة (Retries)</div>
                <div class="stat-card-value"><?= $statusCounts['RETRY'] ?? 0 ?></div>
            </div>
        </div>
        <div class="premium-card-wrapper">
            <div class="stat-card bg-info-custom">
                <div class="stat-card-title">متوسط الاستجابة (Latency)</div>
                <div class="stat-card-value"><?= $avgLatency ?> <small style="font-size: 1rem; opacity: 0.8;">ms</small></div>
            </div>
        </div>
    </div>

    <!-- Live Event Timeline -->
    <div class="row">
        <div class="col-12">
            <div class="table-monitor">
                <div class="table-header">
                    <h5>Live Event Store (سجل الأحداث)</h5>
                </div>
                <div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="text-align: right; direction: rtl; margin: 0;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>الوقت (Timestamp)</th>
                                    <th>نوع الحدث (Event)</th>
                                    <th>القناة (Channel)</th>
                                    <th>الكيان (Entity)</th>
                                    <th>الحالة (Status)</th>
                                    <th>زمن الاستجابة</th>
                                    <th>البيانات (Metadata)</th>
                                    <th>الإجراء (Action)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($events as $e): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border">#<?= $e['id'] ?></span></td>
                                    <td class="text-nowrap text-muted" dir="ltr" style="text-align: right;"><?= $e['created_at'] ?></td>
                                    <td><span class="badge bg-secondary rounded-pill px-3"><?= htmlspecialchars($e['event_type']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($e['channel'] ?: 'system') ?></strong></td>
                                    <td>
                                        <span class="text-primary fw-bold"><?= $e['entity_type'] ? $e['entity_type'] . ' <span class="text-muted">#' . $e['entity_id'] . '</span>' : '-' ?></span>
                                    </td>
                                    <td>
                                        <?php if($e['status'] === 'SUCCESS'): ?>
                                            <span class="badge bg-success rounded-pill px-3"><i class="fa fa-check-circle me-1"></i> SUCCESS</span>
                                        <?php elseif($e['status'] === 'FAILED'): ?>
                                            <span class="badge bg-danger rounded-pill px-3"><i class="fa fa-times-circle me-1"></i> FAILED</span>
                                        <?php elseif($e['status'] === 'RETRY'): ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3"><i class="fa fa-refresh me-1"></i> RETRY</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border rounded-pill px-3"><?= $e['status'] ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= $e['duration_ms'] ?>ms</span></td>
                                    <td>
                                        <?php if($e['metadata']): ?>
                                            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='alert(<?= json_encode(htmlspecialchars(json_encode($e['metadata'])), JSON_HEX_APOS) ?>)'><i class="fa fa-code"></i> عرض JSON</button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($e['status'] === 'FAILED' && $e['event_type'] === 'Incoming Webhook'): ?>
                                            <form method="POST" action="replay_event.php" style="display:inline;">
                                                <input type="hidden" name="event_id" value="<?= $e['id'] ?>">
                                                <button class="btn btn-sm btn-primary rounded-pill px-3"><i class="fa fa-play me-1"></i> إعادة التنفيذ (Replay)</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</section>

<?php require_once 'footer.php'; ?>
