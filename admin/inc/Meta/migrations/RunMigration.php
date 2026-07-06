<?php
declare(strict_types=1);

namespace Meta\Migrations;

use PDO;
use PDOException;
use Exception;

/**
 * RunMigration
 *
 * Executes the 001_meta_integration.sql migration file safely.
 * Supports both CLI and web invocations.
 *
 * CLI usage:
 *   php RunMigration.php
 *
 * Programmatic usage:
 *   RunMigration::run($pdo);
 */
class RunMigration
{
    private const MIGRATION_FILE = __DIR__ . '/001_meta_integration.sql';
    private const MIGRATION_NAME = '001_meta_integration';
    private const MIGRATION_EVENT_TYPE = 'DB_MIGRATION';

    /** @var PDO */
    private PDO $pdo;

    /** @var array<string> */
    private array $log = [];

    /** @var int */
    private int $passed = 0;

    /** @var int */
    private int $failed = 0;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // -------------------------------------------------------------------------
    // Public static entry point (for web / programmatic use)
    // -------------------------------------------------------------------------

    /**
     * Execute the migration using the provided PDO connection.
     *
     * @param PDO $pdo
     * @return array{passed:int, failed:int, log:array<string>}
     */
    public static function run(PDO $pdo): array
    {
        $runner = new self($pdo);
        $runner->execute();
        return [
            'passed' => $runner->passed,
            'failed' => $runner->failed,
            'log'    => $runner->log,
        ];
    }

    // -------------------------------------------------------------------------
    // Core execution
    // -------------------------------------------------------------------------

    /**
     * Load the SQL file and execute every statement.
     */
    private function execute(): void
    {
        $this->writeLine('=== RunMigration: ' . self::MIGRATION_NAME . ' ===');
        $this->writeLine('Started at: ' . date('Y-m-d H:i:s'));
        $this->writeLine('SQL file: ' . self::MIGRATION_FILE);

        if (!file_exists(self::MIGRATION_FILE)) {
            $this->writeLine('[FATAL] Migration SQL file not found: ' . self::MIGRATION_FILE);
            $this->failed++;
            $this->recordMigrationEvent('FAILED', 'SQL file not found');
            return;
        }

        $rawSql = file_get_contents(self::MIGRATION_FILE);
        if ($rawSql === false || trim($rawSql) === '') {
            $this->writeLine('[FATAL] Migration SQL file is empty or unreadable.');
            $this->failed++;
            $this->recordMigrationEvent('FAILED', 'SQL file empty');
            return;
        }

        $statements = $this->splitStatements($rawSql);
        $total      = count($statements);
        $this->writeLine("Parsed {$total} SQL statement(s).");
        $this->writeLine(str_repeat('-', 60));

        foreach ($statements as $index => $sql) {
            $stmtNum = $index + 1;
            $preview = $this->previewSql($sql);

            try {
                (new \SaaS\Repositories\DatabaseRepository($this->pdo))->executeCommand($sql);
                $this->passed++;
                $this->writeLine("[OK]   #{$stmtNum}: {$preview}");
            } catch (PDOException $e) {
                // Some "errors" are actually informational (e.g. column already exists in
                // older MySQL that doesn't support IF NOT EXISTS natively). We classify
                // them as warnings when the SQLSTATE is 42S21 (duplicate column).
                $sqlState = $e->getCode();
                $errMsg   = $e->getMessage();

                if ($this->isBenignAlterError($sqlState, $errMsg)) {
                    // Already exists – safe to continue
                    $this->passed++;
                    $this->writeLine("[SKIP] #{$stmtNum} (already applied): {$preview}");
                } else {
                    $this->failed++;
                    $this->writeLine("[FAIL] #{$stmtNum}: {$preview}");
                    $this->writeLine("       Error [{$sqlState}]: {$errMsg}");
                    error_log("RunMigration FAIL #{$stmtNum}: {$errMsg}");
                }
            }
        }

        $this->writeLine(str_repeat('-', 60));
        $this->writeLine("Result: {$this->passed} passed, {$this->failed} failed out of {$total} statements.");
        $this->writeLine('Completed at: ' . date('Y-m-d H:i:s'));

        $status = $this->failed === 0 ? 'SUCCESS' : 'PARTIAL';
        $this->recordMigrationEvent(
            $status,
            "passed={$this->passed} failed={$this->failed} total={$total}"
        );
    }

    // -------------------------------------------------------------------------
    // SQL parsing helpers
    // -------------------------------------------------------------------------

    /**
     * Split raw SQL into individual executable statements.
     *
     * Handles:
     *  - Single-line comments (--)
     *  - Multi-line comments (/* ... *\/)
     *  - Quoted strings (skips semicolons inside quotes)
     *  - PREPARE/EXECUTE/DEALLOCATE blocks
     *
     * @param string $sql
     * @return array<string>
     */
    private function splitStatements(string $sql): array
    {
        // Strip single-line comments that start at beginning of line or after whitespace
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        // Strip multi-line comments
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $statements = [];
        $buffer     = '';
        $length     = strlen($sql);
        $inString   = false;
        $strChar    = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $buffer .= $char;
                if ($char === $strChar && ($i === 0 || $sql[$i - 1] !== '\\')) {
                    $inString = false;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = true;
                $strChar  = $char;
                $buffer  .= $char;
                continue;
            }

            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        // Catch any trailing statement without a semicolon
        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        // Filter out blank or whitespace-only entries
        return array_values(array_filter($statements, static function (string $s): bool {
            return trim($s) !== '';
        }));
    }

    /**
     * Return a short preview of an SQL statement for log output.
     */
    private function previewSql(string $sql): string
    {
        $oneLine = preg_replace('/\s+/', ' ', trim($sql));
        return mb_substr($oneLine, 0, 120);
    }

    /**
     * Determine whether an ALTER TABLE error is benign (column/index already exists).
     *
     * MySQL SQLSTATE codes:
     *  42S21 = Column already exists (1060)
     *  42000 = Duplicate key name     (1061)
     */
    private function isBenignAlterError(string $sqlState, string $message): bool
    {
        $benignStates = ['42S21', '42000'];
        $benignPhrases = [
            'Duplicate column name',
            'Duplicate key name',
            'already exists',
        ];

        if (in_array($sqlState, $benignStates, true)) {
            foreach ($benignPhrases as $phrase) {
                if (stripos($message, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Audit logging
    // -------------------------------------------------------------------------

    /**
     * Record the migration outcome into tbl_omni_events for audit purposes.
     */
    private function recordMigrationEvent(string $status, string $detail): void
    {
        try {
            // Ensure tenant_id column exists before inserting (graceful fallback)
            $columns = $this->getTableColumns('tbl_omni_events');

            $hasTenantId    = in_array('tenant_id', $columns, true);
            $hasFingerprint = in_array('event_fingerprint', $columns, true);
            $hasMetadata    = in_array('metadata', $columns, true);

            $fingerprint = hash('sha256', self::MIGRATION_NAME . '_' . $status . '_' . date('Y-m-d'));

            if ($hasTenantId && $hasFingerprint && $hasMetadata) {
                $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                    INSERT IGNORE INTO `tbl_omni_events`
                        (`tenant_id`, `event_type`, `entity_type`, `status`, `event_fingerprint`, `metadata`, `created_at`)
                    VALUES
                        (0, :evt, 'migration', :status, :fp, :meta, NOW())
                ");
                $stmt->execute([
                    ':evt'    => self::MIGRATION_EVENT_TYPE,
                    ':status' => $status,
                    ':fp'     => $fingerprint,
                    ':meta'   => json_encode([
                        'migration' => self::MIGRATION_NAME,
                        'detail'    => $detail,
                        'passed'    => $this->passed,
                        'failed'    => $this->failed,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            } elseif ($hasMetadata) {
                $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->prepare("
                    INSERT INTO `tbl_omni_events`
                        (`event_type`, `entity_type`, `status`, `metadata`, `created_at`)
                    VALUES
                        (:evt, 'migration', :status, :meta, NOW())
                ");
                $stmt->execute([
                    ':evt'    => self::MIGRATION_EVENT_TYPE,
                    ':status' => $status,
                    ':meta'   => json_encode([
                        'migration' => self::MIGRATION_NAME,
                        'detail'    => $detail,
                    ], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $this->writeLine("[AUDIT] Migration event recorded: status={$status}");
        } catch (Exception $e) {
            // Non-fatal: audit logging failure should not block migration
            $this->writeLine("[AUDIT WARN] Could not record migration event: " . $e->getMessage());
            error_log('RunMigration audit error: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve column names for a given table.
     *
     * @return array<string>
     */
    private function getTableColumns(string $table): array
    {
        try {
            $stmt = (new \SaaS\Repositories\DatabaseRepository($this->pdo))->query("SHOW COLUMNS FROM `{$table}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_column($rows, 'Field');
        } catch (Exception $e) {
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Output helpers
    // -------------------------------------------------------------------------

    private function writeLine(string $line): void
    {
        $this->log[] = $line;
        if (php_sapi_name() === 'cli') {
            echo $line . PHP_EOL;
        }
    }
}

// =============================================================================
// CLI entry point
// =============================================================================
if (php_sapi_name() === 'cli') {
    // Resolve paths relative to this script's location
    $incDir    = dirname(__DIR__, 2); // admin/inc
    $configFile = $incDir . '/config.php';

    if (!file_exists($configFile)) {
        // Try one level up (running from different cwd)
        $configFile = __DIR__ . '/../../../../admin/inc/config.php';
    }

    if (!file_exists($configFile)) {
        echo '[FATAL] Cannot locate config.php. Expected at: ' . $configFile . PHP_EOL;
        exit(1);
    }

    require_once $configFile;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        echo '[FATAL] $pdo not available after loading config.php' . PHP_EOL;
        exit(1);
    }

    $result = RunMigration::run($pdo);

    echo PHP_EOL;
    echo ($result['failed'] === 0)
        ? '>>> Phase 1 Migration: PASSED (' . $result['passed'] . ' statements OK)' . PHP_EOL
        : '>>> Phase 1 Migration: PARTIAL — ' . $result['failed'] . ' statement(s) failed.' . PHP_EOL;

    exit($result['failed'] > 0 ? 1 : 0);
}
