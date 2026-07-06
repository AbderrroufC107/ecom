<?php
namespace SaaS\Repositories;

use PDO;
use Exception;
use PDOStatement;
use SaaS\TenantContext;

abstract class BaseRepository {
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    
    protected string $tenantKey = 'tenant_id'; 
    protected bool $useSoftDelete = false;
    protected string $softDeleteKey = 'is_deleted';

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    protected function getTable(): string {
        if (empty($this->table)) {
            throw new Exception("Table name not defined for " . static::class);
        }
        return $this->table;
    }

    protected function logAction(string $action, $details = null) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO tbl_audit_log (tenant_id, action, table_name, details, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                TenantContext::getTenantId(),
                $action,
                $this->getTable(),
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null
            ]);
        } catch (Exception $e) {}
    }

    public function beginTransaction() {
        if (!$this->pdo->inTransaction()) $this->pdo->beginTransaction();
    }

    public function commit() {
        if ($this->pdo->inTransaction()) $this->pdo->commit();
    }

    public function rollBack() {
        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
    }

    public function find(int $id) {
        $sql = "SELECT * FROM `{$this->getTable()}` WHERE `{$this->primaryKey}` = ? AND `{$this->tenantKey}` = ?";
        if ($this->useSoftDelete) {
            $sql .= " AND `{$this->softDeleteKey}` = 0";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id, TenantContext::getTenantId()]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findAll(array $conditions = [], string $orderBy = '', int $limit = 0, int $offset = 0): array {
        $where = ["`{$this->tenantKey}` = ?"];
        $params = [TenantContext::getTenantId()];

        if ($this->useSoftDelete) {
            $where[] = "`{$this->softDeleteKey}` = 0";
        }

        foreach ($conditions as $col => $val) {
            $where[] = "`$col` = ?";
            $params[] = $val;
        }

        $sql = "SELECT * FROM `{$this->getTable()}` WHERE " . implode(' AND ', $where);

        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }

        if ($limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset > 0) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int {
        $data[$this->tenantKey] = TenantContext::getTenantId();

        $cols = array_keys($data);
        $colStr = '`' . implode('`, `', $cols) . '`';
        $valStr = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$this->getTable()}` ($colStr) VALUES ($valStr)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        $id = (int) $this->pdo->lastInsertId();
        $this->logAction('CREATE', ['id' => $id, 'data' => $data]);
        
        return $id;
    }

    public function update(int $id, array $data): bool {
        if (isset($data[$this->tenantKey])) {
            unset($data[$this->tenantKey]);
        }
        if (empty($data)) return false;

        $setParts = [];
        $params = [];
        foreach ($data as $col => $val) {
            $setParts[] = "`$col` = ?";
            $params[] = $val;
        }

        $params[] = $id;
        $params[] = TenantContext::getTenantId();

        $sql = "UPDATE `{$this->getTable()}` SET " . implode(', ', $setParts) . " WHERE `{$this->primaryKey}` = ? AND `{$this->tenantKey}` = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $success = $stmt->rowCount() > 0;
        if ($success) $this->logAction('UPDATE', ['id' => $id, 'changes' => $data]);
        
        return $success;
    }

    public function delete(int $id): bool {
        if ($this->useSoftDelete) {
            $sql = "UPDATE `{$this->getTable()}` SET `{$this->softDeleteKey}` = 1 WHERE `{$this->primaryKey}` = ? AND `{$this->tenantKey}` = ?";
        } else {
            $sql = "DELETE FROM `{$this->getTable()}` WHERE `{$this->primaryKey}` = ? AND `{$this->tenantKey}` = ?";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id, TenantContext::getTenantId()]);
        
        $success = $stmt->rowCount() > 0;
        if ($success) $this->logAction('DELETE', ['id' => $id, 'soft' => $this->useSoftDelete]);
        
        return $success;
    }

    public function count(array $conditions = []): int {
        $where = ["`{$this->tenantKey}` = ?"];
        $params = [TenantContext::getTenantId()];

        if ($this->useSoftDelete) {
            $where[] = "`{$this->softDeleteKey}` = 0";
        }

        foreach ($conditions as $col => $val) {
            $where[] = "`$col` = ?";
            $params[] = $val;
        }

        $sql = "SELECT COUNT(*) FROM `{$this->getTable()}` WHERE " . implode(' AND ', $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fallback for complex queries (JOINs, GROUP BYs, etc)
     * Auto-injects tenant_id into WHERE clause if missing.
     */
    public function customQuery(string $sql, array $params = []): array {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Prepares a statement securely, auto-injecting tenant_id to prevent cross-tenant access.
     */
    public function prepare(string $sql): PDOStatement {
        // Safely get tenant_id - returns null if context not initialized (e.g. on login page)
        try {
            $tenant_id = TenantContext::getTenantId();
        } catch (\Exception $e) {
            // TenantContext not initialized — skip injection (e.g. during login, DDL ops)
            return $this->pdo->prepare($sql);
        }
        
        // Skip injection for information_schema or queries that already handle tenant_id
        if (stripos($sql, 'tenant_id') === false && stripos($sql, 'FROM tbl_tenants') === false && stripos($sql, 'information_schema') === false) {
            
            // Handle SELECT/UPDATE/DELETE by appending tenant_id
            if (preg_match('/^\s*(SELECT|UPDATE|DELETE)/i', $sql)) {
                // Determine the alias of the primary table to avoid ambiguous column errors on JOINs
                $tenant_col = 'tenant_id';
                if (preg_match('/FROM\s+([a-zA-Z0-9_`]+)(?:\s+(?:AS\s+)?([a-zA-Z0-9_`]+))?(?:\s+WHERE|\s+JOIN|\s+LEFT|\s+RIGHT|\s+INNER|\s+ON|\s+ORDER|\s+GROUP|\s+LIMIT|\s*$)/i', $sql, $matches)) {
                    $tableName = strtolower(trim($matches[1], '`'));
                    $globalTables = [
                        'tbl_commune', 'tbl_country', 'tbl_delivery_company', 
                        'tbl_language', 'tbl_store', 'tbl_stores', 'tbl_tenants', 
                        'tbl_test_logs', 'tbl_wilaya', 'information_schema', 'tbl_plans', 'tbl_page',
                        'tbl_n8n_integrations', 'tbl_n8n_call_log'
                    ];
                    
                    if (in_array($tableName, $globalTables)) {
                        // Driving table is global; skip auto-injection to prevent "Unknown column tenant_id"
                        return $this->pdo->prepare($sql);
                    }
                    
                    $aliasCandidate = !empty($matches[2]) ? $matches[2] : $matches[1];
                    // Ensure the alias isn't actually a SQL keyword that was matched optionally
                    if (preg_match('/^(WHERE|JOIN|LEFT|RIGHT|INNER|ON|ORDER|GROUP|LIMIT|HAVING|ASC|DESC)$/i', $aliasCandidate)) {
                        $aliasCandidate = $matches[1];
                    }
                    
                    // Backtick it to be safe unless it's already backticked
                    $tenant_col = $aliasCandidate . '.tenant_id';
                }

                if (stripos($sql, 'WHERE') !== false) {
                    $sql = preg_replace('/\bWHERE\b/i', "WHERE {$tenant_col} = " . (int)$tenant_id . ' AND ', $sql, 1);
                } elseif (preg_match('/\b(LIMIT|ORDER BY|GROUP BY)\b/i', $sql)) {
                    $sql = preg_replace('/\b(LIMIT|ORDER BY|GROUP BY)\b/i', "WHERE {$tenant_col} = " . (int)$tenant_id . ' $1', $sql, 1);
                } else {
                    $sql .= " WHERE {$tenant_col} = " . (int)$tenant_id;
                }
            } 
            // Handle INSERT by appending tenant_id
            elseif (preg_match('/^\s*INSERT\s+INTO\s+([a-zA-Z0-9_`]+)\s*\((.*?)\)\s*VALUES\s*\((.*)\)\s*;?\s*$/i', $sql, $matches)) {
                $tableName = strtolower(trim($matches[1], '`'));
                $globalTables = [
                    'tbl_commune', 'tbl_country', 'tbl_delivery_company', 
                    'tbl_language', 'tbl_store', 'tbl_stores', 'tbl_tenants', 
                    'tbl_test_logs', 'tbl_wilaya', 'information_schema', 'tbl_plans', 'tbl_page',
                    'tbl_n8n_integrations', 'tbl_n8n_call_log'
                ];
                if (in_array($tableName, $globalTables)) {
                    return $this->pdo->prepare($sql);
                }
                
                $table = $matches[1];
                $cols = $matches[2];
                $vals = $matches[3];
                // Check if tenant_id is already in the columns
                if (stripos($cols, 'tenant_id') === false) {
                    $sql = "INSERT INTO $table ($cols, tenant_id) VALUES ($vals, " . (int)$tenant_id . ")";
                }
            }
        }
        
        return $this->pdo->prepare($sql);
    }

    public function query(string $sql): PDOStatement {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function executeCommand(string $sql, array $params = []): int {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string|false {
        return $this->pdo->lastInsertId();
    }
}
