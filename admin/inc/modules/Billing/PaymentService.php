<?php

namespace Ecom\Billing;

use PDO;

class PaymentService
{
    public static function record(PDO $pdo, int $invoiceId, int $storeId, float $amount,
        string $method = 'auto', ?string $transactionId = null): int
    {
        $stmt = $pdo->prepare("INSERT INTO tbl_payments (invoice_id, store_id, amount, method, transaction_id, status)
            VALUES (?, ?, ?, ?, ?, 'completed')");
        $stmt->execute([$invoiceId, $storeId, $amount, $method, $transactionId]);
        $paymentId = (int) $pdo->lastInsertId();

        InvoiceService::update($pdo, $invoiceId, [
            'status'  => 'paid',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        return $paymentId;
    }

    public static function getByInvoice(PDO $pdo, int $invoiceId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_payments WHERE invoice_id = ? ORDER BY id ASC");
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    public static function getByStore(PDO $pdo, int $storeId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT p.*, i.invoice_number
            FROM tbl_payments p
            LEFT JOIN tbl_invoices i ON p.invoice_id = i.id
            WHERE p.store_id = ?
            ORDER BY p.id DESC LIMIT ? OFFSET ?");
        $stmt->execute([$storeId, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function getByTransactionId(PDO $pdo, string $transactionId): ?array
    {
        $stmt = $pdo->prepare("SELECT * FROM tbl_payments WHERE transaction_id = ?");
        $stmt->execute([$transactionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getTotalRevenue(PDO $pdo, ?int $storeId = null): float
    {
        if ($storeId) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM tbl_payments WHERE store_id = ? AND status = 'completed'");
            $stmt->execute([$storeId]);
        } else {
            $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM tbl_payments WHERE status = 'completed'");
        }
        return (float) $stmt->fetchColumn();
    }

    public static function getRevenueByPeriod(PDO $pdo, string $startDate, string $endDate): float
    {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM tbl_payments
            WHERE status = 'completed' AND paid_at >= ? AND paid_at <= ?");
        $stmt->execute([$startDate, $endDate]);
        return (float) $stmt->fetchColumn();
    }
}
