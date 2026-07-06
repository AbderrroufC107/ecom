<?php

namespace Ecom\Search;

use PDO;

class SearchService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function searchOrders(string $query, int $limit = 50): array
    {
        $terms = array_filter(explode(' ', trim($query)));
        if (empty($terms)) return [];

        $clean = $this->sanitizeSearchTerm($query);

        // 1. Exact match (highest priority)
        $sql = "SELECT id, order_id, customer_name, customer_phone, product_name, order_status, total_price, order_date
                FROM tbl_order
                WHERE customer_phone = ? OR order_id = ? OR CAST(id AS CHAR) = ?
                LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$clean, $clean, $clean, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) >= $limit) return $results;

        // 2. Prefix match (LIKE 'term%') on phone and name
        $sql = "SELECT id, order_id, customer_name, customer_phone, product_name, order_status, total_price, order_date
                FROM tbl_order
                WHERE customer_phone LIKE ? OR customer_name LIKE ?
                LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$clean . '%', $clean . '%', $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        if (count($results) >= $limit) return array_slice($results, 0, $limit);

        // 3. FULLTEXT search on indexed columns
        $fulltextTerm = '+' . implode(' +*', $terms) . '*';
        $sql = "SELECT id, order_id, customer_name, customer_phone, product_name, order_status, total_price, order_date,
                       MATCH(customer_name, customer_phone, product_name, wilaya, commune) AGAINST(? IN BOOLEAN MODE) AS relevance
                FROM tbl_order
                WHERE MATCH(customer_name, customer_phone, product_name, wilaya, commune) AGAINST(? IN BOOLEAN MODE)
                ORDER BY relevance DESC
                LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$fulltextTerm, $fulltextTerm, $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        if (count($results) >= $limit) return array_slice($results, 0, $limit);

        // 4. Substring LIKE match (broadest, lowest priority)
        $sql = "SELECT id, order_id, customer_name, customer_phone, product_name, order_status, total_price, order_date
                FROM tbl_order
                WHERE customer_name LIKE ? OR customer_phone LIKE ? OR product_name LIKE ? OR wilaya LIKE ?
                LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $likeTerm = '%' . $clean . '%';
        $stmt->execute([$likeTerm, $likeTerm, $likeTerm, $likeTerm, $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $row;
        }

        // 5. Levenshtein fuzzy search (only on already filtered results if we have some)
        if (count($results) < 3) {
            $fuzzyResults = $this->levenshteinSearch('tbl_order', 'customer_name', $clean, $limit);
            foreach ($fuzzyResults as $row) {
                $results[] = $row;
            }
        }

        return array_slice(array_unique($results, SORT_REGULAR), 0, $limit);
    }

    public function searchCustomers(string $query, int $limit = 50): array
    {
        $clean = $this->sanitizeSearchTerm($query);
        $terms = array_filter(explode(' ', trim($query)));
        if (empty($terms)) return [];

        $results = [];

        // 1. Exact phone match
        $sql = "SELECT id, cust_name, cust_phone, cust_address, wilaya, commune, cust_status
                FROM tbl_customer WHERE cust_phone = ? LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$clean, $limit]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($results) >= $limit) return $results;

        // 2. Prefix match
        $sql = "SELECT id, cust_name, cust_phone, cust_address, wilaya, commune, cust_status
                FROM tbl_customer WHERE cust_name LIKE ? OR cust_phone LIKE ? LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$clean . '%', $clean . '%', $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = $row;

        if (count($results) >= $limit) return array_slice($results, 0, $limit);

        // 3. FULLTEXT
        $fulltextTerm = '+' . implode(' +*', $terms) . '*';
        $sql = "SELECT id, cust_name, cust_phone, cust_address, wilaya, commune, cust_status,
                       MATCH(cust_name, cust_phone, cust_address) AGAINST(? IN BOOLEAN MODE) AS relevance
                FROM tbl_customer
                WHERE MATCH(cust_name, cust_phone, cust_address) AGAINST(? IN BOOLEAN MODE)
                ORDER BY relevance DESC LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$fulltextTerm, $fulltextTerm, $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = $row;

        if (count($results) >= $limit) return array_slice($results, 0, $limit);

        // 4. Substring LIKE
        $sql = "SELECT id, cust_name, cust_phone, cust_address, wilaya, commune, cust_status
                FROM tbl_customer WHERE cust_name LIKE ? OR cust_phone LIKE ? OR cust_address LIKE ?
                LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $likeTerm = '%' . $clean . '%';
        $stmt->execute([$likeTerm, $likeTerm, $likeTerm, $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = $row;

        // 5. Levenshtein on filtered set only
        if (count($results) < 3) {
            $fuzzyResults = $this->levenshteinSearch('tbl_customer', 'cust_name', $clean, $limit);
            foreach ($fuzzyResults as $row) $results[] = $row;
        }

        return array_slice(array_unique($results, SORT_REGULAR), 0, $limit);
    }

    public function searchProducts(string $query, int $limit = 50): array
    {
        $clean = $this->sanitizeSearchTerm($query);
        $terms = array_filter(explode(' ', trim($query)));
        if (empty($terms)) return [];

        $results = [];

        // 1. Exact match by p_id
        if (is_numeric($clean)) {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT * FROM tbl_product WHERE p_id = ? LIMIT ?");
            $stmt->execute([(int)$clean, $limit]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($results) >= $limit) return $results;
        }

        // 2. Prefix match
        $sql = "SELECT p_id, p_name, p_current_price, p_old_price, p_qty, p_featured_photo, ecat_id
                FROM tbl_product WHERE p_name LIKE ? LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$clean . '%', $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = $row;

        if (count($results) >= $limit) return array_slice($results, 0, $limit);

        // 3. FULLTEXT
        $fulltextTerm = '+' . implode(' +*', $terms) . '*';
        $sql = "SELECT p_id, p_name, p_current_price, p_old_price, p_qty, p_featured_photo, ecat_id,
                       MATCH(p_name, p_description) AGAINST(? IN BOOLEAN MODE) AS relevance
                FROM tbl_product
                WHERE MATCH(p_name, p_description) AGAINST(? IN BOOLEAN MODE)
                ORDER BY relevance DESC LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $stmt->execute([$fulltextTerm, $fulltextTerm, $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = $row;

        if (count($results) >= $limit) return array_slice($results, 0, $limit);

        // 4. Substring LIKE
        $sql = "SELECT p_id, p_name, p_current_price, p_old_price, p_qty, p_featured_photo, ecat_id
                FROM tbl_product WHERE p_name LIKE ? OR p_description LIKE ? LIMIT ?";
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare($sql);
        $likeTerm = '%' . $clean . '%';
        $stmt->execute([$likeTerm, $likeTerm, $limit]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $results[] = $row;

        // 5. Levenshtein on filtered set only
        if (count($results) < 3) {
            $fuzzyResults = $this->levenshteinSearch('tbl_product', 'p_name', $clean, $limit);
            foreach ($fuzzyResults as $row) $results[] = $row;
        }

        return array_slice(array_unique($results, SORT_REGULAR), 0, $limit);
    }

    private function levenshteinSearch(string $table, string $column, string $query, int $limit = 10): array
    {
        $results = [];

        // Only run Levenshtein on tables with data - first check row count
        $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("SELECT COUNT(*) FROM {$table}");
        $rowCount = (int)$stmt->fetchColumn();

        if ($rowCount > 10000) {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("SELECT MIN(id) as min_id, MAX(id) as max_id FROM {$table}");
            $range = $stmt->fetch(PDO::FETCH_ASSOC);
            $chunkSize = 1000;
            $candidates = [];

            for ($start = (int)$range['min_id']; $start <= (int)$range['max_id']; $start += $chunkSize) {
                $end = $start + $chunkSize - 1;
                $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                    "SELECT id, {$column} FROM {$table} WHERE id BETWEEN ? AND ? AND {$column} LIKE ?"
                );
                $stmt->execute([$start, $end, substr($query, 0, 3) . '%']);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $candidates[$row['id']] = $row[$column];
                }
            }

            $scored = [];
            foreach ($candidates as $id => $val) {
                $dist = levenshtein($query, $val);
                if ($dist <= 3) {
                    $scored[] = ['id' => $id, 'score' => $dist, $column => $val];
                }
            }
            usort($scored, fn($a, $b) => $a['score'] <=> $b['score']);
            $ids = array_column(array_slice($scored, 0, $limit), 'id');
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT * FROM {$table} WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            // For smaller tables, load candidates via prefix filter first
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare(
                "SELECT id, {$column} FROM {$table} WHERE {$column} LIKE ? LIMIT 100"
            );
            $stmt->execute([substr($query, 0, 2) . '%']);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $scored = [];
            foreach ($candidates as $row) {
                $dist = levenshtein($query, $row[$column]);
                if ($dist <= 2) {
                    $scored[] = ['id' => $row['id'], 'score' => $dist];
                }
            }
            usort($scored, fn($a, $b) => $a['score'] <=> $b['score']);
            $ids = array_column(array_slice($scored, 0, $limit), 'id');
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("SELECT * FROM {$table} WHERE id IN ({$placeholders})");
                $stmt->execute($ids);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        return $results;
    }

    private function sanitizeSearchTerm(string $term): string
    {
        $term = trim(mb_substr($term, 0, 100));
        $term = str_replace(['%', '_'], ['\%', '\_'], $term);
        return $term;
    }
}
