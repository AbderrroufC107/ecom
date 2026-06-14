<?php

namespace Ecom\Billing;

use PDO;

class InvoiceService
{
    private static function generateNumber(PDO $pdo): string
    {
        $year = date('Y');
        $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_invoices WHERE YEAR(created_at) = {$year}");
        $count = (int) $stmt->fetchColumn() + 1;
        return "INV-{$year}-" . str_pad($count, 6, '0', STR_PAD_LEFT);
    }

    public static function create(PDO $pdo, int $storeId, float $amount, float $tax = 0.00,
        ?string $dueDate = null, ?int $subscriptionId = null): int
    {
        $number = self::generateNumber($pdo);
        $total = $amount + $tax;
        $stmt = $pdo->prepare("INSERT INTO tbl_invoices (store_id, subscription_id, invoice_number,
            amount, tax, total, status, due_date) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([$storeId, $subscriptionId, $number, $amount, $tax, $total, $dueDate]);
        return (int) $pdo->lastInsertId();
    }

    public static function get(PDO $pdo, int $invoiceId): ?array
    {
        $stmt = $pdo->prepare("SELECT i.*, s.name AS store_name, s.slug AS store_slug
            FROM tbl_invoices i
            LEFT JOIN tbl_stores s ON i.store_id = s.id
            WHERE i.id = ?");
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function update(PDO $pdo, int $invoiceId, array $data): void
    {
        $allowed = ['status', 'paid_at', 'due_date'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $invoiceId;
        $stmt = $pdo->prepare("UPDATE tbl_invoices SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    }

    public static function getInvoices(PDO $pdo, int $storeId, int $page = 1, int $perPage = 20,
        ?string $statusFilter = null): array
    {
        $where = 'i.store_id = ?';
        $params = [$storeId];
        if ($statusFilter) {
            $where .= ' AND i.status = ?';
            $params[] = $statusFilter;
        }
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT i.*, s.name AS store_name
            FROM tbl_invoices i
            LEFT JOIN tbl_stores s ON i.store_id = s.id
            WHERE {$where} ORDER BY i.id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function getCount(PDO $pdo, int $storeId, ?string $statusFilter = null): int
    {
        $where = 'store_id = ?';
        $params = [$storeId];
        if ($statusFilter) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_invoices WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function getAll(PDO $pdo, int $page = 1, int $perPage = 50, ?string $statusFilter = null): array
    {
        $where = '1=1';
        $params = [];
        if ($statusFilter) {
            $where .= ' AND i.status = ?';
            $params[] = $statusFilter;
        }
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT i.*, s.name AS store_name, s.slug AS store_slug
            FROM tbl_invoices i
            LEFT JOIN tbl_stores s ON i.store_id = s.id
            WHERE {$where} ORDER BY i.id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function getAllCount(PDO $pdo, ?string $statusFilter = null): int
    {
        $where = '1=1';
        $params = [];
        if ($statusFilter) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_invoices WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
