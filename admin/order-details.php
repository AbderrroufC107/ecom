<?php require_once('header.php'); ?>
<?php
$dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_order_call_log (
    id INT(11) NOT NULL AUTO_INCREMENT,
    order_id INT(11) NOT NULL,
    call_status VARCHAR(50) NOT NULL,
    call_note TEXT,
    called_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX idx_order_id (order_id),
    INDEX idx_called_at (called_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

admin_ensure_sms_template_table($pdo);
admin_ensure_order_ecotrack_columns($pdo);
admin_ensure_ecotrack_setting_columns($pdo);
if (function_exists('admin_ensure_order_zrexpress_columns')) {
    admin_ensure_order_zrexpress_columns($pdo);
}
if (function_exists('admin_ensure_zrexpress_setting_columns')) {
    admin_ensure_zrexpress_setting_columns($pdo);
}
require_once('inc/employee_functions.php');
require_once('inc/audit.php');
audit_ensure_tables($pdo);
employee_ensure_tables($pdo);
$sms_settings = front_get_settings($pdo);
$sms_gateway_ready = sms_gateway_is_configured($sms_settings);
$ecotrack_settings = ecotrack_normalize_settings($sms_settings);
$ecotrack_ready = ecotrack_is_configured($ecotrack_settings);
$zrexpress_settings = function_exists('zrexpress_normalize_settings') ? zrexpress_normalize_settings($sms_settings) : [];
$zrexpress_ready = function_exists('zrexpress_is_configured') ? zrexpress_is_configured($zrexpress_settings) : false;
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
$flash = admin_pull_flash_message('orders');
$call_error_message = '';
$edit_error_message = '';

if (!function_exists('admin_order_details_normalize_delivery_type')) {
    function admin_order_details_normalize_delivery_type($value)
    { global $dbRepo;
    global $dbRepo;

        $value = trim((string) $value);
        $normalized = strtolower($value);

        if ($value === 'منزل' || $normalized === 'home') {
            return 'منزل';
        }
        if ($value === 'مكتب' || $normalized === 'office') {
            return 'مكتب';
        }
        if ($value === 'مجاني' || $normalized === 'free') {
            return 'مجاني';
        }

        return $value;
    }
}

if (!function_exists('admin_order_details_delivery_label')) {
    function admin_order_details_delivery_label($value)
    { global $dbRepo;
    global $dbRepo;

        $value = admin_order_details_normalize_delivery_type($value);
        if ($value === 'منزل') {
            return 'إلى المنزل';
        }
        if ($value === 'مكتب') {
            return 'إلى المكتب';
        }
        if ($value === 'مجاني') {
            return 'توصيل مجاني';
        }
        return $value !== '' ? $value : 'غير محدد';
    }
}

if (!function_exists('admin_order_details_delivery_type_labels_fixed')) {
    function admin_order_details_delivery_type_labels_fixed() {        if (function_exists('admin_delivery_type_labels')) {
            return admin_delivery_type_labels();
        }

        return [
            'home' => "\u{0645}\u{0646}\u{0632}\u{0644}",
            'office' => "\u{0645}\u{0643}\u{062A}\u{0628}",
            'free' => "\u{0645}\u{062C}\u{0627}\u{0646}\u{064A}",
        ];
    }
}

if (!function_exists('admin_order_details_normalize_delivery_type_fixed')) {
    function admin_order_details_normalize_delivery_type_fixed($value)
    { global $dbRepo;
    global $dbRepo;

        $labels = admin_order_details_delivery_type_labels_fixed();
        $value = function_exists('admin_normalize_delivery_type_text')
            ? admin_normalize_delivery_type_text($value)
            : trim((string) $value);
        $normalized = strtolower($value);

        if ($value === $labels['home'] || $normalized === 'home') {
            return $labels['home'];
        }
        if ($value === $labels['office'] || $normalized === 'office') {
            return $labels['office'];
        }
        if ($value === $labels['free'] || $normalized === 'free') {
            return $labels['free'];
        }

        return $value;
    }
}

if (!function_exists('admin_order_details_delivery_label_fixed')) {
    function admin_order_details_delivery_label_fixed($value)
    { global $dbRepo;
    global $dbRepo;

        $labels = admin_order_details_delivery_type_labels_fixed();
        $value = admin_order_details_normalize_delivery_type_fixed($value);

        if ($value === $labels['home']) {
            return "\u{0625}\u{0644}\u{0649} \u{0627}\u{0644}\u{0645}\u{0646}\u{0632}\u{0644}";
        }
        if ($value === $labels['office']) {
            return "\u{0625}\u{0644}\u{0649} \u{0627}\u{0644}\u{0645}\u{0643}\u{062A}\u{0628}";
        }
        if ($value === $labels['free']) {
            return "\u{062A}\u{0648}\u{0635}\u{064A}\u{0644} \u{0645}\u{062C}\u{0627}\u{0646}\u{064A}";
        }

        return $value !== '' ? $value : "\u{063A}\u{064A}\u{0631} \u{0645}\u{062D}\u{062F}\u{062F}";
    }
}

if (!function_exists('admin_order_details_lookup_name')) {
    function admin_order_details_lookup_name(PDO $pdo, $table, $id_column, $name_column, $value)
    { global $dbRepo;
    global $dbRepo;

        $raw = trim((string) $value);
        if ($raw === '') {
            return 'لا يوجد';
        }

        if (ctype_digit($raw)) {
            $statement = $dbRepo->prepare("SELECT {$name_column} FROM {$table} WHERE {$id_column} = ? LIMIT 1");
            $statement->execute([(int) $raw]);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row[$name_column]) && trim((string) $row[$name_column]) !== '') {
                return (string) $row[$name_column];
            }
        }

        return $raw;
    }
}

if (!function_exists('admin_ecotrack_status_label')) {
    function admin_ecotrack_status_label($value)
    { global $dbRepo;
    global $dbRepo;

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        static $exact_map = null;
        static $normalized_map = null;

        if ($exact_map === null) {
            $exact_map = [
                'Prêt à expédier' => 'جاهز للشحن',
                'Prêt à préparer' => 'جاهز للتحضير',
                'En ramassage' => 'قيد الاستلام',
                'Stock en préparation' => 'المخزون قيد التحضير',
                'Vers hub' => 'في الطريق إلى المركز',
                'En hub' => 'داخل المركز',
                'Vers wilaya' => 'في الطريق إلى الولاية',
                'En préparation' => 'قيد التحضير',
                'En livraison' => 'قيد التوصيل',
                'Suspendus' => 'معلّق',
                'Retours chez livreur' => 'مرتجع لدى الموزع',
                'Retours en traitement' => 'المرتجع قيد المعالجة',
                'Retours prêts' => 'المرتجع جاهز',
                'Retours reçu' => 'تم استلام المرتجع',
                'Retours à dispatcher vers stock' => 'المرتجع بانتظار التحويل إلى المخزون',
                'Retours en transit stock' => 'المرتجع في الطريق إلى المخزون',
                'Retours en stock' => 'المرتجع داخل المخزون',
                'Livre non encaissé' => 'تم التسليم دون تحصيل',
                'Livre encaissé non payé' => 'تم التحصيل ولم يُسدَّد بعد',
                'Paiement prêt' => 'الدفع جاهز',
                'Paiement archivé' => 'الدفع مؤرشف',
                'Retours archivé' => 'المرتجع مؤرشف',
                'Return_received' => 'تم استلام الإرجاع',
            ];

            $normalized_map = [
                'order_information_received_by_carrier' => 'تم تسجيل الطلب لدى الناقل',
                'picked' => 'تم استلام الشحنة من طرف الناقل',
                'accepted_by_carrier' => 'تم استقبال الشحنة في مركز الفرز',
                'dispatched_to_driver' => 'تم تحويل الشحنة إلى الموزع',
                'attempt_delivery' => 'محاولة تسليم',
                'return_asked' => 'تم بدء الإرجاع',
                'return_in_transit' => 'الإرجاع في الطريق',
                'livred' => 'تم التسليم',
                'encaissed' => 'تم التحصيل',
                'payed' => 'تم الدفع',
                'prete_a_expedier' => 'جاهز للشحن',
                'prete_a_preparer' => 'جاهز للتحضير',
                'en_ramassage' => 'قيد الاستلام',
                'stock_en_preparation' => 'المخزون قيد التحضير',
                'vers_hub' => 'في الطريق إلى المركز',
                'en_hub' => 'داخل المركز',
                'vers_wilaya' => 'في الطريق إلى الولاية',
                'en_preparation' => 'قيد التحضير',
                'en_livraison' => 'قيد التوصيل',
                'suspendus' => 'معلّق',
                'retours_chez_livreur' => 'مرتجع لدى الموزع',
                'retours_en_traitement' => 'المرتجع قيد المعالجة',
                'retours_prets' => 'المرتجع جاهز',
                'retours_recu' => 'تم استلام المرتجع',
                'retours_a_dispatcher_vers_stock' => 'المرتجع بانتظار التحويل إلى المخزون',
                'retours_en_transit_stock' => 'المرتجع في الطريق إلى المخزون',
                'retours_en_stock' => 'المرتجع داخل المخزون',
                'livre_non_encaisse' => 'تم التسليم دون تحصيل',
                'livre_encaisse_non_paye' => 'تم التحصيل ولم يُسدَّد بعد',
                'paiement_pret' => 'الدفع جاهز',
                'paiement_archive' => 'الدفع مؤرشف',
                'retours_archive' => 'المرتجع مؤرشف',
                'paye_et_archive' => 'مدفوع ومؤرشف',
                'paye_et_archivee' => 'مدفوع ومؤرشف',
            ];
        }

        if (isset($exact_map[$raw])) {
            return $exact_map[$raw];
        }

        $normalized = strtolower(str_replace([' ', '-', "'"], '_', $raw));
        $normalized = preg_replace('/_+/', '_', (string) $normalized);
        if ($normalized !== '' && isset($normalized_map[$normalized])) {
            return $normalized_map[$normalized];
        }

        return $raw;
    }
}

if (!function_exists('admin_ecotrack_wilaya_label')) {
    function admin_ecotrack_wilaya_label(PDO $pdo, $value)
    { global $dbRepo;
    global $dbRepo;

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $label = admin_order_details_lookup_name($pdo, 'tbl_wilaya', 'id', 'name', $raw);
        if ($label !== $raw && ctype_digit($raw)) {
            return $label . ' (' . $raw . ')';
        }

        return $label;
    }
}

if (!function_exists('admin_ecotrack_format_field_value')) {
    function admin_ecotrack_format_field_value(PDO $pdo, $field, $value)
    { global $dbRepo;
    global $dbRepo;

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        switch ((string) $field) {
            case 'status':
            case 'state':
            case 'activity':
            case 'last_status':
            case 'lastState':
                return admin_ecotrack_status_label($raw);

            case 'originCity':
            case 'destLocationCity':
            case 'wilaya':
            case 'code_wilaya':
            case 'wilaya_id':
            case 'origin_wilaya':
            case 'destination_wilaya':
                return admin_ecotrack_wilaya_label($pdo, $raw);

            case 'station':
            case 'currentStation':
            case 'location':
                return ctype_digit($raw) ? admin_ecotrack_wilaya_label($pdo, $raw) : $raw;

            default:
                return $raw;
        }
    }
}

if (!function_exists('admin_ecotrack_translate_timeline_rows')) {
    function admin_ecotrack_translate_timeline_rows(PDO $pdo, array $rows)
    { global $dbRepo;
    global $dbRepo;

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            foreach (['status', 'activity', 'location', 'station'] as $field) {
                if (!array_key_exists($field, $row)) {
                    continue;
                }

                $rows[$index][$field] = admin_ecotrack_format_field_value($pdo, $field, $row[$field]);
            }
        }

        return $rows;
    }
}

if (!function_exists('admin_ecotrack_pick_latest_timeline_row')) {
    function admin_ecotrack_pick_latest_timeline_row(array $rows)
    { global $dbRepo;
    global $dbRepo;

        $latest_row = [];
        $latest_timestamp = false;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (empty($latest_row)) {
                $latest_row = $row;
            }

            $date = trim((string) ($row['date'] ?? ''));
            $time = trim((string) ($row['time'] ?? ''));
            $candidate = trim($date . ' ' . $time);
            $timestamp = $candidate !== '' ? strtotime($candidate) : false;

            if ($timestamp !== false && ($latest_timestamp === false || $timestamp > $latest_timestamp)) {
                $latest_timestamp = $timestamp;
                $latest_row = $row;
            }
        }

        return $latest_row;
    }
}

if (!function_exists('admin_ecotrack_pick_order_record')) {
    function admin_ecotrack_pick_order_record($payload, $tracking = '', $reference = '')
    { global $dbRepo;
    global $dbRepo;

        if (!is_array($payload)) {
            return [];
        }

        $records = [];
        if (!empty($payload['data']) && is_array($payload['data'])) {
            $records = $payload['data'];
        } elseif (isset($payload[0]) && is_array($payload[0])) {
            $records = $payload;
        } elseif (!empty($payload['tracking']) || !empty($payload['reference'])) {
            return $payload;
        }

        $tracking = trim((string) $tracking);
        $reference = trim((string) $reference);

        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $record_tracking = trim((string) ($record['tracking'] ?? ''));
            $record_reference = trim((string) ($record['reference'] ?? ''));

            if (($tracking !== '' && $record_tracking === $tracking) || ($reference !== '' && $record_reference === $reference)) {
                return $record;
            }
        }

        foreach ($records as $record) {
            if (is_array($record)) {
                return $record;
            }
        }

        return [];
    }
}

if (!function_exists('admin_ecotrack_collect_history_rows')) {
    function admin_ecotrack_collect_history_rows($value, array &$rows)
    { global $dbRepo;
    global $dbRepo;

        if (!is_array($value)) {
            return;
        }

        $row = [
            'date' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($value, ['date', 'created_at', 'updated_at', 'changed_at']) : '',
            'status' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($value, ['status', 'state', 'etat']) : '',
            'activity' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($value, ['activity', 'action', 'event']) : '',
            'note' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($value, ['content', 'remarque', 'note', 'message', 'comment']) : '',
            'location' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($value, ['station', 'hub', 'location', 'ville', 'commune']) : ''
        ];

        if ($row['date'] !== '' || $row['status'] !== '' || $row['activity'] !== '' || $row['note'] !== '' || $row['location'] !== '') {
            $rows[] = $row;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                admin_ecotrack_collect_history_rows($child, $rows);
            }
        }
    }
}

if (!function_exists('admin_ecotrack_extract_history_rows')) {
    function admin_ecotrack_extract_history_rows($payload, $tracking = '')
    { global $dbRepo;
    global $dbRepo;

        if (!is_array($payload)) {
            return [];
        }

        $source = $payload;
        if (function_exists('ecotrack_find_tracking_record')) {
            $record = ecotrack_find_tracking_record($payload, $tracking);
            if (is_array($record)) {
                $source = $record;
            }
        }

        $rows = [];
        admin_ecotrack_collect_history_rows($source, $rows);

        $unique = [];
        $seen = [];
        foreach ($rows as $row) {
            $signature = implode('|', $row);
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $unique[] = $row;
        }

        return array_slice($unique, 0, 60);
    }
}

if (!function_exists('admin_order_details_render_card_grid')) {
    function admin_order_details_render_card_grid(array $items, $extra_class = '')
    { global $dbRepo;
    global $dbRepo;

        if (empty($items)) {
            return '';
        }

        $rows = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $value = (string) ($item['value'] ?? '');
            $is_html = !empty($item['html']);
            if ($label === '' && trim(strip_tags($value)) === '') {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => $value,
                'html' => $is_html,
            ];
        }

        if (empty($rows)) {
            return '';
        }

        $pairs_per_row = 3;
        $class_name = trim('ecotrack-summary-table-wrap ' . (string) $extra_class);
        $html = '<div class="' . htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<table class="ecotrack-summary-table"><tbody>';

        for ($offset = 0, $total = count($rows); $offset < $total; $offset += $pairs_per_row) {
            $html .= '<tr>';

            for ($column = 0; $column < $pairs_per_row; $column++) {
                $index = $offset + $column;
                if (!isset($rows[$index])) {
                    $html .= '<th class="ecotrack-summary-table-label ecotrack-summary-table-label-empty"></th>';
                    $html .= '<td class="ecotrack-summary-table-value ecotrack-summary-table-value-empty"></td>';
                    continue;
                }

                $row = $rows[$index];
                $html .= '<th class="ecotrack-summary-table-label">' . htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8') . '</th>';
                $html .= '<td class="ecotrack-summary-table-value">';
                if (!empty($row['html'])) {
                    $html .= trim(strip_tags((string) $row['value'])) !== '' ? (string) $row['value'] : '<span class="text-muted">-</span>';
                } else {
                    $html .= trim((string) $row['value']) !== '' ? nl2br(htmlspecialchars((string) $row['value'], ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">-</span>';
                }
                $html .= '</td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }
}

if (!function_exists('admin_order_details_render_timeline')) {
    function admin_order_details_render_timeline(array $items, array $meta_fields, array $content_fields)
    { global $dbRepo;
    global $dbRepo;

        if (empty($items)) {
            return '';
        }

        $html = '<div class="ecotrack-timeline">';

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $meta_html = '';
            foreach ($meta_fields as $field => $label) {
                $value = trim((string) ($item[$field] ?? ''));
                if ($value === '') {
                    continue;
                }

                $meta_html .= '<span class="ecotrack-timeline-meta-item">'
                    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                    . ': '
                    . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                    . '</span>';
            }

            $fields_html = '';
            foreach ($content_fields as $field => $label) {
                $value = trim((string) ($item[$field] ?? ''));
                if ($value === '') {
                    continue;
                }

                $fields_html .= '<div class="ecotrack-timeline-field">';
                $fields_html .= '<div class="ecotrack-timeline-field-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
                $fields_html .= '<div class="ecotrack-timeline-field-value">' . nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8')) . '</div>';
                $fields_html .= '</div>';
            }

            if ($meta_html === '' && $fields_html === '') {
                continue;
            }

            $html .= '<div class="ecotrack-timeline-item">';
            if ($meta_html !== '') {
                $html .= '<div class="ecotrack-timeline-meta">' . $meta_html . '</div>';
            }
            if ($fields_html !== '') {
                $html .= '<div class="ecotrack-timeline-fields">' . $fields_html . '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }
}

$order_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($order_id <= 0) {
    header('location: order.php');
    exit;
}

$statement = $dbRepo->prepare("SELECT * FROM tbl_order WHERE id = ? LIMIT 1");
$statement->execute([$order_id]);
$order = $statement->fetch(PDO::FETCH_ASSOC);
if (!$order) {
    header('location: order.php');
    exit;
}

if (isset($_SESSION['user'])) {
    $is_super_admin_user = (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'Super Admin');

    if (!empty($is_employee)) {
        // Employees are scoped by assignment, not by manager_id. An employee may
        // only open an order that is actively assigned to them. (Their session id
        // "emp_<n>" casts to 0, so the manager_id comparison below would otherwise
        // block them from every one of their own orders.)
        $own_stmt = $dbRepo->prepare("SELECT 1 FROM tbl_order_assignment WHERE order_id = ? AND employee_id = ? AND status = 'active' LIMIT 1");
        $own_stmt->execute([(int) $order['id'], (int) ($current_employee_id ?? 0)]);
        if (!$own_stmt->fetchColumn()) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                die('غير مصرح لك بإدارة هذا الطلب لأنه غير مُسند إليك.');
            }
            header('location: order.php');
            exit;
        }
    } elseif (!empty($order['manager_id']) && !$is_super_admin_user) {
        // Regular manager/admin: may only manage their own team's orders.
        $current_manager_id = (int) ($_SESSION['user']['id'] ?? 0);
        if ((int) $order['manager_id'] !== $current_manager_id) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                die('غير مصرح لك بإدارة هذا الطلب لأنه تابع لمدير آخر.');
            }
            header('location: order.php');
            exit;
        }
    }
}

$current_admin_emp = employee_get_current_admin_employee($pdo);
$is_product_restricted_for_user = false;
if ($current_admin_emp !== null && !empty($current_admin_emp['id'])) {
    if (!employee_can_access_order($pdo, (int)$current_admin_emp['id'], (int)$order['id'])) {
        $is_product_restricted_for_user = true;
    }
}

if ($is_product_restricted_for_user) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        die('ليس لديك صلاحية لتعديل هذا الطلب لأنه تابع لمنتج غير مخصص لك.');
    }
    $order['customer_phone'] = '***-***-**** (مخفي)';
    $order['address'] = 'عنوان مخفي - ليس لديك صلاحية';
    if (isset($order['order_notes'])) $order['order_notes'] = 'ملاحظات مخفية';
    if (isset($order['notes'])) $order['notes'] = 'ملاحظات مخفية';
    if (isset($order['shipping_note'])) $order['shipping_note'] = 'ملاحظات مخفية';
}

$call_status_labels = [
    'no_answer' => 'لم يرد',
    'answered' => 'تم الرد',
    'busy' => 'مشغول',
    'phone_off' => 'هاتف مغلق',
    'wrong_number' => 'رقم خاطئ'
];
$call_status_classes = [
    'no_answer' => 'label label-warning',
    'answered' => 'label label-success',
    'busy' => 'label label-info',
    'phone_off' => 'label label-default',
    'wrong_number' => 'label label-danger'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_call'])) {
    $status = isset($_POST['call_status']) ? trim($_POST['call_status']) : '';
    $note = isset($_POST['call_note']) ? trim($_POST['call_note']) : '';
    if (!array_key_exists($status, $call_status_labels)) {
        $call_error_message = 'الرجاء اختيار حالة الاتصال.';
    } else {
        $created_by = isset($_SESSION['user']['full_name']) ? $_SESSION['user']['full_name'] : null;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_order_call_log (order_id, call_status, call_note, called_at, created_by) VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$order_id, $status, ($note !== '' ? $note : null), $created_by]);
        header('location: order-details.php?id=' . $order_id);
        exit;
    }
}

// Order fetched and checked above


$order_assignment = employee_get_assignment_for_order($pdo, (int) $order['id']);
$all_active_employees = employee_get_all($pdo, true);

if (file_exists('inc/telegram_bot.php')) { require_once('inc/telegram_bot.php'); }
require_once('inc/telegram_actions.php');
$telegram_edits = function_exists('telegram_get_edits_for_order') ? telegram_get_edits_for_order($pdo, (int) $order['id']) : [];
$telegram_cancellation = function_exists('telegram_get_cancellation_for_order') ? telegram_get_cancellation_for_order($pdo, (int) $order['id']) : null;
$telegram_actions_log = function_exists('telegram_get_order_telegram_actions') ? telegram_get_order_telegram_actions($pdo, (int) $order['id']) : [];

$order_edit_delivery_company_id = resolve_product_delivery_company_id($pdo, 0);
$order_product_id = (int) ($order['product_id'] ?? 0);
if ($order_product_id > 0) {
    $stmt_product_delivery_company = $dbRepo->prepare("SELECT p_delivery_company_id FROM tbl_product WHERE p_id = ? LIMIT 1");
    $stmt_product_delivery_company->execute([$order_product_id]);
    $preferred_delivery_company_id = (int) $stmt_product_delivery_company->fetchColumn();
    if ($preferred_delivery_company_id > 0) {
        $order_edit_delivery_company_id = resolve_product_delivery_company_id($pdo, $preferred_delivery_company_id);
    }
}

$order_edit_shipping_fees = [];
if ($order_edit_delivery_company_id > 0) {
    $stmt_delivery_prices = $dbRepo->prepare("SELECT wilaya, delivery_type, price FROM tbl_delivery_price WHERE company_id = ?");
    $stmt_delivery_prices->execute([$order_edit_delivery_company_id]);
    foreach ($stmt_delivery_prices->fetchAll(PDO::FETCH_ASSOC) as $delivery_row) {
        $wilaya_name = trim((string) ($delivery_row['wilaya'] ?? ''));
        $delivery_type_name = function_exists('admin_normalize_delivery_type_text')
            ? admin_normalize_delivery_type_text((string) ($delivery_row['delivery_type'] ?? ''))
            : trim((string) ($delivery_row['delivery_type'] ?? ''));
        $price_value = (float) ($delivery_row['price'] ?? 0);
        if ($wilaya_name === '' || $delivery_type_name === '') {
            continue;
        }
        if (!isset($order_edit_shipping_fees[$wilaya_name])) {
            $order_edit_shipping_fees[$wilaya_name] = [];
        }
        $order_edit_shipping_fees[$wilaya_name][$delivery_type_name] = $price_value;
    }
}

$admin_auto_refresh = admin_build_live_refresh_config($pdo, 'order_details', [
    'interval_ms' => 15000,
    'order_id' => $order_id
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_edit_order'])) {
    $edit_values = [
        'product_name' => trim((string) ($_POST['product_name'] ?? '')),
        'customer_name' => trim((string) ($_POST['customer_name'] ?? '')),
        'customer_phone' => trim((string) ($_POST['customer_phone'] ?? '')),
        'wilaya' => trim((string) ($_POST['wilaya'] ?? '')),
        'commune' => trim((string) ($_POST['commune'] ?? '')),
        'address' => trim((string) ($_POST['address'] ?? '')),
        'quantity' => trim((string) ($_POST['quantity'] ?? '')),
        'unit_price' => trim((string) ($_POST['unit_price'] ?? '')),
        'total_price' => trim((string) ($_POST['total_price'] ?? '')),
        'delivery_type' => admin_order_details_normalize_delivery_type_fixed($_POST['delivery_type'] ?? '')
    ];

    if ($edit_values['product_name'] === '') {
        $edit_error_message = 'اسم المنتج مطلوب.';
    } elseif ($edit_values['customer_name'] === '') {
        $edit_error_message = 'اسم الزبون مطلوب.';
    } elseif ($edit_values['customer_phone'] === '') {
        $edit_error_message = 'رقم الهاتف مطلوب.';
    } elseif ($edit_values['wilaya'] === '' || $edit_values['commune'] === '') {
        $edit_error_message = 'الولاية والبلدية مطلوبتان.';
    } elseif ($edit_values['delivery_type'] === '' || !in_array($edit_values['delivery_type'], ['منزل', 'مكتب', 'مجاني'], true)) {
        $edit_error_message = 'نوع التوصيل غير صالح.';
    } elseif ($edit_values['quantity'] === '' || !ctype_digit($edit_values['quantity']) || (int) $edit_values['quantity'] < 1) {
        $edit_error_message = 'الكمية يجب أن تكون رقمًا صحيحًا أكبر من صفر.';
    } elseif ($edit_values['unit_price'] === '' || !is_numeric($edit_values['unit_price']) || (float) $edit_values['unit_price'] < 0) {
        $edit_error_message = 'سعر الوحدة غير صالح.';
    } elseif ($edit_values['total_price'] === '' || !is_numeric($edit_values['total_price']) || (float) $edit_values['total_price'] < 0) {
        $edit_error_message = 'الإجمالي غير صالح.';
    } else {
        $old_order = $order;

        $changes = [];
        $fields_map = [
            'product_name' => 'offer_changed',
            'customer_name' => 'order_edited',
            'customer_phone' => 'phone_changed',
            'wilaya' => 'order_edited',
            'commune' => 'order_edited',
            'address' => 'address_changed',
            'quantity' => 'quantity_changed',
            'unit_price' => 'offer_changed',
            'total_price' => 'offer_changed',
            'delivery_type' => 'order_edited',
        ];

        foreach ($edit_values as $field => $new_val) {
            $old_val = (string) ($old_order[$field] ?? '');
            if ((string) $new_val !== $old_val && isset($fields_map[$field])) {
                $changes[] = [
                    'field' => $field,
                    'action' => $fields_map[$field],
                    'old' => $old_val,
                    'new' => (string) $new_val,
                ];
            }
        }

        $statement = $dbRepo->prepare("
            UPDATE tbl_order
            SET product_name = ?, quantity = ?, unit_price = ?, total_price = ?,
                customer_name = ?, customer_phone = ?, wilaya = ?, commune = ?, address = ?, delivery_type = ?
            WHERE id = ?
            LIMIT 1
        ");
        $statement->execute([
            $edit_values['product_name'],
            (int) $edit_values['quantity'],
            (float) $edit_values['unit_price'],
            (float) $edit_values['total_price'],
            $edit_values['customer_name'],
            $edit_values['customer_phone'],
            $edit_values['wilaya'],
            $edit_values['commune'],
            $edit_values['address'],
            $edit_values['delivery_type'],
            $order_id
        ]);

        $performer_id = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
        foreach ($changes as $ch) {
            audit_log_order($pdo, $order_id, $ch['action'], $ch['old'], $ch['new'], 'admin_panel', $performer_id);
        }
        if (empty($changes)) {
            audit_log_order($pdo, $order_id, 'order_edited', null, null, 'admin_panel', $performer_id);
        }

        admin_set_flash_message('orders', 'success', 'تم تحديث معلومات الطلب بنجاح.');
        header('location: order-details.php?id=' . $order_id);
        exit;
    }

    foreach ($edit_values as $field_name => $field_value) {
        $order[$field_name] = $field_value;
    }
}

$color_name = admin_order_details_lookup_name($pdo, 'tbl_color', 'color_id', 'color_name', $order['order_color'] ?? '');
$size_name = admin_order_details_lookup_name($pdo, 'tbl_size', 'size_id', 'size_name', $order['order_size'] ?? '');

$statement = $dbRepo->prepare("SELECT * FROM tbl_order_call_log WHERE order_id = ? ORDER BY called_at DESC, id DESC");
$statement->execute([$order_id]);
$call_logs = $statement->fetchAll(PDO::FETCH_ASSOC);

$call_count = count($call_logs);
$no_answer_count = 0;
foreach ($call_logs as $log) {
    if ($log['call_status'] === 'no_answer') {
        $no_answer_count++;
    }
}
$last_call = $call_logs ? $call_logs[0] : null;

$delivery_label = admin_order_details_delivery_label_fixed($order['delivery_type'] ?? '');

$customer_type_label = ($order['customer_type'] === 'registered') ? 'عميل مسجل' : 'طلب مباشر';
$order_status_map = [
    'Pending' => ['text' => 'قيد الانتظار', 'class' => 'label label-warning'],
    'Confirmed' => ['text' => 'قيد المعالجة', 'class' => 'label label-primary'],
    'Completed' => ['text' => 'مؤكد', 'class' => 'label label-success'],
    'Returned' => ['text' => 'مرتجع', 'class' => 'label label-info'],
    'Cancelled' => ['text' => 'ملغي', 'class' => 'label label-danger']
];
$order_status = $order_status_map[$order['order_status']] ?? ['text' => $order['order_status'], 'class' => 'label label-default'];
$ecotrack_status = ecotrack_status_meta($order['ecotrack_status'] ?? '');
$ecotrack_reference = ecotrack_build_order_reference($order);
$ecotrack_payload_body = ecotrack_create_order_request_body($pdo, $ecotrack_settings, $order);
if (!is_array($ecotrack_payload_body) || empty($ecotrack_payload_body['orders'])) {
    $ecotrack_payload_body = ['orders' => ['0' => ecotrack_build_order_payload($order)]];
}
$ecotrack_payload_json = ecotrack_json_encode($ecotrack_payload_body, true);
$ecotrack_tracking = trim((string) ($order['ecotrack_tracking'] ?? ''));
$ecotrack_remote_status = trim((string) ($order['ecotrack_remote_status'] ?? ''));
$ecotrack_last_error = trim((string) ($order['ecotrack_last_error'] ?? ''));
$ecotrack_last_payload = trim((string) ($order['ecotrack_last_payload'] ?? ''));
$ecotrack_last_response = trim((string) ($order['ecotrack_last_response'] ?? ''));
$ecotrack_last_order_info_raw = trim((string) ($order['ecotrack_last_order_info'] ?? ''));
$ecotrack_last_updates_raw = trim((string) ($order['ecotrack_last_updates'] ?? ''));
$ecotrack_last_tracking_info = trim((string) ($order['ecotrack_last_tracking_info'] ?? ''));
$ecotrack_last_trackings_info_raw = trim((string) ($order['ecotrack_last_trackings_info'] ?? ''));
$ecotrack_sent_at = trim((string) ($order['ecotrack_sent_at'] ?? ''));
$ecotrack_last_order_info = ecotrack_json_decode($ecotrack_last_order_info_raw);
$ecotrack_last_trackings_info = ecotrack_json_decode($ecotrack_last_trackings_info_raw);
$ecotrack_updates_list = ecotrack_json_decode($ecotrack_last_updates_raw);
if (!is_array($ecotrack_updates_list)) {
    $ecotrack_updates_list = [];
}
$ecotrack_remote_order_record = admin_ecotrack_pick_order_record(is_array($ecotrack_last_order_info) ? $ecotrack_last_order_info : [], $ecotrack_tracking, $ecotrack_reference);
$ecotrack_history_rows = admin_ecotrack_extract_history_rows(is_array($ecotrack_last_trackings_info) ? $ecotrack_last_trackings_info : [], $ecotrack_tracking);
$ecotrack_remote_order_summary = [
    'المرجع البعيد' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['reference']) : '',
    'رقم التتبع' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['tracking']) : '',
    'الحالة' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['status', 'state']) : '',
    'تاريخ الإنشاء' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['created_at', 'date']) : '',
    'العميل' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['client', 'nom_client']) : '',
    'الهاتف' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['phone', 'telephone', 'tel']) : '',
    'العنوان' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['adresse', 'address']) : '',
    'البلدية' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['commune']) : '',
    'الولاية' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['wilaya_id', 'code_wilaya', 'wilaya']) : '',
    'المبلغ' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['montant', 'amount']) : '',
    'المنتجات' => function_exists('ecotrack_find_first_value_by_keys') ? ecotrack_find_first_value_by_keys($ecotrack_remote_order_record, ['products', 'product', 'produit']) : ''
];
if ($is_product_restricted_for_user) {
    if (isset($ecotrack_remote_order_summary['الهاتف'])) $ecotrack_remote_order_summary['الهاتف'] = '***-***-**** (مخفي)';
    if (isset($ecotrack_remote_order_summary['العنوان'])) $ecotrack_remote_order_summary['العنوان'] = 'عنوان مخفي - ليس لديك صلاحية';
}
$ecotrack_remote_status_display = admin_ecotrack_status_label($ecotrack_remote_status);
$ecotrack_remote_order_summary['الحالة'] = admin_ecotrack_format_field_value($pdo, 'status', $ecotrack_remote_order_summary['الحالة'] ?? '');
$ecotrack_remote_order_summary['الولاية'] = admin_ecotrack_format_field_value($pdo, 'wilaya_id', $ecotrack_remote_order_summary['الولاية'] ?? '');
$ecotrack_history_rows = admin_ecotrack_translate_timeline_rows($pdo, $ecotrack_history_rows);
$ecotrack_updates_list = admin_ecotrack_translate_timeline_rows($pdo, $ecotrack_updates_list);
$ecotrack_payload_changed_since_last_attempt = $ecotrack_last_payload !== '' && trim($ecotrack_last_payload) !== trim((string) $ecotrack_payload_json);

$zrexpress_status = function_exists('zrexpress_status_meta') ? zrexpress_status_meta($order['zrexpress_status'] ?? '') : ['label' => 'غير مربوط بعد', 'class' => 'label label-default'];
$zrexpress_reference = 'ZREX-' . $order['id'];
$zrexpress_tracking = trim((string) ($order['zrexpress_tracking'] ?? ''));
$zrexpress_remote_status = trim((string) ($order['zrexpress_remote_status'] ?? ''));
$zrexpress_last_error = trim((string) ($order['zrexpress_last_error'] ?? ''));
$zrexpress_last_payload = trim((string) ($order['zrexpress_last_payload'] ?? ''));
$zrexpress_last_response = trim((string) ($order['zrexpress_last_response'] ?? ''));
$zrexpress_sent_at = trim((string) ($order['zrexpress_sent_at'] ?? ''));
?>

<style>
.order-details-linkbar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 14px;
}
.order-details-linkbar .btn {
    border-radius: 10px;
    font-weight: 700;
    border: 1px solid #dbe4ee;
    background: #fff;
    color: #1f2937;
}
.order-details-linkbar .btn.btn-primary {
    border-color: #2563eb;
    background: linear-gradient(135deg, #2563eb, #3b82f6);
    color: #fff;
}

.order-details-ecotrack-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 16px;
    padding: 14px 16px;
    border: 1px solid #dbe4ee;
    border-radius: 14px;
    background: linear-gradient(135deg, #f8fbff 0%, #edf5ff 100%);
}
.order-details-ecotrack-hero h4 {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    color: #0f172a;
}
.order-details-ecotrack-hero p {
    margin: 4px 0 0;
    color: #475467;
    font-size: 13px;
}

.order-details-ecotrack-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 14px;
    margin-bottom: 14px;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
}
.order-details-ecotrack-card h4 {
    margin: 0 0 12px;
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}

#tab_ecotrack .ecotrack-summary-table-wrap {
    width: 100%;
    overflow-x: auto;
    border: 1px solid #dbe4ee;
    border-radius: 16px;
    background: #ffffff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

#tab_ecotrack .ecotrack-summary-table {
    width: 100%;
    min-width: 760px;
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
}

#tab_ecotrack .ecotrack-summary-table-wrap.ecotrack-card-grid-wide .ecotrack-summary-table {
    min-width: 960px;
}

#tab_ecotrack .ecotrack-summary-table th,
#tab_ecotrack .ecotrack-summary-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #e4ebf3;
    vertical-align: top;
}

#tab_ecotrack .ecotrack-summary-table tr:last-child th,
#tab_ecotrack .ecotrack-summary-table tr:last-child td {
    border-bottom: 0;
}

#tab_ecotrack .ecotrack-summary-table-label {
    width: 13%;
    min-width: 120px;
    background: #f8fafc;
    color: #475467;
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

#tab_ecotrack .ecotrack-summary-table-value {
    width: 20%;
    background: #ffffff;
    color: #101828;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.8;
    word-break: break-word;
}

#tab_ecotrack .ecotrack-summary-table-value .label {
    display: inline-block;
    margin-top: 2px;
}

#tab_ecotrack .ecotrack-summary-table-label-empty,
#tab_ecotrack .ecotrack-summary-table-value-empty {
    background: #ffffff;
}

#tab_ecotrack .ecotrack-actions-panel {
    background: linear-gradient(180deg, #f8fbff 0%, #f3f8ff 100%);
    border: 1px solid #d4e3f4;
    border-radius: 18px;
    padding: 16px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
}

#tab_ecotrack .ecotrack-actions-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-bottom: 12px;
}

#tab_ecotrack .ecotrack-actions-row:last-child {
    margin-bottom: 0;
}

#tab_ecotrack .ecotrack-actions-row form,
#tab_ecotrack .ecotrack-actions-row a {
    display: inline-flex;
}

#tab_ecotrack .ecotrack-actions-row .btn {
    min-height: 44px;
    padding: 10px 16px;
    border-radius: 14px;
    font-size: 14px;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid transparent;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.10);
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
}

#tab_ecotrack .ecotrack-actions-row .btn:hover,
#tab_ecotrack .ecotrack-actions-row .btn:focus {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
    filter: saturate(1.05);
}

#tab_ecotrack .ecotrack-actions-row .btn-default {
    background: #ffffff;
    border-color: #d0dbe7;
    color: #1f2937;
}

#tab_ecotrack .ecotrack-actions-row .btn-primary {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #ffffff;
}

#tab_ecotrack .ecotrack-actions-row .btn-info {
    background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);
    color: #ffffff;
}

#tab_ecotrack .ecotrack-actions-row .btn-success {
    background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
    color: #ffffff;
}

#tab_ecotrack .ecotrack-actions-row .btn-warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #ffffff;
}

#tab_ecotrack .ecotrack-actions-row .btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
}

#tab_ecotrack .ecotrack-actions-row .checkbox-inline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 44px;
    padding: 10px 14px;
    border: 1px solid #d0dbe7;
    border-radius: 14px;
    background: #ffffff;
    color: #344054;
    font-weight: 700;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.06);
}

#tab_ecotrack .ecotrack-actions-row .text-muted {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 10px 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.72);
    color: #667085 !important;
}

#tab_ecotrack .ecotrack-timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

#tab_ecotrack .ecotrack-timeline-item {
    background: #fff;
    border: 1px solid #dbe4ee;
    border-right: 4px solid #2e90fa;
    border-radius: 14px;
    padding: 14px;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
}

#tab_ecotrack .ecotrack-timeline-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 10px;
}

#tab_ecotrack .ecotrack-timeline-meta-item {
    background: #eff8ff;
    color: #175cd3;
    border: 1px solid #b2ddff;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
}

#tab_ecotrack .ecotrack-timeline-fields {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

#tab_ecotrack .ecotrack-timeline-field {
    background: #f8fafc;
    border-radius: 10px;
    padding: 10px 12px;
}

#tab_ecotrack .ecotrack-timeline-field-label {
    color: #667085;
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 6px;
}

#tab_ecotrack .ecotrack-timeline-field-value {
    color: #101828;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.6;
    word-break: break-word;
}

#tab_ecotrack .ecotrack-actions-row form,
#tab_ecotrack .ecotrack-actions-row a,
#tab_ecotrack .ecotrack-actions-row .checkbox-inline {
    margin: 0 !important;
}

@media (max-width: 991px) {
    #tab_ecotrack .ecotrack-summary-table {
        min-width: 680px;
    }
}

#ecotrackActionOverlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

#ecotrackActionOverlay.is-visible {
    display: flex;
}

.ecotrack-action-dialog {
    width: min(520px, 100%);
    background: #fff;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.25);
}

.ecotrack-action-dialog-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
}

.ecotrack-action-dialog-spinner {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: 4px solid #dbeafe;
    border-top-color: #0284c7;
    animation: ecotrack-spin 0.9s linear infinite;
    flex: 0 0 42px;
}

.ecotrack-action-dialog.is-done .ecotrack-action-dialog-spinner {
    animation: none;
    border-color: #dcfce7;
    border-top-color: #16a34a;
    position: relative;
}

.ecotrack-action-dialog.is-done .ecotrack-action-dialog-spinner::after {
    content: '\f00c';
    font-family: FontAwesome;
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #16a34a;
    font-size: 18px;
}

.ecotrack-action-dialog.is-error .ecotrack-action-dialog-spinner {
    animation: none;
    border-color: #fee2e2;
    border-top-color: #dc2626;
    position: relative;
}

.ecotrack-action-dialog.is-error .ecotrack-action-dialog-spinner::after {
    content: '\f00d';
    font-family: FontAwesome;
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #dc2626;
    font-size: 18px;
}

.ecotrack-action-dialog-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}

.ecotrack-action-dialog-text {
    font-size: 15px;
    line-height: 1.9;
    color: #334155;
    margin-bottom: 14px;
    word-break: break-word;
}

.ecotrack-action-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

@keyframes ecotrack-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

@media (max-width: 767px) {
    #tab_ecotrack .ecotrack-card-grid,
    #tab_ecotrack .ecotrack-card-grid-wide {
        grid-template-columns: 1fr;
    }
}
</style>

<section class="content-header">
    <div class="content-header-left">
        <h1>تفاصيل الطلب</h1>
    </div>
    <div class="content-header-right">
        <a href="order.php" class="btn btn-default btn-sm">رجوع إلى الطلبات</a>
    </div>
</section>

<section class="content">
    <div class="order-details-linkbar">
        <a href="order.php" target="_parent" class="btn"><i class="fa fa-list"></i> إدارة الطلبات</a>
        <?php if (!$is_employee): ?>
        <a href="order-statistics.php" target="_parent" class="btn"><i class="fa fa-bar-chart"></i> إحصائيات الطلبات</a>
        <a href="order-statistics.php#ordersTable" target="_parent" class="btn btn-primary"><i class="fa fa-truck"></i> تتبع الشحنات</a>
        <a href="ecotrack-diagnostics.php" target="_parent" class="btn"><i class="fa fa-stethoscope"></i> تشخيص ECOTRACK</a>
        <a href="zrexpress-diagnostics.php" target="_parent" class="btn"><i class="fa fa-stethoscope"></i> تشخيص ZRexpress</a>
        <?php endif; ?>
    </div>
    <?php if ($is_product_restricted_for_user): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-danger" style="background:#fef2f2;border:1px solid #f87171;color:#991b1b;padding:15px;border-radius:8px;font-weight:bold;margin-bottom:15px;">
                <i class="fa fa-exclamation-triangle" style="font-size:18px;margin-left:8px;"></i> ليس لديك صلاحية لعرض أو تعديل هذا الطلب لأنه تابع لمنتج غير مخصص لك.
            </div>
        </div>
    </div>
    <style>
        a[href="#tab_edit"], a[href="#tab_ecotrack"], a[href="#tab_zrexpress"], a[href="#tab_telegram"] { display: none !important; }
        .order-actions-bar, button[name="add_call"] { display: none !important; }
    </style>
    <?php endif; ?>
    <?php if ($flash && !empty($flash['message'])): ?>
    <div class="row">
        <div class="col-md-12">
            <div class="callout callout-<?php echo htmlspecialchars((string) ($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <ul class="nav nav-tabs" id="orderDetailsTabs" role="tablist">
        <li class="active"><a href="#tab_summary" data-toggle="tab">ملخص الطلب</a></li>
        <li><a href="#tab_edit" data-toggle="tab">تعديل الطلب</a></li>
        <?php // Delivery-dispatch tabs: employees need these to confirm an order and
              // push it to the delivery company (ECOTRACK / ZRexpress). ?>
        <li><a href="#tab_ecotrack" data-toggle="tab">ECOTRACK</a></li>
        <li><a href="#tab_zrexpress" data-toggle="tab">ZRexpress</a></li>
        <?php if (!$is_employee): ?>
        <?php // Manager/admin-only tabs. ?>
        <li><a href="#tab_telegram" data-toggle="tab">تلغرام</a></li>
        <li><a href="#tab_audit" data-toggle="tab"><i class="fa fa-history"></i> سجل التدقيق</a></li>
        <li><a href="#tab_timeline" data-toggle="tab"><i class="fa fa-clock-o"></i> السجل الزمني للطلب (ERP)</a></li>
        <li><a href="#tab_api_logs" data-toggle="tab"><i class="fa fa-exchange"></i> سجل API (ERP)</a></li>
        <?php endif; ?>
    </ul>
    <div class="tab-content" style="margin-top:15px;">
        <div class="tab-pane active" id="tab_summary">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">بيانات الطلب</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>رقم الطلب</th>
                                        <td><?php echo (int) $order['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>المنتج</th>
                                        <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>السعر</th>
                                        <td><?php echo htmlspecialchars($order['unit_price']); ?> دج</td>
                                    </tr>
                                    <tr>
                                        <th>الكمية</th>
                                        <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>المجموع</th>
                                        <td><?php echo htmlspecialchars($order['total_price']); ?> دج</td>
                                    </tr>
                                    <tr>
                                        <th>الحالة</th>
                                        <td>
                                            <span class="<?php echo $order_status['class']; ?>"><?php echo $order_status['text']; ?></span>
                                            <?php if (!empty($order['delivery_company_id']) && !empty($order['tracking_number'])): ?>
                                                <div style="margin-top: 10px;">
                                                    <a href="order-sync-now.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-xs"><i class="fa fa-refresh"></i> تحديث الحالة الآن</a>
                                                </div>
                                            <?php elseif (!empty($order['delivery_company_id']) && empty($order['tracking_number'])): ?>
                                                <div style="margin-top: 10px;">
                                                    <a href="order-resend-api.php?id=<?= $order['id'] ?>" class="btn btn-warning btn-xs"><i class="fa fa-send"></i> إعادة إرسال لشركة التوصيل</a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tbody>
                                    <tr>
                                        <th>اسم العميل</th>
                                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>نوع العميل</th>
                                        <td><?php echo $customer_type_label; ?></td>
                                    </tr>
                                    <tr>
                                        <th>رقم الهاتف</th>
                                        <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>العنوان</th>
                                        <td><?php echo htmlspecialchars($order['wilaya'] . ' - ' . $order['commune']); ?><br><?php echo htmlspecialchars($order['address']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>نوع التوصيل</th>
                                        <td><?php echo $delivery_label; ?></td>
                                    </tr>
                                    <tr>
                                        <th>تاريخ الطلب</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>اللون</th>
                                        <td><?php echo htmlspecialchars($color_name); ?></td>
                                    </tr>
                                    <tr>
                                        <th>المقاس</th>
                                        <td><?php echo htmlspecialchars($size_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>الموظف المسؤول</th>
                                        <td>
                                            <?php if ($order_assignment): ?>
                                                <strong style="color:#0369a1;"><?php echo htmlspecialchars($order_assignment['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <small style="display:block;color:#64748b;font-size:11px;">منذ <?php echo date('d/m/Y H:i', strtotime($order_assignment['assigned_at'])); ?></small>
                                            <?php else: ?>
                                                <span style="color:#94a3b8;">غير موزع</span>
                                            <?php endif; ?>
                                            <?php
                                                // "Claim" button: a regular manager who does NOT own this order can
                                                // insert themselves into it so they may confirm it.
                                                $__cu = $_SESSION['user'] ?? [];
                                                $__crole = $__cu['role'] ?? '';
                                                if (($__crole === 'Admin' || $__crole === 'Manager')
                                                    && function_exists('order_can_manager_change')
                                                    && !order_can_manager_change($pdo, (int) $order['id'], $__cu)):
                                            ?>
                                                <a href="order-claim.php?id=<?php echo (int) $order['id']; ?>&redirect=order-details.php?id=<?php echo (int) $order['id']; ?>"
                                                   class="btn btn-xs btn-warning" style="margin-top:6px;display:inline-block;"
                                                   onclick="return confirm('استلام هذا الطلب لإدخال نفسك فيه وتأكيده؟');">
                                                   <i class="fa fa-hand-paper-o"></i> استلام الطلب
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call log: register a follow-up call + history. Backend handler exists
         (add_call) but the UI was missing, so nobody could log a call. Available
         to whoever can open this order (managers see their team's, employees see
         only their own assigned orders). -->
    <div class="row order-actions-bar">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-phone"></i> تسجيل مكالمة (<?php echo (int) $call_count; ?>)</h3>
                </div>
                <div class="box-body">
                    <?php if (!empty($call_error_message)): ?>
                        <div class="callout callout-danger"><?php echo htmlspecialchars($call_error_message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <form method="post" action="order-details.php?id=<?php echo (int) $order_id; ?>" class="form-inline" style="margin-bottom:15px;">
                        <?php $csrf->echoInputField(); ?>
                        <input type="hidden" name="add_call" value="1">
                        <div class="form-group" style="margin-left:8px;">
                            <label style="margin-left:6px;">حالة الاتصال</label>
                            <select name="call_status" class="form-control" required>
                                <option value="">اختر...</option>
                                <?php foreach ($call_status_labels as $__cs_key => $__cs_label): ?>
                                    <option value="<?php echo htmlspecialchars((string) $__cs_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $__cs_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-left:8px;">
                            <input type="text" name="call_note" class="form-control" placeholder="ملاحظة (اختياري)" style="min-width:220px;">
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> تسجيل المكالمة</button>
                    </form>

                    <?php if (!empty($call_logs)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" style="margin-bottom:0;">
                            <thead>
                                <tr><th style="width:150px;">الحالة</th><th>الملاحظة</th><th style="width:150px;">بواسطة</th><th style="width:150px;">التاريخ</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($call_logs as $log): ?>
                                <tr>
                                    <td><span class="<?php echo htmlspecialchars($call_status_classes[$log['call_status']] ?? 'label label-default', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($call_status_labels[$log['call_status']] ?? (string) $log['call_status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                                    <td><?php echo htmlspecialchars((string) ($log['call_note'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($log['created_by'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $log['called_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                        <p class="text-muted" style="margin:0;">لا توجد مكالمات مسجلة بعد.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
        </div>

        <div class="tab-pane" id="tab_edit">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info" id="edit-order">
                <div class="box-header with-border">
                    <h3 class="box-title">تعديل معلومات الطلب</h3>
                </div>
                <div class="box-body">
                    <?php if ($edit_error_message !== ''): ?>
                        <div class="callout callout-danger"><?php echo htmlspecialchars($edit_error_message, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                    <form method="post" action="order-details.php?id=<?php echo $order_id; ?>">
                        <?php $csrf->echoInputField(); ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>اسم المنتج</label>
                                    <input type="text" name="product_name" class="form-control" value="<?php echo htmlspecialchars((string) ($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>الكمية</label>
                                    <input type="number" name="quantity" id="editOrderQuantity" class="form-control" min="1" step="1" value="<?php echo htmlspecialchars((string) ($order['quantity'] ?? '1'), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>نوع التوصيل</label>
                                    <?php $current_delivery_type = admin_order_details_normalize_delivery_type_fixed($order['delivery_type'] ?? ''); ?>
                                    <select name="delivery_type" id="editOrderDeliveryType" class="form-control" required>
                                        <option value="منزل" <?php echo $current_delivery_type === 'منزل' ? 'selected' : ''; ?>>توصيل إلى المنزل</option>
                                        <option value="مكتب" <?php echo $current_delivery_type === 'مكتب' ? 'selected' : ''; ?>>توصيل إلى المكتب</option>
                                        <option value="مجاني" <?php echo $current_delivery_type === 'مجاني' ? 'selected' : ''; ?>>توصيل مجاني</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>سعر الوحدة</label>
                                    <input type="number" name="unit_price" id="editOrderUnitPrice" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string) ($order['unit_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>الإجمالي</label>
                                    <div class="input-group">
                                        <input type="number" name="total_price" id="editOrderTotalPrice" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string) ($order['total_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-default" id="editOrderRecalculate">حساب تلقائي</button>
                                        </span>
                                    </div>
                                    <small class="text-muted">يمكنك تعديل الإجمالي يدويًا إذا كان يشمل الشحن أو أي تعديل إضافي.</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>رقم الهاتف</label>
                                    <input type="text" name="customer_phone" class="form-control" value="<?php echo htmlspecialchars((string) ($order['customer_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>اسم الزبون</label>
                                    <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars((string) ($order['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>الولاية</label>
                                    <input type="text" name="wilaya" id="editOrderWilaya" class="form-control" value="<?php echo htmlspecialchars((string) ($order['wilaya'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>البلدية</label>
                                    <input type="text" name="commune" class="form-control" value="<?php echo htmlspecialchars((string) ($order['commune'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>العنوان التفصيلي</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="اكتب العنوان الكامل كما تريد أن يظهر في الطلب."><?php echo htmlspecialchars((string) ($order['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <button type="submit" name="form_edit_order" class="btn btn-primary"><i class="fa fa-save"></i> حفظ التعديلات</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
        </div>

        <div class="tab-pane" id="tab_ecotrack">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">ECOTRACK</h3>
                </div>
                <div class="box-body">
                    <div id="ecotrackPanelContent">
                    <div class="order-details-ecotrack-hero">
                        <div>
                            <h4><i class="fa fa-truck"></i> تفاصيل الشحنة المرتبطة بالطلب</h4>
                            <p>مراجعة الحالة الحالية، تنفيذ إجراءات الربط، وتتبع آخر تحديثات ECOTRACK.</p>
                        </div>
                    </div>
                    <?php if (!$ecotrack_ready): ?>
                        <div class="callout callout-warning">
                        إعداد ECOTRACK غير مكتمل بعد. أضف التوكن من <a href="settings.php#tab_ecotrack">Parametres du site</a> أولاً.
                        </div>
                    <?php endif; ?>

                    <div id="ecotrackAjaxFeedback"></div>
                    <div id="ecotrackToolbarTop"></div>
                    <div class="row">
                        <div class="col-md-7">
                            <?php
                            $ecotrack_status_summary = [
                                'حالة الربط' => $ecotrack_status['label'],
                                'المرجع المحلي' => $ecotrack_reference,
                                'رقم التتبع' => $ecotrack_tracking !== '' ? $ecotrack_tracking : 'غير متوفر',
                                'الحالة البعيدة' => $ecotrack_remote_status_display !== '' ? $ecotrack_remote_status_display : 'لم تتم المزامنة بعد',
                                'آخر إرسال' => $ecotrack_sent_at !== '' ? date('d/m/Y H:i', strtotime($ecotrack_sent_at)) : 'لم يرسل بعد'
                            ];
                            $ecotrack_status_cards = [];
                            foreach ($ecotrack_status_summary as $summary_label => $summary_value) {
                                $ecotrack_status_cards[] = [
                                    'label' => (string) $summary_label,
                                    'value' => $summary_label === 'حالة الربط'
                                        ? '<span class="' . htmlspecialchars($ecotrack_status['class'], ENT_QUOTES, 'UTF-8') . '" style="padding: 4px 10px; border-radius: 4px;">' . htmlspecialchars((string) $summary_value, ENT_QUOTES, 'UTF-8') . '</span>'
                                        : (string) $summary_value,
                                    'html' => $summary_label === 'حالة الربط'
                                ];
                            }
                            ?>
                            <div class="order-details-ecotrack-card">
                                <h4><i class="fa fa-info-circle"></i> معلومات الشحنة</h4>
                                <?php echo admin_order_details_render_card_grid($ecotrack_status_cards); ?>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <?php if ($ecotrack_last_error !== ''): ?>
                                <div class="callout callout-danger" style="border-radius: 8px; box-shadow: none;">
                                    <strong><i class="fa fa-exclamation-triangle"></i> آخر خطأ:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($ecotrack_last_error, ENT_QUOTES, 'UTF-8')); ?>
                                </div>
                            <?php else: ?>
                                <div class="callout callout-success" style="border-radius: 8px; box-shadow: none; background: #ecfdf5; border-color: #10b981; color: #065f46;">
                                    <i class="fa fa-check-circle"></i> لا توجد أخطاء حالياً. الربط سليم.
                                </div>
                            <?php endif; ?>

                            <?php if ($ecotrack_last_response !== ''): ?>
                                <details style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-bottom: 10px;">
                                    <summary style="cursor:pointer; font-weight: bold; color: #475569;">عرض آخر رد (Response)</summary>
                                    <div style="margin-top:8px;">
                                        <textarea class="form-control" rows="4" style="font-size:12px; font-family:monospace;" readonly><?php echo htmlspecialchars($ecotrack_last_response, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- الأزرار والإجراءات نرفعها للأعلى لتكون واضحة -->
                    <div id="ecotrackLiveBlocks" class="order-details-ecotrack-card">
                        <h4><i class="fa fa-cogs"></i> الإجراءات المتاحة</h4>
                        <div id="ecotrackActionsBlock" class="ecotrack-actions-panel" style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php if ($ecotrack_ready): ?>
                                <?php if ($ecotrack_tracking === ''): ?>
                                    <form method="post" action="order-ecotrack-action.php">
                                        <?php $csrf->echoInputField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                        <input type="hidden" name="action" value="create">
                                        <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                        <button type="submit" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;"><i class="fa fa-paper-plane"></i> إرسال إلى ECOTRACK</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="order-ecotrack-action.php">
                                        <?php $csrf->echoInputField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                        <button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> تحديث الطلب</button>
                                    </form>
                                    <form method="post" action="order-ecotrack-action.php">
                                        <?php $csrf->echoInputField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                        <input type="hidden" name="action" value="sync">
                                        <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                        <button type="submit" class="btn btn-info"><i class="fa fa-exchange"></i> مزامنة الحالة</button>
                                    </form>
                                    <form method="post" action="order-ecotrack-action.php" style="display: flex; align-items: center; gap: 10px; background: #fef3c7; padding: 5px 10px; border-radius: 6px; border: 1px solid #fde68a;">
                                        <?php $csrf->echoInputField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                        <input type="hidden" name="action" value="ship">
                                        <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                        <label style="margin:0; color:#92400e; cursor:pointer;"><input type="checkbox" name="ask_collection" value="1"> طلب Ramassage</label>
                                        <button type="submit" class="btn btn-warning btn-sm"><i class="fa fa-truck"></i> تأكيد الشحن</button>
                                    </form>
                                    <form method="post" action="order-ecotrack-action.php">
                                        <?php $csrf->echoInputField(); ?>
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('هل تريد بالتأكيد الحذف من ECOTRACK؟');"><i class="fa fa-trash"></i> حذف</button>
                                    </form>
                                    <a href="order-ecotrack-label.php?order_id=<?php echo (int) $order['id']; ?>" class="btn btn-default" target="_blank"><i class="fa fa-file-pdf-o"></i> تحميل الليبل</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <summary style="cursor:pointer; font-weight: bold; color: #334155; font-size: 15px;"><i class="fa fa-database"></i> تفاصيل بيانات الحمولة (Payload)</summary>
                        <div style="margin-top: 15px;">
                            <?php
                            $payload_view = [];
                            if (is_array($ecotrack_payload_body) && !empty($ecotrack_payload_body['orders'][0]) && is_array($ecotrack_payload_body['orders'][0])) {
                                $payload_view = $ecotrack_payload_body['orders'][0];
                            }
                            $payload_labels = [
                                'reference' => 'المرجع',
                                'nom_client' => 'اسم العميل',
                                'telephone' => 'الهاتف',
                                'adresse' => 'العنوان',
                                'commune' => 'البلدية',
                                'montant' => 'المبلغ',
                                'produit' => 'المنتج',
                                'quantite' => 'الكمية'
                            ];
                            $payload_display = [];
                            foreach ($payload_labels as $field => $label) {
                                if (array_key_exists($field, $payload_view)) {
                                    $payload_display[] = ['label' => $label, 'value' => (string)$payload_view[$field]];
                                }
                            }
                            echo admin_order_details_render_card_grid($payload_display);
                            ?>
                            <details style="margin-top:10px;">
                                <summary style="cursor:pointer; color:#64748b;">عرض الكود البرمجي (JSON)</summary>
                                <textarea class="form-control" rows="5" style="font-family:monospace; font-size:12px; margin-top:5px;" readonly><?php echo htmlspecialchars((string) $ecotrack_payload_json, ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </details>
                        </div>
                    </details>
                    
                    <?php if ($ecotrack_payload_changed_since_last_attempt): ?>
                        <div class="callout callout-warning" style="margin-top:10px; border-radius: 6px;">
                            توجد تغييرات في بيانات الطلب لم تُرسل بعد لـ ECOTRACK. يرجى "تحديث الطلب".
                        </div>
                    <?php endif; ?>
                    <details style="margin-top: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px;">
                        <summary style="cursor:pointer; font-weight: bold; color: #64748b; font-size: 14px;"><i class="fa fa-wrench"></i> خيارات وأدوات متقدمة</summary>
                        <div style="margin-top: 15px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                            <?php if ($ecotrack_ready && $ecotrack_tracking !== ''): ?>
                                <form method="post" action="order-ecotrack-action.php" style="display:flex; gap:5px;">
                                    <?php $csrf->echoInputField(); ?>
                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                    <input type="hidden" name="action" value="add_note">
                                    <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                    <input type="text" name="content" class="form-control input-sm" style="width:200px;" maxlength="255" placeholder="إضافة ملاحظة للمندوب">
                                    <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-commenting"></i> إرسال</button>
                                </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-default btn-sm" id="copyEcotrackPayload"><i class="fa fa-copy"></i> نسخ الحمولة</button>
                            <a href="ecotrack-diagnostics.php" class="btn btn-default btn-sm"><i class="fa fa-stethoscope"></i> فحص تشخيصي</a>
                        </div>
                    </details>

                    <?php
                    $ecotrack_remote_order_summary = array_filter($ecotrack_remote_order_summary, static function ($value) {
                        return trim((string) $value) !== '';
                    });
                    ?>

                    <div id="ecotrackResultsBlock">
                    <?php if (!empty($ecotrack_remote_order_summary) || !empty($ecotrack_history_rows) || $ecotrack_last_order_info_raw !== '' || $ecotrack_last_trackings_info_raw !== ''): ?>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (!empty($ecotrack_remote_order_summary)): ?>
                                    <label>آخر معلومات الطلب من ECOTRACK</label>
                                    <?php
                                    $remote_order_cards = [];
                                    foreach ($ecotrack_remote_order_summary as $summary_label => $summary_value) {
                                        $remote_order_cards[] = [
                                            'label' => (string) $summary_label,
                                            'value' => (string) $summary_value
                                        ];
                                    }
                                    ?>
                                    <?php echo admin_order_details_render_card_grid($remote_order_cards, 'ecotrack-card-grid-wide'); ?>
                                <?php elseif ($ecotrack_last_order_info_raw !== ''): ?>
                                    <div class="form-group">
                                        <label>آخر معلومات الطلب من ECOTRACK</label>
                                        <textarea class="form-control" rows="12" readonly><?php echo htmlspecialchars($ecotrack_last_order_info_raw, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($ecotrack_history_rows)): ?>
                                    <label>آخر تاريخ عمليات محفوظ</label>
                                    <?php
                                    echo admin_order_details_render_timeline(
                                        $ecotrack_history_rows,
                                        ['date' => 'التاريخ'],
                                        [
                                            'status' => 'الحالة',
                                            'activity' => 'النشاط',
                                            'note' => 'الملاحظة',
                                            'location' => 'الموقع'
                                        ]
                                    );
                                    ?>
                                <?php elseif ($ecotrack_last_trackings_info_raw !== ''): ?>
                                    <div class="form-group">
                                        <label>آخر تاريخ عمليات محفوظ</label>
                                        <textarea class="form-control" rows="12" readonly><?php echo htmlspecialchars($ecotrack_last_trackings_info_raw, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($ecotrack_last_tracking_info !== '' || !empty($ecotrack_updates_list)): ?>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <?php if ($ecotrack_last_tracking_info !== ''): ?>
                                    <?php $tracking_payload = ecotrack_json_decode($ecotrack_last_tracking_info); ?>
                                    <?php if (is_array($tracking_payload)): ?>
                                        <label>آخر سجل تتبع محفوظ</label>
                                        <?php
                                        $tracking_labels = [
                                            'recipientName' => 'اسم المستلم',
                                            'shippedBy' => 'المرسل',
                                            'originCity' => 'ولاية الانطلاق',
                                            'destLocationCity' => 'ولاية الوصول',
                                            'currentStation' => 'المحطة الحالية',
                                            'status' => 'الحالة',
                                            'tracking' => 'رقم التتبع',
                                            'reference' => 'المرجع'
                                        ];
                                        $tracking_summary_cells = [];
                                        foreach ($tracking_payload as $key => $value) {
                                            if (is_array($value)) {
                                                continue;
                                            }
                                            $tracking_summary_cells[] = [
                                                'label' => $tracking_labels[$key] ?? $key,
                                                'value' => admin_ecotrack_format_field_value($pdo, $key, $value)
                                            ];
                                        }
                                        ?>
                                        <?php echo admin_order_details_render_card_grid($tracking_summary_cells, 'ecotrack-card-grid-wide'); ?>

                                        <?php
                                        $activity_rows = [];
                                        if (!empty($tracking_payload['activity']) && is_array($tracking_payload['activity'])) {
                                            $activity_rows = $tracking_payload['activity'];
                                        } elseif (!empty($tracking_payload['activities']) && is_array($tracking_payload['activities'])) {
                                            $activity_rows = $tracking_payload['activities'];
                                        }
                                        $activity_rows = admin_ecotrack_translate_timeline_rows($pdo, $activity_rows);
                                        ?>
                                        <?php if (!empty($activity_rows)): ?>
                                            <?php $latest_tracking_event = admin_ecotrack_pick_latest_timeline_row($activity_rows); ?>
                                            <?php if (!empty($latest_tracking_event)): ?>
                                                <label>آخر تحديث تتبع</label>
                                                <?php
                                                echo admin_order_details_render_card_grid([
                                                    ['label' => 'الحالة', 'value' => (string) ($latest_tracking_event['status'] ?? '')],
                                                    ['label' => 'التاريخ', 'value' => (string) ($latest_tracking_event['date'] ?? '')],
                                                    ['label' => 'الوقت', 'value' => (string) ($latest_tracking_event['time'] ?? '')],
                                                    ['label' => 'المحطة', 'value' => (string) ($latest_tracking_event['station'] ?? '')],
                                                ], 'ecotrack-card-grid-wide');
                                                ?>
                                            <?php endif; ?>
                                            <label>سجل النشاط</label>
                                            <?php
                                            echo admin_order_details_render_timeline(
                                                $activity_rows,
                                                [
                                                    'date' => 'التاريخ',
                                                    'time' => 'الوقت'
                                                ],
                                                [
                                                    'status' => 'الحالة',
                                                    'station' => 'المحطة'
                                                ]
                                            );
                                            ?>
                                        <?php endif; ?>

                                        <details style="margin-top:8px;">
                                            <summary style="cursor:pointer;">عرض JSON الخام</summary>
                                            <textarea class="form-control" rows="10" readonly><?php echo htmlspecialchars($ecotrack_last_tracking_info, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </details>
                                    <?php else: ?>
                                        <div class="form-group">
                                            <label>آخر سجل تتبع محفوظ</label>
                                            <textarea class="form-control" rows="12" readonly><?php echo htmlspecialchars($ecotrack_last_tracking_info, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if (!empty($ecotrack_updates_list)): ?>
                                    <label>آخر قائمة ملاحظات محفوظة</label>
                                    <?php
                                    echo admin_order_details_render_timeline(
                                        $ecotrack_updates_list,
                                        ['created_at' => 'التاريخ'],
                                        [
                                            'remarque' => 'الملاحظة',
                                            'station' => 'المحطة',
                                            'livreur' => 'الناقل'
                                        ]
                                    );
                                    ?>
                                <?php elseif ($ecotrack_last_updates_raw !== ''): ?>
                                    <div class="form-group">
                                        <label>آخر قائمة ملاحظات محفوظة</label>
                                        <textarea class="form-control" rows="12" readonly><?php echo htmlspecialchars($ecotrack_last_updates_raw, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

        <div class="tab-pane" id="tab_zrexpress">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title">ZRexpress</h3>
                        </div>
                        <div class="box-body">
                            <div class="order-details-ecotrack-hero">
                                <div>
                                    <h4><i class="fa fa-truck"></i> تفاصيل الشحنة المرتبطة بالطلب (ZRexpress)</h4>
                                    <p>مراجعة الحالة الحالية، تنفيذ إجراءات الربط، وتتبع آخر تحديثات ZRexpress.</p>
                                </div>
                            </div>
                            <?php if (!$zrexpress_ready): ?>
                                <div class="callout callout-warning">
                                إعداد ZRexpress غير مكتمل بعد. أضف التوكن والمفتاح من <a href="settings.php#tab_zrexpress">إعدادات الموقع</a> أولاً.
                                </div>
                            <?php endif; ?>

                            <div id="zrexpressAjaxFeedback"></div>
                            
                            <div class="row">
                                <div class="col-md-7">
                                    <?php
                                    $zrexpress_status_summary = [
                                        'حالة الربط' => $zrexpress_status['label'],
                                        'المرجع المحلي' => $zrexpress_reference,
                                        'رقم التتبع' => $zrexpress_tracking !== '' ? $zrexpress_tracking : 'غير متوفر',
                                        'الحالة البعيدة' => $zrexpress_remote_status !== '' ? $zrexpress_remote_status : 'لم تتم المزامنة بعد',
                                        'آخر إرسال' => $zrexpress_sent_at !== '' ? date('d/m/Y H:i', strtotime($zrexpress_sent_at)) : 'لم يرسل بعد'
                                    ];
                                    $zrexpress_status_cards = [];
                                    foreach ($zrexpress_status_summary as $summary_label => $summary_value) {
                                        $zrexpress_status_cards[] = [
                                            'label' => (string) $summary_label,
                                            'value' => $summary_label === 'حالة الربط'
                                                ? '<span class="' . htmlspecialchars($zrexpress_status['class'], ENT_QUOTES, 'UTF-8') . '" style="padding: 4px 10px; border-radius: 4px;">' . htmlspecialchars((string) $summary_value, ENT_QUOTES, 'UTF-8') . '</span>'
                                                : (string) $summary_value,
                                            'html' => $summary_label === 'حالة الربط'
                                        ];
                                    }
                                    ?>
                                    <div class="order-details-ecotrack-card">
                                        <h4><i class="fa fa-info-circle"></i> معلومات الشحنة</h4>
                                        <?php echo admin_order_details_render_card_grid($zrexpress_status_cards); ?>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <?php if ($zrexpress_last_error !== ''): ?>
                                        <div class="callout callout-danger" style="border-radius: 8px; box-shadow: none;">
                                            <strong><i class="fa fa-exclamation-triangle"></i> آخر خطأ:</strong><br>
                                            <?php echo nl2br(htmlspecialchars($zrexpress_last_error, ENT_QUOTES, 'UTF-8')); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="callout callout-success" style="border-radius: 8px; box-shadow: none; background: #ecfdf5; border-color: #10b981; color: #065f46;">
                                            <i class="fa fa-check-circle"></i> لا توجد أخطاء حالياً. الربط سليم.
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($zrexpress_last_response !== ''): ?>
                                        <details style="background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; margin-bottom: 10px;">
                                            <summary style="cursor:pointer; font-weight: bold; color: #475569;">عرض آخر رد (Response)</summary>
                                            <div style="margin-top:8px;">
                                                <textarea class="form-control" rows="4" style="font-size:12px; font-family:monospace;" readonly><?php echo htmlspecialchars($zrexpress_last_response, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="order-details-ecotrack-card">
                                <h4><i class="fa fa-cogs"></i> الإجراءات المتاحة</h4>
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    <?php if ($zrexpress_ready): ?>
                                        <?php if ($zrexpress_tracking === ''): ?>
                                            <form method="post" action="order-zrexpress-action.php">
                                                <?php $csrf->echoInputField(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                <input type="hidden" name="action" value="create">
                                                <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                                <button type="submit" class="btn btn-success" style="padding: 8px 20px; font-weight: bold;"><i class="fa fa-paper-plane"></i> إرسال إلى ZRexpress</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="order-zrexpress-action.php">
                                                <?php $csrf->echoInputField(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                <input type="hidden" name="action" value="sync">
                                                <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                                <button type="submit" class="btn btn-info"><i class="fa fa-exchange"></i> مزامنة الحالة</button>
                                            </form>
                                            <form method="post" action="order-zrexpress-action.php">
                                                <?php $csrf->echoInputField(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                <input type="hidden" name="action" value="pret">
                                                <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                                <button type="submit" class="btn btn-warning"><i class="fa fa-truck"></i> تغيير الحالة إلى جاهز للشحن</button>
                                            </form>
                                            <form method="post" action="order-zrexpress-action.php">
                                                <?php $csrf->echoInputField(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="redirect" value="order-details.php?id=<?php echo (int) $order['id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('هل تريد بالتأكيد حذف رقم التتبع محلياً؟');"><i class="fa fa-trash"></i> حذف محلي</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane" id="tab_telegram">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">نشاط التلغرام</h3>
                </div>
                <div class="box-body">
                    <?php if (!empty($telegram_cancellation)): ?>
                        <div class="callout callout-danger">
                            <h4><i class="fa fa-ban"></i> تم إلغاء الطلب عبر التلغرام</h4>
                            <p><strong>السبب:</strong> <?php echo htmlspecialchars($telegram_cancellation['reason'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>بواسطة:</strong> <?php echo htmlspecialchars($telegram_cancellation['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>التاريخ:</strong> <?php echo date('d/m/Y H:i', strtotime($telegram_cancellation['created_at'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($telegram_actions_log)): ?>
                        <h4 style="margin-top:0;">سجل الإجراءات</h4>
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الإجراء</th>
                                    <th>الموظف</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ai = 0; foreach ($telegram_actions_log as $alog): $ai++; ?>
                                <tr>
                                    <td><?php echo $ai; ?></td>
                                    <td>
                                        <?php
                                        $action_labels = [
                                            'confirm' => '<span class="label label-primary">تأكيد</span>',
                                            'cancel' => '<span class="label label-danger">إلغاء</span>',
                                            'edit_pending' => '<span class="label label-warning">تعديل (قيد)</span>',
                                            'edit_completed' => '<span class="label label-success">تعديل (تم)</span>',
                                        ];
                                        echo $action_labels[$alog['action_type']] ?? '<span class="label label-default">' . htmlspecialchars($alog['action_type'], ENT_QUOTES, 'UTF-8') . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($alog['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($alog['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($telegram_edits)): ?>
                        <h4>سجل التعديلات</h4>
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الحقل</th>
                                    <th>القيمة القديمة</th>
                                    <th>القيمة الجديدة</th>
                                    <th>الموظف</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $ei = 0; foreach ($telegram_edits as $edit): $ei++; ?>
                                <tr>
                                    <td><?php echo $ei; ?></td>
                                    <td><?php echo htmlspecialchars(telegram_get_field_label($edit['field_name']), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($edit['old_value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars((string) ($edit['new_value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($edit['employee_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($edit['edited_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php
                    $event_logs = [];
                    if (function_exists('telegram_was_event_recently_sent')) {
                        $stmt_ev = $dbRepo->prepare("SELECT * FROM tbl_event_log WHERE order_id = ? ORDER BY created_at DESC LIMIT 20");
                        $stmt_ev->execute([(int) $order['id']]);
                        $event_logs = $stmt_ev->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ?>
                    <?php if (!empty($event_logs)): ?>
                        <h4>سجل أحداث المراقبة</h4>
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr><th>الحدث</th><th>التاريخ</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($event_logs as $ev): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $ev_labels = [
                                            'unprocessed_order' => '<span class="label label-warning">طلب غير معالج</span>',
                                            'unassigned_orders' => '<span class="label label-danger">غير موزع</span>',
                                            'ecotrack_status_changed' => '<span class="label label-info">تحديث ECOTRACK</span>',
                                            'delivered' => '<span class="label label-success">تم التسليم</span>',
                                            'returned' => '<span class="label label-danger">مرتجع</span>',
                                        ];
                                        echo $ev_labels[$ev['event_type']] ?? '<span class="label label-default">' . htmlspecialchars($ev['event_type'], ENT_QUOTES, 'UTF-8') . '</span>';
                                        ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($ev['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (empty($telegram_cancellation) && empty($telegram_actions_log) && empty($telegram_edits) && empty($event_logs)): ?>
                        <div class="text-muted text-center" style="padding:20px;">
                            <i class="fa fa-telegram" style="font-size:48px;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
                            لا توجد إجراءات تلغرام لهذا الطلب.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
        </div>

        <div class="tab-pane" id="tab_audit">
    <div class="row">
        <div class="col-md-12">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title">سجل التدقيق (Audit Trail)</h3>
                </div>
                <div class="box-body">
                    <?php
                    $audit_logs = audit_get_for_entity($pdo, 'order', $order_id, 200);
                    ?>
                    <?php if (!empty($audit_logs)): ?>
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الإجراء</th>
                                <th>المصدر</th>
                                <th>المنفذ</th>
                                <th>القيمة القديمة</th>
                                <th>القيمة الجديدة</th>
                                <th>IP</th>
                                <th>التاريخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($audit_logs as $al): ?>
                            <tr>
                                <td><?php echo (int) $al['id']; ?></td>
                                <td>
                                    <i class="fa <?php echo audit_get_action_icon($al['action_type']); ?>"></i>
                                    <?php echo htmlspecialchars(audit_get_action_label($al['action_type']), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><span class="label label-default"><?php echo htmlspecialchars(audit_get_source_label($al['source']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td><small><?php echo htmlspecialchars($al['performed_by_type'] . '#' . $al['performed_by_id'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;font-size:12px;">
                                    <?php if ($al['old_value'] !== null && $al['old_value'] !== ''): ?>
                                        <span class="text-danger"><s><?php echo htmlspecialchars(mb_substr((string) $al['old_value'], 0, 150), ENT_QUOTES, 'UTF-8'); ?></s></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;font-size:12px;">
                                    <?php if ($al['new_value'] !== null && $al['new_value'] !== ''): ?>
                                        <span class="text-success"><?php echo htmlspecialchars(mb_substr((string) $al['new_value'], 0, 150), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($al['ip_address'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                <td><small><?php echo date('d/m/Y H:i', strtotime($al['created_at'])); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-muted text-center" style="padding:20px;">
                        <i class="fa fa-history" style="font-size:48px;color:#cbd5e1;display:block;margin-bottom:12px;"></i>
                        لا توجد إجراءات تدقيق لهذا الطلب.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
        </div>

        <!-- ERP Phase 8: Timeline Tab -->
        <div class="tab-pane" id="tab_timeline">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">السجل الزمني للطلب (Timeline)</h3>
                </div>
                <div class="box-body">
                    <?php
                    $stmt = $dbRepo->prepare("SELECT t.*, u.full_name as user_name, c.name as company_name FROM tbl_order_timeline t LEFT JOIN tbl_user u ON t.user_id = u.id LEFT JOIN tbl_delivery_company c ON t.delivery_company_id = c.id WHERE t.order_id = ? ORDER BY t.created_at DESC");
                    $stmt->execute([$order['id']]);
                    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if(empty($timeline)): ?>
                        <p class="text-center text-muted">لا توجد أحداث مسجلة لهذا الطلب.</p>
                    <?php else: ?>
                    <ul class="timeline">
                        <?php foreach($timeline as $tl): ?>
                        <li>
                            <i class="fa fa-clock-o bg-blue"></i>
                            <div class="timeline-item">
                                <span class="time"><i class="fa fa-clock-o"></i> <?= $tl['created_at']; ?></span>
                                <h3 class="timeline-header"><?= htmlspecialchars($tl['action']); ?></h3>
                                <div class="timeline-body">
                                    <?= nl2br(htmlspecialchars($tl['description'] ?? '')); ?>
                                </div>
                                <div class="timeline-footer">
                                    <span class="text-muted"><i class="fa fa-user"></i> <?= $tl['user_name'] ? htmlspecialchars($tl['user_name']) : 'النظام/API'; ?></span>
                                    <?php if($tl['company_name']): ?>
                                        &nbsp;|&nbsp; <span class="text-muted"><i class="fa fa-truck"></i> <?= htmlspecialchars($tl['company_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <li><i class="fa fa-clock-o bg-gray"></i></li>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ERP Phase 8: API Logs Tab -->
        <div class="tab-pane" id="tab_api_logs">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">سجل التخاطب مع شركة التوصيل (API Logs)</h3>
                </div>
                <div class="box-body table-responsive">
                    <?php
                    $stmt = $dbRepo->prepare("SELECT l.*, c.name as company_name FROM tbl_api_request_log l LEFT JOIN tbl_delivery_company c ON l.delivery_company_id = c.id WHERE l.order_id = ? ORDER BY l.created_at DESC");
                    $stmt->execute([$order['id']]);
                    $api_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if(empty($api_logs)): ?>
                        <p class="text-center text-muted">لا توجد سجلات API لهذا الطلب.</p>
                    <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>الشركة</th>
                                <th>الطلب (Endpoint)</th>
                                <th>رد الخادم (Code)</th>
                                <th>الزمن المستغرق</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($api_logs as $log): ?>
                            <tr>
                                <td><?= $log['created_at']; ?></td>
                                <td><?= htmlspecialchars($log['company_name'] ?? '-'); ?></td>
                                <td><span class="label label-default"><?= $log['method']; ?></span> <?= htmlspecialchars($log['endpoint']); ?></td>
                                <td>
                                    <?php if($log['http_code'] >= 200 && $log['http_code'] < 300): ?>
                                        <span class="label label-success"><?= $log['http_code']; ?></span>
                                    <?php else: ?>
                                        <span class="label label-danger"><?= $log['http_code']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $log['response_time_ms']; ?> ms</td>
                                <td>
                                    <button class="btn btn-default btn-xs" onclick="alert('Request:\n<?= addslashes(htmlspecialchars($log['request_body'] ?? '')); ?>\n\nResponse:\n<?= addslashes(htmlspecialchars($log['response_body'] ?? '')); ?>');">عرض Payload</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>


    </div>
</section>

<div id="ecotrackActionOverlay" aria-hidden="true">
    <div class="ecotrack-action-dialog" id="ecotrackActionDialog">
        <div class="ecotrack-action-dialog-head">
            <div class="ecotrack-action-dialog-spinner"></div>
            <div class="ecotrack-action-dialog-title" id="ecotrackActionDialogTitle">تنفيذ عملية ECOTRACK</div>
        </div>
        <div class="ecotrack-action-dialog-text" id="ecotrackActionDialogText">جارٍ تنفيذ العملية...</div>
        <div class="ecotrack-action-dialog-actions">
            <button type="button" class="btn btn-default" id="ecotrackActionDialogClose" style="display:none;">إغلاق</button>
        </div>
    </div>
</div>

<script>
window.addEventListener('load', function() {
    var orderDetailsStateKey = 'order-details-view-state-<?php echo (int) $order_id; ?>';
    var ecotrackActionOverlay = document.getElementById('ecotrackActionOverlay');
    var ecotrackActionDialog = document.getElementById('ecotrackActionDialog');
    var ecotrackActionDialogTitle = document.getElementById('ecotrackActionDialogTitle');
    var ecotrackActionDialogText = document.getElementById('ecotrackActionDialogText');
    var ecotrackActionDialogClose = document.getElementById('ecotrackActionDialogClose');

    function escapeHtml(value) { global $dbRepo;
    global $dbRepo;

        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function arrangeEcotrackTab() {        var ecotrackToolbarTop = document.getElementById('ecotrackToolbarTop');
        var ecotrackActionsBlock = document.getElementById('ecotrackActionsBlock');

        if (ecotrackToolbarTop && ecotrackActionsBlock && ecotrackToolbarTop.parentNode) {
            if (ecotrackToolbarTop.nextElementSibling !== ecotrackActionsBlock) {
                ecotrackToolbarTop.parentNode.insertBefore(ecotrackActionsBlock, ecotrackToolbarTop.nextSibling);
            }
        }
    }

    function openEcotrackActionOverlay(title, text) { global $dbRepo;
    global $dbRepo;

        if (!ecotrackActionOverlay || !ecotrackActionDialog) {
            return;
        }

        ecotrackActionDialog.classList.remove('is-done', 'is-error');
        if (ecotrackActionDialogTitle) {
            ecotrackActionDialogTitle.textContent = title || 'تنفيذ عملية ECOTRACK';
        }
        if (ecotrackActionDialogText) {
            ecotrackActionDialogText.textContent = text || 'جارٍ تنفيذ العملية...';
        }
        if (ecotrackActionDialogClose) {
            ecotrackActionDialogClose.style.display = 'none';
        }
        ecotrackActionOverlay.classList.add('is-visible');
        ecotrackActionOverlay.setAttribute('aria-hidden', 'false');
    }

    function finishEcotrackActionOverlay(state, text) { global $dbRepo;
    global $dbRepo;

        if (!ecotrackActionOverlay || !ecotrackActionDialog) {
            return;
        }

        ecotrackActionDialog.classList.remove('is-done', 'is-error');
        if (state === 'success') {
            ecotrackActionDialog.classList.add('is-done');
        } else if (state === 'error') {
            ecotrackActionDialog.classList.add('is-error');
        }

        if (ecotrackActionDialogText) {
            ecotrackActionDialogText.textContent = text || '';
        }
        if (ecotrackActionDialogClose) {
            ecotrackActionDialogClose.style.display = 'inline-block';
        }
    }

    function closeEcotrackActionOverlay() {        if (!ecotrackActionOverlay) {
            return;
        }

        ecotrackActionOverlay.classList.remove('is-visible');
        ecotrackActionOverlay.setAttribute('aria-hidden', 'true');
    }

    function showEcotrackAjaxFeedback(type, message) { global $dbRepo;
    global $dbRepo;

        var feedbackContainer = document.getElementById('ecotrackAjaxFeedback');
        if (!feedbackContainer) {
            return;
        }

        if (!message) {
            feedbackContainer.innerHTML = '';
            return;
        }

        feedbackContainer.innerHTML = '<div class="callout callout-' + escapeHtml(type || 'info') + '" style="margin-bottom:12px;">' + escapeHtml(message) + '</div>';
    }

    arrangeEcotrackTab();

    if (ecotrackActionDialogClose) {
        ecotrackActionDialogClose.addEventListener('click', closeEcotrackActionOverlay);
    }

    function getActiveOrderDetailsTab() {        var activeTabLink = document.querySelector('#orderDetailsTabs li.active a[href^="#"]')
            || document.querySelector('#orderDetailsTabs a[aria-expanded="true"][href^="#"]');
        if (activeTabLink && activeTabLink.getAttribute('href')) {
            return activeTabLink.getAttribute('href');
        }

        if (window.location.hash && document.querySelector('#orderDetailsTabs a[href="' + window.location.hash + '"]')) {
            return window.location.hash;
        }

        return '#tab_summary';
    }

    function updateOrderDetailsHash(hash) { global $dbRepo;
    global $dbRepo;

        if (!hash) {
            return;
        }

        if (window.history && typeof window.history.replaceState === 'function') {
            window.history.replaceState(null, '', window.location.pathname + window.location.search + hash);
            return;
        }

        window.location.hash = hash;
    }

    function persistOrderDetailsViewState() {        var state = {
            hash: getActiveOrderDetailsTab(),
            scrollY: window.pageYOffset || document.documentElement.scrollTop || 0
        };

        try {
            sessionStorage.setItem(orderDetailsStateKey, JSON.stringify(state));
        } catch (error) {
        }

        document.querySelectorAll('input[name="redirect"]').forEach(function(input) {
            var baseRedirect = input.getAttribute('data-base-redirect');
            if (!baseRedirect) {
                baseRedirect = String(input.value || '').replace(/#.*/, '');
                input.setAttribute('data-base-redirect', baseRedirect);
            }

            if (/^order-details\.php\?id=\d+$/.test(baseRedirect)) {
                input.value = baseRedirect + state.hash;
            }
        });
    }

    function readStoredOrderDetailsViewState() {        try {
            var rawState = sessionStorage.getItem(orderDetailsStateKey);
            if (!rawState) {
                return null;
            }

            var parsedState = JSON.parse(rawState);
            sessionStorage.removeItem(orderDetailsStateKey);
            return parsedState && typeof parsedState === 'object' ? parsedState : null;
        } catch (error) {
            return null;
        }
    }

    var restoredOrderDetailsState = readStoredOrderDetailsViewState();
    if (!window.location.hash && restoredOrderDetailsState && restoredOrderDetailsState.hash && document.querySelector('#orderDetailsTabs a[href="' + restoredOrderDetailsState.hash + '"]')) {
        updateOrderDetailsHash(restoredOrderDetailsState.hash);
    }

    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            persistOrderDetailsViewState();
        });
    });

    document.querySelectorAll('#tab_ecotrack a.btn[href]').forEach(function(link) {
        if (String(link.getAttribute('target') || '').toLowerCase() === '_blank') {
            return;
        }

        link.addEventListener('click', function() {
            persistOrderDetailsViewState();
        });
    });

    if (typeof jQuery === 'undefined') {
        if (restoredOrderDetailsState && Number.isFinite(parseInt(restoredOrderDetailsState.scrollY, 10))) {
            window.scrollTo(0, parseInt(restoredOrderDetailsState.scrollY, 10));
        }
        return;
    }

    (function($) {
        var tabSwitchScrollY = null;

        if (window.location.hash) {
            var hash = window.location.hash;
            if ($(hash).length) {
                $('#orderDetailsTabs a[href="' + hash + '"]').tab('show');
            }
        }

        $('#orderDetailsTabs a[data-toggle="tab"]').on('click', function() {
            tabSwitchScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        });

        $('#orderDetailsTabs a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (e.target && e.target.getAttribute) {
                var target = e.target.getAttribute('href');
                if (target) {
                    updateOrderDetailsHash(target);
                }
            }

            if (tabSwitchScrollY !== null) {
                var preservedScrollY = tabSwitchScrollY;
                var restoreOrderDetailsScroll = function() {
                    window.scrollTo(0, preservedScrollY);
                };

                window.requestAnimationFrame(restoreOrderDetailsScroll);
                setTimeout(restoreOrderDetailsScroll, 60);
                tabSwitchScrollY = null;
            }
        });

        $('#orderDetailsTabs a[data-toggle="tab"]').on('hide.bs.tab', function() {
            if (tabSwitchScrollY === null) {
                tabSwitchScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
            }
        });

        if (!window.location.hash && restoredOrderDetailsState && restoredOrderDetailsState.hash && $(restoredOrderDetailsState.hash).length) {
            var restoredScrollBeforeTabShow = Number.isFinite(parseInt(restoredOrderDetailsState.scrollY, 10))
                ? parseInt(restoredOrderDetailsState.scrollY, 10)
                : (window.pageYOffset || document.documentElement.scrollTop || 0);
            tabSwitchScrollY = restoredScrollBeforeTabShow;
            $('#orderDetailsTabs a[href="' + restoredOrderDetailsState.hash + '"]').tab('show');
        } else if (window.location.hash && $('#orderDetailsTabs a[href="' + window.location.hash + '"]').length) {
            updateOrderDetailsHash(window.location.hash);
            if (tabSwitchScrollY !== null) {
                window.scrollTo(0, tabSwitchScrollY);
                tabSwitchScrollY = null;
            }
        }

        if (!window.location.hash && restoredOrderDetailsState && Number.isFinite(parseInt(restoredOrderDetailsState.scrollY, 10))) {
            var restoredScrollY = parseInt(restoredOrderDetailsState.scrollY, 10);
            var restoreScrollPosition = function() {
                window.scrollTo(0, restoredScrollY);
            };

            window.requestAnimationFrame(restoreScrollPosition);
            setTimeout(restoreScrollPosition, 120);
            setTimeout(restoreScrollPosition, 350);
        }

        if (!restoredOrderDetailsState && !window.location.hash) {
            updateOrderDetailsHash(getActiveOrderDetailsTab());
        } else if (!restoredOrderDetailsState && window.location.hash) {
            updateOrderDetailsHash(window.location.hash);
        }

        var smsTemplates = <?php echo json_encode($sms_template_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var smsContext = {
            order_id: <?php echo json_encode((string) ($order['id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            customer_name: <?php echo json_encode((string) ($order['customer_name'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            customer_phone: <?php echo json_encode((string) ($order['customer_phone'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            phone: <?php echo json_encode((string) ($order['customer_phone'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            phone_local: <?php echo json_encode((string) ($order['customer_phone'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            phone_e164: <?php echo json_encode((string) ($order['customer_phone'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            product_name: <?php echo json_encode((string) ($order['product_name'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            quantity: <?php echo json_encode((string) ($order['quantity'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            total_price: <?php echo json_encode(sms_gateway_format_money($order['total_price'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            total_price_formatted: <?php echo json_encode(sms_gateway_format_money($order['total_price'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            status: <?php echo json_encode((string) ($order['order_status'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            wilaya: <?php echo json_encode((string) ($order['wilaya'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            commune: <?php echo json_encode((string) ($order['commune'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            address: <?php echo json_encode((string) ($order['address'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
            site_name: <?php echo json_encode($sms_site_name, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
        };

        function renderSmsTemplate(template, context) { global $dbRepo;
    global $dbRepo;

            var output = template || '';
            Object.keys(context).forEach(function(key) {
                output = output.split('{{' + key + '}}').join(context[key] || '');
            });
            return output;
        }

        function reloadEcotrackPanel(responsePayload, afterReload) { global $dbRepo;
    global $dbRepo;

            $('#ecotrackPanelContent').load('order-details.php?id=<?php echo (int) $order_id; ?> #ecotrackPanelContent > *', function(response, status) {
                arrangeEcotrackTab();
                if (responsePayload && responsePayload.message) {
                    showEcotrackAjaxFeedback(responsePayload.type || (responsePayload.success ? 'success' : 'danger'), responsePayload.message);
                }
                if (typeof afterReload === 'function') {
                    afterReload(status === 'success');
                }
            });
        }

        // تم حذف تبويب SMS من هذه الصفحة، نترك هذا الحارس لتفادي أخطاء JS إن وُجدت عناصر بالصدفة.
        if ($('#detailsSmsTemplate').length) {
            $('#detailsSmsTemplate').on('change', function() {
                var selectedId = $(this).val();
                var match = smsTemplates.find(function(template) {
                    return String(template.id || '') === String(selectedId || '');
                });
                $('#detailsSmsMessage').val(match ? renderSmsTemplate(match.body || '', smsContext) : '');
            });
        }

        var orderEditShippingFees = <?php echo json_encode($order_edit_shipping_fees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var editQty = document.getElementById('editOrderQuantity');
        var editUnitPrice = document.getElementById('editOrderUnitPrice');
        var editTotalPrice = document.getElementById('editOrderTotalPrice');
        var editRecalculateButton = document.getElementById('editOrderRecalculate');
        var editDeliveryType = document.getElementById('editOrderDeliveryType');
        var editWilaya = document.getElementById('editOrderWilaya');

        function getEditShippingFee() {            if (!editDeliveryType) {
                return 0;
            }

            var deliveryType = String(editDeliveryType.value || '').trim();
            if (deliveryType === 'مجاني') {
                return 0;
            }

            if (!editWilaya) {
                return 0;
            }

            var wilaya = String(editWilaya.value || '').trim();
            if (!wilaya || !orderEditShippingFees || typeof orderEditShippingFees !== 'object') {
                return 0;
            }

            var wilayaFees = orderEditShippingFees[wilaya];
            if (!wilayaFees || typeof wilayaFees !== 'object') {
                return 0;
            }

            var fee = Number(wilayaFees[deliveryType] || 0);
            return isFinite(fee) && fee > 0 ? fee : 0;
        }

        function recalculateEditOrderTotal() {            if (!editQty || !editUnitPrice || !editTotalPrice) {
                return;
            }

            var quantity = parseInt(editQty.value || '0', 10);
            var unitPrice = parseFloat(editUnitPrice.value || '0');
            if (!isFinite(quantity) || quantity < 1 || !isFinite(unitPrice) || unitPrice < 0) {
                return;
            }

            var shippingFee = getEditShippingFee();
            editTotalPrice.value = ((quantity * unitPrice) + shippingFee).toFixed(2);
        }

        if (editRecalculateButton) {
            editRecalculateButton.addEventListener('click', recalculateEditOrderTotal);
        }

        // حساب تلقائي مباشر عند تغيير الكمية أو سعر الوحدة (بدون انتظار الضغط على الزر).
        if (editQty) {
            editQty.addEventListener('input', recalculateEditOrderTotal);
            editQty.addEventListener('change', recalculateEditOrderTotal);
        }
        if (editUnitPrice) {
            editUnitPrice.addEventListener('input', recalculateEditOrderTotal);
            editUnitPrice.addEventListener('change', recalculateEditOrderTotal);
        }
        if (editDeliveryType) {
            editDeliveryType.addEventListener('change', recalculateEditOrderTotal);
        }
        if (editWilaya) {
            editWilaya.addEventListener('input', recalculateEditOrderTotal);
            editWilaya.addEventListener('change', recalculateEditOrderTotal);
        }

        $(document).on('click', '#copyEcotrackPayload', function() {
            var ecotrackPayloadPreview = document.getElementById('ecotrackPayloadPreview');
            var copyEcotrackPayloadButton = this;
            if (!ecotrackPayloadPreview) {
                return;
            }

            ecotrackPayloadPreview.focus();
            ecotrackPayloadPreview.select();

            try {
                document.execCommand('copy');
                copyEcotrackPayloadButton.innerHTML = '<i class="fa fa-check"></i> تم النسخ';
                setTimeout(function() {
                    copyEcotrackPayloadButton.innerHTML = '<i class="fa fa-copy"></i> نسخ الحمولة';
                }, 1800);
            } catch (error) {
                copyEcotrackPayloadButton.innerHTML = '<i class="fa fa-times"></i> تعذر النسخ';
            }
        });

        $(document).on('click', '#tab_ecotrack form[action="order-ecotrack-action.php"] button[type="submit"]', function() {
            $(this.form).data('submitLabel', $.trim($(this).text()).replace(/\s+/g, ' '));
        });

        $(document).on('submit', '#tab_ecotrack form[action="order-ecotrack-action.php"]', function(event) {
            event.preventDefault();

            var $form = $(this);
            var actionValue = String($form.find('input[name="action"]').val() || '').toLowerCase();
            var submitLabel = String($form.data('submitLabel') || 'تنفيذ عملية ECOTRACK');
            var noteInput = $form.find('input[name="content"]');
            var noteValue = noteInput.length ? $.trim(noteInput.val()) : '';

            if (actionValue === 'add_note') {
                if (!noteValue) {
                    showEcotrackAjaxFeedback('warning', 'أدخل ملاحظة التتبع أولاً.');
                    return;
                }
                if (noteValue.length > 255) {
                    showEcotrackAjaxFeedback('warning', 'ملاحظة التتبع يجب ألا تتجاوز 255 حرفًا.');
                    return;
                }
            }

            openEcotrackActionOverlay(submitLabel, 'جارٍ تنفيذ العملية داخل ECOTRACK...');

            var payload = $form.serializeArray();
            payload.push({ name: 'ajax', value: '1' });

            $.ajax({
                url: $form.attr('action'),
                method: 'POST',
                data: $.param(payload),
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).done(function(response) {
                reloadEcotrackPanel(response, function(reloadOk) {
                    if (reloadOk) {
                        finishEcotrackActionOverlay(response && response.success ? 'success' : 'error', response && response.message ? response.message : 'تم تنفيذ العملية.');
                    } else {
                        finishEcotrackActionOverlay('error', 'تم تنفيذ العملية لكن تعذر تحديث قسم ECOTRACK تلقائيًا.');
                    }

                    if (response && response.success) {
                        setTimeout(closeEcotrackActionOverlay, 1400);
                    }
                });
            }).fail(function(xhr) {
                var errorMessage = 'تعذر تنفيذ العملية حاليًا.';

                if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }

                finishEcotrackActionOverlay('error', errorMessage);
                showEcotrackAjaxFeedback('danger', errorMessage);
            });
        });
    })(jQuery);
});
</script>


<script>
window.addEventListener("load", function() {
    if (window.location.hash) {
        var $targetTab = $('#orderDetailsTabs a[href="' + window.location.hash + '"]');
        if ($targetTab.length) {
            $targetTab.tab("show");
        }
    }

    function toggleParentSaveBtn(target) { global $dbRepo;
    global $dbRepo;

        var showSave = (target === '#tab_edit' || target === '#tab_summary'); // Allow save from summary tab too
        if (window.parent && window.parent.document.getElementById('iframeDrawerSaveBtn')) {
            window.parent.document.getElementById('iframeDrawerSaveBtn').style.display = showSave ? 'inline-block' : 'none';
        }
    }

    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        toggleParentSaveBtn($(e.target).attr("href"));
    });

    // Run once on load
    var initialTab = $('#orderDetailsTabs li.active a').attr('href');
    if (initialTab) {
        toggleParentSaveBtn(initialTab);
    }
});
</script>

<?php require_once('footer.php'); ?>
