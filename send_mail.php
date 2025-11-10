<?php
declare(strict_types=1);
use function Kontact\render_theme_html;

/**
 * Detect if the currently selected EMAIL_THEME supports $message_html (inner wrapper content).
 * If not, we'll swap to wrapper_theme.php for auto-replies only.
 */
$__kontact_theme_supports_inner = (function(array $cfg): bool {
    try {
        $theme = $cfg['EMAIL_THEME'] ?? '';
        if (!$theme) return false;
        $file = __DIR__ . '/themes/' . basename((string)$theme);
        if (!is_file($file)) return false;
        $s = @file_get_contents($file);
        return is_string($s) && strpos($s, '$message_html') !== false;
    } catch (\Throwable $e) { return false; }
});

/** Lightweight logger for Kontact */
if (!function_exists('kontact_log')) {
    function kontact_log($file, $line){
        $logdir = __DIR__ . '/storage/logs';
        if (!is_dir($logdir)) @mkdir($logdir, 0775, true);
        @file_put_contents($logdir . '/' . $file, '[' . date('c') . '] ' . $line . "\n", FILE_APPEND);
    }
}

/* Load config + optional libs */
$cfg_file = __DIR__ . '/config/config.inc.php';
if (!is_file($cfg_file)) { http_response_code(500); exit('Missing config.inc.php'); }
$FILE_CFG = include $cfg_file;
if (is_file(__DIR__ . '/lib/bootstrap.php')) require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/blocklist.php';
if (is_file(__DIR__ . '/lib/recaptcha_enterprise.php')) require_once __DIR__ . '/lib/recaptcha_enterprise.php';
if (is_file(__DIR__ . '/lib/Mailer.php'))    require_once __DIR__ . '/lib/Mailer.php';
@set_time_limit(20);
@ini_set('default_socket_timeout','5');


/* Helpers */
function KCFG(string $key, $default=null){
    if (function_exists('\Kontact\cfg')) return \Kontact\cfg($key,$default);
    global $FILE_CFG; $U=strtoupper($key); return $FILE_CFG[$key]??$FILE_CFG[$U]??$default;
}

/* --- ROBUST TEMPLATE SELECTION (DB-first, logged) --- */
if (!isset($GLOBALS['cfg']) || !is_array($GLOBALS['cfg'])) { $GLOBALS['cfg'] = []; }
try {
    if (class_exists('\Kontact\Database')) {
        $___db_for_tpl = new \Kontact\Database($GLOBALS['cfg']);
        $___pdo = $___db_for_tpl->pdo();
        if ($___pdo) {
            $st = $___pdo->query("SELECT `key`,`value` FROM kontact_settings");
            if ($st) {
                foreach ($st->fetchAll(\PDO::FETCH_NUM) as $row) {
                    $k = (string)$row[0]; $v = $row[1];
                    $GLOBALS['cfg'][$k] = $v;
                    $GLOBALS['cfg'][strtoupper($k)] = $v;
                }
            }
        }
    }
} catch (\Throwable $e) { /* ignore */ }
$__tplName = KCFG('AUTOREPLY_TEMPLATE', KCFG('AUTO_REPLY_TEMPLATE', 'auto_reply_ack'));
$__tplName = trim((string)$__tplName);
try {
    if (function_exists('kontact_log')) {
        $srcx = isset($GLOBALS['cfg']['AUTOREPLY_TEMPLATE']) ? 'db' : (isset($GLOBALS['cfg']['AUTO_REPLY_TEMPLATE']) ? 'file' : 'default');
        kontact_log('autoresponder.log', 'tpl_resolve src=' . $srcx . ' name=' . $__tplName);
    }
} catch (\Throwable $e) {}
/* --- END robust template selection --- */

function clean_string($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }

function deny_spam($reason, $spammer_page){
    @error_log("[kontact] denied: ".$reason);
    try {
        // Always log denies
        if (function_exists('kontact_log')) { kontact_log('antispam.log', 'deny reason='.(string)$reason.' ip='.($_SERVER['REMOTE_ADDR']??'')); }
        // Auto-block only captcha failures (not rate_limited, etc.)
        $r = (string)$reason;
        if (stripos($r,'captcha_fail') === 0) {
            if (class_exists('\Kontact\Database')) {
                try {
                    $cfg = $GLOBALS['cfg'] ?? [];
                    $db  = new \Kontact\Database($cfg);
                    $pdo = $db->pdo();
                    if ($pdo) {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS kontact_blocklist (
                            id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            ip VARCHAR(64) NOT NULL,
                            reason VARCHAR(255) NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            expires_at TIMESTAMP NULL,
                            UNIQUE KEY uniq_ip (ip)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        if ($ip !== '') {
                            $stmt = $pdo->prepare('INSERT INTO kontact_blocklist(ip,reason) VALUES(?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), created_at=NOW()');
                            $stmt->execute([$ip, $r]);
                                        try { \Kontact\blocklist_write_snapshot($db); } catch (\Throwable $e) { /* ignore */ }
if (function_exists('kontact_log')) { kontact_log('antispam.log', 'auto_block ip='.$ip.' reason='.$r); }
                        }
                    }
                } catch (\Throwable $e) {
                    if (function_exists('kontact_log')) { kontact_log('antispam.log', 'auto_block_error '.$e->getMessage()); }
                }
            }
        }
    } catch (\Throwable $e) { /* ignore */ }
    header("Location: " . $spammer_page); exit;
}

/* Pages */
$thankyou_page = KCFG('THANKYOU_PAGE', KCFG('thankyou_page','/thank_you.html'));
$error_page    = KCFG('ERROR_PAGE',    KCFG('error_page',   '/error_message.html'));
$spammer_page  = KCFG('SPAMMER_PAGE',  KCFG('ruaspammer',   '/spammer.html'));

/* Security pre-checks */
try {
    if (class_exists('\Kontact\Security') && class_exists('\Kontact\Database')) {
        $dbForSec = new \Kontact\Database([
            'DATABASE_HOST'=>KCFG('DATABASE_HOST','localhost'),
            'DATABASE_USER'=>KCFG('DATABASE_USER',''),
            'DATABASE_PASS'=>KCFG('DATABASE_PASS',''),
            'DATABASE_NAME'=>KCFG('DATABASE_NAME',''),
        ]);
        if (\Kontact\Security::isBlocked($dbForSec)) { deny_spam('security:isBlocked', $spammer_page); }
    }
} catch (\Throwable $e) {}

/* LOG: begin submit attempt */
try {
  $ip0 = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua0 = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $keys0 = implode(',', array_slice(array_map('strval', array_keys($_POST)), 0, 25));
  kontact_log('submit_attempts.log', 'ip='.$ip0.' ua='.substr($ua0,0,180).' post_keys='.$keys0);
} catch (\Throwable $e) {}

/* Collect POST */
/* Client identifiers */
$ip = class_exists('\Kontact\Security') ? \Kontact\Security::ip() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
$ua = class_exists('\Kontact\Security') ? \Kontact\Security::userAgent() : ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
$user_ip = $ip;
$user_agent = $ua;
$posted_recipient_id = isset($_POST['recipient_id']) ? (int)$_POST['recipient_id'] : 0;
$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');

/* --- Rate limit: submissions per IP --- */
$rl_window = (int)KCFG('RATE_LIMIT_WINDOW_SEC', 120); // seconds
$rl_max    = (int)KCFG('RATE_LIMIT_MAX', 3);          // allowed count in window
if ($rl_max > 0 && $rl_window > 0) {
    $cutoff = date('Y-m-d H:i:s', time() - $rl_window);
    $ipAddr = (string)$ip;
    $recentCount = 0;
    if (isset($pdo) && $pdo) {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) c FROM kontact_audit WHERE event_type='submit' AND ip=? AND created_at > ?");
            $st->execute([$ipAddr, $cutoff]); $row = $st->fetch(); $recentCount = (int)($row['c'] ?? 0);
        } catch (\Throwable $e) { /* ignore */ }
    } else {
        $dir = __DIR__ . '/storage/ratelimit';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $ff = $dir . '/' . preg_replace('/[^a-z0-9\.\-_:]/i','_', $ipAddr) . '.txt';
        $timestamps = [];
        if (is_file($ff)) {
            $lines = @file($ff, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $ln) { $t = (int)$ln; if ($t > (time() - $rl_window)) $timestamps[] = $t; }
        }
        $recentCount = count($timestamps);
        // append current attempt preemptively; will not log on deny_spam
                $timestamps[] = time();
        @file_put_contents($ff, implode("\n", $timestamps));
    }
    if ($recentCount >= $rl_max) {
        deny_spam('rate_limit', $error_page);
    }
}
/* --- end Rate limit --- */
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
if ($name===''||$email===''||$subject===''||$message===''){ header("Location: $error_page"); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { header("Location: $error_page"); exit; }

// Sanitize header-related fields
$email   = preg_replace('/[\r\n]+/', '', $email);
$subject = preg_replace('/[\r\n]+/', ' ', $subject);
$name    = preg_replace('/[\r\n]+/', ' ', $name);
if (strlen($subject) > 200) $subject = substr($subject, 0, 200);
if (strlen($name) > 200)    $name    = substr($name, 0, 200);

/* CSRF + origin check */
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$csrf = (string)($_POST['csrf_token'] ?? ($_POST['csrf'] ?? ''));
$session_kontact_csrf = $_SESSION['kontact_csrf'] ?? null;
$session_legacy_csrf  = $_SESSION['csrf'] ?? null;

// Enforce CSRF only if a session token exists (script-driven pages may not pre-seed a token)
$csrf_required = is_string($session_kontact_csrf) || is_string($session_legacy_csrf);
$csrf_ok = false;
if ($csrf_required) {
    if (is_string($session_kontact_csrf) && $session_kontact_csrf !== '' && is_string($csrf) && $csrf !== '') {
        $csrf_ok = hash_equals($session_kontact_csrf, $csrf);
    }
    if (!$csrf_ok && is_string($session_legacy_csrf) && $session_legacy_csrf !== '' && is_string($csrf) && $csrf !== '') {
        $csrf_ok = hash_equals($session_legacy_csrf, $csrf);
    }
    if (!$csrf_ok) {
        deny_spam('csrf:missing_or_mismatch', $spammer_page);
    }
}

// Same-origin guard still applies
$origin = $_SERVER['HTTP_ORIGIN']  ?? '';
$refer  = $_SERVER['HTTP_REFERER'] ?? '';
$host   = $_SERVER['HTTP_HOST']    ?? '';
$bad_origin = false;
if ($origin) { $bad_origin = parse_url($origin, PHP_URL_HOST) !== $host; }
if ($refer)  { $bad_origin = $bad_origin || (parse_url($refer, PHP_URL_HOST) !== $host); }
if ($bad_origin) { deny_spam('origin_mismatch', $spammer_page); }

/* Anti-bot: honeypot + optional signed min submit time */
$hp = $_POST['hp'] ?? '';
if (is_string($hp) && trim($hp) !== '') { deny_spam('honeypot_filled', $spammer_page); }
$kt = isset($_POST['kt']) ? (int)$_POST['kt'] : 0;
$ksig = $_POST['ksig'] ?? '';
$submit_secret = (string)KCFG('SUBMIT_SECRET','');
$minSec = (int)KCFG('MIN_SUBMIT_SECONDS', 4);
$have_tokens = ($kt > 0 && (string)$ksig !== '');
if ($have_tokens) {
    $calc = hash_hmac('sha256', (string)$kt, $submit_secret);
    if (!hash_equals($calc, (string)$ksig)) {
        // Don't block; just log and continue (diagnostic-friendly)
        @error_log("[kontact] kt/ksig signature mismatch; skipping timing gate");
    } else {
        if ($kt>0 && $minSec>0 && time() - $kt < $minSec) {
            deny_spam('fast_submit', $spammer_page);
        }
    }
}

/* Recipient selection (JSON-config only) */
$recipient      = '';
$allowed        = [];
$json_map       = [];
$posted_id      = (int)$posted_recipient_id;
$mode_multi     = (string)KCFG('RECIPIENT_MODE','single');
$recipients_json= (string)KCFG('RECIPIENTS_JSON','');

try {
    if ($recipients_json !== '') {
        $decoded = json_decode($recipients_json, true);
        if (is_array($decoded)) {
            $idx = 1;
            foreach ($decoded as $row) {
                $em = isset($row['email']) ? trim((string)$row['email']) : '';
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $json_map[$idx] = $em;
                    $allowed[] = $em;
                    $idx++;
                }
            }
        }
    }
} catch (\Throwable $e) {
    try { kontact_log('routing.log', 'recipients_json_parse_error '.$e->getMessage()); } catch (\Throwable $ee) {}
}

// De-legacy: only JSON controls the dropdown. If no JSON, fall back to webmaster once.
$wm = KCFG('WEBMASTER_EMAIL','');
if (empty($allowed) && $wm && filter_var($wm, FILTER_VALIDATE_EMAIL)) {
    $allowed[] = $wm;
    $json_map[1] = $wm;
}

// Pick from posted id strictly via json_map
if ($posted_id >= 1 && isset($json_map[$posted_id])) {
    $recipient = $json_map[$posted_id];
}

// Final fallback: if nothing resolved, pick first allowed (if any)
if ($recipient==='' && !empty($allowed)) { $recipient = $allowed[0]; }

if ($recipient===''){ header("Location: $error_page"); exit; }

/* LOG routing */
try { 
    kontact_log('routing.log', 'resolved recipient=' . ($recipient?:'(none)') 
        . ' allowed=' . implode(',', $allowed) 
        . ' posted_id='.(int)$posted_id
        . ' mode='.$mode_multi);
} catch (\Throwable $e) {}

/* No legacy user_id mapping necessary; set 0 for analytics unless you want JSON-backed IDs persisted */
$user_id = 0;
/* reCAPTCHA (Enterprise) */
$rc_token = '';
if (isset($_POST['recaptcha_token']) && is_string($_POST['recaptcha_token'])) {
    $rc_token = $_POST['recaptcha_token'];
} elseif (isset($_POST['g-recaptcha-response']) && is_string($_POST['g-recaptcha-response'])) {
    $rc_token = $_POST['g-recaptcha-response'];
} elseif (isset($_POST['rce_token']) && is_string($_POST['rce_token'])) {
    $rc_token = $_POST['rce_token'];
} elseif (!empty($_SERVER['HTTP_X_RECAPTCHA_TOKEN'])) {
    $rc_token = (string)$_SERVER['HTTP_X_RECAPTCHA_TOKEN'];
} else {
    foreach ($_POST as $k => $v) {
        if (!is_string($k) || !is_string($v)) continue;
        if (preg_match('/recaptcha|g-recaptcha|captcha_token/i', $k)) { $rc_token = $v; break; }
    }
}
$rc_token = is_string($rc_token) ? trim($rc_token) : '';

$rc_site  = (string)KCFG('RECAPTCHA_SITE','');
$rc_proj  = (string)KCFG('RECAPTCHA_PROJECT_ID','');
$rc_key   = (string)KCFG('RECAPTCHA_API_KEY','');
if ($rc_site && $rc_proj && $rc_key) {
    if (!function_exists('\Kontact\recaptcha_enterprise_verify')) { deny_spam('captcha:init', $error_page); }
    [$rc_ok, $rc_score, $rc_reason, $rc_raw] = \Kontact\recaptcha_enterprise_verify($GLOBALS['cfg'] ?? [], $rc_token, $_SERVER['REMOTE_ADDR'] ?? '');
    try { kontact_log('recaptcha.log', 'ok='.(int)$rc_ok.' score='.(string)$rc_score.' reason='.(string)$rc_reason.' ip='.($ip??'')); } catch (\Throwable $e) {}
    if (!$rc_ok) { deny_spam('captcha_fail:'.$rc_reason, $error_page); }
}

/* Compose email (theme + plain text) */
$site_name  = KCFG('SITE_NAME',  KCFG('site_name','Website'));
$site_url   = KCFG('SITE_URL',   KCFG('site_url',''));
$site_logo  = KCFG('SITE_LOGO',  KCFG('site_logo',''));
$form_title = KCFG('FORM_TITLE', KCFG('form_title','Visitor'));
$form_name  = KCFG('FORM_NAME',  KCFG('form_name','Contact'));
$date  = date('r');

$admin_inner = ""
  . "<h2>".clean_string($site_name)." — ".clean_string($form_name)."</h2>"
  . "<p><strong>From:</strong> ".clean_string($name)." &lt;".clean_string($email)."&gt;</p>"
  . "<p><strong>Subject:</strong> ".clean_string($subject)."</p>"
  . "<p><strong>Message:</strong><br>".nl2br(clean_string($message))."</p>"
  . "<hr><p style=\"font-size:12px;opacity:.8\">Sent: ".$date." • IP: ".clean_string($ip)." • UA: ".clean_string($ua)."</p>";
$email_body = render_theme_html($GLOBALS['cfg'] ?? [], $subject, $admin_inner, [
  'site_name'=>$site_name, 'site_url'=>$site_url, 'form_title'=>$form_title, 'form_name'=>$form_name,
  'name'=>$name, 'email'=>$email, 'user_ip'=>$ip, 'message'=>$message, 'subject'=>$subject
]);

$plain_text  = $site_name." — ".$form_name."\n";
$plain_text .= "From: $name <$email>\n";
$plain_text .= "Subject: $subject\n\n";
$plain_text .= $message."\n\n";
$plain_text .= "Sent: $date • IP: $ip • UA: $ua\n";
if ($site_url) $plain_text .= "$site_url\n";

/* DB logging — dynamic columns */
$pdo = null; $db = null;
try {
    $db_host=KCFG('DATABASE_HOST',''); $db_name=KCFG('DATABASE_NAME','');
    $db_user=KCFG('DATABASE_USER',''); $db_pass=KCFG('DATABASE_PASS','');
    $table = KCFG('DATABASE_TABLE','kontacts'); $table = preg_replace('/[^A-Za-z0-9_]/','',$table) ?: 'kontacts';

    if ($db_host && $db_name && $db_user && class_exists('\Kontact\Database')) {
        $db = new \Kontact\Database(['DATABASE_HOST'=>$db_host,'DATABASE_USER'=>$db_user,'DATABASE_PASS'=>$db_pass,'DATABASE_NAME'=>$db_name]);
        $pdo = $db->pdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `$table` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NULL,
            `subject` VARCHAR(255) NOT NULL,
            `email` VARCHAR(255) NOT NULL,
            `message` MEDIUMTEXT NOT NULL,
            `user_ip` VARCHAR(64) NOT NULL,
            `user_id` INT NULL DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) { $cols[$r['Field']] = true; }

        $candidates = ['name','subject','email','message','user_ip','user_id'];
        $insCols = [];
        foreach ($candidates as $c) { if (isset($cols[$c])) $insCols[] = $c; }
        if (!empty($insCols)) {
            $ph = implode(',', array_fill(0, count($insCols), '?'));
            $sql = "INSERT INTO `$table` (".implode(',', $insCols).") VALUES ($ph)";
            $st = $pdo->prepare($sql);
            $vals = [];
            foreach ($insCols as $c) { $vals[] = ${$c}; }
            $st->execute($vals);
        }
    }
} catch (\Throwable $e) { /* ignore DB errors for send flow */ }

/* Throttling and content checks */
$domain = substr(strrchr($email, "@"), 1) ?: '';
$skip_mx = (KCFG('SKIP_MX_CHECK','1')==='1');
if (!$skip_mx && $domain && !@checkdnsrr($domain, 'MX')) { header("Location: $error_page"); exit; }

$max_links = (int)KCFG('MAX_LINKS', 3);
if ($max_links >= 0) {
    preg_match_all('#https?://#i', $message, $m1);
    if (count($m1[0]) > $max_links) { deny_spam('too_many_links', $spammer_page); }
}
$kw = (string)KCFG('SPAM_KEYWORDS','');
if ($kw !== '') {
    $re = '/'.str_replace('\|','|', preg_quote($kw,'/')).'/i';
    if (preg_match($re, $subject.' '.$message)) { deny_spam('keyword_hit', $spammer_page); }
}

if (isset($pdo) && $pdo) {
    $limitPer = (int)KCFG('SUBMIT_THROTTLE_PER_MIN', 5);
    $windowMin = (int)KCFG('SUBMIT_THROTTLE_WINDOW_MIN', 10);
    $st2 = $pdo->prepare("SELECT COUNT(*) c FROM `$table` WHERE user_ip=? AND created_at > (NOW() - INTERVAL ? MINUTE)");
    $st2->execute([$user_ip, $windowMin]); $row2=$st2->fetch(); $c2 = (int)($row2['c'] ?? 0);
    if ($c2 > $limitPer) { deny_spam('rate_limited', $spammer_page); }
}

/* LOG: message build */
try { kontact_log('message_build.log', 'subject_len=' . strlen($subject) . ' html_len=' . strlen($email_body) . ' text_len=' . strlen($plain_text)); } catch (\Throwable $e) {}

/* Send email using Mailer (SMTP or mail()) */
$fromEmail = KCFG('FROM_EMAIL', KCFG('WEBMASTER_EMAIL','no-reply@localhost'));
$fromName  = KCFG('FROM_NAME',  $site_name ?: 'Kontact');
$smtpOpts = [
    'enable_smtp' => (KCFG('ENABLE_SMTP','0')==='1' && KCFG('SMTP_HOST','')!==''),
    'host' => KCFG('SMTP_HOST',''),
    'port' => (int)KCFG('SMTP_PORT',587),
    'user' => KCFG('SMTP_USER',''),
    'pass' => KCFG('SMTP_PASS',''),
    'secure' => KCFG('SMTP_SECURE','tls'),
    'from_email' => $fromEmail,
    'from_name'  => $fromName,
    'reply_email'=> $email,
    'dkim_domain'     => KCFG('DKIM_DOMAIN',''),
    'dkim_selector'    => KCFG('DKIM_SELECTOR',''),
    'dkim_private_key' => KCFG('DKIM_PRIVATE_KEY',''),
    'dkim_identity'    => KCFG('DKIM_IDENTITY',''),
];
$sent = class_exists('\Kontact\Mailer')
    ? \Kontact\Mailer::send($recipient, $subject, $email_body, $plain_text, $smtpOpts)
    : (KCFG('ALLOW_MAIL_FUNCTION','0')==='1' ? @mail($recipient, $subject, $email_body, "From: $email\r\nReply-To: $email\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n") : false);

/* LOG: delivery attempt + result */
try {
    $method = class_exists('\Kontact\Mailer') ? 'mailer' : ((KCFG('ALLOW_MAIL_FUNCTION','0')==='1') ? 'mail()' : 'disabled');
    kontact_log('delivery.log', 'attempt method='.$method.' to='.$recipient.' subject='.substr($subject,0,140));
    kontact_log($sent ? 'send_success.log' : 'send_errors.log', 'to='.$recipient.' from='.$fromEmail.' subject='.substr($subject,0,140));
} catch (\Throwable $e) {}

/* Auto-reply (optional, throttled) */
try {
    kontact_log('autoresponder.log', 'begin auto-reply flow');
    kontact_log('autoresponder.log', 'flags: sent='.(int)$sent.', ENABLE_AUTOREPLY='.KCFG('ENABLE_AUTOREPLY','0').', AUTO_REPLY_ENABLE='.KCFG('AUTO_REPLY_ENABLE','0').', email='.($email?:'(none)'));

    if ($sent && ((KCFG('ENABLE_AUTOREPLY','0')==='1') || (KCFG('AUTO_REPLY_ENABLE','0')==='1')) && $email) {
        // Determine throttle backend
        $canSend = true;
        if (isset($pdo) && $pdo) {
            $thMin = (int)KCFG('AUTOREPLY_THROTTLE_MIN', 60);
            $pdo->exec("CREATE TABLE IF NOT EXISTS kontact_audit (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(64) NOT NULL,
                ip VARCHAR(64) NULL,
                user_agent VARCHAR(255) NULL,
                detail VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $st3 = $pdo->prepare("SELECT COUNT(*) c FROM kontact_audit WHERE event_type='auto_reply' AND detail=? AND created_at > (NOW() - INTERVAL ? MINUTE)");
            $st3->execute([$email, $thMin]); $row3=$st3->fetch(); $canSend = ((int)($row3['c'] ?? 0) === 0);
            kontact_log('autoresponder.log', 'pdo throttle: thMin='.$thMin.', prior_count='.((int)($row3['c'] ?? 0)));
        } else {
            $thMin = (int)KCFG('AUTOREPLY_THROTTLE_MIN', 60);
            $hash = sha1(strtolower(trim((string)$email)));
            $ffdir = __DIR__ . '/storage/auto_reply';
            if (!is_dir($ffdir)) @mkdir($ffdir, 0775, true);
            $ff = $ffdir . '/' . $hash . '.txt';
            if (is_file($ff)) {
                $last = (int)@file_get_contents($ff);
                $canSend = (time() - $last) > ($thMin * 60);
                kontact_log('autoresponder.log', 'fs throttle: last='.@date('c',$last).', canSend='.(int)$canSend);
            } else {
                kontact_log('autoresponder.log', 'fs throttle: first send');
            }
        }

        $tplName = $__tplName;
        kontact_log('autoresponder.log', 'template='.$tplName);

        // Fetch template via DB even if $db wasn't set
        $tpl = null;
        $__tpldb = null;
        if (isset($db) && $db && method_exists($db,'fetchOne')) { $__tpldb = $db; }
        elseif (class_exists('\Kontact\Database')) { try { $__tpldb = new \Kontact\Database($GLOBALS['cfg'] ?? []); } catch (\Throwable $e) { $__tpldb = null; } }
        if ($__tpldb && method_exists($__tpldb,'fetchOne')) {
            try { $tpl = $__tpldb->fetchOne('SELECT subject, body_html FROM kontact_templates WHERE LOWER(name)=LOWER(?) LIMIT 1', [$tplName]); }
            catch (\Throwable $e) { $tpl = null; }
        }
        if (!$tpl) { kontact_log('autoresponder.log', 'template_not_found name=' . $tplName); }

        $replacements = [
            '{{site_name}}'    => $site_name,
            '{{form_name}}'    => $form_name,
            '{{name}}'         => clean_string($name),
            '{{email}}'        => clean_string($email),
            '{{subject}}'      => clean_string($subject),
            '{{message_html}}' => nl2br(clean_string($message)),
        ];

        $subTpl     = $tpl['subject'] ?? ('We received your message — ' . $site_name);
        $ar_subject = strtr($subTpl, $replacements);

        $ar_inner = $tpl ? strtr($tpl['body_html'], $replacements)
                         : ('<p>Hi '.clean_string($name).',</p>'
                            .'<p>We received your message and will get back to you shortly.</p>');

        $cfg_for_ar = $GLOBALS['cfg'] ?? [];
        if (!($__kontact_theme_supports_inner)($cfg_for_ar)) { $cfg_for_ar['EMAIL_THEME'] = 'wrapper_theme.php'; }

        $ar_body_html = render_theme_html($cfg_for_ar, $ar_subject, $ar_inner, [
          'site_name'=>$site_name, 'site_url'=>$site_url, 'form_title'=>$form_title, 'form_name'=>$form_name,
          'name'=>$name, 'email'=>$email, 'user_ip'=>$ip,
          // IMPORTANT: force AR vars (avoid echo of original submission)
          'subject'=>$ar_subject,
          'message'=>'',
          'message_html'=>$ar_inner
        ]);

        // Resolve CSS variables (var(--x)) from :root for picky email clients
        try {
            if (preg_match('~<style[^>]*>(.*?)</style>~is', $ar_body_html, $mcss)) {
                $css = (string)$mcss[1];
                $cssResolved = $css;
                if (preg_match('~:root\\s*\\{(.*?)\\}~is', $css, $mroot)) {
                    $rootDecl = $mroot[1];
                    if (preg_match_all('~--([a-zA-Z0-9_-]+)\\s*:\\s*([^;]+);~', $rootDecl, $mvars, PREG_SET_ORDER)) {
                        $map = [];
                        foreach ($mvars as $mv) { $map[$mv[1]] = trim($mv[2]); }
                        $cssResolved = preg_replace_callback('~var\\(\\s*--([a-zA-Z0-9_-]+)\\s*\\)~', function($mm) use ($map) {
                            $k = $mm[1]; return isset($map[$k]) ? $map[$k] : $mm[0];
                        }, $css);
                        $ar_body_html = str_replace($css, $cssResolved, $ar_body_html);
                    }
                }
            }
        } catch (\Throwable $e) {}

        $ar_text = "Hi $name,\n\nWe received your message and will get back to you shortly.\n\n— $site_name\n";

        // Allow custom FROM for auto-replies, else reuse site defaults
        $ar_from_email = KCFG('AUTO_REPLY_FROM_EMAIL', $fromEmail);
        $ar_from_name  = KCFG('AUTO_REPLY_FROM_NAME',  $fromName);

        if ($canSend) {
            $ok = class_exists('\Kontact\Mailer')
                ? \Kontact\Mailer::send($email, $ar_subject, $ar_body_html, $ar_text, [
                    'enable_smtp'=>$smtpOpts['enable_smtp'],'host'=>$smtpOpts['host'],'port'=>$smtpOpts['port'],
                    'user'=>$smtpOpts['user'],'pass'=>$smtpOpts['pass'],'secure'=>$smtpOpts['secure'],
                    'from_email'=>$ar_from_email,'from_name'=>$ar_from_name,'reply_email'=>$recipient
                ])
                : (KCFG('ALLOW_MAIL_FUNCTION','0')==='1' ? @mail($email, $ar_subject, $ar_body_html, "From: $ar_from_email\r\nReply-To: $recipient\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n") : false);

            kontact_log('autoresponder.log', 'send='.(int)$ok.', to='.$email.', subject='.$ar_subject);
            if (!$ok) { kontact_log('autoresponder.log', 'send returned false — check SMTP or mail()'); }

            if (isset($pdo) && $pdo) {
                $st4=$pdo->prepare("INSERT INTO kontact_audit (event_type, ip, user_agent, detail) VALUES (?,?,?,?)");
                $st4->execute(['auto_reply', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $email]);
            } else {
                // file throttle touch
                $ffdir = __DIR__ . '/storage/auto_reply';
                if (!is_dir($ffdir)) @mkdir($ffdir, 0775, true);
                $hash = sha1(strtolower(trim((string)$email)));
                @file_put_contents($ffdir . '/' . $hash . '.txt', (string)time());
            }
        } else {
            kontact_log('autoresponder.log', 'throttled — not sending');
        }
    }
} catch (\Throwable $e) { kontact_log('autoresponder.log', 'exception: '.$e->getMessage()); }

// Record successful submission for rate-limit/audit
try {
    if (isset($pdo) && $pdo) {
        $st5 = $pdo->prepare("INSERT INTO kontact_audit (event_type, ip, user_agent, detail) VALUES (?,?,?,?)");
        $st5->execute(['submit', $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', $email]);
    }
} catch (\Throwable $e) { /* ignore */ }

/* Redirect */
header("Location: " . ($sent ? $thankyou_page : $error_page));
exit;
