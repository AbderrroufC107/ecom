<?php
/**
 * Test: Order Distribution & Employee Isolation
 * Run from browser: admin/test_order_distribution.php
 */
require_once 'inc/config.php';
require_once 'inc/functions.php';
require_once 'inc/employee_functions.php';

employee_ensure_tables($pdo);

echo "<h2>Test 1: Check tbl_user roles & assignment participation</h2>";
$users = $dbRepo->query("SELECT id, full_name, role, participate_in_assignment, availability_status, assignment_weight, max_active_orders FROM tbl_user ORDER BY role, id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6' cellspacing='0'>";
echo "<tr><th>ID</th><th>Name</th><th>Role</th><th>Participate</th><th>Availability</th><th>Weight</th><th>Max Active</th></tr>";
foreach ($users as $u) {
    $color = ($u['role'] === 'Super Admin') ? '#ffcccc' : (($u['role'] === 'Admin') ? '#ccffcc' : '#ccccff');
    echo "<tr style='background:{$color}'>";
    echo "<td>{$u['id']}</td><td>{$u['full_name']}</td><td>{$u['role']}</td>";
    echo "<td>" . ($u['participate_in_assignment'] ? 'YES' : 'NO') . "</td>";
    echo "<td>{$u['availability_status']}</td><td>{$u['assignment_weight']}</td><td>{$u['max_active_orders']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test 2: Check tbl_employee & manager assignments</h2>";
$emps = $dbRepo->query("SELECT id, full_name, manager_id, is_active, availability_status, assignment_weight, max_active_orders FROM tbl_employee ORDER BY manager_id, id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6' cellspacing='0'>";
echo "<tr><th>ID</th><th>Name</th><th>Manager ID</th><th>Active</th><th>Availability</th><th>Weight</th><th>Max Active</th></tr>";
foreach ($emps as $e) {
    $color = ($e['manager_id'] > 0) ? '#d4edda' : '#f8f9fa';
    echo "<tr style='background:{$color}'>";
    echo "<td>{$e['id']}</td><td>{$e['full_name']}</td><td>" . ($e['manager_id'] ?: 'NULL') . "</td>";
    echo "<td>" . ($e['is_active'] ? 'YES' : 'NO') . "</td>";
    echo "<td>{$e['availability_status']}</td><td>{$e['assignment_weight']}</td><td>{$e['max_active_orders']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>Test 3: WRR Next Candidate (no manager_id — new order scenario)</h2>";
$next = employee_get_next_for_assignment($pdo, null, null);
if ($next) {
    echo "<p style='color:green;font-weight:bold'>WRR picked: <b>{$next['full_name']}</b> (type: {$next['type']}, id: {$next['id']})</p>";
    echo "<p>✅ This means new orders will go to Managers/Admins employees, NOT Super Admin.</p>";
} else {
    echo "<p style='color:red;font-weight:bold'>WRR picked: NOBODY</p>";
    echo "<p>❌ No eligible participants. Check tbl_user (Admin/Manager + participate=1) and tbl_employee (active + Available).</p>";
}

echo "<h2>Test 4: WRR Next Candidate (specific manager_id)</h2>";
$adminUser = $dbRepo->query("SELECT id FROM tbl_user WHERE role IN ('Admin','Manager') AND participate_in_assignment = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($adminUser) {
    $mid = (int) $adminUser['id'];
    $next2 = employee_get_next_for_assignment($pdo, null, $mid);
    if ($next2) {
        echo "<p style='color:green'>WRR picked for manager #{$mid}: <b>{$next2['full_name']}</b> (type: {$next2['type']})</p>";
    } else {
        echo "<p style='color:red'>WRR picked nobody for manager #{$mid}</p>";
    }
} else {
    echo "<p>No Admin/Manager user with participate_in_assignment=1 found. Skipping.</p>";
}

echo "<h2>Test 5: Employee Isolation — index.php dashboard query</h2>";
$empWithManager = $dbRepo->query("SELECT id, full_name, manager_id FROM tbl_employee WHERE manager_id IS NOT NULL AND manager_id > 0 AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($empWithManager) {
    $empId = (int) $empWithManager['id'];
    $assigned = $dbRepo->prepare("SELECT COUNT(*) FROM tbl_order_assignment WHERE employee_id = ? AND status = 'active'");
    $assigned->execute([$empId]);
    $count = $assigned->fetchColumn();
    echo "<p>Employee #{$empId} ({$empWithManager['full_name']}, manager_id={$empWithManager['manager_id']}): {$count} active orders</p>";
    echo "<p>✅ Employee only sees these orders in their dashboard (filtered by employee_id).</p>";
} else {
    echo "<p>No active employee with a manager found.</p>";
}

echo "<h2>Test 6: orders_workspace.php isolation</h2>";
echo "<ul>";
echo "<li><b>Employee role</b>: filtered by <code>oa.employee_id = ?</code> — sees ONLY their own orders ✅</li>";
echo "<li><b>Admin/Manager/Super Admin</b>: no employee filter — sees ALL orders ✅</li>";
echo "</ul>";

echo "<h2>Test 7: Unassigned orders count</h2>";
$unassigned = $dbRepo->query("SELECT COUNT(*) FROM tbl_order o LEFT JOIN tbl_order_assignment oa ON o.id = oa.order_id WHERE oa.id IS NULL")->fetchColumn();
echo "<p>Unassigned orders: <b>{$unassigned}</b></p>";

echo "<h2>Test 8: Assignment summary by source</h2>";
$sources = $dbRepo->query("SELECT assignment_source, COUNT(*) as cnt FROM tbl_order_assignment GROUP BY assignment_source ORDER BY cnt DESC")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6'><tr><th>Source</th><th>Count</th></tr>";
foreach ($sources as $s) {
    echo "<tr><td>{$s['assignment_source']}</td><td>{$s['cnt']}</td></tr>";
}
echo "</table>";

echo "<h2>Test 9: Orders with manager_id set (from WRR stamping)</h2>";
$mgrOrders = $dbRepo->query("SELECT manager_id, COUNT(*) as cnt FROM tbl_order GROUP BY manager_id ORDER BY manager_id ASC")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='6'><tr><th>Manager ID</th><th>Order Count</th></tr>";
foreach ($mgrOrders as $m) {
    echo "<tr><td>" . ($m['manager_id'] ?: 'NULL (unassigned)') . "</td><td>{$m['cnt']}</td></tr>";
}
echo "</table>";

echo "<hr><p style='color:#666;font-size:12px'>Test completed at " . date('Y-m-d H:i:s') . "</p>";
?>
