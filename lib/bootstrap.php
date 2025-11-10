<?php
declare(strict_types=1);
namespace Kontact;

// i18n bootstrap (global tr/_e)
require_once __DIR__ . '/../localization/i18n.php';

/**
 * Kontact bootstrap (clean)
 * - Safe session start
 * - Load config into $GLOBALS['cfg'] (normalize upper keys)
 * - Load DB settings overlay from kontact_settings (if DB available)
 * - Ensure Database class is available
 * - Guarded helpers: CSRF, cfg()/cfgv(), render_theme_html(), clean_string()
 */

// Safe session
if (\session_status() !== \PHP_SESSION_ACTIVE) { @\session_start(); }

// Load config file
if (empty($GLOBALS['cfg'])) {
    $file = __DIR__ . '/../config/config.inc.php';
    if (\is_file($file)) {
        $loaded = require $file; // may return array and/or set $GLOBALS['cfg']
        if (\is_array($loaded) && empty($GLOBALS['cfg'])) { $GLOBALS['cfg'] = $loaded; }
    }
}
// Normalize config keys (also uppercased mirror)
if (!empty($GLOBALS['cfg']) && \is_array($GLOBALS['cfg'])) {
    $norm = [];
    foreach ($GLOBALS['cfg'] as $k => $v) {
        $norm[$k] = $v;
        $U = \strtoupper((string)$k);
        if (!\array_key_exists($U, $norm)) { $norm[$U] = $v; }
    }
    $GLOBALS['cfg'] = $norm;
}
$cfg = $GLOBALS['cfg'] ?? [];

// Load DB class
require_once __DIR__ . '/Database.php';

// Overlay DB settings from kontact_settings (if DB creds present)
try {
    $haveDb = !empty($cfg['DB_NAME']) || !empty($cfg['DATABASE_NAME']);
    if ($haveDb) {
        $db = new \Kontact\Database($cfg);
        $pdo = $db->pdo();
        $st = $pdo->query("SELECT `key`,`value` FROM kontact_settings");
        foreach ($st->fetchAll(\PDO::FETCH_NUM) as $row) {
            $GLOBALS['cfg'][(string)$row[0]] = $row[1];
            $GLOBALS['cfg'][\strtoupper((string)$row[0])] = $row[1];
        }
        $cfg = $GLOBALS['cfg'];
    }
} catch (\Throwable $e) {
    // Do not hard-fail admin UI if settings table missing or DB down
}

/* -------- CSRF helpers (guarded) -------- */
if (!\function_exists(__NAMESPACE__ . '\\csrf_token')) {
    function csrf_token(): string {
        if (\session_status() !== \PHP_SESSION_ACTIVE) { @\session_start(); }
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = \bin2hex(\random_bytes(16)); }
        return $_SESSION['csrf'];
    }
}
if (!\function_exists(__NAMESPACE__ . '\\csrf_verify')) {
    function csrf_verify($token): void {
        if (\session_status() !== \PHP_SESSION_ACTIVE) { @\session_start(); }
        $ok = \is_string($token) && \hash_equals($_SESSION['csrf'] ?? '', $token);
        if (!$ok) {
            \http_response_code(400);
            throw new \RuntimeException('Bad CSRF token');
        }
    }
}

/* -------- Config helpers (guarded) -------- */
if (!\function_exists(__NAMESPACE__ . '\\cfg')) {
    function cfg(string $key, $default = null) {
        $a = $GLOBALS['cfg'] ?? [];
        return \array_key_exists($key, $a) ? $a[$key] : (\array_key_exists(\strtoupper($key), $a) ? $a[\strtoupper($key)] : $default);
    }
}
if (!\function_exists(__NAMESPACE__ . '\\cfgv')) {
    function cfgv(string $key, string $default = ''): string {
        $v = cfg($key, $default);
        return \is_string($v) ? $v : (\is_null($v) ? $default : (string)$v);
    }
}

/* -------- Escaping helpers used by themes (guarded) -------- */
if (!\function_exists(__NAMESPACE__ . '\\clean_string')) {
    function clean_string($s): string {
        return \htmlspecialchars((string)$s, \ENT_QUOTES, 'UTF-8');
    }
}
if (!\function_exists(__NAMESPACE__ . '\\esc')) {
    function esc($s): string { return clean_string($s); }
}

/* -------- Theme wrapper (guarded) -------- */
if (!\function_exists(__NAMESPACE__ . '\\render_theme_html')) {
    function render_theme_html(array $cfg, string $subject, string $inner_html, array $vars = []): string {
        $theme = $cfg['EMAIL_THEME'] ?? '';
        $themePath = __DIR__ . '/../themes/' . \basename((string)$theme);

        // Common variables exposed to theme scope
        $site_name  = $cfg['SITE_NAME']  ?? 'Kontact';
        $site_url   = $cfg['SITE_URL']   ?? '';
        $site_logo  = $cfg['SITE_LOGO']  ?? '';
        $form_title = $cfg['FORM_TITLE'] ?? 'Visitor';
        $form_name  = $cfg['FORM_NAME']  ?? 'Contact';
        $message_html = $inner_html;
        $subject = $subject; // expose subject to theme

        foreach ($vars as $k=>$v) { ${$k} = $v; }
        if (!isset($user_ip)) { $user_ip = ''; }

        if ($theme && \is_file($themePath)) {
            \ob_start();
            try {
                
                // Ensure legacy theme expectations are met
                // 1) Global clean_string() used by old theme files
                if (!function_exists('clean_string')) {
                    // Try bundled polyfill (safe to require multiple times)
                    $poly = __DIR__ . '/clean_string_polyfill.php';
                    if (is_file($poly)) { require_once $poly; }
                    if (!function_exists('clean_string')) {
                        function clean_string($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
                    }
                }
                // 2) Provide common variables many themes expect
                if (!isset($name))    { $name = ''; }
                if (!isset($email))   { $email = ''; }
                if (!isset($user_ip)) { $user_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
                if (!isset($message)) { $message = strip_tags($inner_html); }
                if (!isset($subject)) { $subject = (string)$subject; }
include $themePath;
                $out = \ob_get_clean();
                if (\is_string($out) && \strlen($out)) { return $out; }
                // Legacy themes may set $email_body instead of echoing
                if (isset($email_body) && \is_string($email_body) && \strlen($email_body)) { return $email_body; }

                if (\is_string($out) && \strlen($out)) { return $out; }
            } catch (\Throwable $e) { \ob_end_clean(); }
        }

        // Fallback
        return "<!doctype html><html><head><meta charset=\"utf-8\"><title>".\htmlspecialchars($subject)."</title></head>"
             . "<body style=\"font-family:Arial,sans-serif;\">"
             . "<div style=\"max-width:680px;margin:20px auto;padding:16px;border:1px solid #eee;\">"
             . "<div style=\"margin-bottom:12px;\">"
             . ($site_logo ? "<img src=\"".\htmlspecialchars($site_logo)."\" alt=\"\" style=\"max-height:48px;\">" : "<strong>".\htmlspecialchars($site_name)."</strong>")
             . "</div>"
             . $inner_html
             . "</div></body></html>";
    }
}
