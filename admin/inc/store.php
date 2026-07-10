<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoload.php';

use Ecom\Store\StoreRepository;
use Ecom\Store\StoreService;
use Ecom\Store\StoreSubscription;
use Ecom\Billing\InvoiceService;
use Ecom\Billing\PaymentService;
use Ecom\Billing\PlanService;
use Ecom\Queue\QueueService;
use Ecom\Queue\QueueWorker;
use Ecom\Queue\QueueHealth;
use Ecom\Backup\BackupService;
use Ecom\Backup\RestoreService;
use Ecom\Backup\RetentionService;
use Ecom\Audit\AuditService;
use Ecom\Api\ApiKeyService;
use Ecom\Api\WebhookService;
use Ecom\Api\RateLimitService;
use Ecom\Common\Helpers;
use Ecom\Recovery\RecoveryService;
use Ecom\Recovery\RiskService;
use Ecom\Cache\CacheService;
use Ecom\Search\SearchService;

/* ========================================================================
   STORE REPOSITORY — CRUD wrappers
   ======================================================================== */

if (!function_exists('store_ensure_tables')) {
    function store_ensure_tables(PDO $pdo): void
    { global $dbRepo;
        static $done = false;
        if ($done) return;

        $lock_file = __DIR__ . '/../cache/store_tables.lock';
        if (file_exists($lock_file)) {
            $done = true;
            return;
        }

        $dbRepo->executeCommand("
            CREATE TABLE IF NOT EXISTS tbl_store (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_name VARCHAR(190) NOT NULL DEFAULT '',
                store_slug VARCHAR(120) NOT NULL DEFAULT '',
                store_domain VARCHAR(190) NOT NULL DEFAULT '',
                owner_name VARCHAR(190) NOT NULL DEFAULT '',
                owner_email VARCHAR(190) NOT NULL DEFAULT '',
                logo VARCHAR(500) NOT NULL DEFAULT '',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                plan_type VARCHAR(30) NOT NULL DEFAULT 'starter',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        StoreRepository::ensureTables($pdo);
        @file_put_contents($lock_file, '1');
        $done = true;
    }
}

if (!function_exists('store_migrate_all_tables')) {
    function store_migrate_all_tables(PDO $pdo): void { global $dbRepo; StoreRepository::migrateTables($pdo); }
}

if (!function_exists('store_get')) {
    function store_get(PDO $pdo, int $id): ?array { global $dbRepo; return StoreRepository::get($pdo, $id); }
}

if (!function_exists('store_get_by_slug')) {
    function store_get_by_slug(PDO $pdo, string $slug): ?array { global $dbRepo; return StoreRepository::getBySlug($pdo, $slug); }
}

if (!function_exists('store_get_by_domain')) {
    function store_get_by_domain(PDO $pdo, string $domain): ?array { global $dbRepo; return StoreRepository::getByDomain($pdo, $domain); }
}

if (!function_exists('store_get_all')) {
    function store_get_all(PDO $pdo, ?array $filters = null, int $page = 1, int $perPage = 50): array
    { global $dbRepo;
        return StoreRepository::getAll($pdo, $filters, $page, $perPage);
    }
}

if (!function_exists('store_create')) {
    function store_create(PDO $pdo, array $data): int { global $dbRepo; return StoreRepository::create($pdo, $data); }
}

if (!function_exists('store_update')) {
    function store_update(PDO $pdo, int $id, array $data): void { global $dbRepo; StoreRepository::update($pdo, $id, $data); }
}

if (!function_exists('store_delete')) {
    function store_delete(PDO $pdo, int $id): void { global $dbRepo; StoreRepository::delete($pdo, $id); }
}

/* ========================================================================
   STORE SERVICE — business logic wrappers
   ======================================================================== */

if (!function_exists('store_set_current_id')) {
    function store_set_current_id(?int $id): void { global $dbRepo; StoreService::setCurrentId($id); }
}

if (!function_exists('store_current_id')) {
    function store_current_id(): ?int { global $dbRepo; return StoreService::getCurrentId(); }
}

if (!function_exists('store_resolve')) {
    function store_resolve(PDO $pdo): int { global $dbRepo; return StoreService::resolve($pdo); }
}

if (!function_exists('store_authenticate')) {
    function store_authenticate(PDO $pdo, string $email, string $password): ?array
    { global $dbRepo;
        return StoreService::authenticate($pdo, $email, $password);
    }
}

if (!function_exists('store_create_user')) {
    function store_create_user(PDO $pdo, int $storeId, string $name, string $email,
        string $password, string $role = 'staff'): int
    { global $dbRepo;
        return StoreService::createUser($pdo, $storeId, $name, $email, $password, $role);
    }
}

if (!function_exists('store_get_store_users')) {
    function store_get_store_users(PDO $pdo, int $storeId): array { global $dbRepo; return StoreService::getUsers($pdo, $storeId); }
}

if (!function_exists('store_get_setting')) {
    function store_get_setting(PDO $pdo, int $storeId, string $key, mixed $default = null): mixed
    { global $dbRepo;
        return StoreService::getSetting($pdo, $storeId, $key, $default);
    }
}

if (!function_exists('store_set_setting')) {
    function store_set_setting(PDO $pdo, int $storeId, string $key, mixed $value): void
    { global $dbRepo;
        StoreService::setSetting($pdo, $storeId, $key, $value);
    }
}

if (!function_exists('store_get_all_settings')) {
    function store_get_all_settings(PDO $pdo, int $storeId): array
    { global $dbRepo;
        return StoreService::getAllSettings($pdo, $storeId);
    }
}

if (!function_exists('store_get_stats')) {
    function store_get_stats(PDO $pdo, int $storeId): array { global $dbRepo; return StoreService::getStats($pdo, $storeId); }
}

if (!function_exists('store_get_global_stats')) {
    function store_get_global_stats(PDO $pdo): array { global $dbRepo; return StoreService::getGlobalStats($pdo); }
}

if (!function_exists('store_get_theme')) {
    function store_get_theme(PDO $pdo, int $storeId): ?array { global $dbRepo; return StoreService::getTheme($pdo, $storeId); }
}

if (!function_exists('store_save_theme')) {
    function store_save_theme(PDO $pdo, int $storeId, array $themeData): void
    { global $dbRepo;
        StoreService::saveTheme($pdo, $storeId, $themeData);
    }
}

if (!function_exists('store_validate_ownership')) {
    function store_validate_ownership(PDO $pdo, int $storeId): bool
    { global $dbRepo;
        return StoreService::validateOwnership($pdo, $storeId);
    }
}

if (!function_exists('store_generate_slug')) {
    function store_generate_slug(string $name): string { global $dbRepo; return Helpers::generateSlug($name); }
}

if (!function_exists('store_build_where')) {
    function store_build_where(PDO $pdo, array $filters, string $alias = 's'): array
    { global $dbRepo;
        return StoreService::buildWhere($pdo, $filters, $alias);
    }
}

if (!function_exists('store_apply_where')) {
    function store_apply_where(PDO $pdo, string $baseSql, array $filters, string $alias = 's'): array
    { global $dbRepo;
        return StoreService::applyWhere($pdo, $baseSql, $filters, $alias);
    }
}

/* ========================================================================
   SUBSCRIPTION wrappers
   ======================================================================== */

if (!function_exists('store_get_subscription')) {
    function store_get_subscription(PDO $pdo, int $storeId): ?array { global $dbRepo; return StoreSubscription::get($pdo, $storeId); }
}

if (!function_exists('store_get_subscription_status')) {
    function store_get_subscription_status(PDO $pdo, int $store_id): array
    { global $dbRepo;
        $status = StoreSubscription::getStatus($pdo, $store_id);
        $sub = StoreSubscription::get($pdo, $store_id);

        $now = new DateTime();
        if ($status === 'active' && $sub && !empty($sub['expires_at'])) {
            $expires = new DateTime($sub['expires_at']);
            $expires_end = clone $expires;
            $expires_end->modify('+7 days');

            if ($now > $expires_end) {
                return ['valid' => false, 'status' => 'expired', 'read_only' => true, 'message' => 'انتهت صلاحية الاشتراك'];
            }
            if ($now > $expires) {
                $grace_days = (int) $now->diff($expires_end)->days;
                return ['valid' => true, 'status' => 'grace_period', 'read_only' => false, 'grace_days_left' => $grace_days, 'expired_at' => $expires->format('Y-m-d'), "message" => "فترة سماح: {$grace_days} يوم متبقي قبل الإيقاف"];
            }
            $days_left = (int) $now->diff($expires)->days;
            return ['valid' => true, 'status' => 'active', 'read_only' => false, 'days_left' => $days_left, 'expires_at' => $expires->format('Y-m-d'), 'message' => "الاشتراك نشط — {$days_left} يوم متبقي"];
        }

        if ($status === 'active') {
            return ['valid' => true, 'status' => 'active', 'read_only' => false, 'message' => 'الاشتراك نشط'];
        }
        if ($status === 'expired' || $status === 'cancelled') {
            return ['valid' => false, 'status' => $status, 'read_only' => true, 'message' => 'الاشتراك ' . ($status === 'expired' ? 'منتهي' : 'ملغي')];
        }
        return ['valid' => false, 'status' => $status, 'read_only' => true, 'message' => 'حالة اشتراك غير معروفة'];
    }
}

if (!function_exists('store_is_read_only')) {
    function store_is_read_only(PDO $pdo, int $store_id): bool
    { global $dbRepo;
        $status = store_get_subscription_status($pdo, $store_id);
        return $status['read_only'] ?? true;
    }
}

if (!function_exists('store_require_write_access')) {
    function store_require_write_access(PDO $pdo, int $store_id): void
    { global $dbRepo;
        if (store_is_read_only($pdo, $store_id)) {
            $status = store_get_subscription_status($pdo, $store_id);
            $msg = $status['message'] ?? 'المتجر في وضع القراءة فقط بسبب حالة الاشتراك';
            $_SESSION['billing_error'] = $msg;
            if (!headers_sent()) { header('location: billing.php'); exit; }
            exit($msg);
        }
    }
}

if (!function_exists('store_get_plan_limits')) {
    function store_get_plan_limits(PDO $pdo, int $storeId): array
    { global $dbRepo;
        return StoreSubscription::getPlanLimits($pdo, $storeId);
    }
}

if (!function_exists('store_check_feature')) {
    function store_check_feature(PDO $pdo, int $storeId, string $feature): bool
    { global $dbRepo;
        return StoreSubscription::checkFeature($pdo, $storeId, $feature);
    }
}

if (!function_exists('store_check_employee_limit')) {
    function store_check_employee_limit(PDO $pdo, int $storeId, int $currentCount): bool
    { global $dbRepo;
        return StoreSubscription::checkEmployeeLimit($pdo, $storeId, $currentCount);
    }
}

if (!function_exists('store_update_subscription')) {
    function store_update_subscription(PDO $pdo, int $storeId, array $data): void
    { global $dbRepo;
        StoreSubscription::update($pdo, $storeId, $data);
    }
}

/* ========================================================================
   USAGE TRACKING — inline (legacy schema)
   ======================================================================== */

if (!function_exists('store_get_monthly_usage')) {
    function store_get_monthly_usage(PDO $pdo, int $store_id, ?string $month = null): array
    { global $dbRepo;
        if ($month === null) $month = date('Y-m');
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_store_usage WHERE store_id = ? AND month_year = ? LIMIT 1");
        $stmt->execute([$store_id, $month]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usage) $usage = ['store_id' => $store_id, 'month_year' => $month, 'orders_count' => 0, 'employees_count' => 0, 'api_calls' => 0, 'telegram_messages' => 0, 'recovery_tasks' => 0, 'ai_reports' => 0];
        return $usage;
    }
}

if (!function_exists('store_ensure_usage_row')) {
    function store_ensure_usage_row(PDO $pdo, int $store_id, string $month): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("INSERT IGNORE INTO tbl_store_usage (store_id, month_year) VALUES (?, ?)");
        $stmt->execute([$store_id, $month]);
    }
}

if (!function_exists('store_track_usage')) {
    function store_track_usage(PDO $pdo, int $store_id, string $metric, int $increment = 1): void
    { global $dbRepo;
        $month = date('Y-m');
        $allowed_metrics = ['orders_count' => 'orders_count', 'order' => 'orders_count', 'employees_count' => 'employees_count', 'employee' => 'employees_count', 'api_calls' => 'api_calls', 'api_call' => 'api_calls', 'telegram_messages' => 'telegram_messages', 'telegram_message' => 'telegram_messages', 'recovery_tasks' => 'recovery_tasks', 'recovery_task' => 'recovery_tasks', 'ai_reports' => 'ai_reports', 'ai_report' => 'ai_reports'];
        $column = $allowed_metrics[$metric] ?? null;
        if ($column === null) return;
        store_ensure_usage_row($pdo, $store_id, $month);
        $stmt = $dbRepo->prepare("UPDATE tbl_store_usage SET `$column` = `$column` + ? WHERE store_id = ? AND month_year = ?");
        $stmt->execute([$increment, $store_id, $month]);
    }
}

if (!function_exists('store_check_quota')) {
    function store_check_quota(PDO $pdo, int $store_id, string $metric): array
    { global $dbRepo;
        $store = store_get($pdo, $store_id);
        if (!$store) return ['allowed' => false, 'current' => 0, 'max' => 0, 'message' => 'المتجر غير موجود'];
        $plan = store_get_plan_limits($pdo, $store['plan_type'] ?? 'starter');
        $usage = store_get_monthly_usage($pdo, $store_id);
        $metric_map = ['orders_count' => ['current' => 'orders_count', 'max' => 'max_orders_monthly', 'label' => 'الطلبات'], 'order' => ['current' => 'orders_count', 'max' => 'max_orders_monthly', 'label' => 'الطلبات'], 'employees_count' => ['current' => 'employees_count', 'max' => 'max_employees', 'label' => 'الموظفين'], 'employee' => ['current' => 'employees_count', 'max' => 'max_employees', 'label' => 'الموظفين'], 'telegram_messages' => ['current' => 'telegram_messages', 'max' => 'max_telegram_bots', 'label' => 'رسائل التلغرام'], 'telegram_message' => ['current' => 'telegram_messages', 'max' => 'max_telegram_bots', 'label' => 'رسائل التلغرام']];
        $map = $metric_map[$metric] ?? null;
        if (!$map) return ['allowed' => true, 'current' => 0, 'max' => 999999, 'message' => ''];
        $current = (int) ($usage[$map['current']] ?? 0);
        $max = (int) ($plan[$map['max']] ?? 999999);
        if ($max >= 999999) return ['allowed' => true, 'current' => $current, 'max' => $max, 'message' => ''];
        $allowed = $current < $max;
        return ['allowed' => $allowed, 'current' => $current, 'max' => $max, 'percent' => $max > 0 ? round(($current / $max) * 100) : 0, 'message' => $allowed ? '' : "لقد تجاوزت الحد المسموح من {$map['label']} لهذا الشهر ({$current}/{$max})"];
    }
}

if (!function_exists('store_check_order_quota')) {
    function store_check_order_quota(PDO $pdo, int $store_id): array { global $dbRepo; return store_check_quota($pdo, $store_id, 'order'); }
}

if (!function_exists('store_enforce_order_quota')) {
    function store_enforce_order_quota(PDO $pdo, int $store_id): void
    { global $dbRepo;
        $quota = store_check_order_quota($pdo, $store_id);
        if (!$quota['allowed']) {
            $_SESSION['billing_error'] = $quota['message'];
            if (!headers_sent()) { header('location: billing.php'); exit; }
            exit($quota['message']);
        }
    }
}

if (!function_exists('store_check_usage_alert')) {
    function store_check_usage_alert(PDO $pdo, int $store_id, string $metric, ?array $quota_result = null): ?string
    { global $dbRepo;
        if ($quota_result === null) $quota_result = store_check_quota($pdo, $store_id, $metric);
        $percent = $quota_result['percent'] ?? 0;
        if ($percent <= 0 || $quota_result['max'] >= 999999) return null;
        $alert_key = "usage_alert_{$metric}_" . date('Y-m');
        $last_alert = store_get_setting($pdo, $store_id, $alert_key, '');
        $thresholds = [80, 90, 100];
        $alert_at = null;
        foreach ($thresholds as $t) { if ($percent >= $t) $alert_at = $t; }
        if ($alert_at === null || $last_alert === (string) $alert_at) return null;
        store_set_setting($pdo, $store_id, $alert_key, (string) $alert_at);
        $plan = store_get_plan_limits($pdo, store_get($pdo, $store_id)['plan_type'] ?? 'starter');
        $metric_labels = ['order' => 'الطلبات', 'employee' => 'الموظفين', 'telegram_message' => 'رسائل التلغرام'];
        $label = $metric_labels[$metric] ?? $metric;
        $message = "⚠️ تنبيه استخدام المتجر (ID:{$store_id})\nالحد: {$label}\nالاستخدام: {$quota_result['current']}/{$quota_result['max']} ({$percent}%)\nالخطة: {$plan['label_ar']}\nالتنبيه عند: {$alert_at}%";
        if (defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== 'BOT_TOKEN' && defined('EVENT_BOT_CHAT_ID') && EVENT_BOT_CHAT_ID !== '') {
            try { $telegram_message = urlencode($message); $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage?chat_id=" . EVENT_BOT_CHAT_ID . "&text={$telegram_message}&parse_mode=Markdown"; file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 5]])); } catch (Exception $e) {}
        }
        return $message;
    }
}

if (!function_exists('store_get_usage_summary')) {
    function store_get_usage_summary(PDO $pdo, int $store_id): array
    { global $dbRepo;
        $month = date('Y-m');
        $usage = store_get_monthly_usage($pdo, $store_id, $month);
        $plan = store_get_plan_limits($pdo, store_get($pdo, $store_id)['plan_type'] ?? 'starter');
        $summary = [];
        $fields = ['orders_count' => ['label' => 'الطلبات', 'max' => 'max_orders_monthly'], 'employees_count' => ['label' => 'الموظفين', 'max' => 'max_employees'], 'api_calls' => ['label' => 'استدعاءات API', 'max' => 'max_api_calls'], 'telegram_messages' => ['label' => 'رسائل تلغرام', 'max' => 'max_telegram_bots']];
        foreach ($fields as $field => $cfg) {
            $used = (int) ($usage[$field] ?? 0);
            $max = (int) ($plan[$cfg['max']] ?? 0);
            $summary[] = ['field' => $field, 'label' => $cfg['label'], 'used' => $used, 'max' => $max, 'percent' => $max > 0 ? round(($used / $max) * 100) : 0];
        }
        return $summary;
    }
}

/* ========================================================================
   BILLING wrappers
   ======================================================================== */

if (!function_exists('store_create_invoice')) {
    function store_create_invoice(PDO $pdo, int $storeId, float $amount, float $tax = 0.00,
        ?string $dueDate = null, ?int $subscriptionId = null): int
    { global $dbRepo;
        return InvoiceService::create($pdo, $storeId, $amount, $tax, $dueDate, $subscriptionId);
    }
}

if (!function_exists('store_get_invoices')) {
    function store_get_invoices(PDO $pdo, int $storeId, int $page = 1, int $perPage = 20,
        ?string $statusFilter = null): array
    { global $dbRepo;
        return InvoiceService::getInvoices($pdo, $storeId, $page, $perPage, $statusFilter);
    }
}

if (!function_exists('store_get_invoice_count')) {
    function store_get_invoice_count(PDO $pdo, int $storeId, ?string $statusFilter = null): int
    { global $dbRepo;
        return InvoiceService::getCount($pdo, $storeId, $statusFilter);
    }
}

if (!function_exists('store_get_all_invoices')) {
    function store_get_all_invoices(PDO $pdo, int $page = 1, int $perPage = 50,
        ?string $statusFilter = null): array
    { global $dbRepo;
        return InvoiceService::getAll($pdo, $page, $perPage, $statusFilter);
    }
}

if (!function_exists('store_get_all_invoice_count')) {
    function store_get_all_invoice_count(PDO $pdo, ?string $statusFilter = null): int
    { global $dbRepo;
        return InvoiceService::getAllCount($pdo, $statusFilter);
    }
}

if (!function_exists('store_get_store_invoice_count')) {
    function store_get_store_invoice_count(PDO $pdo, int $storeId): int
    { global $dbRepo;
        return InvoiceService::getCount($pdo, $storeId);
    }
}

if (!function_exists('store_record_payment')) {
    function store_record_payment(PDO $pdo, int $invoiceId, int $storeId, float $amount,
        string $method = 'auto', ?string $transactionId = null): int
    { global $dbRepo;
        return PaymentService::record($pdo, $invoiceId, $storeId, $amount, $method, $transactionId);
    }
}

if (!function_exists('store_get_payments')) {
    function store_get_payments(PDO $pdo, int $invoiceId): array
    { global $dbRepo;
        return PaymentService::getByInvoice($pdo, $invoiceId);
    }
}

if (!function_exists('store_change_plan')) {
    function store_change_plan(PDO $pdo, int $storeId, int $newPlanId, ?string $expiresAt = null,
        float $proratedAmount = 0.00): int
    { global $dbRepo;
        return PlanService::changePlan($pdo, $storeId, $newPlanId, $expiresAt, $proratedAmount);
    }
}

/* ========================================================================
   QUEUE wrappers
   ======================================================================== */

if (!function_exists('store_enqueue_job')) {
    function store_enqueue_job(PDO $pdo, string $type, string $priority = 'normal',
        ?array $payload = null, ?int $storeId = null, ?string $scheduledAt = null): int
    { global $dbRepo;
        return QueueService::enqueue($pdo, $type, $priority, $payload, $storeId, $scheduledAt);
    }
}

if (!function_exists('store_dequeue_job')) {
    function store_dequeue_job(PDO $pdo, string $type): ?array { global $dbRepo; return QueueService::dequeue($pdo, $type); }
}

if (!function_exists('store_update_job_status')) {
    function store_update_job_status(PDO $pdo, int $jobId, string $status, ?string $errorMessage = null): void
    { global $dbRepo;
        QueueService::updateStatus($pdo, $jobId, $status, $errorMessage);
    }
}

if (!function_exists('store_get_queue_stats')) {
    function store_get_queue_stats(PDO $pdo): array { global $dbRepo; return QueueService::getStats($pdo); }
}

if (!function_exists('store_get_queue_jobs')) {
    function store_get_queue_jobs(PDO $pdo, int $page = 1, int $perPage = 50,
        ?string $statusFilter = null, ?string $typeFilter = null): array
    { global $dbRepo;
        return QueueService::getJobs($pdo, $page, $perPage, $statusFilter, $typeFilter);
    }
}

if (!function_exists('store_get_queue_job_count')) {
    function store_get_queue_job_count(PDO $pdo, ?string $statusFilter = null,
        ?string $typeFilter = null): int
    { global $dbRepo;
        return QueueService::getJobCount($pdo, $statusFilter, $typeFilter);
    }
}

if (!function_exists('store_get_job')) {
    function store_get_job(PDO $pdo, int $jobId): ?array { global $dbRepo; return QueueService::getJob($pdo, $jobId); }
}

if (!function_exists('store_retry_job')) {
    function store_retry_job(PDO $pdo, int $jobId): void { global $dbRepo; QueueService::retry($pdo, $jobId); }
}

if (!function_exists('store_cancel_job')) {
    function store_cancel_job(PDO $pdo, int $jobId): void { global $dbRepo; QueueService::cancel($pdo, $jobId); }
}

if (!function_exists('store_requeue_job')) {
    function store_requeue_job(PDO $pdo, int $jobId, int $delayMinutes = 0): void
    { global $dbRepo;
        QueueService::requeue($pdo, $jobId, $delayMinutes);
    }
}

if (!function_exists('store_get_failed_jobs')) {
    function store_get_failed_jobs(PDO $pdo, int $page = 1, int $perPage = 50): array
    { global $dbRepo;
        return QueueService::getFailedJobs($pdo, $page, $perPage);
    }
}

if (!function_exists('store_get_failed_job_count')) {
    function store_get_failed_job_count(PDO $pdo): int { global $dbRepo; return QueueService::getFailedJobCount($pdo); }
}

if (!function_exists('store_delete_failed_job')) {
    function store_delete_failed_job(PDO $pdo, int $failedJobId): void
    { global $dbRepo;
        QueueService::deleteFailedJob($pdo, $failedJobId);
    }
}

if (!function_exists('store_move_to_failed_jobs')) {
    function store_move_to_failed_jobs(PDO $pdo, int $originalJobId, string $type, ?array $payload,
        string $priority, int $attempts, int $maxAttempts, string $errorMessage): void
    { global $dbRepo;
        QueueService::moveToFailed($pdo, $originalJobId, $type, $payload, $priority,
            $attempts, $maxAttempts, $errorMessage);
    }
}

if (!function_exists('store_cleanup_stuck_jobs')) {
    function store_cleanup_stuck_jobs(PDO $pdo, int $timeoutMinutes = 30): int
    { global $dbRepo;
        return QueueWorker::cleanupStuck($pdo, $timeoutMinutes);
    }
}

if (!function_exists('store_process_job')) {
    function store_process_job(PDO $pdo, array $job): array { global $dbRepo; return QueueWorker::processJob($pdo, $job); }
}

if (!function_exists('store_get_backoff_minutes')) {
    function store_get_backoff_minutes(int $attempt): int { global $dbRepo; return QueueWorker::getBackoffMinutes($attempt); }
}

if (!function_exists('store_get_job_types_list')) {
    function store_get_job_types_list(): array { global $dbRepo; return QueueWorker::getJobTypes(); }
}

if (!function_exists('store_get_priority_list')) {
    function store_get_priority_list(): array { global $dbRepo; return QueueWorker::getPriorities(); }
}

if (!function_exists('store_bulk_enqueue')) {
    function store_bulk_enqueue(PDO $pdo, array $jobs): int { global $dbRepo; return QueueService::bulkEnqueue($pdo, $jobs); }
}

if (!function_exists('store_requeue_failed_jobs')) {
    function store_requeue_failed_jobs(PDO $pdo): int { global $dbRepo; return QueueService::requeueFailedJobs($pdo); }
}

if (!function_exists('store_purge_completed_jobs')) {
    function store_purge_completed_jobs(PDO $pdo, int $olderThanDays = 7): int
    { global $dbRepo;
        return QueueService::purgeCompleted($pdo, $olderThanDays);
    }
}

if (!function_exists('store_purge_failed_jobs')) {
    function store_purge_failed_jobs(PDO $pdo, int $olderThanDays = 30): int
    { global $dbRepo;
        return QueueService::purgeFailedJobs($pdo, $olderThanDays);
    }
}

if (!function_exists('store_get_queue_health')) {
    function store_get_queue_health(PDO $pdo): array { global $dbRepo; return QueueHealth::getHealth($pdo); }
}

if (!function_exists('store_get_queue_avg_processing_time')) {
    function store_get_queue_avg_processing_time(PDO $pdo): float { global $dbRepo; return QueueHealth::getAvgProcessingTime($pdo); }
}

if (!function_exists('store_get_queue_by_type_over_time')) {
    function store_get_queue_by_type_over_time(PDO $pdo, int $hours = 24): array
    { global $dbRepo;
        return QueueHealth::getByTypeOverTime($pdo, $hours);
    }
}

if (!function_exists('store_queue_health_alert')) {
    function store_queue_health_alert(PDO $pdo, ?string $telegramChatId = null): array
    { global $dbRepo;
        return QueueHealth::checkAndAlert($pdo, $telegramChatId);
    }
}

/* ========================================================================
   QUEUE HANDLER wrappers
   ======================================================================== */

if (!function_exists('store_handle_telegram_send')) {
    function store_handle_telegram_send(PDO $pdo, array $payload, array $job): bool
    { global $dbRepo;
        return QueueWorker::handleTelegramSend($pdo, $payload, $job);
    }
}

if (!function_exists('store_handle_webhook_delivery')) {
    function store_handle_webhook_delivery(PDO $pdo, array $payload, array $job): bool
    { global $dbRepo;
        return QueueWorker::handleWebhookDelivery($pdo, $payload, $job);
    }
}

if (!function_exists('store_handle_ecotrack_sync')) {
    function store_handle_ecotrack_sync(PDO $pdo, array $payload, array $job): bool
    { global $dbRepo;
        return QueueWorker::handleEcotrackSync($pdo, $payload, $job);
    }
}

if (!function_exists('store_handle_ai_report')) {
    function store_handle_ai_report(PDO $pdo, array $payload, array $job): array
    { global $dbRepo;
        return QueueWorker::handleAiReport($pdo, $payload, $job);
    }
}

if (!function_exists('store_handle_email_send')) {
    function store_handle_email_send(PDO $pdo, array $payload): bool
    { global $dbRepo;
        $to = $payload['to'] ?? '';
        $subject = $payload['subject'] ?? '';
        $body = $payload['body'] ?? '';
        if (empty($to) || empty($subject)) return false;
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=utf-8\r\nFrom: " . (defined('MAIL_FROM') ? MAIL_FROM : 'noreply@ecom.local');
        return mail($to, $subject, $body, $headers);
    }
}

if (!function_exists('store_handle_invoice_generation')) {
    function store_handle_invoice_generation(PDO $pdo, array $payload): int
    { global $dbRepo;
        $storeId = (int) ($payload['store_id'] ?? 0);
        $amount = (float) ($payload['amount'] ?? 0);
        return $storeId ? InvoiceService::create($pdo, $storeId, $amount) : 0;
    }
}

if (!function_exists('store_enqueue_telegram')) {
    function store_enqueue_telegram(PDO $pdo, string $chatId, string $message): int
    { global $dbRepo;
        return QueueService::enqueueTelegram($pdo, $chatId, $message);
    }
}

if (!function_exists('store_enqueue_webhook')) {
    function store_enqueue_webhook(PDO $pdo, int $webhookId, string $event, array $payload): int
    { global $dbRepo;
        return QueueService::enqueueWebhook($pdo, $webhookId, $event, $payload);
    }
}

if (!function_exists('store_enqueue_ecotrack_sync')) {
    function store_enqueue_ecotrack_sync(PDO $pdo, string $action, array $data,
        int $storeId, string $priority = 'normal'): int
    { global $dbRepo;
        return QueueService::enqueueEcotrackSync($pdo, $action, $data, $storeId, $priority);
    }
}

if (!function_exists('store_enqueue_ai_report')) {
    function store_enqueue_ai_report(PDO $pdo, int $storeId, string $reportType): int
    { global $dbRepo;
        return QueueService::enqueueAiReport($pdo, $storeId, $reportType);
    }
}

if (!function_exists('store_enqueue_email')) {
    function store_enqueue_email(PDO $pdo, string $to, string $subject, string $body): int
    { global $dbRepo;
        return QueueService::enqueue($pdo, 'email_send', 'normal', ['to' => $to, 'subject' => $subject, 'body' => $body]);
    }
}

if (!function_exists('store_enqueue_invoice')) {
    function store_enqueue_invoice(PDO $pdo, int $storeId, float $amount): int
    { global $dbRepo;
        return QueueService::enqueue($pdo, 'invoice_generation', 'high', ['store_id' => $storeId, 'amount' => $amount]);
    }
}

if (!function_exists('store_enqueue_recovery_scan')) {
    function store_enqueue_recovery_scan(PDO $pdo, int $storeId): int
    { global $dbRepo;
        return QueueService::enqueue($pdo, 'recovery_scan', 'normal', [], $storeId);
    }
}

if (!function_exists('store_enqueue_risk_recalculation')) {
    function store_enqueue_risk_recalculation(PDO $pdo, int $storeId): int
    { global $dbRepo;
        return QueueService::enqueue($pdo, 'risk_recalculation', 'normal', [], $storeId);
    }
}

/* ========================================================================
   BACKUP wrappers
   ======================================================================== */

if (!function_exists('store_create_backup_job')) {
    function store_create_backup_job(PDO $pdo, string $type, string $scope = 'global',
        ?int $storeId = null, ?array $selectedTables = null): int
    { global $dbRepo;
        return BackupService::createJob($pdo, $type, $scope, $storeId, $selectedTables);
    }
}

if (!function_exists('store_get_backup_job')) {
    function store_get_backup_job(PDO $pdo, int $backupId): ?array { global $dbRepo; return BackupService::getJob($pdo, $backupId); }
}

if (!function_exists('store_update_backup_job')) {
    function store_update_backup_job(PDO $pdo, int $backupId, array $data): void
    { global $dbRepo;
        BackupService::updateJob($pdo, $backupId, $data);
    }
}

if (!function_exists('store_get_backup_jobs')) {
    function store_get_backup_jobs(PDO $pdo, int $page = 1, int $perPage = 20,
        ?string $typeFilter = null, ?string $statusFilter = null): array
    { global $dbRepo;
        return BackupService::getJobs($pdo, $page, $perPage, $typeFilter, $statusFilter);
    }
}

if (!function_exists('store_get_backup_job_count')) {
    function store_get_backup_job_count(PDO $pdo, ?string $typeFilter = null,
        ?string $statusFilter = null): int
    { global $dbRepo;
        return BackupService::getJobCount($pdo, $typeFilter, $statusFilter);
    }
}

if (!function_exists('store_delete_backup_job')) {
    function store_delete_backup_job(PDO $pdo, int $backupId): void { global $dbRepo; BackupService::deleteJob($pdo, $backupId); }
}

if (!function_exists('store_get_backup_config')) {
    function store_get_backup_config(PDO $pdo, string $key, mixed $default = null): mixed
    { global $dbRepo;
        return BackupService::getConfig($pdo, $key, $default);
    }
}

if (!function_exists('store_set_backup_config')) {
    function store_set_backup_config(PDO $pdo, string $key, string $value): void
    { global $dbRepo;
        BackupService::setConfig($pdo, $key, $value);
    }
}

if (!function_exists('store_get_all_backup_configs')) {
    function store_get_all_backup_configs(PDO $pdo): array { global $dbRepo; return BackupService::getAllConfigs($pdo); }
}

if (!function_exists('store_get_backup_storage_path')) {
    function store_get_backup_storage_path(): string { global $dbRepo; return BackupService::getStoragePath(); }
}

if (!function_exists('store_backup_write_log')) {
    function store_backup_write_log(PDO $pdo, int $backupId, string $message,
        ?int $storeId = null, string $level = 'info'): void
    { global $dbRepo;
        BackupService::writeLog($pdo, $backupId, $message, $storeId, $level);
    }
}

if (!function_exists('store_get_backup_logs')) {
    function store_get_backup_logs(PDO $pdo, int $backupId): array { global $dbRepo; return BackupService::getLogs($pdo, $backupId); }
}

if (!function_exists('store_execute_database_backup')) {
    function store_execute_database_backup(PDO $pdo, int $backupId, ?int $storeId = null,
        ?array $selectedTables = null): string
    { global $dbRepo;
        return BackupService::executeDatabaseBackup($pdo, $backupId, $storeId, $selectedTables);
    }
}

if (!function_exists('store_execute_files_backup')) {
    function store_execute_files_backup(PDO $pdo, int $backupId, ?int $storeId = null): string
    { global $dbRepo;
        return BackupService::executeFilesBackup($pdo, $backupId, $storeId);
    }
}

if (!function_exists('store_backup_upload_s3')) {
    function store_backup_upload_s3(PDO $pdo, int $backupId): bool { global $dbRepo; return BackupService::uploadToS3($pdo, $backupId); }
}

if (!function_exists('store_apply_retention_policy')) {
    function store_apply_retention_policy(PDO $pdo): int { global $dbRepo; return RetentionService::apply($pdo); }
}

if (!function_exists('store_handle_backup_database')) {
    function store_handle_backup_database(PDO $pdo, array $payload, array $job): string
    { global $dbRepo;
        return BackupService::handleDatabaseJob($pdo, $payload, $job);
    }
}

if (!function_exists('store_handle_backup_files')) {
    function store_handle_backup_files(PDO $pdo, array $payload, array $job): string
    { global $dbRepo;
        return BackupService::handleFilesJob($pdo, $payload, $job);
    }
}

if (!function_exists('store_handle_backup_store')) {
    function store_handle_backup_store(PDO $pdo, array $payload, array $job): array
    { global $dbRepo;
        return BackupService::handleStoreJob($pdo, $payload, $job);
    }
}

if (!function_exists('store_enqueue_backup_database')) {
    function store_enqueue_backup_database(PDO $pdo, ?int $storeId = null,
        ?array $selectedTables = null, string $priority = 'low'): int
    { global $dbRepo;
        return BackupService::enqueueDatabase($pdo, $storeId, $selectedTables, $priority);
    }
}

if (!function_exists('store_enqueue_backup_files')) {
    function store_enqueue_backup_files(PDO $pdo, ?int $storeId = null, string $priority = 'low'): int
    { global $dbRepo;
        return BackupService::enqueueFiles($pdo, $storeId, $priority);
    }
}

if (!function_exists('store_enqueue_backup_store')) {
    function store_enqueue_backup_store(PDO $pdo, int $storeId, string $priority = 'low'): int
    { global $dbRepo;
        return BackupService::enqueueStore($pdo, $storeId, $priority);
    }
}

if (!function_exists('store_get_backup_health')) {
    function store_get_backup_health(PDO $pdo): array { global $dbRepo; return BackupService::getHealth($pdo); }
}

if (!function_exists('store_check_backup_alerts')) {
    function store_check_backup_alerts(PDO $pdo): array { global $dbRepo; return BackupService::checkAlerts($pdo); }
}

if (!function_exists('store_get_backup_type_labels')) {
    function store_get_backup_type_labels(): array { global $dbRepo; return BackupService::getTypeLabels(); }
}

if (!function_exists('store_get_backup_scope_labels')) {
    function store_get_backup_scope_labels(): array { global $dbRepo; return BackupService::getScopeLabels(); }
}

if (!function_exists('store_get_backup_status_labels')) {
    function store_get_backup_status_labels(): array { global $dbRepo; return BackupService::getStatusLabels(); }
}

if (!function_exists('store_backup_download')) {
    function store_backup_download(PDO $pdo, int $backupId): ?string { global $dbRepo; return BackupService::download($pdo, $backupId); }
}

if (!function_exists('store_get_backup_storage_summary')) {
    function store_get_backup_storage_summary(PDO $pdo): array { global $dbRepo; return BackupService::getStorageSummary($pdo); }
}

/* ========================================================================
   RESTORE wrappers
   ======================================================================== */

if (!function_exists('store_create_restore_request')) {
    function store_create_restore_request(PDO $pdo, int $backupId, int $requestedBy,
        ?int $storeId = null, ?string $notes = null): int
    { global $dbRepo;
        return RestoreService::createRequest($pdo, $backupId, $requestedBy, $storeId, $notes);
    }
}

if (!function_exists('store_get_restore_request')) {
    function store_get_restore_request(PDO $pdo, int $requestId): ?array
    { global $dbRepo;
        return RestoreService::getRequest($pdo, $requestId);
    }
}

if (!function_exists('store_update_restore_request')) {
    function store_update_restore_request(PDO $pdo, int $requestId, array $data): void
    { global $dbRepo;
        RestoreService::updateRequest($pdo, $requestId, $data);
    }
}

if (!function_exists('store_get_restore_requests')) {
    function store_get_restore_requests(PDO $pdo, int $page = 1, int $perPage = 20,
        ?string $statusFilter = null): array
    { global $dbRepo;
        return RestoreService::getRequests($pdo, $page, $perPage, $statusFilter);
    }
}

if (!function_exists('store_get_restore_request_count')) {
    function store_get_restore_request_count(PDO $pdo, ?string $statusFilter = null): int
    { global $dbRepo;
        return RestoreService::getRequestCount($pdo, $statusFilter);
    }
}

if (!function_exists('store_approve_restore_request')) {
    function store_approve_restore_request(PDO $pdo, int $requestId, int $approvedBy): void
    { global $dbRepo;
        RestoreService::approve($pdo, $requestId, $approvedBy);
    }
}

if (!function_exists('store_reject_restore_request')) {
    function store_reject_restore_request(PDO $pdo, int $requestId): void
    { global $dbRepo;
        RestoreService::reject($pdo, $requestId);
    }
}

/* ========================================================================
   AUDIT wrappers
   ======================================================================== */

if (!function_exists('audit_log')) {
    function audit_log(PDO $pdo, int $storeId, string $action, string $entityType = '',
        int $entityId = 0, ?array $oldValue = null, ?array $newValue = null, ?int $staffId = null): int
    { global $dbRepo;
        return AuditService::log($pdo, $storeId, $action, $entityType, $entityId, $oldValue, $newValue, $staffId);
    }
}

if (!function_exists('audit_get')) {
    function audit_get(PDO $pdo, ?int $storeId = null, int $page = 1, int $perPage = 50,
        ?string $action = null, ?string $entityType = null,
        ?string $dateFrom = null, ?string $dateTo = null): array
    { global $dbRepo;
        return AuditService::getLogs($pdo, $storeId, $page, $perPage, $action, $entityType, $dateFrom, $dateTo);
    }
}

/* ========================================================================
   API KEY wrappers
   ======================================================================== */

if (!function_exists('store_generate_api_key')) {
    function store_generate_api_key(): string { global $dbRepo; return ApiKeyService::generateKey(); }
}

if (!function_exists('store_create_api_key')) {
    function store_create_api_key(PDO $pdo, int $storeId, string $label, array $permissions = [],
        ?array $ipWhitelist = null, ?string $expiresAt = null): int
    { global $dbRepo;
        return ApiKeyService::create($pdo, $storeId, $label, $permissions, $ipWhitelist, $expiresAt);
    }
}

if (!function_exists('store_get_api_keys')) {
    function store_get_api_keys(PDO $pdo, int $storeId): array { global $dbRepo; return ApiKeyService::getKeys($pdo, $storeId); }
}

if (!function_exists('store_get_api_key_by_id')) {
    function store_get_api_key_by_id(PDO $pdo, int $keyId, int $storeId): ?array
    { global $dbRepo;
        return ApiKeyService::getById($pdo, $keyId, $storeId);
    }
}

if (!function_exists('store_revoke_api_key')) {
    function store_revoke_api_key(PDO $pdo, int $keyId, int $storeId): void
    { global $dbRepo;
        ApiKeyService::revoke($pdo, $keyId, $storeId);
    }
}

if (!function_exists('store_rotate_api_key')) {
    function store_rotate_api_key(PDO $pdo, int $keyId, int $storeId): string
    { global $dbRepo;
        return ApiKeyService::rotate($pdo, $keyId, $storeId);
    }
}

if (!function_exists('store_get_api_key_permissions')) {
    function store_get_api_key_permissions(PDO $pdo, string $apiKey): array
    { global $dbRepo;
        return ApiKeyService::getPermissions($pdo, $apiKey);
    }
}

if (!function_exists('store_validate_api_key')) {
    function store_validate_api_key(PDO $pdo, string $apiKey): ?array {
        global $dbRepo;
        $row = ApiKeyService::validate($pdo, $apiKey);
        if ($row === null) {
            return null;
        }
        // Route handlers (api/v1/*.php) check permissions_list; the DB row only has the raw JSON column.
        $row['permissions_list'] = json_decode((string) ($row['permissions'] ?? '[]'), true) ?: [];
        $row['id'] = $row['id'] ?? null;
        return $row;
    }
}

if (!function_exists('store_log_api_call')) {
    function store_log_api_call(PDO $pdo, int $storeId, string $endpoint, string $method,
        int $statusCode, ?int $apiKeyId = null, int $responseTimeMs = 0): void
    { global $dbRepo;
        ApiKeyService::logCall($pdo, $storeId, $endpoint, $method, $statusCode, $apiKeyId, $responseTimeMs);
    }
}

if (!function_exists('store_get_api_usage_stats')) {
    function store_get_api_usage_stats(PDO $pdo, int $storeId): array
    { global $dbRepo;
        return ApiKeyService::getUsageStats($pdo, $storeId);
    }
}

if (!function_exists('store_get_api_logs')) {
    function store_get_api_logs(PDO $pdo, int $storeId, int $page = 1, int $perPage = 50): array
    { global $dbRepo;
        return ApiKeyService::getLogs($pdo, $storeId, $page, $perPage);
    }
}

if (!function_exists('store_get_available_permissions')) {
    function store_get_available_permissions(): array { global $dbRepo; return ApiKeyService::getAvailablePermissions(); }
}

if (!function_exists('store_api_json')) {
    function store_api_json(array $data, int $statusCode = 200): void
    { global $dbRepo;
        ApiKeyService::jsonResponse($data, $statusCode);
    }
}

/* ========================================================================
   WEBHOOK wrappers
   ======================================================================== */

if (!function_exists('store_create_webhook')) {
    function store_create_webhook(PDO $pdo, int $storeId, string $url, array $events,
        ?string $secret = null): int
    { global $dbRepo;
        return WebhookService::create($pdo, $storeId, $url, $events, $secret);
    }
}

if (!function_exists('store_get_webhooks')) {
    function store_get_webhooks(PDO $pdo, int $storeId): array { global $dbRepo; return WebhookService::getWebhooks($pdo, $storeId); }
}

if (!function_exists('store_update_webhook')) {
    function store_update_webhook(PDO $pdo, int $webhookId, int $storeId, array $data): void
    { global $dbRepo;
        WebhookService::update($pdo, $webhookId, $storeId, $data);
    }
}

if (!function_exists('store_delete_webhook')) {
    function store_delete_webhook(PDO $pdo, int $webhookId, int $storeId): void
    { global $dbRepo;
        WebhookService::delete($pdo, $webhookId, $storeId);
    }
}

if (!function_exists('store_get_webhooks_for_event')) {
    function store_get_webhooks_for_event(PDO $pdo, string $event, ?int $storeId = null): array
    { global $dbRepo;
        return WebhookService::getForEvent($pdo, $event, $storeId);
    }
}

if (!function_exists('store_deliver_webhook')) {
    function store_deliver_webhook(PDO $pdo, array $webhook, string $event, array $payload): bool
    { global $dbRepo;
        return WebhookService::deliver($pdo, $webhook, $event, $payload);
    }
}

if (!function_exists('store_trigger_webhook')) {
    function store_trigger_webhook(PDO $pdo, string $event, array $payload, ?int $storeId = null): array
    { global $dbRepo;
        return WebhookService::trigger($pdo, $event, $payload, $storeId);
    }
}

if (!function_exists('store_get_webhook_events_list')) {
    function store_get_webhook_events_list(): array { global $dbRepo; return WebhookService::getEventsList(); }
}

/* ========================================================================
   RATE LIMIT wrappers
   ======================================================================== */

if (!function_exists('store_get_rate_limit')) {
    function store_get_rate_limit(PDO $pdo, int $storeId): array { global $dbRepo; return RateLimitService::getLimit($pdo, $storeId); }
}

/* ========================================================================
   HELPERS
   ======================================================================== */

if (!function_exists('store_format_bytes')) {
    function store_format_bytes(int $bytes): string { global $dbRepo; return Helpers::formatBytes($bytes); }
}

/* ========================================================================
   RECOVERY wrappers
   ======================================================================== */

if (!function_exists('store_handle_recovery_scan')) {
    function store_handle_recovery_scan(PDO $pdo, array $payload): array
    { global $dbRepo;
        return RecoveryService::performFullHealthCheck($pdo);
    }
}

if (!function_exists('store_handle_risk_recalculation')) {
    function store_handle_risk_recalculation(PDO $pdo, array $payload): array
    { global $dbRepo;
        return RiskService::getCombinedRisk($pdo);
    }
}

/* ========================================================================
   QUEUE HANDLER DISPATCH — add new types to store_process_job
   ======================================================================== */

// The QueueWorker::processJob method handles dispatching via method name convention.
// If a handler cannot be found via method convention, this extension point
// allows additional handlers. extend store_process_job in custom code:
//   store_process_job($pdo, $job) then falls through to QueueWorker::processJob.
