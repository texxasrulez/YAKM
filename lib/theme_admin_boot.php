<?php
declare(strict_types=1);
/**
 * Universal theme bootstrap for ADMIN/preview context.
 * Provides the helper(s) and variables most themes expect from send_mail.php.
 * Safe to include multiple times.
 */

// polyfill clean_string()
if (!function_exists('clean_string')) {
    function clean_string($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    }
}

// Build a settings array from kontact_settings and expose a KCFG() helper
if (!isset($__KONTACT_SETTINGS_BOOTSTRAPPED)) {
    $__KONTACT_SETTINGS_BOOTSTRAPPED = true;
    try {
        if (!isset($db) || !($db instanceof \Kontact\Database)) {
            require_once __DIR__ . '/bootstrap.php';
            $db = new \Kontact\Database($GLOBALS['cfg']);
        }
        $rows = $db->fetchAll('SELECT `key`,`value` FROM kontact_settings');
        $KS = [];
        foreach ($rows as $r) { $KS[$r['key']] = $r['value']; }
    } catch (\Throwable $e) {
        $KS = [];
    }

    if (!function_exists('KCFG')) {
        function KCFG(string $key, $default=null){
            global $KS;
            if (!is_array($KS)) $KS = [];
            $u = strtoupper($key);
            return $KS[$key] ?? $KS[$u] ?? $default;
        }
    }

    // Expose common variables many themes use
    $site_name  = KCFG('SITE_NAME',  KCFG('site_name','Website'));
    $site_url   = KCFG('SITE_URL',   KCFG('site_url',''));
    $site_logo  = KCFG('SITE_LOGO',  KCFG('site_logo',''));
    $form_title = KCFG('FORM_TITLE', KCFG('form_title','Visitor'));
    $form_name  = KCFG('FORM_NAME',  KCFG('form_name','Contact'));
    $date  = date('r');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua    = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Friendly sample data so templates render something
    if (!isset($name))    $name    = 'Ada Lovelace';
    if (!isset($email))   $email   = 'ada@example.com';
    if (!isset($subject)) $subject = 'Hello from the Preview';
    if (!isset($message)) $message = "This is a preview of your email template.\nLine 2.";

    // Some themes expect $email_body to be set/modified
    if (!isset($email_body)) $email_body = '';
}
