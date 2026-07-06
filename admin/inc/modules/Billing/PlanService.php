<?php

namespace Ecom\Billing;

use PDO;
use Ecom\Store\StoreRepository;
use Ecom\Store\StoreSubscription;

class PlanService
{
    public static function get(PDO $pdo, int $planId): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_plans WHERE id = ?");
        $stmt->execute([$planId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getBySlug(PDO $pdo, string $slug): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT * FROM tbl_plans WHERE slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAll(PDO $pdo): array
    { global $dbRepo;
        $stmt = $dbRepo->query("SELECT * FROM tbl_plans ORDER BY price ASC");
        return $stmt->fetchAll();
    }

    public static function create(PDO $pdo, string $name, string $slug, float $price,
        int $maxProducts, int $maxEmployees, int $maxStorageMb, array $features = []): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_plans (name, slug, price, max_products, max_employees,
            max_storage_mb, features) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $price, $maxProducts, $maxEmployees, $maxStorageMb,
            json_encode($features)]);
        return (int) $dbRepo->lastInsertId();
    }

    public static function update(PDO $pdo, int $planId, array $data): void
    { global $dbRepo;
        $allowed = ['name', 'slug', 'price', 'max_products', 'max_employees', 'max_storage_mb', 'features'];
        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'features') {
                    $sets[] = "{$field} = ?";
                    $params[] = json_encode($data[$field]);
                } else {
                    $sets[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }
        }
        if (empty($sets)) {
            return;
        }
        $params[] = $planId;
        $stmt = $dbRepo->prepare("UPDATE tbl_plans SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    }

    public static function delete(PDO $pdo, int $planId): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("DELETE FROM tbl_plans WHERE id = ?");
        $stmt->execute([$planId]);
    }

    public static function changePlan(PDO $pdo, int $storeId, int $newPlanId,
        ?string $expiresAt = null, float $proratedAmount = 0.00): int
    { global $dbRepo;
        $plan = self::get($pdo, $newPlanId);
        if (!$plan) {
            throw new \RuntimeException('Plan not found');
        }

        StoreRepository::update($pdo, $storeId, [
            'plan_id'         => $newPlanId,
            'plan_expires_at' => $expiresAt,
        ]);

        StoreSubscription::create($pdo, $storeId, $newPlanId, 'active', $expiresAt);

        if ($proratedAmount > 0) {
            $invoiceId = InvoiceService::create($pdo, $storeId, $proratedAmount, 0.00, $expiresAt);
            PaymentService::record($pdo, $invoiceId, $storeId, $proratedAmount, 'auto');
            return $invoiceId;
        }

        return 0;
    }
}
