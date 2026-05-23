<?php
declare(strict_types=1);
namespace Kontact;

use Kontact\Database;
use PDO;

if (\session_status() !== \PHP_SESSION_ACTIVE) { @\session_start(); }

/** Attempt admin login. Returns true on success. */
function login_attempt(string $email, string $password): bool {
    $db = new Database($GLOBALS['cfg'] ?? []);
    $pdo = $db->pdo();

    $st = $pdo->prepare("SELECT * FROM kontact_admins WHERE LOWER(email)=LOWER(?) AND is_active=1 LIMIT 1");
    $st->execute([$email]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { return false; }

    $hash = (string)($row['password_hash'] ?? '');
    if ($hash === '') { return false; }

    $ok = \password_verify($password, $hash);

    // Legacy fallback (md5/sha1) then upgrade
    if (!$ok) {
        $lh = \strtolower($hash);
        if (\strlen($hash) === 32 && $lh === \md5($password)) { $ok = true; }
        elseif (\strlen($hash) === 40 && $lh === \sha1($password)) { $ok = true; }
    }

    if (!$ok) { return false; }

    if (\password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $new = \password_hash($password, PASSWORD_DEFAULT);
        try {
            $pdo->prepare("UPDATE kontact_admins SET password_hash=? WHERE id=?")->execute([$new, (int)$row['id']]);
        } catch (\Throwable $e) { /* ignore */ }
    }

    $_SESSION['admin_id'] = (int)$row['id'];
    $_SESSION['admin_email'] = (string)$row['email'];
    $_SESSION['admin_role'] = (string)($row['role'] ?? 'admin');
    if (\function_exists('session_regenerate_id')) { @\session_regenerate_id(true); }

    try { $pdo->prepare("UPDATE kontact_admins SET last_login_at=NOW() WHERE id=?")->execute([(int)$row['id']]); } catch (\Throwable $e) {}

    return true;
}

function is_admin(): bool {
    return isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] > 0;
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: login.php?e=auth');
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
    if (\ini_get('session.use_cookies')) {
        $p = \session_get_cookie_params();
        \setcookie(\session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (\session_status() === \PHP_SESSION_ACTIVE) { \session_destroy(); }
}
