<?php
require_once('header.php');

admin_ensure_order_call_log_table($pdo);
admin_ensure_order_status_log_table($pdo);
admin_ensure_sms_template_table($pdo);
admin_ensure_ecotrack_setting_columns($pdo);
admin_ensure_order_ecotrack_columns($pdo);

$sms_settings = front_get_settings($pdo);
$ecotrack_settings = ecotrack_normalize_settings($sms_settings);
$ecotrack_ready = ecotrack_is_configured($ecotrack_settings);
$sms_gateway_ready = sms_gateway_is_configured($sms_settings);
$sms_templates = admin_get_sms_templates($pdo, true);
$sms_template_payload = [];
foreach ($sms_templates as $sms_template) {
    $sms_template_payload[] = [
        'id' => (int) $sms_template['id'],
        'name' => (string) ($sms_template['template_name'] ?? ''),
        'body' => (string) ($sms_template['template_body'] ?? '')
    ];
}
$sms_site_name = trim((string) ($sms_settings['meta_title_home'] ?? ''));
if ($sms_site_name === '') {
    $sms_site_name = 'BoomStore';
}
$admin_auto_refresh = admin_build_live_refresh_config($pdo, 'orders', ['interval_ms' => 15000]);

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
                return ['Completed' => 'تأكيد المحدد', 'Cancelled' => 'إلغاء المحدد', 'delete' => 'حذف المحدد'];
            case 'Completed':
                return ['Returned' => 'تحويل المحدد إلى مرتجع', 'Cancelled' => 'إلغاء المحدد', 'Pending' => 'إعادة المحدد للمراجعة', 'delete' => 'حذف المحدد'];
            case 'Returned':
                return ['Completed' => 'إعادة المحدد إلى مؤكد', 'Cancelled' => 'إلغاء المحدد', 'Pending' => 'إعادة المحدد للمراجعة', 'delete' => 'حذف المحدد'];
            case 'Cancelled':
                return ['Pending' => 'إعادة المحدد للمراجعة', 'Completed' => 'اعتماد المحدد كمؤكد', 'delete' => 'حذف المحدد'];
            case 'Confirmed':
                return ['Completed' => 'نقل المحدد إلى مؤكد', 'Returned' => 'تحويل المحدد إلى مرتجع', 'Cancelled' => 'إلغاء المحدد', 'Pending' => 'إعادة المحدد للمراجعة', 'delete' => 'حذف المحدد'];
            default:
                return ['Pending' => 'إعادة المحدد للمراجعة', 'Completed' => 'اعتماد المحدد كمؤكد', 'Cancelled' => 'إلغاء المحدد', 'delete' => 'حذف المحدد'];
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

$statement = $dbRepo->prepare("SELECT o.*, c.color_name AS resolved_color_name, COALESCE(cs.call_count, 0) AS call_count, COALESCE(cs.no_answer_count, 0) AS no_answer_count, cl.called_at AS last_call_at, cl.call_status AS last_call_status, sl.status_note AS last_status_note, sl.changed_at AS last_status_changed_at, sl.changed_by AS last_status_changed_by FROM tbl_order o LEFT JOIN tbl_color c ON c.color_id = o.order_color LEFT JOIN (SELECT order_id, COUNT(*) AS call_count, SUM(CASE WHEN call_status = 'no_answer' THEN 1 ELSE 0 END) AS no_answer_count, MAX(id) AS last_call_id FROM tbl_order_call_log GROUP BY order_id) cs ON cs.order_id = o.id LEFT JOIN tbl_order_call_log cl ON cl.id = cs.last_call_id LEFT JOIN (SELECT logs.order_id, logs.status_note, logs.changed_at, logs.changed_by FROM tbl_order_status_log logs INNER JOIN (SELECT order_id, MAX(id) AS last_id FROM tbl_order_status_log GROUP BY order_id) last_log ON last_log.last_id = logs.id) sl ON sl.order_id = o.id ORDER BY o.order_date DESC, o.id DESC");
$statement->execute();
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
.orders-page .bulk-sms-summary{margin-bottom:14px;padding:12px 14px;border:1px solid var(--line);border-radius:14px;background:#f8fbfe;color:var(--ink);line-height:1.8}
.orders-page .bulk-sms-preview{max-height:190px;overflow:auto;border:1px solid var(--line);border-radius:12px;background:#fff;padding:12px}
.orders-page .bulk-sms-preview ul{margin:0;padding:0;list-style:none}
.orders-page .bulk-sms-preview li{padding:8px 0;border-bottom:1px solid #edf1f5}
.orders-page .bulk-sms-preview li:last-child{border-bottom:none}
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
    <?php if (!$sms_gateway_ready): ?>
                <div class="flash warning">SMS Gateway غير مهيأ بعد. فعّل الإعدادات وأضف القوالب من صفحة <a href="settings.php#tab_sms">Parametres du site</a>.</div>
    <?php endif; ?>

    <div class="hero">
        <h2>لوحة متابعة الطلبات</h2>
        <p>التأكيد لم يعد مجرد زر واحد. الآن تستطيع رؤية الطلبات الجاهزة للحسم، الطلبات التي تحتاج متابعة، ثم اختيار الإجراء المناسب لكل طلب أو على مجموعة كاملة.</p>
        <div class="hero-actions">
            <a href="#tab_orders_pending" class="btn btn-primary js-order-tab-shortcut"><i class="fa fa-clock-o"></i> افتح الطلبات التي تحتاج تأكيد</a>
            <a href="order-statistics.php" class="btn btn-default"><i class="fa fa-bar-chart"></i> الإحصائيات</a>
            <a href="incomplete-orders.php" class="btn btn-default"><i class="fa fa-exclamation-triangle"></i> الطلبات غير المكتملة</a>
            <a href="ecotrack-diagnostics.php" class="btn btn-default"><i class="fa fa-stethoscope"></i> فحوصات ECOTRACK GET</a>
                        <a href="settings.php#tab_sms" class="btn btn-default"><i class="fa fa-commenting"></i> إعداد رسائل SMS</a>
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
                                <?php if ($sms_gateway_ready): ?>
                                    <button type="button" class="btn btn-success js-open-bulk-sms"><i class="fa fa-commenting"></i> SMS للمحدد</button>
                                <?php endif; ?>
                            </div>
                            <div class="bulkbar-note">
                                يتم التحقق من صحة انتقال الحالة لكل طلب. الطلب غير المطابق سيتم تجاهله مع إشعار واضح بعد التنفيذ.
                                <?php if ($ecotrack_ready): ?> ويمكنك أيضًا إرسال عدة طلبات إلى ECOTRACK أو حذفها منه أو فتح طباعة جماعية 4 بوردرو في ورقة A4.<?php endif; ?>
                                <?php echo $sms_gateway_ready ? ' ويمكنك أيضاً إرسال رسالة جماعية لنفس الطلبات المحددة.' : ''; ?>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover orders-table" id="table-<?php echo htmlspecialchars(strtolower($status_code), ENT_QUOTES, 'UTF-8'); ?>">
                                <thead>
                                    <tr>
                                        <th style="width:46px"><input type="checkbox" class="js-select-all" data-group="<?php echo htmlspecialchars($status_code, ENT_QUOTES, 'UTF-8'); ?>"></th>
                                        <th>الطلب</th>
                                        <th>العميل</th>
                                        <th>المتابعة</th>
                                        <th>التوصيل</th>
                                        <th>الحالة</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($section_orders as $order): ?>
                                        <?php
                                        $order_id = (int) $order['id'];
                                        $status_meta = admin_get_order_status_meta($order['normalized_status']);
                                        $delivery_meta = admin_order_delivery_label($order['delivery_type'] ?? '');
                                        $customer_meta = admin_order_customer_type_label($order['customer_type'] ?? '');
                                        $allowed_transitions = admin_get_order_allowed_transitions($order['normalized_status']);
                                        $allowed_transitions_json = htmlspecialchars(json_encode(array_values($allowed_transitions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                        $call_count = (int) ($order['call_count'] ?? 0);
                                        $last_call_meta = admin_get_order_call_status_meta($order['last_call_status'] ?? '');
                                        $followup_meta = admin_order_followup_meta($order);
                                        $primary_action = admin_order_primary_action($order['normalized_status'], $order);
                                        $ecotrack_tracking = trim((string) ($order['ecotrack_tracking'] ?? ''));
                                        $ecotrack_remote_status = trim((string) ($order['ecotrack_remote_status'] ?? ''));
                                        $color_name = trim((string) ($order['resolved_color_name'] ?? ''));
                                        if ($color_name === '' && !empty($order['order_color']) && !is_numeric((string) $order['order_color'])) {
                                            $color_name = trim((string) $order['order_color']);
                                        }
                                        ?>
                                        <tr>
                                            <td><input type="checkbox" name="order_ids[]" value="<?php echo $order_id; ?>" class="js-order-checkbox" data-group="<?php echo htmlspecialchars($status_code, ENT_QUOTES, 'UTF-8'); ?>" data-order-id="<?php echo $order_id; ?>" data-order-label="#<?php echo $order_id; ?> - <?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-phone="<?php echo htmlspecialchars((string) ($order['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-customer="<?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-product="<?php echo htmlspecialchars((string) ($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-quantity="<?php echo htmlspecialchars((string) ($order['quantity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-total="<?php echo htmlspecialchars(sms_gateway_format_money($order['total_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-status="<?php echo htmlspecialchars((string) ($order['normalized_status'] ?? $status_code), ENT_QUOTES, 'UTF-8'); ?>" data-order-wilaya="<?php echo htmlspecialchars((string) ($order['wilaya'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-commune="<?php echo htmlspecialchars((string) ($order['commune'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-address="<?php echo htmlspecialchars((string) ($order['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></td>
                                            <td data-order="<?php echo (int) strtotime((string) ($order['order_date'] ?? 'now')); ?>">
                                                <div class="order-main">
                                                    <strong><?php echo htmlspecialchars((string) ($order['product_name'] ?? 'منتج غير محدد'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <div class="order-meta">
                                                        <span class="tag">#<?php echo $order_id; ?></span>
                                                        <span><?php echo date('d/m/Y H:i', strtotime((string) $order['order_date'])); ?></span>
                                                        <span><?php echo (int) ($order['quantity'] ?? 0); ?> × <?php echo htmlspecialchars(admin_format_order_amount($order['unit_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <span>الإجمالي: <?php echo htmlspecialchars(admin_format_order_amount($order['total_price'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                    <div class="order-sub">
                                                        <?php if (!empty($order['order_size'])): ?><span class="tag">المقاس: <?php echo htmlspecialchars((string) $order['order_size'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                                        <?php if ($color_name !== ''): ?><span class="tag">اللون: <?php echo htmlspecialchars($color_name, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                                        <?php if ($ecotrack_tracking !== ''): ?><span class="tag">ECOTRACK: <?php echo htmlspecialchars($ecotrack_tracking, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                                        <?php if ($ecotrack_remote_status !== ''): ?><span class="tag">الحالة البعيدة: <?php echo htmlspecialchars($ecotrack_remote_status, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="order-main">
                                                    <strong><?php echo htmlspecialchars((string) ($order['customer_name'] ?? 'عميل غير محدد'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <div class="order-meta"><span class="pill <?php echo htmlspecialchars($customer_meta['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($customer_meta['label'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                                                    <div class="order-sub">
                                                        <?php if (!empty($order['customer_phone'])): ?>
                                                            <span><i class="fa fa-phone"></i> <a href="tel:<?php echo htmlspecialchars((string) $order['customer_phone'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $order['customer_phone'], ENT_QUOTES, 'UTF-8'); ?></a></span>
                                                        <?php else: ?>
                                                            <span>رقم الهاتف غير متوفر</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pill <?php echo htmlspecialchars($followup_meta['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($followup_meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <div class="callbox">
                                                    <?php if ($call_count > 0): ?>
                                                        <small>المحاولات: <?php echo $call_count; ?><?php if ((int) ($order['no_answer_count'] ?? 0) > 0): ?> | لم يرد: <?php echo (int) $order['no_answer_count']; ?><?php endif; ?></small>
                                                        <small>آخر اتصال: <?php echo !empty($order['last_call_at']) ? date('d/m/Y H:i', strtotime((string) $order['last_call_at'])) : 'غير مسجل'; ?></small>
                                                        <small><span class="<?php echo htmlspecialchars($last_call_meta['badge_class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($last_call_meta['label'], ENT_QUOTES, 'UTF-8'); ?></span></small>
                                                    <?php else: ?>
                                                        <small>لا توجد أي محاولة اتصال مسجلة لهذا الطلب.</small>
                                                    <?php endif; ?>
                                                    <small class="followup-hint"><?php echo htmlspecialchars($followup_meta['hint'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <small><a href="order-details.php?id=<?php echo $order_id; ?>" class="callbox-link"><i class="fa fa-phone"></i> راجع سجل الاتصالات</a></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="pill <?php echo htmlspecialchars($delivery_meta['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($delivery_meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <div class="order-sub">
                                                    <span><?php echo htmlspecialchars(trim((string) ($order['wilaya'] ?? '') . ' - ' . (string) ($order['commune'] ?? ''), ' -'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span><?php echo htmlspecialchars(admin_order_excerpt((string) ($order['address'] ?? 'لا يوجد عنوان تفصيلي')), ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="statusbox">
                                                    <span class="<?php echo htmlspecialchars($status_meta['badge_class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <div class="status-note"><?php echo htmlspecialchars(admin_order_excerpt((string) ($order['last_status_note'] ?? 'لا توجد ملاحظة حالة مسجلة حتى الآن.')), ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <small>
                                                        <?php if (!empty($order['last_status_changed_at'])): ?>
                                                            آخر تحديث: <?php echo date('d/m/Y H:i', strtotime((string) $order['last_status_changed_at'])); ?>
                                                            <?php if (!empty($order['last_status_changed_by'])): ?> بواسطة <?php echo htmlspecialchars((string) $order['last_status_changed_by'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                                        <?php else: ?>
                                                            لا يوجد سجل حالة سابق.
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="row-actions">
                                                    <a href="order-details.php?id=<?php echo $order_id; ?>" class="btn btn-info btn-xs"><i class="fa fa-address-card-o"></i> التفاصيل والتعديل</a>
                                                    <?php if ($ecotrack_tracking !== ''): ?>
                                                        <a href="order-ecotrack-label.php?order_id=<?php echo $order_id; ?>&inline=1" class="btn btn-default btn-xs" target="_blank" rel="noopener"><i class="fa fa-print"></i> طباعة الليبل</a>
                                                    <?php endif; ?>
                                                    <?php if ($sms_gateway_ready && !empty($order['customer_phone'])): ?>
                                                        <button
                                                            type="button"
                                                            class="btn btn-default btn-xs js-open-sms-modal"
                                                            data-order-id="<?php echo $order_id; ?>"
                                                            data-order-label="#<?php echo $order_id; ?> - <?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-phone="<?php echo htmlspecialchars((string) ($order['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-customer="<?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-product="<?php echo htmlspecialchars((string) ($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-quantity="<?php echo htmlspecialchars((string) ($order['quantity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-total="<?php echo htmlspecialchars(sms_gateway_format_money($order['total_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-status="<?php echo htmlspecialchars((string) ($order['normalized_status'] ?? $status_code), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-wilaya="<?php echo htmlspecialchars((string) ($order['wilaya'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-commune="<?php echo htmlspecialchars((string) ($order['commune'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-order-address="<?php echo htmlspecialchars((string) ($order['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                        >
                                                            <i class="fa fa-commenting"></i> SMS
                                                        </button>
                                                    <?php elseif (!$sms_gateway_ready): ?>
                            <a href="settings.php#tab_sms" class="btn btn-default btn-xs"><i class="fa fa-cog"></i> ضبط SMS</a>
                                                    <?php endif; ?>
                                                    <?php if (($primary_action['type'] ?? 'modal') === 'link'): ?>
                                                        <a href="<?php echo htmlspecialchars($primary_action['href'], ENT_QUOTES, 'UTF-8'); ?>" class="btn <?php echo htmlspecialchars($primary_action['class'], ENT_QUOTES, 'UTF-8'); ?> btn-xs"><i class="fa <?php echo htmlspecialchars($primary_action['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($primary_action['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                                                    <?php else: ?>
                                                        <button type="button" class="btn <?php echo htmlspecialchars($primary_action['class'], ENT_QUOTES, 'UTF-8'); ?> btn-xs js-open-modal" data-order-id="<?php echo $order_id; ?>" data-order-label="#<?php echo $order_id; ?> - <?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-product="<?php echo htmlspecialchars((string) ($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-current-status-label="<?php echo htmlspecialchars($status_meta['label'], ENT_QUOTES, 'UTF-8'); ?>" data-allowed="<?php echo $allowed_transitions_json; ?>" data-preferred="<?php echo htmlspecialchars($primary_action['status'], ENT_QUOTES, 'UTF-8'); ?>" data-suggested-note="<?php echo htmlspecialchars($followup_meta['suggested_note'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa <?php echo htmlspecialchars($primary_action['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($primary_action['label'], ENT_QUOTES, 'UTF-8'); ?></button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-default btn-xs js-open-modal" data-order-id="<?php echo $order_id; ?>" data-order-label="#<?php echo $order_id; ?> - <?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-order-product="<?php echo htmlspecialchars((string) ($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-current-status-label="<?php echo htmlspecialchars($status_meta['label'], ENT_QUOTES, 'UTF-8'); ?>" data-allowed="<?php echo $allowed_transitions_json; ?>" data-preferred="<?php echo htmlspecialchars($followup_meta['preferred_status'], ENT_QUOTES, 'UTF-8'); ?>" data-suggested-note="<?php echo htmlspecialchars($followup_meta['suggested_note'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa fa-sliders"></i> خيارات الحالة</button>
                                                    <a href="order-delete.php?id=<?php echo $order_id; ?>" class="btn btn-danger btn-xs" onclick="return confirm('هل أنت متأكد من حذف هذا الطلب نهائياً؟');"><i class="fa fa-trash"></i> حذف</a>
                                                </div>
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

<div class="modal fade orders-page" id="orderSmsModal" tabindex="-1" role="dialog" aria-labelledby="orderSmsModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="send-order-sms.php" id="orderSmsForm">
                <?php $csrf->echoInputField(); ?>
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="orderSmsModalLabel">إرسال رسالة SMS</h4>
                </div>
                <div class="modal-body">
                    <div class="modal-order">
                        <strong id="smsModalOrderLabel">الطلب</strong>
                        <span id="smsModalOrderMeta">سيظهر هنا رقم الهاتف والرسالة المحددة.</span>
                    </div>
                    <input type="hidden" name="order_id" id="smsModalOrderId" value="">
                    <input type="hidden" name="redirect" value="order.php#tab_orders_pending" class="js-order-page-redirect">
                    <div class="form-group">
                        <label>رقم الهاتف</label>
                        <input type="text" name="phone" id="smsModalPhone" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="smsModalTemplate">الرسالة الثابتة</label>
                        <select id="smsModalTemplate" class="form-control">
                            <option value="">اختر رسالة ثابتة أو اكتب رسالة مخصصة</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="smsModalMessage">نص الرسالة</label>
                        <textarea name="message" id="smsModalMessage" class="form-control" rows="6" placeholder="اكتب الرسالة بشكل عادي أو اختر رسالة ثابتة ثم عدّلها كما تريد." required></textarea>
                    </div>
                    <p class="text-muted" style="margin:0;">اكتب الرسالة بشكل عادي، وسيتم تجهيز طلب الإرسال في الخلفية عند الإرسال.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-success" id="smsModalSubmitButton"><i class="fa fa-paper-plane"></i> إرسال الآن</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade orders-page" id="bulkOrderSmsModal" tabindex="-1" role="dialog" aria-labelledby="bulkOrderSmsModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" action="send-bulk-sms.php" id="bulkOrderSmsForm">
                <?php $csrf->echoInputField(); ?>
                <input type="hidden" name="bulk_mode" value="1">
                <input type="hidden" name="redirect" value="order.php#tab_orders_pending" class="js-order-page-redirect">
                <input type="hidden" name="bulk_items" id="bulkSmsItems" value="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="bulkOrderSmsModalLabel">إرسال SMS جماعي</h4>
                </div>
                <div class="modal-body">
                    <div class="bulk-sms-summary">
                        <strong>عدد الطلبات المحددة: <span id="bulkSmsCount">0</span></strong>
                        <div id="bulkSmsHint">اختر الطلبات أولاً ثم افتح نافذة الإرسال الجماعي.</div>
                    </div>
                    <div class="form-group">
                        <label>المحددون</label>
                        <div class="bulk-sms-preview">
                            <ul id="bulkSmsRecipientList"><li>لا توجد عناصر محددة بعد.</li></ul>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="bulkSmsTemplate">الرسالة الثابتة</label>
                        <select id="bulkSmsTemplate" class="form-control">
                            <option value="">اختر رسالة ثابتة أو اكتب رسالة جماعية مخصصة</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bulkSmsMessage">نص الرسالة</label>
                        <textarea name="message" id="bulkSmsMessage" class="form-control" rows="6" placeholder="اكتب الرسالة التي تريد إرسالها للمحددّين." required></textarea>
                    </div>
                    <p class="text-muted" style="margin:0;">اكتب رسالة عادية، وسيتم إرسالها للمحددّين في الخلفية.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">إغلاق</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-paper-plane"></i> إرسال للمحدد</button>
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
                        columnDefs: [{ orderable: false, targets: [0, 6] }]
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

        $(document).on('submit', '.bulk-form', function(event) {
            var $form = $(this);
            var action = $.trim($form.find('.bulk-action-select').val());
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
        var smsTemplates = <?php echo json_encode($sms_template_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var smsSiteName = <?php echo json_encode($sms_site_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var $modal = $('#orderStatusModal');
        var $smsModal = $('#orderSmsModal');
        var $bulkSmsModal = $('#bulkOrderSmsModal');

        if ($modal.length) {
            $modal.appendTo('body');
        }
        if ($smsModal.length) {
            $smsModal.appendTo('body');
        }
        if ($bulkSmsModal.length) {
            $bulkSmsModal.appendTo('body');
        }

        function parseAllowed(value) { global $dbRepo;
    global $dbRepo;

            try {
                return JSON.parse(value || '[]');
            } catch (error) {
                return [];
            }
        }

        function buildSmsContext($source) { global $dbRepo;
    global $dbRepo;

            return {
                order_id: $source.attr('data-order-id') || '',
                customer_name: $source.attr('data-order-customer') || '',
                customer_phone: $source.attr('data-order-phone') || '',
                phone: $source.attr('data-order-phone') || '',
                product_name: $source.attr('data-order-product') || '',
                quantity: $source.attr('data-order-quantity') || '',
                total_price_formatted: $source.attr('data-order-total') || '',
                total_price: $source.attr('data-order-total') || '',
                status: $source.attr('data-order-status') || '',
                wilaya: $source.attr('data-order-wilaya') || '',
                commune: $source.attr('data-order-commune') || '',
                address: $source.attr('data-order-address') || '',
                phone_local: $source.attr('data-order-phone') || '',
                phone_e164: $source.attr('data-order-phone') || '',
                site_name: smsSiteName || 'BoomStore'
            };
        }

        function renderSmsTemplate(template, context) { global $dbRepo;
    global $dbRepo;

            var output = template || '';
            Object.keys(context).forEach(function(key) {
                output = output.split('{{' + key + '}}').join(context[key] || '');
            });
            return output;
        }

        function populateSmsTemplates() {            var $select = $('#smsModalTemplate');
            $select.empty().append('<option value="">اختر رسالة ثابتة أو اكتب رسالة مخصصة</option>');
            smsTemplates.forEach(function(template) {
                $select.append($('<option>', {
                    value: String(template.id || ''),
                    text: template.name || 'Template',
                    'data-body': template.body || ''
                }));
            });
        }

        function populateBulkSmsTemplates() {            var $select = $('#bulkSmsTemplate');
            $select.empty().append('<option value="">اختر رسالة ثابتة أو اكتب رسالة جماعية مخصصة</option>');
            smsTemplates.forEach(function(template) {
                $select.append($('<option>', {
                    value: String(template.id || ''),
                    text: template.name || 'Template',
                    'data-body': template.body || ''
                }));
            });
        }

        function checkboxSmsContext($checkbox) { global $dbRepo;
    global $dbRepo;

            return buildSmsContext({
                attr: function(name) {
                    return $checkbox.attr(name);
                }
            });
        }

        function collectBulkSmsRecipients($trigger) { global $dbRepo;
    global $dbRepo;

            var items = [];
            var $form = $trigger.closest('form');
            $form.find('.js-order-checkbox:checked').each(function() {
                items.push(checkboxSmsContext($(this)));
            });
            return items;
        }

        function bulkSmsPreview(items) { global $dbRepo;
    global $dbRepo;

            if (!items.length) {
                return '<li>لا توجد عناصر صالحة للإرسال.</li>';
            }

            var html = items.slice(0, 8).map(function(item) {
                var name = item.customer_name || ('طلب #' + (item.order_id || ''));
                var phone = item.customer_phone || 'بدون رقم';
                return '<li><strong>' + $('<div>').text(name).html() + '</strong><br><small>' + $('<div>').text(phone + ' | #' + (item.order_id || '')).html() + '</small></li>';
            }).join('');

            if (items.length > 8) {
                html += '<li><small>و' + (items.length - 8) + ' عنصر/عناصر إضافية...</small></li>';
            }

            return html;
        }

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

        populateSmsTemplates();
        populateBulkSmsTemplates();

        $(document).on('click', '.js-open-sms-modal', function() {
            var $btn = $(this);
            var context = buildSmsContext($btn);
            $('#smsModalOrderId').val(context.order_id);
            $('#smsModalOrderLabel').text($btn.attr('data-order-label') || 'الطلب');
            $('#smsModalOrderMeta').text((context.customer_phone || '') + ' | ' + (context.product_name || ''));
            $('#smsModalPhone').val(context.customer_phone || '');
            $('#smsModalTemplate').val('');
            $('#smsModalMessage').val('');
            $('#orderSmsModal').data('templateContext', context).modal('show');
        });

        $('#smsModalTemplate').on('change', function() {
            var selectedId = $(this).val();
            var context = $('#orderSmsModal').data('templateContext') || {};
            var match = smsTemplates.find(function(template) {
                return String(template.id || '') === String(selectedId || '');
            });
            $('#smsModalMessage').val(match ? renderSmsTemplate(match.body || '', context) : '');
        });

        $(document).on('click', '.js-open-bulk-sms', function() {
            var allItems = collectBulkSmsRecipients($(this));
            if (!allItems.length) {
                alert('حدد طلباً واحداً على الأقل قبل فتح الإرسال الجماعي.');
                return;
            }

            var validItems = allItems.filter(function(item) {
                return $.trim(item.customer_phone || '') !== '';
            });

            if (!validItems.length) {
                alert('العناصر المحددة لا تحتوي على أرقام هاتف صالحة للإرسال.');
                return;
            }

            $('#bulkSmsItems').val(JSON.stringify(validItems));
            $('#bulkSmsCount').text(validItems.length);
            $('#bulkSmsHint').text(validItems.length === allItems.length ? 'سيتم إرسال الرسالة نفسها لكل الطلبات المحددة.' : 'تم تجاهل العناصر التي لا تحتوي على رقم هاتف.');
            $('#bulkSmsRecipientList').html(bulkSmsPreview(validItems));
            $('#bulkSmsTemplate').val('');
            $('#bulkSmsMessage').val('');
            $('#bulkOrderSmsModal').modal('show');
        });

        $('#bulkSmsTemplate').on('change', function() {
            var selectedId = $(this).val();
            var match = smsTemplates.find(function(template) {
                return String(template.id || '') === String(selectedId || '');
            });
            $('#bulkSmsMessage').val(match ? (match.body || '') : '');
        });

        $('#bulkOrderSmsForm').on('submit', function(event) {
            if ($.trim($('#bulkSmsItems').val()) === '') {
                alert('لا توجد عناصر محددة للإرسال الجماعي.');
                event.preventDefault();
                return;
            }

            if ($.trim($('#bulkSmsMessage').val()) === '') {
                alert('اكتب الرسالة قبل الإرسال.');
                event.preventDefault();
            }
        });
    })(jQuery);
});
</script>

<?php require_once('footer.php'); ?>
