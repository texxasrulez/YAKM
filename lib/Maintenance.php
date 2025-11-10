<?php
declare(strict_types=1);
namespace Kontact;

use Kontact\Database;

/**
 * Maintenance service: rotates logs, prunes audit rows, ensures indexes.
 * Returns a summary array for UI/CLI.
 */
function run_maintenance(array $cfg): array {
    $db  = new Database($cfg);
    $pdo = $db->pdo();
    $summary = [
        'rotated' => [], 'purged_logs' => 0, 'pruned_audit' => 0, 'indexes' => [], 'errors' => []
    ];

    $logDir = __DIR__ . '/../storage/logs';
    $logRetentionDays = (int)($cfg['LOG_RETENTION_DAYS'] ?? 30);
    $logMaxSizeBytes  = (int)($cfg['LOG_MAX_SIZE_BYTES'] ?? 1048576); // 1 MiB
    $now = time();

    // Rotate if too large
    if (is_dir($logDir)) {
        foreach (glob($logDir . '/*.log') as $log) {
            clearstatcache(true, $log);
            $size = @filesize($log);
            if ($size !== false && $size > $logMaxSizeBytes) {
                $stamp = date('Ymd-His', $now);
                $gz = $log . '.' . $stamp . '.gz';
                $data = @file_get_contents($log);
                if (is_string($data) && $data !== '') {
                    $gzdata = @gzencode($data, 6);
                    if ($gzdata !== false) { @file_put_contents($gz, $gzdata); $summary['rotated'][] = basename($gz); }
                }
                // Truncate
                $fh = @fopen($log, 'w'); if ($fh) fclose($fh);
            }
        }
        // Purge old logs
        $cutoff = $now - ($logRetentionDays * 86400);
        foreach (glob($logDir . '/*') as $f) {
            $mt = @filemtime($f);
            if ($mt !== false && $mt < $cutoff) { if (@unlink($f)) $summary['purged_logs']++; }
        }
    }

    // Prune audit rows
    $auditRetentionDays = (int)($cfg['AUDIT_RETENTION_DAYS'] ?? 30);
    $cutoffDate = date('Y-m-d H:i:s', $now - ($auditRetentionDays * 86400));
    try {
        $st = $pdo->prepare('DELETE FROM kontact_audit WHERE created_at < ?');
        $st->execute([$cutoffDate]);
        $summary['pruned_audit'] = $st->rowCount();
    } catch (\Throwable $e) {
        $summary['errors'][] = 'audit_prune: '.$e->getMessage();
    }

    // Ensure indexes
    try {
        $schemaRow = $db->fetchOne('SELECT DATABASE() as db', []);
        $schema = $schemaRow['db'] ?? null;
        if ($schema) {
            $have = function(string $table, string $name) use ($db, $schema): bool {
                $rows = $db->fetchAll(
                    'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND INDEX_NAME=? LIMIT 1',
                    [$schema, $table, $name]
                );
                return !empty($rows);
            };
            $tryCreate = function(string $sql, string $name) use ($db, &$summary) {
                try { $db->exec($sql); $summary['indexes'][] = $name; } catch (\Throwable $e) {}
            };
            if (!$have('kontact_audit','idx_audit_ev_detail_created')) {
                $tryCreate('CREATE INDEX idx_audit_ev_detail_created ON kontact_audit (event_type, detail, created_at)', 'idx_audit_ev_detail_created');
            }
            if (!$have('kontact_audit','idx_audit_created')) {
                $tryCreate('CREATE INDEX idx_audit_created ON kontact_audit (created_at)', 'idx_audit_created');
            }
            if (!$have('kontact_blocklist','idx_blocklist_ip')) {
                $tryCreate('CREATE INDEX idx_blocklist_ip ON kontact_blocklist (ip)', 'idx_blocklist_ip');
            }
        }
    } catch (\Throwable $e) {
        $summary['errors'][] = 'indexes: '.$e->getMessage();
    }

    return $summary;
}
