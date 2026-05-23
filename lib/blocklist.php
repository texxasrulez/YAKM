<?php
declare(strict_types=1);

namespace Kontact;

/**
 * Write kontact/feeds/blocked_ips.txt as a plain newline-separated list of active IPs.
 * Returns the number of IPs written. Best-effort; suppresses FS errors and logs.
 */
function blocklist_write_snapshot(\Kontact\Database $db): int {
    try {
        $pdo = $db->pdo();
        // Prune expired entries first
        $pdo->exec("DELETE FROM kontact_blocklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");

        $stmt = $pdo->query("SELECT ip FROM kontact_blocklist WHERE (expires_at IS NULL OR expires_at >= NOW()) ORDER BY created_at DESC, id DESC");
        $uniq = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $ip = trim((string)($row['ip'] ?? ''));
            if ($ip === '') { continue; }
            if (@filter_var($ip, FILTER_VALIDATE_IP)) {
                $uniq[$ip] = true;
            }
        }
        $ips = array_keys($uniq);
        $content = $ips ? (implode("\n", $ips) . "\n") : "";

        $dir  = __DIR__ . '/../feeds';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $tmp  = $dir . '/blocked_ips.txt.tmp';
        $dest = $dir . '/blocked_ips.txt';

        @file_put_contents($tmp, $content);
        @chmod($tmp, 0644);
        @rename($tmp, $dest);
        if (\function_exists('kontact_log')) { try { \kontact_log('security.log', 'blocklist_snapshot_write count=' . count($ips)); } catch (\Throwable $e) {} }
        return count($ips);
    } catch (\Throwable $e) {
        if (\function_exists('kontact_log')) { try { \kontact_log('security.log', 'blocklist_snapshot_error ' . $e->getMessage()); } catch (\Throwable $e2) {} }
        return 0;
    }
}
