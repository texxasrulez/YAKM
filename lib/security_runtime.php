<?php
declare(strict_types=1);
namespace Kontact;

use Kontact\Database;

/**
 * Audit helper and rate-limit enforcement for public endpoints like send_mail.php.
 * Integrate by requiring this file and calling enforce_rate_limit_or_abort($cfg).
 */
function audit(string $type, ?string $ip, ?string $ua, ?string $detail=null): void {
  try {
    $db = new Database($GLOBALS['cfg']);
    $db->exec('CREATE TABLE IF NOT EXISTS kontact_audit (
      id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      event_type VARCHAR(64) NOT NULL,
      ip VARCHAR(64) NULL,
      user_agent VARCHAR(255) NULL,
      detail VARCHAR(255) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $db->exec('INSERT INTO kontact_audit(event_type, ip, user_agent, detail) VALUES(?,?,?,?)', [$type,$ip,$ua,$detail]);
  } catch (\Throwable $e) { /* best-effort */ }
}

/**
 * Checks rate limits using kontact_settings:
 *  RL_ENABLE (1/0), RL_WINDOW_MIN, RL_MAX_PER_IP, RL_NOTIFY (1/0)
 * If over limit, records an audit event and either exits with HTTP 429 or returns false.
 *
 * @return bool true if allowed, false if blocked
 */
function enforce_rate_limit_or_abort(array $server): bool {
  try {
    $db = new Database($GLOBALS['cfg']);
    // load settings
    $rows = $db->fetchAll('SELECT `key`,`value` FROM kontact_settings WHERE `key` IN ("RL_ENABLE","RL_WINDOW_MIN","RL_MAX_PER_IP","RL_NOTIFY")');
    $s = [];
    foreach ($rows as $r) { $s[$r['key']] = $r['value']; }
    if (($s['RL_ENABLE'] ?? '0') !== '1') return true;

    $ip = $server['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $server['HTTP_USER_AGENT'] ?? null;
    $win = max(1, (int)($s['RL_WINDOW_MIN'] ?? 10));
    $max = max(1, (int)($s['RL_MAX_PER_IP'] ?? 5));
    $notify = (($s['RL_NOTIFY'] ?? '0') === '1');

    // Count submissions from this IP in window using kontact_audit (event_type='form_submit')
    $db->exec('CREATE TABLE IF NOT EXISTS kontact_audit (
      id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      event_type VARCHAR(64) NOT NULL,
      ip VARCHAR(64) NULL,
      user_agent VARCHAR(255) NULL,
      detail VARCHAR(255) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $row = $db->fetchOne('SELECT COUNT(*) AS c FROM kontact_audit WHERE ip=? AND event_type="form_submit" AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)', [$ip, $win]);
    $count = (int)($row['c'] ?? 0);

    if ($count >= $max) {
      audit('rate_limit_block', $ip, $ua, "window=".$win."m max=".$max);
      if ($notify) {
        // Email webmaster
        $to = cfg('WEBMASTER_EMAIL', '');
        if ($to) {
          $site = cfg('SITE_NAME','Kontact');
          $subject = "[${site}] Rate limit triggered";
          $html = "<html><body><p>Rate limit triggered for IP <strong>{$ip}</strong>.</p><p>Window: {$win}m, Max: {$max}</p></body></html>";
          $text = "Rate limit triggered for IP {$ip}. Window: {$win}m, Max: {$max}\n";
          \Kontact\Mailer::send($to, $subject, $html, $text, [
            'enable_smtp' => (cfg('ENABLE_SMTP','0')==='1'),
            'host' => cfg('SMTP_HOST',''),
            'port' => (int)cfg('SMTP_PORT',587),
            'user' => cfg('SMTP_USER',''),
            'pass' => cfg('SMTP_PASS',''),
            'secure' => cfg('SMTP_SECURE','tls'),
            'from_email' => cfg('FROM_EMAIL', $to),
            'from_name'  => cfg('FROM_NAME', $site),
            'reply_email'=> $to,
          ]);
        }
      }
      // Block; send HTTP 429
      if (!headers_sent()) { http_response_code(429); }
      echo "Too many requests. Please try again later.";
      return false;
    }

    // Otherwise allow and record a submit attempt marker
    audit('form_submit', $ip, $ua, 'incoming');
    return true;
  } catch (\Throwable $e) {
    return true; // fail-open
  }
}
