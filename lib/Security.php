<?php
declare(strict_types=1);
namespace Kontact;
require_once __DIR__ . '/bootstrap.php';

class Security {
    public static function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
    public static function userAgent(): string { return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'; }
    public static function isBlocked(Database $db): bool {
        $ip = self::ip();
        $row = $db->fetchOne('SELECT id FROM kontact_ip_blocklist WHERE ip = ?', [$ip]);
        return (bool)$row;
    }
    public static function recordFailure(?Database $db, string $type, string $detail=''): void {
        if (!$db) return;
        $db->insert('INSERT INTO kontact_audit (event_type, ip, user_agent, detail) VALUES (?,?,?,?)',
            [$type, self::ip(), self::userAgent(), $detail]);
    }
    public static function tooManyFailures(Database $db, string $type, int $limit, int $minutes): bool {
        $row = $db->fetchOne('SELECT COUNT(*) as c FROM kontact_audit WHERE event_type=? AND ip=? AND created_at > (NOW() - INTERVAL ? MINUTE)',
            [$type, self::ip(), $minutes]);
        return ($row['c'] ?? 0) >= $limit;
    }
}
