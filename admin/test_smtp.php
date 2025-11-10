<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/theme_admin_boot.php';
require_once __DIR__ . '/inc/layout.php';

use function Kontact\cfgv;
use function Kontact\render_theme_html;
use Kontact\Admin;

$cfg = $GLOBALS['cfg'] ?? [];

$to   = cfgv('WEBMASTER_EMAIL');
$sub  = 'SMTP Test • Kontact';
$inner = '<p>This is a <strong>themed</strong> SMTP test from Kontact.</p><p>If you see branding, your theme applied.</p>';

$html = render_theme_html($cfg, $sub, $inner, [
  'name' => 'SMTP Tester',
  'email' => $to ?: 'noreply@example.com',
  'user_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
  'subject' => $sub,
  'message' => 'This is a themed SMTP test message.',
    'name' => 'SMTP Tester',
    'email' => $to,
]);
$text = "This is a themed SMTP test from Kontact.\nTheme applied: " . ($cfg['EMAIL_THEME'] ?? '(none)') . "\n";

$err = null;
$ok  = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sent = false;
        if (\class_exists('\\Kontact\\Mailer')) {
            $mailer = new \Kontact\Mailer();
            if (\method_exists($mailer, 'send')) {
                $sent = $mailer->send($to, $sub, $html, $text, [
                    'from_email' => cfgv('FROM_EMAIL', cfgv('WEBMASTER_EMAIL')),
                    'from_name'  => cfgv('SITE_NAME', 'Kontact'),
                ]);
            }
        }
        if (!$sent) {
            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $from = cfgv('FROM_EMAIL', cfgv('WEBMASTER_EMAIL'));
            if ($from) { $headers .= "From: ".$from."\r\n"; }
            $sent = @mail($to, $sub, $html, $headers);
        }
        $ok = (bool)$sent;
        if (!$ok) { $err = 'Send attempt returned false.'; }
    } catch (\Throwable $e) { $err = $e->getMessage(); }
}

Admin\header_nav('SMTP Test', 'smtp');
?>
<div class="card">
 <div class="card">
  <h2><?php _e('smtp_test','SMTP Test'); ?></h2>
  <p><?php _e('current_theme','Current theme:'); ?> <code><?=htmlspecialchars($cfg['EMAIL_THEME'] ?? '(none)')?></code></p>
  <?php if ($ok): ?><div id="snotifications" class="flash"><?php _e('test_email_sent_to','Test email sent to'); ?> <?=htmlspecialchars($to)?> <?php _e('using_theme','using theme'); ?> <?=htmlspecialchars($cfg['EMAIL_THEME'] ?? '(none)')?>.</div><?php endif; ?>
  <?php if ($err): ?><div id="snotifications" class="flash error"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <form method="post">
    <button class="btn" type="submit"><?php _e('send_test_email','Send Test Email'); ?></button>
  </form>
</div>
</div>
<?php Admin\footer(); ?>
