<?php
declare(strict_types=1);

use Kontact\Database;

try {
    require_once __DIR__ . '/../lib/auth.php';
    Kontact\require_admin();
    require_once __DIR__ . '/../lib/bootstrap.php';
    // Provide helpers/vars themes expect
    if (is_file(__DIR__.'/../lib/theme_admin_boot.php')) {
        require_once __DIR__ . '/../lib/theme_admin_boot.php';
    } else {
        // Fallback polyfill for clean_string and KCFG if boot not present
        if (!function_exists('clean_string')) {
            function clean_string($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
        }
        if (!function_exists('KCFG')) {
            function KCFG(string $key, $default=null){
                return $default;
            }
        }
    }

    $db = new Database($GLOBALS['cfg']);

    // Get template name
    $name = isset($_GET['name']) ? trim((string)$_GET['name']) : '';
    if ($name === '') { http_response_code(400); echo 'Missing ?name='; exit; }

    // Load template
    $tpl = $db->fetchOne('SELECT name, subject, body_html FROM kontact_templates WHERE name=?', [$name]);
    if (!$tpl) { http_response_code(404); echo 'Template not found'; exit; }

    // Load settings for placeholders + theme
    $rows = $db->fetchAll('SELECT `key`,`value` FROM kontact_settings');
    $s = []; foreach ($rows as $r) { $s[$r['key']] = $r['value']; }

    $site_name  = (string)($s['SITE_NAME'] ?? 'Website');
    $form_name  = (string)($s['FORM_NAME'] ?? 'Contact');
    $site_url   = (string)($s['SITE_URL'] ?? '');
    $site_logo  = (string)($s['SITE_LOGO'] ?? '');
    $email_theme= (string)($s['EMAIL_THEME'] ?? '');

    // Sample substitution values
    $sample = [
        '{{site_name}}'    => $site_name,
        '{{form_name}}'    => $form_name,
        '{{name}}'         => 'Alto Vezdezenheimer',
        '{{email}}'        => 'alto@example.com',
        '{{subject}}'      => 'Hello from the Preview',
        '{{message_html}}' => nl2br(htmlspecialchars("This is a preview of your email template.\nLine 2.", ENT_QUOTES, 'UTF-8')),
    ];

    $subject = (string)($tpl['subject'] ?? '');
    $body    = (string)($tpl['body_html'] ?? '');
    $rendered_subject = strtr($subject, $sample);
    $rendered_body    = strtr($body, $sample);

    // Prepare the variables themes usually expect
    $name    = 'Alto Vezdezenheimer';
    $email   = 'alto@example.com';
    $message = $rendered_body; // feed the template output as the message content
    $date    = date('r');
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua      = $_SERVER['HTTP_USER_AGENT'] ?? 'admin-preview';

    // --- Apply theme like send_mail.php ---
    $email_body = ''; // theme may set this
    $maybe = '';

    $theme_file = $email_theme;
    $theme_path = __DIR__ . '/../themes/' . basename((string)$theme_file);

    if ($theme_file && is_file($theme_path)) {
        ob_start();
        include $theme_path;
        $maybe = trim((string)ob_get_clean());
        if (isset($email_body) && stripos((string)$email_body, '<html') !== false) {
            // theme set $email_body correctly
        } elseif ($maybe !== '' && stripos($maybe, '<html') !== false) {
            $email_body = $maybe;
        } elseif (isset($message) && stripos((string)$message, '<html') !== false) {
            $email_body = (string)$message;
        }
    }

    // Fallback: if no theme or no html chosen above, wrap in minimal shell
    if (!$email_body || stripos($email_body, '<html') === false) {
        $inner = $rendered_body;
        $email_body = '<!doctype html><html><head><meta charset="utf-8"><title>'
            . htmlspecialchars($rendered_subject ?: ('Preview — '.$site_name), ENT_QUOTES, 'UTF-8')
            . '</title><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.5;padding:24px;color:#222}'
            . 'h2{margin:0 0 12px}</style>'
            . '</head><body>'
            . '<h2>' . clean_string($site_name) . ' — ' . clean_string($form_name) . '</h2>'
            . '<div>' . $inner . '</div>'
            . '</body></html>';
    }

    header('Content-Type: text/html; charset=utf-8');
    echo $email_body;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Template preview failed.' . "\n";
    // echo $e->getMessage();
    exit;
}
