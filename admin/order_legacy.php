<?php
require_once('header.php');

admin_ensure_order_call_log_table($pdo);
admin_ensure_order_status_log_table($pdo);
admin_ensure_ecotrack_setting_columns($pdo);
admin_ensure_order_ecotrack_columns($pdo);
if (function_exists('admin_ensure_zrexpress_setting_columns')) {
    admin_ensure_zrexpress_setting_columns($pdo);
}
if (function_exists('admin_ensure_order_zrexpress_columns')) {
    admin_ensure_order_zrexpress_columns($pdo);
}
require_once('inc/employee_functions.php');
employee_ensure_tables($pdo);
employee_auto_assign_unassigned($pdo, 100);

$settings = front_get_settings($pdo);
$ecotrack_settings = ecotrack_normalize_settings($settings);
$ecotrack_ready = ecotrack_is_configured($ecotrack_settings);
$zrexpress_settings = function_exists('zrexpress_normalize_settings') ? zrexpress_normalize_settings($settings) : [];
$zrexpress_ready = function_exists('zrexpress_is_configured') ? zrexpress_is_configured($zrexpress_settings) : false;
$admin_auto_refresh = admin_build_live_refresh_config($pdo, 'orders', ['interval_ms' => 15000]);

$employee_filter = isset($_GET['employee']) ? trim($_GET['employee']) : '';
if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Employee') {
    $employee_filter = 'my';
}
$filter_employee_id = 0;
$filter_unassigned = false;
$filter_my = false;

if ($employee_filter === 'unassigned') {
    $filter_unassigned = true;
} elseif ($employee_filter === 'my') {
    $my_emp = employee_get_current_admin_employee($pdo);
    if ($my_emp) {
        $filter_employee_id = (int) $my_emp['id'];
        $filter_my = true;
    }
} elseif ($employee_filter !== '' && ctype_digit($employee_filter)) {
    $filter_employee_id = (int) $employee_filter;
}

$all_employees = employee_get_all($pdo, true);

if (!function_exists('admin_order_delivery_label')) {
    function admin_order_delivery_label($value)
    { global $dbRepo;
    global $dbRepo;

        $value = function_exists('admin_normalize_delivery_type_text')
            ? admin_normalize_delivery_type_text($value)
            : trim((string) $value);
        $normalized = strtolower($value);
        $labels = function_exists('admin_delivery_type_labels')
            ? admin_delivery_type_labels()
            : [
                'home' => "\u{0645}\u{0646}\u{0632}\u{0644}",
                'office' => "\u{0645}\u{0643}\u{062A}\u{0628}",
                'free' => "\u{0645}\u{062C}\u{0627}\u{0646}\u{064A}",
            ];
        if ($value === $labels['home'] || $normalized === 'home') {
            return ['label' => 'إلى المنزل', 'class' => 'is-home'];
        }
        if ($value === $labels['office'] || $normalized === 'office') {
            return ['label' => 'إلى المكتب', 'class' => 'is-office'];
        }
        if ($value === $labels['free'] || $normalized === 'free') {
            return ['label' => 'توصيل مجاني', 'class' => 'is-free'];
        }
        return ['label' => $value !== '' ? $value : 'غير محدد', 'class' => 'is-other'];
    }
}

if (!function_exists('admin_order_customer_type_label')) {
    function admin_order_customer_type_label($value)
    { global $dbRepo;
    global $dbRepo;

        return (string) $value === 'registered'
            ? ['label' => 'عميل مسجل', 'class' => 'is-registered']
            : ['label' => 'طلب مباشر', 'class' => 'is-direct'];
    }
}

if (!function_exists('admin_order_excerpt')) {
    function admin_order_excerpt($value, $limit = 90)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr') && mb_strlen($value, 'UTF-8') > $limit) {
            return rtrim(mb_substr($value, 0, $limit, 'UTF-8')) . '...';
        }
        if (strlen($value) > $limit) {
            return rtrim(substr($value, 0, $limit)) . '...';
        }
        return $value;
    }
}

if (!function_exists('admin_order_tab_id')) {
    function admin_order_tab_id($status)
    { global $dbRepo;
    global $dbRepo;

        $status = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $status)));
        $status = trim($status, '_');
        if ($status === '') {
            $status = 'other';
        }

        return 'tab_orders_' . $status;
    }
}

if (!function_exists('admin_order_bulk_options')) {
    function admin_order_bulk_options($status)
    { global $dbRepo;
    global $dbRepo;

        $status = admin_normalize_order_status($status);
        switch ($status) {
            case 'Pending':
                return ['Completed' => 'تأكيد المحدد', 'Cancelled' => 'إلغاء المحدد'];
            case 'Completed':
                return ['Returned' => 'تحويل المحدد إلى مرتجع', 'Cancelled' => 'إلغاء المحدد', 'Pending' => 'إعادة المحدد للمراجعة'];
            case 'Returned':
                return ['Completed' => 'إعادة المحدد إلى مؤكد', 'Cancelled' => 'إلغاء المحدد', 'Pending' => 'إعادة المحدد للمراجعة'];
            case 'Cancelled':
                return ['Pending' => 'إعادة المحدد للمراجعة', 'Completed' => 'اعتماد المحدد كمؤكد'];
            case 'Confirmed':
                return ['Completed' => 'نقل المحدد إلى مؤكد', 'Returned' => 'تحويل المحدد إلى مرتجع', 'Cancelled' => 'إلغاء المحدد', 'Pending' => 'إعادة المحدد للمراجعة'];
            default:
                return ['Pending' => 'إعادة المحدد للمراجعة', 'Completed' => 'اعتماد المحدد كمؤكد', 'Cancelled' => 'إلغاء المحدد'];
        }
    }
}

if (!function_exists('admin_order_primary_action')) {
    function admin_order_primary_action($status, array $order = [])
    { global $dbRepo;
    global $dbRepo;

        $status = admin_normalize_order_status($status);
        switch ($status) {
            case 'Pending':
                if ($order) {
                    $followup_meta = admin_order_followup_meta($order);
                    if ($followup_meta['key'] === 'answered') {
                        return ['type' => 'modal', 'status' => 'Completed', 'label' => 'تأكيد سريع', 'class' => 'btn-success', 'icon' => 'fa-random'];
                    }
                    if ($followup_meta['key'] === 'wrong_number') {
                        return ['type' => 'modal', 'status' => 'Cancelled', 'label' => 'إلغاء بسبب الرقم', 'class' => 'btn-danger', 'icon' => 'fa-random'];
                    }

                    return [
                        'type' => 'link',
                        'href' => 'order-details.php?id=' . (int) ($order['id'] ?? 0),
                        'label' => $followup_meta['key'] === 'no_calls' ? 'ابدأ الاتصال' : 'سجل المتابعة',
                        'class' => $followup_meta['key'] === 'no_calls' ? 'btn-default' : 'btn-warning',
                        'icon' => 'fa-phone'
                    ];
                }
                return ['type' => 'modal', 'status' => 'Completed', 'label' => 'تأكيد سريع', 'class' => 'btn-success', 'icon' => 'fa-random'];
            case 'Completed':
                return ['type' => 'modal', 'status' => 'Returned', 'label' => 'تحويل إلى مرتجع', 'class' => 'btn-warning', 'icon' => 'fa-random'];
            case 'Returned':
                return ['type' => 'modal', 'status' => 'Completed', 'label' => 'إعادة إلى مؤكد', 'class' => 'btn-success', 'icon' => 'fa-random'];
            case 'Cancelled':
                return ['type' => 'modal', 'status' => 'Pending', 'label' => 'إعادة للمراجعة', 'class' => 'btn-default', 'icon' => 'fa-random'];
            case 'Confirmed':
                return ['type' => 'modal', 'status' => 'Completed', 'label' => 'اعتماد نهائي', 'class' => 'btn-success', 'icon' => 'fa-random'];
            default:
                return ['type' => 'modal', 'status' => 'Pending', 'label' => 'تحديث الحالة', 'class' => 'btn-primary', 'icon' => 'fa-random'];
        }
    }
}

if (!function_exists('admin_order_followup_meta')) {
    function admin_order_followup_meta(array $order)
    { global $dbRepo;
    global $dbRepo;

        $call_count = (int) ($order['call_count'] ?? 0);
        $last_call_status = (string) ($order['last_call_status'] ?? '');
        $no_answer_count = (int) ($order['no_answer_count'] ?? 0);

        if ($call_count === 0) {
            return [
                'key' => 'no_calls',
                'class' => 'followup-none',
                'label' => 'بدون اتصال',
                'hint' => 'ابدأ بمحاولة اتصال وتوثيق النتيجة قبل تأكيد الطلب.',
                'suggested_note' => 'لم تتم أي محاولة اتصال بعد. يلزم التواصل مع الزبون أولاً.',
                'preferred_status' => ''
            ];
        }

        if ($last_call_status === 'answered') {
            return [
                'key' => 'answered',
                'class' => 'followup-ready',
                'label' => 'تم الرد',
                'hint' => 'آخر اتصال كان ناجحاً. يمكن تأكيد الطلب إذا كانت البيانات مكتملة.',
                'suggested_note' => 'تم الرد وتأكيد بيانات الزبون والطلب.',
                'preferred_status' => 'Completed'
            ];
        }

        if ($last_call_status === 'wrong_number') {
            return [
                'key' => 'wrong_number',
                'class' => 'followup-risk',
                'label' => 'رقم خاطئ',
                'hint' => 'يفضل تصحيح الرقم أو إلغاء الطلب مع توثيق السبب بوضوح.',
                'suggested_note' => 'تعذر التواصل لأن رقم الهاتف غير صحيح.',
                'preferred_status' => 'Cancelled'
            ];
        }

        if ($last_call_status === 'busy') {
            return [
                'key' => 'busy',
                'class' => 'followup-warning',
                'label' => 'الخط مشغول',
                'hint' => 'أعد المحاولة لاحقاً قبل اتخاذ قرار الحالة النهائية.',
                'suggested_note' => 'تم الاتصال لكن الخط كان مشغولاً وستتم إعادة المحاولة.',
                'preferred_status' => ''
            ];
        }

        if ($last_call_status === 'phone_off') {
            return [
                'key' => 'phone_off',
                'class' => 'followup-warning',
                'label' => 'هاتف مغلق',
                'hint' => 'الهاتف مغلق حالياً. يفضّل إعادة المحاولة في وقت آخر قبل إلغاء الطلب.',
                'suggested_note' => 'تمت محاولة الاتصال لكن هاتف الزبون كان مغلقاً.',
                'preferred_status' => ''
            ];
        }

        if ($last_call_status === 'no_answer') {
            if ($no_answer_count >= 3) {
                return [
                    'key' => 'no_answer_3',
                    'class' => 'followup-risk',
                    'label' => 'لم يرد بعد 3 محاولات',
                    'hint' => 'تمت عدة محاولات دون رد. وثق الملاحظة ثم قرر الإلغاء أو إبقاء الطلب للمراجعة.',
                    'suggested_note' => 'ثلاث محاولات اتصال دون رد من الزبون.',
                    'preferred_status' => ''
                ];
            }

            if ($no_answer_count === 2) {
                return [
                    'key' => 'no_answer_2',
                    'class' => 'followup-warning',
                    'label' => 'لم يرد - متابعة ثانية',
                    'hint' => 'المتابعة الثانية دون رد. يوصى بمحاولة أخيرة قبل الحسم.',
                    'suggested_note' => 'المتابعة الثانية دون رد، ستتم محاولة أخيرة.',
                    'preferred_status' => ''
                ];
            }

            return [
                'key' => 'no_answer_1',
                'class' => 'followup-warning',
                'label' => 'لم يرد - محاولة أولى',
                'hint' => 'لم يرد في أول محاولة. أعد الاتصال لاحقاً وسجل النتيجة.',
                'suggested_note' => 'لم يتم الرد في أول محاولة اتصال.',
                'preferred_status' => ''
            ];
        }

        return [
            'key' => 'neutral',
            'class' => 'followup-neutral',
            'label' => 'قيد المتابعة',
            'hint' => 'راجع سجل الاتصالات ثم حدّد الإجراء المناسب.',
            'suggested_note' => '',
            'preferred_status' => ''
        ];
    }
}
$flash = admin_pull_flash_message('orders');
$section_config = [
    'Pending' => ['title' => 'طلبات تحتاج تأكيد', 'icon' => 'fa-clock-o', 'desc' => 'طلبات جديدة أو معلقة بانتظار القرار النهائي.'],
    'Completed' => ['title' => 'طلبات مؤكدة', 'icon' => 'fa-check-circle', 'desc' => 'طلبات اعتمدتها الإدارة وأصبحت مؤكدة.'],
    'Returned' => ['title' => 'طلبات مرجعة', 'icon' => 'fa-undo', 'desc' => 'طلبات تعذر إتمامها أو أُعيدت بعد التنفيذ.'],
    'Cancelled' => ['title' => 'طلبات ملغاة', 'icon' => 'fa-times-circle', 'desc' => 'طلبات تم إلغاؤها من الإدارة أو من العميل.'],
    'Confirmed' => ['title' => 'طلبات قيد المعالجة', 'icon' => 'fa-refresh', 'desc' => 'حالة قديمة ما زالت مدعومة في النظام.']
];

$summary = ['total' => 0, 'today' => 0, 'pending' => 0, 'completed' => 0, 'returned' => 0, 'cancelled' => 0, 'confirmed' => 0, 'completed_amount' => 0.0, 'pending_no_calls' => 0, 'pending_ready' => 0, 'pending_followup' => 0];
$orders_by_status = [];
$status_totals = [];
foreach (array_keys($section_config) as $status_key) {
    $orders_by_status[$status_key] = [];
    $status_totals[$status_key] = 0.0;
}

$sql = "SELECT o.*, c.color_name AS resolved_color_name, COALESCE(cs.call_count, 0) AS call_count, COALESCE(cs.no_answer_count, 0) AS no_answer_count, cl.called_at AS last_call_at, cl.call_status AS last_call_status, sl.status_note AS last_status_note, sl.changed_at AS last_status_changed_at, sl.changed_by AS last_status_changed_by, e.full_name AS assigned_employee_name, e.id AS assigned_employee_id, oa.assigned_at AS assigned_at FROM tbl_order o LEFT JOIN tbl_color c ON c.color_id = o.order_color LEFT JOIN (SELECT order_id, COUNT(*) AS call_count, SUM(CASE WHEN call_status = 'no_answer' THEN 1 ELSE 0 END) AS no_answer_count, MAX(id) AS last_call_id FROM tbl_order_call_log GROUP BY order_id) cs ON cs.order_id = o.id LEFT JOIN tbl_order_call_log cl ON cl.id = cs.last_call_id LEFT JOIN (SELECT logs.order_id, logs.status_note, logs.changed_at, logs.changed_by FROM tbl_order_status_log logs INNER JOIN (SELECT order_id, MAX(id) AS last_id FROM tbl_order_status_log GROUP BY order_id) last_log ON last_log.last_id = logs.id) sl ON sl.order_id = o.id LEFT JOIN tbl_order_assignment oa ON oa.order_id = o.id AND oa.status = 'active' LEFT JOIN tbl_employee e ON e.id = oa.employee_id";
$params = [];
if ($filter_unassigned) {
    $sql .= " WHERE oa.id IS NULL";
} elseif ($filter_employee_id > 0) {
    $sql .= " WHERE oa.employee_id = ?";
    $params[] = $filter_employee_id;
}
$sql .= " ORDER BY o.order_date DESC, o.id DESC";
$statement = $dbRepo->prepare($sql);
$statement->execute($params);
$orders = $statement->fetchAll(PDO::FETCH_ASSOC);

foreach ($orders as $index => $order) {
    $status = admin_normalize_order_status($order['order_status'] ?? '');
    if (!isset($orders_by_status[$status])) {
        $orders_by_status[$status] = [];
        $status_totals[$status] = 0.0;
        $section_config[$status] = ['title' => 'حالات أخرى', 'icon' => 'fa-folder-open', 'desc' => 'طلبات بحالات غير مصنفة.'];
    }

    $orders[$index]['normalized_status'] = $status;
    $orders_by_status[$status][] = $orders[$index];
    $status_totals[$status] += (float) ($order['total_price'] ?? 0);
    $summary['total']++;

    if (!empty($order['order_date']) && date('Y-m-d', strtotime($order['order_date'])) === date('Y-m-d')) {
        $summary['today']++;
    }

    if ($status === 'Pending') {
        $summary['pending']++;
        $followup_meta = admin_order_followup_meta($order);
        if ($followup_meta['key'] === 'no_calls') {
            $summary['pending_no_calls']++;
        } elseif ($followup_meta['key'] === 'answered') {
            $summary['pending_ready']++;
        } elseif (in_array($followup_meta['key'], ['no_answer_1', 'no_answer_2', 'no_answer_3', 'busy', 'phone_off', 'wrong_number'], true)) {
            $summary['pending_followup']++;
        }
    } elseif ($status === 'Completed') {
        $summary['completed']++;
        $summary['completed_amount'] += (float) ($order['total_price'] ?? 0);
    } elseif ($status === 'Returned') {
        $summary['returned']++;
    } elseif ($status === 'Cancelled') {
        $summary['cancelled']++;
    } elseif ($status === 'Confirmed') {
        $summary['confirmed']++;
    }
}

$status_labels = [];
foreach (admin_order_status_definitions() as $status_code => $status_meta) {
    $status_labels[$status_code] = $status_meta['label'];
}

$status_button_meta = [
    'Pending' => ['text' => 'إعادة للمراجعة', 'class' => 'btn btn-default', 'placeholder' => 'مثال: الطلب يحتاج مراجعة قبل الحسم النهائي.'],
    'Completed' => ['text' => 'تأكيد الطلب', 'class' => 'btn btn-success', 'placeholder' => 'مثال: تم تأكيد العميل والعنوان ونوع التوصيل.'],
    'Returned' => ['text' => 'حفظ كمرتجع', 'class' => 'btn btn-warning', 'placeholder' => 'مثال: رفض الاستلام أو تعذر التوصيل.'],
    'Cancelled' => ['text' => 'حفظ كملغي', 'class' => 'btn btn-danger', 'placeholder' => 'مثال: العميل ألغى الطلب أو الرقم غير صالح.'],
    'Confirmed' => ['text' => 'نقل إلى قيد المعالجة', 'class' => 'btn btn-primary', 'placeholder' => 'مثال: الطلب مثبت ويحتاج متابعة تنفيذية.']
];
?>
<style>
.orders-page{--c1:#1f6fb2;--ink:#263442;--muted:#6c7a89;--line:#dde6ee}.orders-page .note{margin:8px 0 0;color:var(--muted);font-size:14px;line-height:1.8}.orders-page .flash{padding:15px 18px;border-radius:14px;margin-bottom:18px;font-weight:700}.orders-page .flash.success{background:#eaf8f0;color:#1b6c49}.orders-page .flash.danger{background:#fdeeee;color:#a13030}.orders-page .flash.warning{background:#fff6e8;color:#9b6206}.orders-page .hero,.orders-page .panel{background:#fff;border-top:3px solid var(--c1);border-radius:16px;box-shadow:0 12px 28px rgba(18,35,52,.08);overflow:hidden}.orders-page .hero{padding:22px;margin-bottom:20px}.orders-page .hero h2{margin:0 0 10px;color:var(--ink);font-size:28px;font-weight:800}.orders-page .hero p{margin:0;color:var(--muted);line-height:1.9}.orders-page .hero-actions,.orders-page .status-nav,.orders-page .bulkbar,.orders-page .row-actions,.orders-page .order-meta,.orders-page .order-sub,.orders-page .section-head{display:flex;flex-wrap:wrap;gap:10px}.orders-page .hero-actions{margin-top:16px}.orders-page .hero-actions .btn,.orders-page .bulkbar .btn,.orders-page .row-actions .btn,.orders-page .status-nav a{border-radius:12px;font-weight:700}.orders-page .stats,.orders-page .pending-cues{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:18px 0 0}.orders-page .pending-cues{grid-template-columns:repeat(3,minmax(0,1fr));margin:0 0 20px}.orders-page .stat,.orders-page .cue{padding:16px;border:1px solid var(--line);border-radius:14px;background:linear-gradient(180deg,#fff 0,#f8fbfe 100%)}.orders-page .stat small,.orders-page .cue small{display:block;color:var(--muted);font-size:12px;font-weight:700;margin-bottom:6px}.orders-page .stat strong,.orders-page .cue strong{display:block;color:var(--ink);font-size:24px}.orders-page .status-nav{margin-bottom:20px}.orders-page .status-nav a{padding:11px 15px;border:1px solid var(--line);background:#fff;color:var(--ink)}.orders-page .status-nav .count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 9px;border-radius:999px;background:#edf4fb;color:var(--c1);font-size:12px}.orders-page .panel{margin-bottom:20px}.orders-page .panel .box-header{padding:18px 20px;border-bottom:1px solid #e8eef4;background:#f9fbfd}.orders-page .panel .box-body{padding:20px}.orders-page .section-head{justify-content:space-between;align-items:flex-start}.orders-page .section-head h3{margin:0;color:var(--ink);font-size:22px;font-weight:800}.orders-page .section-head p{margin:6px 0 0;color:var(--muted)}.orders-page .section-metrics{display:flex;gap:12px;flex-wrap:wrap}.orders-page .section-metrics span{padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);font-weight:700}.orders-page .bulkbar{align-items:flex-end;justify-content:space-between;margin-bottom:15px;padding:14px;border:1px solid var(--line);border-radius:14px;background:#f8fbfe}.orders-page .bulkbar-main{display:flex;gap:10px;flex-wrap:wrap;flex:1 1 640px}.orders-page .bulkbar-main .form-control{min-width:210px;border-radius:12px}.orders-page .bulkbar-note{flex:1 1 240px;color:var(--muted);font-size:13px;line-height:1.8}.orders-page .table>thead>tr>th{background:#f7fafd;color:#4f6070;font-weight:800;border-color:#e3eaf1;vertical-align:middle}.orders-page .table>tbody>tr>td{vertical-align:top;border-color:#e8eef4}.orders-page .orders-table>thead>tr>th:first-child,.orders-page .orders-table>tbody>tr>td:first-child{text-align:center;vertical-align:middle}.orders-page .orders-table input[type="checkbox"]{width:20px;height:20px;cursor:pointer;accent-color:var(--c1);margin:0;display:block;margin-left:auto;margin-right:auto}.orders-page .pill{display:inline-flex;align-items:center;justify-content:center;padding:6px 11px;border-radius:999px;font-size:12px;font-weight:700}.orders-page .pill.is-registered{background:#e9f8ef;color:#1f8b5f}.orders-page .pill.is-direct{background:#eef5fb;color:var(--c1)}.orders-page .pill.is-home{background:#e9f2ff;color:#255cb8}.orders-page .pill.is-office{background:#f2f3f6;color:#5d6672}.orders-page .pill.is-free{background:#eaf8f0;color:#1f8b5f}.orders-page .pill.is-other{background:#f5f7fa;color:#667586}.orders-page .pill.followup-none{background:#fef2f2;color:#b93838}.orders-page .pill.followup-ready{background:#eaf8f0;color:#1f8b5f}.orders-page .pill.followup-warning{background:#fff6e8;color:#a96a04}.orders-page .pill.followup-risk{background:#fdeeee;color:#b93838}.orders-page .pill.followup-neutral{background:#eef5fb;color:var(--c1)}.orders-page .order-main strong{display:block;color:var(--ink);font-size:15px;line-height:1.6}.orders-page .order-meta,.orders-page .order-sub{margin-top:8px;color:var(--muted);font-size:12px;line-height:1.8}.orders-page .tag{display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;background:#f1f5f9;color:#536372;font-size:11px;font-weight:700}.orders-page .callbox,.orders-page .statusbox{margin-top:10px;padding:11px 12px;border:1px solid var(--line);border-radius:12px;background:#fafcfe}.orders-page .callbox small,.orders-page .statusbox small{display:block;color:var(--muted);line-height:1.8}.orders-page .statusbox .label{display:inline-block;margin-bottom:8px;padding:8px 12px;border-radius:999px;font-size:12px}.orders-page .status-note{margin:8px 0;color:var(--ink);line-height:1.8}.orders-page .row-actions{flex-direction:column;min-width:140px}.orders-page .empty{padding:26px;border:1px dashed var(--line);border-radius:16px;text-align:center;background:#fbfdff;color:var(--muted);line-height:1.9}.orders-page .modal-content{border-radius:18px;overflow:hidden}.orders-page .modal-header{background:#f8fbfe}.orders-page .modal-order{margin-bottom:15px;padding:13px 14px;border:1px solid var(--line);border-radius:14px;background:#f8fbfe}.orders-page .modal-order strong{display:block;color:var(--ink);font-size:16px}.orders-page .modal-order span{display:block;margin-top:6px;color:var(--muted);line-height:1.8}@media (max-width:991px){.orders-page .stats{grid-template-columns:repeat(2,minmax(0,1fr))}.orders-page .pending-cues{grid-template-columns:1fr}.orders-page .section-head{flex-direction:column}}@media (max-width:767px){.orders-page .stats{grid-template-columns:1fr}.orders-page .bulkbar-main{flex-direction:column}.orders-page .bulkbar-main .form-control,.orders-page .bulkbar-main .btn,.orders-page .hero-actions .btn{width:100%}.orders-page .status-nav{flex-direction:column}}
</style>
<style>
.orders-page .callbox .followup-hint{margin-top:6px;color:#425466;font-weight:700}
.orders-page .callbox .callbox-link{display:inline-flex;align-items:center;margin-top:4px;color:var(--c1);font-weight:800}
.orders-page .orders-tabs-custom{background:transparent;box-shadow:none;border:0;margin-bottom:20px}
.orders-page .orders-status-tabs{display:flex;flex-wrap:wrap;gap:10px;border-bottom:0;margin:0 0 18px;padding:0}
.orders-page .orders-status-tabs>li{float:none;margin:0}
.orders-page .orders-status-tabs>li>a{display:inline-flex;align-items:center;gap:8px;padding:11px 15px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--ink);font-weight:700;margin:0}
.orders-page .orders-status-tabs>li.active>a,
.orders-page .orders-status-tabs>li.active>a:focus,
.orders-page .orders-status-tabs>li.active>a:hover{background:linear-gradient(135deg,#f5fbff 0,#eaf4ff 100%);border:1px solid #bfd4ea;color:#0f4c81}
.orders-page .orders-status-tabs .count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 9px;border-radius:999px;background:#edf4fb;color:var(--c1);font-size:12px}
.orders-page .orders-status-tab-content{background:transparent;padding:0;border:0}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>Gestion des commandes</h1>
        <p class="note">صفحة موحدة لتأكيد الطلبات، إلغائها، إرجاعها، ومراجعة سجل المكالمات من مكان واحد.</p>
    </div>
</section>

<section class="content orders-page">
    
    <?php if ($flash && !empty($flash['message'])): ?>
        <div class="flash <?php echo htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="hero">
        <h2>لوحة متابعة الطلبات</h2>
        <p>التأكيد لم يعد مجرد زر واحد. الآن تستطيع رؤية الطلبات الجاهزة للحسم، الطلبات التي تحتاج متابعة، ثم اختيار الإجراء المناسب لكل طلب أو على مجموعة كاملة.</p>
        <div class="hero-actions">
            <a href="#tab_orders_pending" class="btn btn-primary js-order-tab-shortcut"><i class="fa fa-clock-o"></i> افتح الطلبات التي تحتاج تأكيد</a>
            <?php if (!$is_employee): ?>
            <a href="order-statistics.php" class="btn btn-default"><i class="fa fa-bar-chart"></i> الإحصائيات</a>
            <a href="incomplete-orders.php" class="btn btn-default"><i class="fa fa-exclamation-triangle"></i> الطلبات غير المكتملة</a>
            <a href="ecotrack-diagnostics.php" class="btn btn-default"><i class="fa fa-stethoscope"></i> فحوصات ECOTRACK GET</a>
            <a href="zrexpress-diagnostics.php" class="btn btn-default"><i class="fa fa-stethoscope"></i> فحوصات ZRexpress</a>
            <?php endif; ?>
        </div>
        <div class="hero-actions" style="margin-top:8px;">
            <?php if (!$is_employee): ?>
            <form method="get" class="form-inline" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <select name="employee" class="form-control input-sm" onchange="this.form.submit();">
                    <option value="">جميع الطلبات</option>
                    <option value="unassigned" <?php echo $filter_unassigned ? 'selected' : ''; ?>>غير موزعة</option>
                    <option value="my" <?php echo $filter_my ? 'selected' : ''; ?>>طلباتي</option>
                    <?php foreach ($all_employees as $emp): ?>
                        <option value="<?php echo (int) $emp['id']; ?>" <?php echo $filter_employee_id === (int) $emp['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($employee_filter !== ''): ?>
                    <a href="order.php" class="btn btn-default btn-sm"><i class="fa fa-times"></i> إلغاء الفلتر</a>
                <?php endif; ?>
            </form>
            <?php else: ?>
                <span class="label label-primary" style="font-size: 14px; padding: 6px 12px; border-radius: 8px;">عرض طلباتي فقط</span>
            <?php endif; ?>
        </div>
        <div class="stats">
            <div class="stat"><small>إجمالي الطلبات</small><strong><?php echo $summary['total']; ?></strong></div>
            <div class="stat"><small>طلبات اليوم</small><strong><?php echo $summary['today']; ?></strong></div>
            <div class="stat"><small>بانتظار التأكيد</small><strong><?php echo $summary['pending']; ?></strong></div>
            <div class="stat"><small>القيمة المؤكدة</small><strong><?php echo htmlspecialchars(admin_format_order_amount($summary['completed_amount']), ENT_QUOTES, 'UTF-8'); ?></strong></div>
        </div>
    </div>

    <div class="pending-cues">
        <div class="cue"><small>بدون أي اتصال</small><strong><?php echo $summary['pending_no_calls']; ?></strong></div>
        <div class="cue"><small>جاهزة للتأكيد</small><strong><?php echo $summary['pending_ready']; ?></strong></div>
        <div class="cue"><small>تحتاج متابعة</small><strong><?php echo $summary['pending_followup']; ?></strong></div>
    </div>

    <div class="nav-tabs-custom orders-tabs-custom">
        <ul class="nav nav-tabs orders-status-tabs" id="orderStatusTabs">
            <?php foreach ($section_config as $status_code => $config): ?>
                <?php $tab_id = admin_order_tab_id($status_code); ?>
                <li class="<?php echo $status_code === 'Pending' ? 'active' : ''; ?>">
                    <a href="#<?php echo htmlspecialchars($tab_id, ENT_QUOTES, 'UTF-8'); ?>" data-toggle="tab">
                        <i class="fa <?php echo htmlspecialchars($config['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span><?php echo htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="count"><?php echo count($orders_by_status[$status_code] ?? []); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content orders-status-tab-content">
    <?php foreach ($section_config as $status_code => $config): ?>
        <?php
        $section_orders = $orders_by_status[$status_code] ?? [];
        $bulk_options = admin_order_bulk_options($status_code);
        if ($ecotrack_ready) {
            $bulk_options['ecotrack_create'] = 'إرسال المحدد إلى ECOTRACK + اعتماد محلي';
            $bulk_options['ecotrack_delete'] = 'حذف المحدد من ECOTRACK + استرجاع الحالة';
            $bulk_options['ecotrack_print_4up'] = 'طباعة 4 بوردرو في A4';
        }
        if ($zrexpress_ready) {
            $bulk_options['zrexpress_create'] = 'إرسال المحدد إلى ZRexpress + اعتماد محلي';
            $bulk_options['zrexpress_delete'] = 'حذف المحدد من ZRexpress (محلياً)';
        }
        $primary_action = admin_order_primary_action($status_code);
        $tab_id = admin_order_tab_id($status_code);
        ?>
        <div class="tab-pane <?php echo $status_code === 'Pending' ? 'active' : ''; ?>" id="<?php echo htmlspecialchars($tab_id, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="panel" id="section-<?php echo htmlspecialchars($status_code, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="box-header">
                <div class="section-head">
                    <div>
                        <h3><i class="fa <?php echo htmlspecialchars($config['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($config['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($config['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="section-metrics">
                        <span><?php echo count($section_orders); ?> طلب</span>
                        <span><?php echo htmlspecialchars(admin_format_order_amount($status_totals[$status_code] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </div>
            <div class="box-body">
                <?php if (!$section_orders): ?>
                    <div class="empty">لا توجد طلبات حالياً داخل هذا القسم.</div>
                <?php else: ?>
                    <form method="post" action="order-bulk-action.php" class="bulk-form">
                        <?php $csrf->echoInputField(); ?>
                        <input type="hidden" name="redirect" class="bulk-redirect-input" value="order.php#<?php echo htmlspecialchars($tab_id, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="bulkbar">
                            <div class="bulkbar-main">
                                <select name="action" class="form-control bulk-action-select" required>
                                    <option value="">اختر الإجراء الجماعي</option>
                                    <?php foreach ($bulk_options as $action_value => $action_label): ?>
                                        <option value="<?php echo htmlspecialchars($action_value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="status_note" class="form-control" placeholder="ملاحظة جماعية اختيارية">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-check-square-o"></i> تنفيذ على المحدد</button>
                                <?php if (!$is_employee): ?>
                                <button type="submit" name="action" value="delete" class="btn btn-danger js-bulk-delete" formnovalidate><i class="fa fa-trash"></i> حذف المحدد</button>
                                <?php endif; ?>
                            </div>
                            <div class="bulkbar-note">
                                يتم التحقق من صحة انتقال الحالة لكل طلب. الطلب غير المطابق سيتم تجاهله مع إشعار واضح بعد التنفيذ.
                                <?php if ($ecotrack_ready): ?> ويمكنك أيضًا إرسال عدة طلبات إلى ECOTRACK أو حذفها منه أو فتح طباعة جماعية 4 بوردرو في ورقة A4.<?php endif; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            
                            <table class="table table-bordered table-hover orders-table dataTable" id="table-<?php echo htmlspecialchars(strtolower($status_code), ENT_QUOTES, 'UTF-8'); ?>" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th class="col-checkbox" style="width:30px;"><input type="checkbox" class="js-select-all" data-group="<?php echo htmlspecialchars($status_code, ENT_QUOTES, 'UTF-8'); ?>"></th>
                                        <th class="col-order">الطلب</th>
                                        <th>العميل</th>
                                        <th>المنتج</th>
                                        <th>القيمة</th>
                                        <th>التوصيل</th>
                                        <th>الموظف</th>
                                        <th>حالة الطلب</th>
                                        <th>حالة التوصيل</th>
                                        <th class="col-actions">الإجراءات</th>
                                        <th class="col-expand" style="width:30px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($section_orders as $order): ?>
                                        <?php
                                        $order_id = (int) $order['id'];
                                        $status_meta = admin_get_order_status_meta($order['normalized_status']);
                                        $ecotrack_remote_status = trim((string) ($order['ecotrack_remote_status'] ?? ''));
                                        $zrexpress_remote_status = trim((string) ($order['zrexpress_remote_status'] ?? ''));
                                        $employee_name = $order['assigned_employee_name'] ?? '-';
                                        if (empty($employee_name)) $employee_name = '-';
                                        $company = !empty($order['ecotrack_tracking']) ? 'EcoTrack' : (!empty($order['zrexpress_tracking']) ? 'ZR Express' : '-');
                                        ?>
                                        <tr data-order-id="<?php echo $order_id; ?>">
                                            <td class="col-checkbox"><input type="checkbox" name="order_ids[]" value="<?php echo $order_id; ?>" class="js-order-checkbox" data-group="<?php echo htmlspecialchars($status_code, ENT_QUOTES, 'UTF-8'); ?>"></td>
                                            <td class="col-order">
                                                <strong>#<?php echo $order_id; ?></strong><br>
                                                <small style="color:#94a3b8; font-size:11px;"><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></small>
                                            </td>
                                            <td>
                                                <strong style="color:#334155; font-size:13px;"><?php echo htmlspecialchars($order['customer_name'] ?: '-'); ?></strong><br>
                                                <span style="color:#64748b; font-size:12px; direction:ltr; display:inline-block;"><?php echo htmlspecialchars($order['customer_phone'] ?: '-'); ?></span>
                                            </td>
                                            <td>
                                                <strong style="color:#334155; font-size:13px;"><?php echo htmlspecialchars($order['product_name'] ?: '-'); ?></strong>
                                                <?php if($order['quantity'] > 1): ?>
                                                <span style="background:#f1f5f9; padding:2px 5px; border-radius:4px; font-size:11px; color:#475569; margin-right:4px;">x<?php echo $order['quantity']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><strong style="color:#0f172a; font-size:13px;"><?php echo number_format((float)($order['total_price'] ?: 0), 0); ?> دج</strong></td>
                                            <td>
                                                <span style="font-size:13px; color:#334155; display:block;"><?php echo htmlspecialchars($company); ?></span>
                                                <span style="font-size:11px; color:#64748b;"><?php echo htmlspecialchars($order['wilaya'] ?: '-'); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size:12px; <?php echo $employee_name !== '-' ? 'background:#e0f2fe; color:#0369a1; padding:2px 8px; border-radius:999px;' : 'color:#94a3b8;'; ?>">
                                                    <?php echo htmlspecialchars($employee_name); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge-status status-<?php echo strtolower($order['normalized_status']); ?>"><?php echo htmlspecialchars($status_meta['label']); ?></span>
                                            </td>
                                            <td>
                                                <span style="font-size:12px; color:#64748b;"><?php echo htmlspecialchars($ecotrack_remote_status ?: ($zrexpress_remote_status ?: '-')); ?></span>
                                            </td>
                                            <td class="col-actions">
                                                <div class="dropdown">
                                                    <button class="btn btn-default btn-xs dropdown-toggle" type="button" data-toggle="dropdown" style="border:1px solid #cbd5e1; border-radius:4px; font-weight:600; color:#475569;">المزيد ⋮</button>
                                                    <ul class="dropdown-menu pull-right" style="box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border:1px solid #e2e8f0; border-radius:6px; min-width:120px;">
                                                        <li><a href="order-details.php?id=<?php echo $order_id; ?>"><i class="fa fa-eye text-primary"></i> عرض</a></li>
                                                        <li><a href="order-edit.php?id=<?php echo $order_id; ?>"><i class="fa fa-pencil text-warning"></i> تعديل</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                            <td class="col-expand text-center">
                                                <button type="button" class="btn btn-xs btn-default btn-expand-row" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; color:#64748b;"><i class="fa fa-chevron-down"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        </div>
    <?php endforeach; ?>
        </div>
    </div>
</section>

<div class="modal fade orders-page" id="orderStatusModal" tabindex="-1" role="dialog" aria-labelledby="orderStatusModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="order-change-status.php" id="orderStatusForm">
                <?php $csrf->echoInputField(); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="orderStatusModalLabel">تحديث حالة الطلب</h4>
                </div>
                <div class="modal-body">
                    <div class="modal-order">
                        <strong id="modalOrderLabel">الطلب</strong>
                        <span id="modalOrderMeta">سيظهر هنا ملخص سريع عن الطلب المحدد.</span>
                    </div>
                    <input type="hidden" name="id" id="modalOrderId" value="">
                    <input type="hidden" name="redirect" value="order.php#tab_orders_pending" class="js-order-page-redirect">
                    <div class="form-group">
                        <label>الحالة الحالية</label>
                        <input type="text" id="modalCurrentStatus" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="modalTargetStatus">الحالة الجديدة</label>
                        <select name="status" id="modalTargetStatus" class="form-control" required>
                            <option value="">اختر الحالة الجديدة</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modalStatusNote">ملاحظة القرار</label>
                        <textarea name="status_note" id="modalStatusNote" class="form-control" rows="4" placeholder="أضف سبب التأكيد أو الإلغاء أو الإرجاع لتبقى المتابعة أوضح لاحقاً."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitButton">حفظ التغيير</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    if (typeof jQuery === 'undefined') {
        return;
    }

    (function($) {
        var language = {
            decimal: "", emptyTable: "لا توجد بيانات متاحة في الجدول", info: "عرض _START_ إلى _END_ من أصل _TOTAL_ سجل",
            infoEmpty: "عرض 0 إلى 0 من أصل 0 سجل", infoFiltered: "(تمت التصفية من أصل _MAX_ سجل)", infoPostFix: "",
            thousands: ",", lengthMenu: "عرض _MENU_ سجل", loadingRecords: "جارٍ التحميل...", processing: "جارٍ المعالجة...",
            search: "بحث:", zeroRecords: "لم يتم العثور على نتائج مطابقة",
            paginate: { first: "الأول", last: "الأخير", next: "التالي", previous: "السابق" },
            aria: { sortAscending: ": تفعيل الترتيب التصاعدي", sortDescending: ": تفعيل الترتيب التنازلي" }
        };

        if ($.fn.DataTable) {
            $('.orders-table').each(function() {
                if (!$.fn.dataTable.isDataTable(this)) {
                    $(this).DataTable({
                        order: [[1, 'desc']],
                        pageLength: 10,
                        autoWidth: false,
                        language: language,
                        columnDefs: [{ orderable: false, targets: [0, 7] }]
                    });
                }
            });
        }

        function getOrderTabHash() {            var hash = window.location.hash || '#tab_orders_pending';
            if (!$('#orderStatusTabs a[href="' + hash + '"]').length) {
                hash = '#tab_orders_pending';
            }
            return hash;
        }

        function applyOrderPageRedirects(hash) { global $dbRepo;
    global $dbRepo;

            var targetHash = hash || getOrderTabHash();
            $('.js-order-page-redirect').val('order.php' + targetHash);
            $('.bulk-redirect-input').val('order.php' + targetHash);
        }

        function adjustVisibleOrderTables() {            if (!$.fn.DataTable) {
                return;
            }

            $('.tab-pane.active .orders-table').each(function() {
                if ($.fn.dataTable.isDataTable(this)) {
                    $(this).DataTable().columns.adjust().draw(false);
                }
            });
        }

        var initialOrderTabHash = getOrderTabHash();
        if ($('#orderStatusTabs a[href="' + initialOrderTabHash + '"]').length) {
            $('#orderStatusTabs a[href="' + initialOrderTabHash + '"]').tab('show');
        }
        applyOrderPageRedirects(initialOrderTabHash);
        setTimeout(adjustVisibleOrderTables, 0);

        $('#orderStatusTabs a[data-toggle="tab"]').on('shown.bs.tab', function() {
            var href = $(this).attr('href') || '';
            if (href === '' || !$('#orderStatusTabs a[href="' + href + '"]').length) {
                return;
            }

            if (window.history && typeof window.history.replaceState === 'function') {
                window.history.replaceState(null, '', window.location.pathname + href);
            } else {
                window.location.hash = href;
            }

            applyOrderPageRedirects(href);
            setTimeout(adjustVisibleOrderTables, 50);
        });

        $(document).on('click', '.js-order-tab-shortcut', function(event) {
            var href = $(this).attr('href') || '';
            var $targetTab = $('#orderStatusTabs a[href="' + href + '"]');
            if (!$targetTab.length) {
                return;
            }

            event.preventDefault();
            $targetTab.tab('show');
        });

        function syncAll(group) { global $dbRepo;
    global $dbRepo;

            var $boxes = $('.js-order-checkbox[data-group="' + group + '"]');
            $('.js-select-all[data-group="' + group + '"]').prop('checked', $boxes.length > 0 && $boxes.length === $boxes.filter(':checked').length);
        }

        $(document).on('change', '.js-select-all', function() {
            var group = $(this).data('group');
            $('.js-order-checkbox[data-group="' + group + '"]').prop('checked', this.checked);
        });

        $(document).on('change', '.js-order-checkbox', function() {
            syncAll($(this).data('group'));
        });

        $(document).on('click', '.js-bulk-delete', function() {
            $(this).closest('.bulk-form').data('submitAction', 'delete');
        });

        $(document).on('submit', '.bulk-form', function(event) {
            var $form = $(this);
            var action = $.trim($form.data('submitAction') || $form.find('.bulk-action-select').val());
            $form.removeData('submitAction');
            if (action === '') {
                alert('اختر الإجراء الجماعي أولاً.');
                event.preventDefault();
                return;
            }
            var $checked = $form.find('.js-order-checkbox:checked');
            if ($checked.length === 0) {
                alert('حدد طلباً واحداً على الأقل قبل تنفيذ الإجراء الجماعي.');
                event.preventDefault();
                return;
            }
            if (action === 'delete' && !confirm('هل أنت متأكد من حذف الطلبات المحددة نهائياً؟')) {
                event.preventDefault();
                return;
            }
            if (action === 'ecotrack_print_4up') {
                var params = $.param($checked.map(function() {
                    return { name: 'order_ids[]', value: $(this).val() };
                }).get());
                var printUrl = 'order-ecotrack-bulk-label.php' + (params ? ('?' + params) : '');
                event.preventDefault();
                var printWindow = window.open(printUrl, '_blank');
                if (!printWindow) {
                    window.location.href = printUrl;
                }
            }
        });

        var statusLabels = <?php echo json_encode($status_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var statusButtonMeta = <?php echo json_encode($status_button_meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        function refreshModalButton() {            var selected = $('#modalTargetStatus').val();
            var meta = statusButtonMeta[selected] || { text: 'حفظ التغيير', class: 'btn btn-primary', placeholder: 'أضف ملاحظة توضح سبب تغيير الحالة.' };
            $('#modalSubmitButton').removeClass('btn-primary btn-success btn-warning btn-danger btn-default').addClass(meta.class).text(meta.text);
            $('#modalStatusNote').attr('placeholder', meta.placeholder);
            $('#modalSubmitButton').prop('disabled', selected === '');
        }

        $(document).on('click', '.js-open-modal', function() {
            var $btn = $(this);
            var allowed = parseAllowed($btn.attr('data-allowed'));
            var preferred = $btn.attr('data-preferred') || '';
            var suggestedNote = $btn.attr('data-suggested-note') || '';
            var $select = $('#modalTargetStatus');

            $('#modalOrderId').val($btn.attr('data-order-id'));
            $('#modalOrderLabel').text($btn.attr('data-order-label'));
            $('#modalOrderMeta').text($btn.attr('data-order-product'));
            $('#modalCurrentStatus').val($btn.attr('data-current-status-label'));
            $('#modalStatusNote').val(suggestedNote);

            $select.empty().append('<option value="">اختر الحالة الجديدة</option>');
            $.each(allowed, function(_, code) {
                $select.append($('<option>', { value: code, text: statusLabels[code] || code }));
            });

            if (preferred && allowed.indexOf(preferred) !== -1) {
                $select.val(preferred);
            } else if (allowed.length === 1) {
                $select.val(allowed[0]);
            }

            refreshModalButton();
            $('#orderStatusModal').modal('show');
        });

        $('#modalTargetStatus').on('change', refreshModalButton);

        $('#orderStatusForm').on('submit', function(e) {
            e.preventDefault();
            var $form = $(this);
            var $btn = $('#modalSubmitButton');
            
            $btn.prop('disabled', true).text('جارٍ الحفظ...');
            
            $.ajax({
                url: $form.attr('action') || 'order-change-status.php',
                type: 'POST',
                data: $form.serialize() + '&ajax=1',
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        $('#orderStatusModal').modal('hide');
                        if (typeof showToast !== 'undefined') {
                            showToast(response.message || 'تم تحديث الحالة بنجاح', 'success', 3000);
                        } else {
                            alert(response.message || 'تم تحديث الحالة بنجاح');
                        }
                        
                        // Dynamically move the row or reload the data table
                        var orderId = $('#modalOrderId').val();
                        var $btnRow = $('.js-open-modal[data-order-id="' + orderId + '"]');
                        if ($btnRow.length) {
                            var $tr = $btnRow.closest('tr');
                            var table = $tr.closest('table').DataTable();
                            if (table && table.row) {
                                table.row($tr).remove().draw(false);
                            } else {
                                $tr.fadeOut(400, function() { $(this).remove(); });
                            }
                        }
                    } else {
                        alert(response && response.message ? response.message : 'حدث خطأ غير متوقع');
                    }
                },
                error: function() {
                    alert('تعذر الاتصال بالخادم. يرجى المحاولة مرة أخرى.');
                },
                complete: function() {
                    refreshModalButton();
                }
            });
        });
    })(jQuery);
});
</script>


<style>
/* CSS Reset and Improvements for Table */
.orders-table th { background: #f8fafc; color: #475569; font-size: 13px; font-weight: 700; border-bottom: 2px solid #e2e8f0 !important; white-space: nowrap; }
.orders-table td { vertical-align: middle !important; border-bottom: 1px solid #f1f5f9; padding: 10px 8px !important; }
.orders-table tr:hover { background: #f8fafc !important; }

/* Global Status Colors */
.badge-status { padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; display: inline-block; }
.badge-status.status-pending { background-color: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
.badge-status.status-completed { background-color: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
.badge-status.status-returned { background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
.badge-status.status-cancelled { background-color: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

/* Sticky Columns for Horizontal Scrolling (RTL) */
.table-responsive { overflow-x: auto; width: 100%; position: relative; }
.col-checkbox { position: sticky !important; right: 0; z-index: 2; background: inherit; }
.col-order { position: sticky !important; right: 30px; z-index: 2; background: inherit; }
.col-actions { position: sticky !important; left: 30px; z-index: 2; background: inherit; }
.col-expand { position: sticky !important; left: 0; z-index: 2; background: inherit; }
.orders-table thead th.col-checkbox, .orders-table thead th.col-order { background: #f8fafc; z-index: 3; border-left: 1px solid #e2e8f0; }
.orders-table thead th.col-actions, .orders-table thead th.col-expand { background: #f8fafc; z-index: 3; border-right: 1px solid #e2e8f0; }
.orders-table tbody td.col-checkbox, .orders-table tbody td.col-order { border-left: 1px solid #e2e8f0; }
.orders-table tbody td.col-actions, .orders-table tbody td.col-expand { border-right: 1px solid #e2e8f0; }
.orders-table tbody tr { background: #fff; } /* Ensures sticky cols cover scrolling data */

/* DataTables Resets */
.dataTables_filter { display: none; } /* Hide default search */
.dataTables_length { margin-bottom: 10px; }
table.dataTable { margin-top: 0 !important; margin-bottom: 0 !important; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dtInstances = {};
    
    // Initialize DataTables
    $('.orders-table').each(function() {
        var id = $(this).attr('id');
        dtInstances[id] = $(this).DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Arabic.json"
            },
            "paging": true,
            "lengthMenu": [[50, 100, 250, 500, -1], [50, 100, 250, 500, "الكل"]],
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "colReorder": true // requires DataTables ColReorder extension
        });
    });

    // Global Search Filter
    $('#global_search').on('keyup', function() {
        var val = $(this).val();
        for(var id in dtInstances) {
            dtInstances[id].search(val).draw();
        }
    });

    // Custom Filters
    $('#filter_status').on('change', function() {
        var val = $(this).find('option:selected').text();
        if($(this).val() === '') val = '';
        for(var id in dtInstances) {
            dtInstances[id].column(7).search(val).draw();
        }
    });
    $('#filter_company').on('change', function() {
        var val = $(this).val();
        for(var id in dtInstances) {
            dtInstances[id].column(5).search(val).draw(); // column 5 includes company text
        }
    });
    $('#filter_employee').on('change', function() {
        var val = $(this).val();
        for(var id in dtInstances) {
            dtInstances[id].column(6).search(val ? '^'+val+'$' : '', true, false).draw();
        }
    });

    // Expand Row Feature (Lazy Loading)
    $('.orders-table tbody').on('click', '.btn-expand-row', function() {
        var btn = $(this);
        var tr = btn.closest('tr');
        var tableId = tr.closest('table').attr('id');
        var dt = dtInstances[tableId];
        var row = dt.row(tr);
        var orderId = tr.data('order-id');

        if (row.child.isShown()) {
            row.child.hide();
            tr.removeClass('shown');
            btn.html('<i class="fa fa-chevron-down"></i>');
        } else {
            btn.html('<i class="fa fa-spinner fa-spin"></i>');
            $.ajax({
                url: 'ajax-order-details.php',
                type: 'GET',
                data: { id: orderId },
                success: function(res) {
                    row.child(res).show();
                    tr.addClass('shown');
                    btn.html('<i class="fa fa-chevron-up"></i>');
                },
                error: function() {
                    btn.html('<i class="fa fa-chevron-down text-danger"></i>');
                }
            });
        }
    });
});
</script>

<?php require_once('footer.php'); ?>

