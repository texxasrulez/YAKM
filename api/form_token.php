<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Make sure HTTPS cookies behave; you can also set these in php.ini
if (PHP_SAPI !== 'cli') {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }
}

$secret = '';
$cfg_file = __DIR__ . '/../config/config.inc.php';
if (is_file($cfg_file)) {
    $cfg = include $cfg_file;
    if (is_array($cfg)) {
        $secret = (string)($cfg['SUBMIT_SECRET'] ?? $cfg['submit_secret'] ?? '');
    }
}

// Token pieces
$kt = time();
$csrf = bin2hex(random_bytes(16));
$_SESSION['kontact_csrf'] = $csrf;

// Optional HMAC guard so kt can't be forged trivially
$ksig = $secret !== '' ? hash_hmac('sha256', (string)$kt, $secret) : '';

echo json_encode([
    'kt' => $kt,
    'ksig' => $ksig,
    'csrf_token' => $csrf,
]);
