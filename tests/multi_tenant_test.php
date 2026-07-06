<?php
/**
 * ============================================================
 * Multi-Tenant Isolation Test Suite
 * ============================================================
 * Verifies that the Repository Pattern + TenantContext enforces
 * strict per-tenant data separation with ZERO cross-tenant leaks.
 *
 * Usage:  php tests/multi_tenant_test.php
 * ============================================================
 */

define('TENANT_TEST_MODE', true);

// Bootstrap – this sets up $pdo and all $*Repo globals.
// In CLI mode, TenantMiddleware::boot() is a no-op, so we control
// TenantContext::init() ourselves.
require_once __DIR__ . '/../admin/inc/config.php';

global $pdo, $productRepo, $orderRepo, $dbRepo;

echo "============================================================\n";
echo "  ENTERPRISE SAAS MULTI-TENANT ISOLATION TEST SUITE\n";
echo "============================================================\n\n";

// ──────────────────────────────────────────────────────────────
// Test runner
// ──────────────────────────────────────────────────────────────
$passed = 0;
$failed = 0;

function runTest(string $name, callable $fn): void {
    global $passed, $failed;
    echo "  » $name ... ";
    try {
        $ok = $fn();
        if ($ok === true) {
            echo "✅ PASS\n";
            $passed++;
        } else {
            echo "❌ FAIL (returned false)\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "❌ FAIL — " . $e->getMessage() . "\n";
        $failed++;
    }
}

// ──────────────────────────────────────────────────────────────
// Fixture setup — use raw PDO to create tenants
// ──────────────────────────────────────────────────────────────
echo "[Setup]\n";
$pdo->exec("DELETE FROM tbl_tenants WHERE name IN ('__TenantA__','__TenantB__')");
$pdo->exec("INSERT INTO tbl_tenants (name, domain, status) VALUES ('__TenantA__','a.test','ACTIVE')");
$tid_a = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO tbl_tenants (name, domain, status) VALUES ('__TenantB__','b.test','ACTIVE')");
$tid_b = (int) $pdo->lastInsertId();
echo "  Created Tenant A (id=$tid_a) and Tenant B (id=$tid_b)\n\n";

// Track IDs for cleanup
$pids = []; // product ids (raw)
$oids = []; // order ids

// ──────────────────────────────────────────────────────────────
// 1.  PRODUCT ISOLATION
// ──────────────────────────────────────────────────────────────
echo "[1] Product Isolation\n";

runTest("Tenant A can create a product", function() use ($tid_a, $productRepo, &$pids) {
    \SaaS\TenantContext::init($tid_a);
    $id = $productRepo->create([
        'p_name'          => '__ProdA__',
        'p_current_price' => 100,
        'p_old_price'     => 120,
        'p_qty'           => 5,
        'p_is_active'     => 1,
        'p_is_featured'   => 0,
    ]);
    $pids[] = $id;
    return $id > 0;
});

runTest("Tenant B can create a product", function() use ($tid_b, $productRepo, &$pids) {
    \SaaS\TenantContext::init($tid_b);
    $id = $productRepo->create([
        'p_name'          => '__ProdB__',
        'p_current_price' => 200,
        'p_old_price'     => 250,
        'p_qty'           => 10,
        'p_is_active'     => 1,
        'p_is_featured'   => 0,
    ]);
    $pids[] = $id;
    return $id > 0;
});

runTest("Tenant A CANNOT read Tenant B's products", function() use ($tid_a, $productRepo) {
    \SaaS\TenantContext::init($tid_a);
    $rows = $productRepo->findAll(['p_name' => '__ProdB__']);
    return count($rows) === 0;
});

runTest("Tenant B CANNOT read Tenant A's products", function() use ($tid_b, $productRepo) {
    \SaaS\TenantContext::init($tid_b);
    $rows = $productRepo->findAll(['p_name' => '__ProdA__']);
    return count($rows) === 0;
});

runTest("Tenant A sees exactly its own product", function() use ($tid_a, $productRepo) {
    \SaaS\TenantContext::init($tid_a);
    $rows = $productRepo->findAll(['p_name' => '__ProdA__']);
    return count($rows) === 1 && (int)$rows[0]['tenant_id'] === $tid_a;
});

// ──────────────────────────────────────────────────────────────
// 2.  CROSS-TENANT WRITE PREVENTION
// ──────────────────────────────────────────────────────────────
echo "\n[2] Cross-Tenant Write Prevention\n";

runTest("Tenant B CANNOT UPDATE Tenant A's product", function() use ($tid_a, $tid_b, $productRepo) {
    // Get Tenant A's product id
    \SaaS\TenantContext::init($tid_a);
    $rows = $productRepo->findAll(['p_name' => '__ProdA__']);
    if (empty($rows)) throw new \Exception("__ProdA__ not found");
    $pid = (int)$rows[0]['p_id'];

    // Switch to Tenant B and attempt update
    \SaaS\TenantContext::init($tid_b);
    $updated = $productRepo->update($pid, ['p_current_price' => 9999]);
    if ($updated) return false; // Should NOT have updated

    // Verify price unchanged in Tenant A
    \SaaS\TenantContext::init($tid_a);
    $check = $productRepo->find($pid);
    return (int)$check['p_current_price'] === 100;
});

runTest("Tenant B CANNOT DELETE Tenant A's product", function() use ($tid_a, $tid_b, $productRepo) {
    \SaaS\TenantContext::init($tid_a);
    $rows = $productRepo->findAll(['p_name' => '__ProdA__']);
    if (empty($rows)) throw new \Exception("__ProdA__ not found");
    $pid = (int)$rows[0]['p_id'];

    \SaaS\TenantContext::init($tid_b);
    $deleted = $productRepo->delete($pid);
    if ($deleted) return false;

    \SaaS\TenantContext::init($tid_a);
    return $productRepo->find($pid) !== null;
});

runTest("count() is scoped per-tenant", function() use ($tid_a, $tid_b, $productRepo) {
    \SaaS\TenantContext::init($tid_a);
    $cntA = $productRepo->count(['p_name' => '__ProdA__']);

    \SaaS\TenantContext::init($tid_b);
    $cntB = $productRepo->count(['p_name' => '__ProdA__']);

    return $cntA === 1 && $cntB === 0;
});

// ──────────────────────────────────────────────────────────────
// 3.  RAW QUERY FALLBACK VIA DatabaseRepository
// ──────────────────────────────────────────────────────────────
echo "\n[3] Raw Query Fallback Auto-Isolation (DatabaseRepository)\n";

runTest("dbRepo->prepare() auto-injects tenant_id — Tenant B sees only 1 row", function() use ($tid_b, $dbRepo) {
    \SaaS\TenantContext::init($tid_b);
    // Developer deliberately omits WHERE tenant_id — dbRepo must inject it
    $stmt = $dbRepo->prepare("SELECT COUNT(*) AS c FROM tbl_product WHERE p_name LIKE '__Prod%'");
    $stmt->execute();
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    // Tenant B has 1 test product; Tenant A's should not appear
    return (int)$row['c'] === 1;
});

runTest("dbRepo->prepare() auto-injects tenant_id — Tenant A sees only 1 row", function() use ($tid_a, $dbRepo) {
    \SaaS\TenantContext::init($tid_a);
    $stmt = $dbRepo->prepare("SELECT COUNT(*) AS c FROM tbl_product WHERE p_name LIKE '__Prod%'");
    $stmt->execute();
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    return (int)$row['c'] === 1;
});

// ──────────────────────────────────────────────────────────────
// 4.  ORDER ISOLATION
// ──────────────────────────────────────────────────────────────
echo "\n[4] Order Isolation\n";

runTest("Order created by Tenant A is invisible to Tenant B", function() use ($tid_a, $tid_b, $orderRepo, &$oids) {
    \SaaS\TenantContext::init($tid_a);
    $id = $orderRepo->create([
        'customer_name'  => '__OrderCustA__',
        'order_status'   => 'Pending',
        'total_price'    => 500,
        'customer_phone' => '0555000000',
        'wilaya'         => '01',
        'commune'        => 'Test',
        'address'        => 'Test Address',
        'order_date'     => date('Y-m-d H:i:s'),
    ]);
    $oids[] = $id;

    \SaaS\TenantContext::init($tid_b);
    $rows = $orderRepo->findAll(['customer_name' => '__OrderCustA__']);
    return count($rows) === 0;
});

// ──────────────────────────────────────────────────────────────
// CLEANUP
// ──────────────────────────────────────────────────────────────
echo "\n[Cleanup]\n";
$pdo->exec("DELETE FROM tbl_product WHERE p_name LIKE '__Prod%'");
$pdo->exec("DELETE FROM tbl_order  WHERE customer_name LIKE '__OrderCust%'");
$pdo->exec("DELETE FROM tbl_tenants WHERE name IN ('__TenantA__','__TenantB__')");
echo "  ✅ Test data removed\n";

// ──────────────────────────────────────────────────────────────
// RESULTS
// ──────────────────────────────────────────────────────────────
$total = $passed + $failed;
echo "\n============================================================\n";
echo "RESULTS: $passed / $total tests passed\n";
echo "------------------------------------------------------------\n";
if ($failed === 0) {
    echo "✅  ALL TESTS PASSED\n";
    echo "🔒  ZERO TENANT VIOLATIONS CONFIRMED\n";
    echo "🏆  ENTERPRISE SAAS — PRODUCTION CERTIFIED\n";
} else {
    echo "❌  $failed TEST(S) FAILED — NOT PRODUCTION READY\n";
}
echo "============================================================\n";

exit($failed === 0 ? 0 : 1);
