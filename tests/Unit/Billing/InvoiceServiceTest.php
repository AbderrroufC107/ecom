<?php

namespace Tests\Unit\Billing;

use PHPUnit\Framework\TestCase;
use Ecom\Billing\InvoiceService;
use Ecom\Billing\PaymentService;
use Ecom\Billing\PlanService;

class InvoiceServiceTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("CREATE TABLE tbl_stores (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL DEFAULT '',
            slug TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            plan_id INTEGER DEFAULT NULL,
            status TEXT DEFAULT 'active'
        )");

        $this->pdo->exec("CREATE TABLE tbl_plans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            price REAL NOT NULL DEFAULT 0,
            max_products INTEGER DEFAULT 0,
            max_employees INTEGER DEFAULT 5,
            max_storage_mb INTEGER DEFAULT 100,
            features TEXT DEFAULT NULL
        )");

        $this->pdo->exec("CREATE TABLE tbl_invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            store_id INTEGER NOT NULL,
            subscription_id INTEGER DEFAULT NULL,
            invoice_number TEXT NOT NULL UNIQUE,
            amount REAL NOT NULL,
            tax REAL DEFAULT 0,
            total REAL NOT NULL,
            status TEXT DEFAULT 'pending',
            due_date TEXT DEFAULT NULL,
            paid_at TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $this->pdo->exec("CREATE TABLE tbl_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_id INTEGER NOT NULL,
            store_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            method TEXT DEFAULT 'auto',
            transaction_id TEXT DEFAULT NULL,
            status TEXT DEFAULT 'pending',
            paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function testCreateInvoice(): void
    {
        $id = InvoiceService::create($this->pdo, 1, 99.99, 10.00, '2026-07-01');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testGetInvoice(): void
    {
        $id = InvoiceService::create($this->pdo, 1, 50.00);
        $invoice = InvoiceService::get($this->pdo, $id);
        $this->assertNotNull($invoice);
        $this->assertEquals(50.00, (float) $invoice['amount']);
    }

    public function testRecordPayment(): void
    {
        $invoiceId = InvoiceService::create($this->pdo, 1, 100.00);
        $paymentId = PaymentService::record($this->pdo, $invoiceId, 1, 100.00, 'card', 'txn_001');
        $this->assertGreaterThan(0, $paymentId);

        $invoice = InvoiceService::get($this->pdo, $invoiceId);
        $this->assertEquals('paid', $invoice['status']);
    }

    public function testGetPaymentsByInvoice(): void
    {
        $invoiceId = InvoiceService::create($this->pdo, 1, 75.00);
        PaymentService::record($this->pdo, $invoiceId, 1, 75.00);
        $payments = PaymentService::getByInvoice($this->pdo, $invoiceId);
        $this->assertCount(1, $payments);
    }

    public function testGetInvoicesWithPagination(): void
    {
        InvoiceService::create($this->pdo, 1, 10.00);
        InvoiceService::create($this->pdo, 1, 20.00);
        $invoices = InvoiceService::getInvoices($this->pdo, 1, 1, 1);
        $this->assertCount(1, $invoices);
        $this->assertEquals(2, InvoiceService::getCount($this->pdo, 1));
    }

    public function testInvoiceNumberFormat(): void
    {
        $id = InvoiceService::create($this->pdo, 1, 25.00);
        $invoice = InvoiceService::get($this->pdo, $id);
        $this->assertStringStartsWith('INV-' . date('Y') . '-', $invoice['invoice_number']);
    }

    public function testTotalCalculation(): void
    {
        $id = InvoiceService::create($this->pdo, 1, 80.00, 20.00);
        $invoice = InvoiceService::get($this->pdo, $id);
        $this->assertEquals(100.00, (float) $invoice['total']);
    }

    public function testCreatePlan(): void
    {
        $id = PlanService::create($this->pdo, 'Starter', 'starter', 29.99, 100, 5, 500, ['api', 'reports']);
        $this->assertGreaterThan(0, $id);

        $plan = PlanService::get($this->pdo, $id);
        $this->assertEquals('Starter', $plan['name']);
    }

    public function testGetAllPlans(): void
    {
        PlanService::create($this->pdo, 'Basic', 'basic', 9.99, 50, 2, 200);
        PlanService::create($this->pdo, 'Pro', 'pro', 49.99, 500, 10, 2000);
        $plans = PlanService::getAll($this->pdo);
        $this->assertCount(2, $plans);
    }
}
