<?php

namespace Ecom\Store;

use PDO;
use RuntimeException;

class StoreRepository
{
    public static function ensureTables(PDO $pdo): void
    { global $dbRepo;
        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(50) NOT NULL UNIQUE,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            max_products INT NOT NULL DEFAULT 0,
            max_employees INT NOT NULL DEFAULT 5,
            max_storage_mb INT NOT NULL DEFAULT 100,
            features JSON DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_stores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            domain VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            currency VARCHAR(10) DEFAULT 'USD',
            status ENUM('active','suspended','trial','cancelled') DEFAULT 'trial',
            plan_id INT DEFAULT NULL,
            plan_expires_at DATETIME DEFAULT NULL,
            owner_name VARCHAR(255) DEFAULT NULL,
            owner_contact VARCHAR(255) DEFAULT NULL,
            logo VARCHAR(500) DEFAULT NULL,
            timezone VARCHAR(100) DEFAULT 'UTC',
            language VARCHAR(10) DEFAULT 'en',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (plan_id) REFERENCES tbl_plans(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_store_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_store_setting (store_id, setting_key),
            FOREIGN KEY (store_id) REFERENCES tbl_stores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_store_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','manager','staff') DEFAULT 'staff',
            status ENUM('active','inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (store_id) REFERENCES tbl_stores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $dbRepo->executeCommand("CREATE TABLE IF NOT EXISTS tbl_store_themes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL UNIQUE,
            theme_json JSON DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (store_id) REFERENCES tbl_stores(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function migrateTables(PDO $pdo): void
    { global $dbRepo;
        $tableDefs = [
            'tbl_stores' => "CREATE TABLE IF NOT EXISTS tbl_stores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(100) NOT NULL UNIQUE,
                domain VARCHAR(255) DEFAULT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                address TEXT DEFAULT NULL,
                currency VARCHAR(10) DEFAULT 'USD',
                status ENUM('active','suspended','trial','cancelled') DEFAULT 'trial',
                plan_id INT DEFAULT NULL,
                plan_expires_at DATETIME DEFAULT NULL,
                owner_name VARCHAR(255) DEFAULT NULL,
                owner_contact VARCHAR(255) DEFAULT NULL,
                logo VARCHAR(500) DEFAULT NULL,
                timezone VARCHAR(100) DEFAULT 'UTC',
                language VARCHAR(10) DEFAULT 'en',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_plans' => "CREATE TABLE IF NOT EXISTS tbl_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) NOT NULL UNIQUE,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                max_products INT NOT NULL DEFAULT 0,
                max_employees INT NOT NULL DEFAULT 5,
                max_storage_mb INT NOT NULL DEFAULT 100,
                features JSON DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_store_settings' => "CREATE TABLE IF NOT EXISTS tbl_store_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_store_setting (store_id, setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_store_users' => "CREATE TABLE IF NOT EXISTS tbl_store_users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin','manager','staff') DEFAULT 'staff',
                status ENUM('active','inactive') DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_store_themes' => "CREATE TABLE IF NOT EXISTS tbl_store_themes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL UNIQUE,
                theme_json JSON DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_subscriptions' => "CREATE TABLE IF NOT EXISTS tbl_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                plan_id INT NOT NULL,
                status ENUM('active','paused','cancelled','expired') DEFAULT 'active',
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME DEFAULT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_invoices' => "CREATE TABLE IF NOT EXISTS tbl_invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                subscription_id INT DEFAULT NULL,
                invoice_number VARCHAR(50) NOT NULL UNIQUE,
                amount DECIMAL(10,2) NOT NULL,
                tax DECIMAL(10,2) DEFAULT 0.00,
                total DECIMAL(10,2) NOT NULL,
                status ENUM('pending','paid','overdue','cancelled','refunded') DEFAULT 'pending',
                due_date DATE DEFAULT NULL,
                paid_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_payments' => "CREATE TABLE IF NOT EXISTS tbl_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                store_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                method ENUM('card','transfer','cash','auto') DEFAULT 'auto',
                transaction_id VARCHAR(255) DEFAULT NULL,
                status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
                paid_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_api_keys' => "CREATE TABLE IF NOT EXISTS tbl_api_keys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                label VARCHAR(255) NOT NULL,
                api_key VARCHAR(64) NOT NULL UNIQUE,
                permissions JSON DEFAULT NULL,
                ip_whitelist JSON DEFAULT NULL,
                status ENUM('active','revoked') DEFAULT 'active',
                last_used_at DATETIME DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_api_logs' => "CREATE TABLE IF NOT EXISTS tbl_api_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                api_key_id INT DEFAULT NULL,
                endpoint VARCHAR(255) NOT NULL,
                method VARCHAR(10) NOT NULL,
                status_code INT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                response_time_ms INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_webhooks' => "CREATE TABLE IF NOT EXISTS tbl_webhooks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                url VARCHAR(500) NOT NULL,
                events JSON NOT NULL,
                secret VARCHAR(64) DEFAULT NULL,
                status ENUM('active','paused','failed') DEFAULT 'active',
                last_triggered_at DATETIME DEFAULT NULL,
                failure_count INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_audit_log' => "CREATE TABLE IF NOT EXISTS tbl_audit_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                store_id INT DEFAULT NULL,
                staff_id INT DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(100) DEFAULT NULL,
                entity_id INT DEFAULT NULL,
                old_value JSON DEFAULT NULL,
                new_value JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(500) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_queue_jobs' => "CREATE TABLE IF NOT EXISTS tbl_queue_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT DEFAULT NULL,
                type VARCHAR(100) NOT NULL,
                payload JSON DEFAULT NULL,
                priority ENUM('high','normal','low') DEFAULT 'normal',
                status ENUM('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                backoff_minutes INT DEFAULT 5,
                scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                started_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_failed_jobs' => "CREATE TABLE IF NOT EXISTS tbl_failed_jobs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                original_job_id INT DEFAULT NULL,
                store_id INT DEFAULT NULL,
                type VARCHAR(100) NOT NULL,
                payload JSON DEFAULT NULL,
                priority ENUM('high','normal','low') DEFAULT 'normal',
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                error_message TEXT DEFAULT NULL,
                failed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_backup_job' => "CREATE TABLE IF NOT EXISTS tbl_backup_job (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT DEFAULT NULL,
                type ENUM('database','files','full') NOT NULL,
                scope ENUM('global','store','selected_tables') DEFAULT 'global',
                selected_tables JSON DEFAULT NULL,
                status ENUM('pending','running','completed','failed') DEFAULT 'pending',
                file_path VARCHAR(500) DEFAULT NULL,
                file_size BIGINT DEFAULT 0,
                checksum VARCHAR(64) DEFAULT NULL,
                storage_location ENUM('local','s3') DEFAULT 'local',
                s3_key VARCHAR(500) DEFAULT NULL,
                started_at DATETIME DEFAULT NULL,
                completed_at DATETIME DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_restore_request' => "CREATE TABLE IF NOT EXISTS tbl_restore_request (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_id INT NOT NULL,
                store_id INT DEFAULT NULL,
                requested_by INT NOT NULL,
                status ENUM('pending','approved','rejected','executed') DEFAULT 'pending',
                approved_by INT DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                executed_at DATETIME DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_backup_config' => "CREATE TABLE IF NOT EXISTS tbl_backup_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) NOT NULL UNIQUE,
                config_value TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_backup_log' => "CREATE TABLE IF NOT EXISTS tbl_backup_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backup_id INT NOT NULL,
                store_id INT DEFAULT NULL,
                log_level ENUM('info','warning','error') DEFAULT 'info',
                message TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'tbl_store_usage' => "CREATE TABLE IF NOT EXISTS tbl_store_usage (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_id INT NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                used INT DEFAULT 0,
                limit_value INT DEFAULT 0,
                recorded_at DATE NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_usage_date (store_id, resource_type, recorded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        $orderedTables = [
            'tbl_plans', 'tbl_stores', 'tbl_store_settings', 'tbl_store_users',
            'tbl_store_themes', 'tbl_subscriptions', 'tbl_invoices', 'tbl_payments',
            'tbl_api_keys', 'tbl_api_logs', 'tbl_webhooks', 'tbl_audit_log',
            'tbl_queue_jobs', 'tbl_failed_jobs',
            'tbl_backup_job', 'tbl_restore_request', 'tbl_backup_config', 'tbl_backup_log',
            'tbl_store_usage',
        ];

        foreach ($orderedTables as $table) {
            if (isset($tableDefs[$table])) {
                $dbRepo->executeCommand($tableDefs[$table]);
            }
        }
    }

    public static function get(PDO $pdo, int $id): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
            p.max_products, p.max_employees, p.max_storage_mb, p.features
            FROM tbl_stores s
            LEFT JOIN tbl_plans p ON s.plan_id = p.id
            WHERE s.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getBySlug(PDO $pdo, string $slug): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
            p.max_products, p.max_employees, p.max_storage_mb, p.features
            FROM tbl_stores s
            LEFT JOIN tbl_plans p ON s.plan_id = p.id
            WHERE s.slug = ?");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getByDomain(PDO $pdo, string $domain): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
            p.max_products, p.max_employees, p.max_storage_mb, p.features
            FROM tbl_stores s
            LEFT JOIN tbl_plans p ON s.plan_id = p.id
            WHERE s.domain = ?");
        $stmt->execute([$domain]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getByEmail(PDO $pdo, string $email): ?array
    { global $dbRepo;
        $stmt = $dbRepo->prepare("SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
            p.max_products, p.max_employees, p.max_storage_mb, p.features
            FROM tbl_stores s
            LEFT JOIN tbl_plans p ON s.plan_id = p.id
            WHERE s.email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAll(PDO $pdo, ?array $filters = null, int $page = 1, int $perPage = 50): array
    { global $dbRepo;
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND s.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['plan_id'])) {
            $where .= ' AND s.plan_id = ?';
            $params[] = (int) $filters['plan_id'];
        }
        if (!empty($filters['search'])) {
            $where .= ' AND (s.name LIKE ? OR s.email LIKE ? OR s.slug LIKE ?)';
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $offset = ($page - 1) * $perPage;
        $stmt = $dbRepo->prepare("SELECT s.*, p.name AS plan_name, p.slug AS plan_slug,
            p.max_products, p.max_employees, p.max_storage_mb, p.features
            FROM tbl_stores s
            LEFT JOIN tbl_plans p ON s.plan_id = p.id
            WHERE {$where} ORDER BY s.id DESC LIMIT ? OFFSET ?");
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->execute($allParams);
        return $stmt->fetchAll();
    }

    public static function getCount(PDO $pdo, ?array $filters = null): int
    { global $dbRepo;
        $where = '1=1';
        $params = [];

        if (!empty($filters['status'])) {
            $where .= ' AND status = ?';
            $params[] = $filters['status'];
        }

        $stmt = $dbRepo->prepare("SELECT COUNT(*) AS cnt FROM tbl_stores WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function create(PDO $pdo, array $data): int
    { global $dbRepo;
        $stmt = $dbRepo->prepare("INSERT INTO tbl_stores (name, slug, domain, email, phone, address,
            currency, status, plan_id, plan_expires_at, owner_name, owner_contact, logo, timezone, language)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['slug'],
            $data['domain'] ?? null,
            $data['email'],
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['currency'] ?? 'USD',
            $data['status'] ?? 'trial',
            $data['plan_id'] ?? null,
            $data['plan_expires_at'] ?? null,
            $data['owner_name'] ?? null,
            $data['owner_contact'] ?? null,
            $data['logo'] ?? null,
            $data['timezone'] ?? 'UTC',
            $data['language'] ?? 'en',
        ]);
        return (int) $dbRepo->lastInsertId();
    }

    public static function update(PDO $pdo, int $id, array $data): void
    { global $dbRepo;
        $allowed = ['name', 'slug', 'domain', 'email', 'phone', 'address', 'currency',
            'status', 'plan_id', 'plan_expires_at', 'owner_name', 'owner_contact',
            'logo', 'timezone', 'language'];
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
        $params[] = $id;
        $stmt = $dbRepo->prepare("UPDATE tbl_stores SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);
    }

    public static function delete(PDO $pdo, int $id): void
    { global $dbRepo;
        $stmt = $dbRepo->prepare("DELETE FROM tbl_stores WHERE id = ?");
        $stmt->execute([$id]);
    }
}
