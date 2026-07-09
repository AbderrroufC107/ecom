<?php
/**
 * AssistantContext.php
 * Builds a live, role-scoped data snapshot that is injected into the AI
 * assistant's system prompt so it "knows" the store — bounded by what the
 * current user is allowed to see.
 *
 *  - Super Admin / Admin  -> global store context (all stats + problem detection)
 *  - Employee (emp_<id>)  -> only their assigned orders, performance and earnings
 */

if (!function_exists('assistant_build_context')) {

    /**
     * @return array{scope:string,name:string,context:string}
     */
    function assistant_build_context(PDO $pdo, array $sessionUser): array
    {
        global $dbRepo;

        $idRaw      = (string) ($sessionUser['id'] ?? '');
        $name       = (string) ($sessionUser['full_name'] ?? 'المستخدم');
        $role       = (string) ($sessionUser['role'] ?? '');
        $isEmployee = strpos($idRaw, 'emp_') === 0;

        if ($isEmployee) {
            return [
                'scope'   => 'employee',
                'name'    => $name,
                'context' => assistant_employee_context($pdo, (int) substr($idRaw, 4), $name),
            ];
        }

        return [
            'scope'   => 'admin',
            'name'    => $name,
            'context' => assistant_admin_context($pdo, $name, $role, (int) ($sessionUser['id'] ?? 0)),
        ];
    }

    /** Human-friendly age from hours: "3 أيام" / "5 ساعات". */
    function assistant_age(int $hours): string
    {
        if ($hours >= 48) return intdiv($hours, 24) . ' يوماً';
        return $hours . ' ساعة';
    }

    /** Safe scalar query helper. */
    function assistant_scalar($stmt, array $params = [])
    {
        try {
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------
    // ADMIN / SUPER ADMIN — full store context + problem detection
    // ---------------------------------------------------------------
    function assistant_admin_context(PDO $pdo, string $name, string $role, int $userId = 0): string
    {
        global $dbRepo;
        $L = [];
        $L[] = "دور المستخدم: {$role} (وصول كامل). اسمه: {$name}.";

        try {
            // Status breakdown (all time)
            $rows = $dbRepo->query("SELECT order_status, COUNT(*) c FROM tbl_order GROUP BY order_status")->fetchAll(PDO::FETCH_ASSOC);
            $byStatus = [];
            $total = 0;
            foreach ($rows as $r) { $byStatus[$r['order_status'] ?: 'غير محدد'] = (int)$r['c']; $total += (int)$r['c']; }
            $parts = [];
            foreach ($byStatus as $k => $v) { $parts[] = "$k: $v"; }
            $L[] = "إجمالي الطلبات: {$total}. التوزيع حسب الحالة — " . implode(' | ', $parts) . '.';
        } catch (\Throwable $e) {}

        // Today & last 7 days
        $todayCount = assistant_scalar($dbRepo->prepare("SELECT COUNT(*) FROM tbl_order WHERE DATE(order_date)=CURDATE()"));
        $weekCount  = assistant_scalar($dbRepo->prepare("SELECT COUNT(*) FROM tbl_order WHERE order_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)"));
        $L[] = "طلبات اليوم: " . (int)$todayCount . " | آخر 7 أيام: " . (int)$weekCount . ".";

        // Revenue (completed)
        $rev30 = assistant_scalar($dbRepo->prepare("SELECT COALESCE(SUM(total_price),0) FROM tbl_order WHERE order_status IN ('Completed','Delivered') AND order_date>=DATE_SUB(NOW(),INTERVAL 30 DAY)"));
        $rev7  = assistant_scalar($dbRepo->prepare("SELECT COALESCE(SUM(total_price),0) FROM tbl_order WHERE order_status IN ('Completed','Delivered') AND order_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)"));
        $L[] = "إيراد الطلبات المكتملة — آخر 7 أيام: " . number_format((float)$rev7, 0) . " دج | آخر 30 يوماً: " . number_format((float)$rev30, 0) . " دج.";

        // Top products
        try {
            $tp = $dbRepo->query("SELECT product_name, COUNT(*) c FROM tbl_order WHERE order_date>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY product_name ORDER BY c DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            $items = array_map(fn($r) => trim($r['product_name']) . " ({$r['c']})", $tp);
            if ($items) $L[] = "أكثر المنتجات طلباً (30 يوماً): " . implode('، ', $items) . '.';
        } catch (\Throwable $e) {}

        // Top wilayas
        try {
            $tw = $dbRepo->query("SELECT wilaya, COUNT(*) c FROM tbl_order WHERE wilaya<>'' AND order_date>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY wilaya ORDER BY c DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            $items = array_map(fn($r) => trim($r['wilaya']) . " ({$r['c']})", $tw);
            if ($items) $L[] = "أعلى الولايات طلباً (30 يوماً): " . implode('، ', $items) . '.';
        } catch (\Throwable $e) {}

        // Employees
        $empActive = assistant_scalar($dbRepo->prepare("SELECT COUNT(*) FROM tbl_employee WHERE is_active=1"));
        if ($empActive !== null) $L[] = "عدد الموظفين النشطين: " . (int)$empActive . '.';

        // ---- Employees who received orders (active assignments) ----
        try {
            $er = $dbRepo->query("
                SELECT a.employee_id, e.full_name, COUNT(*) c
                FROM tbl_order_assignment a
                LEFT JOIN tbl_employee e ON e.id = a.employee_id
                WHERE a.status='active' AND a.employee_id IS NOT NULL
                GROUP BY a.employee_id ORDER BY c DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
            if ($er) {
                $items = array_map(function ($r) {
                    $nm = trim((string)($r['full_name'] ?? '')) ?: ('موظف محذوف #' . $r['employee_id']);
                    return "{$nm} ({$r['c']})";
                }, $er);
                $L[] = "عدد الموظفين الذين استقبلوا طلبات: " . count($er) . " — التوزيع: " . implode('، ', $items) . '.';
            } else {
                $L[] = "لا يوجد موظفون استقبلوا طلبات بعد.";
            }
        } catch (\Throwable $e) {}

        // ---- Orders distribution by manager (via the assigned employee's manager) ----
        // NOTE: order.manager_id is vestigial (never written), so we attribute an
        // order to a manager through its assigned employee's manager_id instead.
        try {
            $mg = $dbRepo->query("
                SELECT COALESCE(e.manager_id, 0) mid, u.full_name, COUNT(*) c
                FROM tbl_order_assignment a
                JOIN tbl_employee e ON e.id = a.employee_id
                LEFT JOIN tbl_user u ON u.id = e.manager_id
                WHERE a.status='active' AND a.employee_id IS NOT NULL
                GROUP BY COALESCE(e.manager_id, 0), u.full_name
                ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
            if ($mg) {
                $items = array_map(function ($r) use ($userId) {
                    if (empty($r['mid'])) return "بلا مدير محدّد ({$r['c']})";
                    $nm = trim((string)($r['full_name'] ?? '')) ?: ('مدير #' . $r['mid']);
                    $me = ((int)$r['mid'] === $userId) ? ' (أنت)' : '';
                    return "{$nm}{$me} ({$r['c']})";
                }, $mg);
                $L[] = "توزيع الطلبات حسب المدير (عبر موظفيهم): " . implode('، ', $items) . '.';
            }
        } catch (\Throwable $e) {}

        // ---- Delivery-company (ecotrack/zrexpress) status updates ----
        try {
            $dc = $dbRepo->query("
                SELECT id, ecotrack_status, ecotrack_remote_status, zrexpress_status, zrexpress_remote_status,
                       COALESCE(ecotrack_remote_time, ecotrack_sent_at) t
                FROM tbl_order
                WHERE (ecotrack_status IS NOT NULL AND ecotrack_status NOT IN ('', 'pending'))
                   OR (zrexpress_status IS NOT NULL AND zrexpress_status NOT IN ('', 'pending'))
                   OR (ecotrack_previous_order_status IS NOT NULL AND ecotrack_previous_order_status <> '' AND ecotrack_previous_order_status <> ecotrack_status)
                ORDER BY t DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
            if ($dc) {
                $items = array_map(function ($r) {
                    $st = trim((string)($r['ecotrack_remote_status'] ?: $r['ecotrack_status'] ?: $r['zrexpress_remote_status'] ?: $r['zrexpress_status']));
                    return "#{$r['id']} → " . ($st ?: 'تحديث');
                }, $dc);
                $L[] = "\nتحديثات شركة التوصيل (أحدث الطرود): " . implode('، ', $items) . '.';
            } else {
                $L[] = "\nلا توجد تحديثات جديدة من شركة التوصيل حالياً (كل الطرود قيد الانتظار).";
            }
        } catch (\Throwable $e) {}

        // ---- Problem detection ----
        $problems = [];

        // Pending orders needing follow-up + the responsible employee who didn't confirm them.
        try {
            $pend = $dbRepo->query("
                SELECT o.id, TIMESTAMPDIFF(HOUR, o.order_date, NOW()) hrs, e.full_name emp, a.employee_id
                FROM tbl_order o
                LEFT JOIN tbl_order_assignment a ON a.order_id = o.id AND a.status='active'
                LEFT JOIN tbl_employee e ON e.id = a.employee_id
                WHERE o.order_status='Pending' AND o.order_date < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY o.order_date ASC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
            if ($pend) {
                $lines = array_map(function ($r) {
                    $who = $r['employee_id']
                        ? ('الموظف ' . (trim((string)$r['emp']) ?: ('#' . $r['employee_id'])) . ' لم يؤكّده')
                        : 'غير موزّع على أي موظف';
                    return "  • طلب #{$r['id']} معلّق منذ " . assistant_age((int)$r['hrs']) . " — {$who}.";
                }, $pend);
                $problems[] = "⚠️ طلبات معلّقة تحتاج متابعة:\n" . implode("\n", $lines);
            }
        } catch (\Throwable $e) {}

        try {
            $unassigned = assistant_scalar($dbRepo->prepare("SELECT COUNT(*) FROM tbl_order o WHERE o.order_status IN ('Pending','Confirmed') AND NOT EXISTS (SELECT 1 FROM tbl_order_assignment a WHERE a.order_id=o.id AND a.status='active')"));
            if ((int)$unassigned > 0) $problems[] = "⚠️ {$unassigned} طلب غير موزّع على أي موظف.";
        } catch (\Throwable $e) {}

        try {
            $rate = $dbRepo->query("SELECT ROUND(SUM(order_status='Cancelled')/NULLIF(COUNT(*),0)*100,1) r, COUNT(*) c FROM tbl_order WHERE order_date>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC);
            if ($rate && (int)$rate['c'] >= 10 && (float)$rate['r'] >= 20) $problems[] = "⚠️ معدل الإلغاء آخر 7 أيام مرتفع: {$rate['r']}%.";
        } catch (\Throwable $e) {}

        try {
            $failed = assistant_scalar($dbRepo->prepare("SELECT COUNT(*) FROM tbl_order WHERE sync_status='failed' OR (ecotrack_last_error IS NOT NULL AND ecotrack_last_error<>'')"));
            if ((int)$failed > 0) $problems[] = "⚠️ {$failed} طلب فشلت مزامنة شركة التوصيل له.";
        } catch (\Throwable $e) {}

        if ($problems) {
            $L[] = "\nمشاكل مكتشفة تلقائياً:\n- " . implode("\n- ", $problems);
        } else {
            $L[] = "\nلا توجد مشاكل حرجة مكتشفة حالياً.";
        }

        return implode("\n", $L);
    }

    // ---------------------------------------------------------------
    // EMPLOYEE — strictly their own orders / performance / earnings
    // ---------------------------------------------------------------
    function assistant_employee_context(PDO $pdo, int $employeeId, string $name): string
    {
        global $dbRepo;

        if (!function_exists('employee_get_stats') && file_exists(__DIR__ . '/../employee_functions.php')) {
            require_once __DIR__ . '/../employee_functions.php';
        }

        $L = [];
        $L[] = "دور المستخدم: موظف (Employee). اسمه: {$name}. النطاق: بياناته الشخصية فقط.";

        $s = function_exists('employee_get_stats') ? employee_get_stats($pdo, $employeeId) : null;
        if ($s) {
            $total = (int)$s['total_assigned'];
            $completed = (int)$s['completed'];
            $delivery = $total > 0 ? round($completed / $total * 100, 1) : 0;
            $L[] = "طلباتك المُسندة: {$total} — مكتملة: {$completed}، مؤكّدة: {$s['confirmed']}، معلّقة: {$s['pending']}، ملغاة: {$s['cancelled']}، مرتجعة: {$s['returned']}.";
            $L[] = "معدل توصيلك: {$delivery}%.";

            $rate = (float)$s['commission_per_order'];
            $earned = $completed * $rate;
            $L[] = "عمولتك لكل طلب: " . number_format($rate, 2) . " دج | إجمالي عمولة الطلبات المكتملة: " . number_format($earned, 2) . " دج.";
            $L[] = "طلبات مكتملة غير مدفوعة العمولة: " . (int)$s['unpaid_completed'] . ".";
        } else {
            $L[] = "تعذّر جلب إحصائياتك حالياً.";
        }

        // Payments to date
        try {
            $paid = assistant_scalar($dbRepo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM tbl_employee_payments WHERE employee_id=?"), [$employeeId]);
            if ($paid !== null) $L[] = "إجمالي ما تم دفعه لك: " . number_format((float)$paid, 2) . " دج.";
        } catch (\Throwable $e) {}

        // Their stuck pending orders
        try {
            $stuck = $dbRepo->prepare("SELECT COUNT(*) c FROM tbl_order_assignment a INNER JOIN tbl_order o ON o.id=a.order_id WHERE a.employee_id=? AND a.status='active' AND o.order_status='Pending' AND o.order_date < DATE_SUB(NOW(),INTERVAL 24 HOUR)");
            $stuck->execute([$employeeId]);
            $c = (int)$stuck->fetchColumn();
            if ($c > 0) $L[] = "\n⚠️ لديك {$c} طلب معلّق منذ أكثر من 24 ساعة — يُفضّل متابعتها.";
        } catch (\Throwable $e) {}

        return implode("\n", $L);
    }
}
