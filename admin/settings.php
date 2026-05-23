<?php
declare(strict_types=1);
use Kontact\Database;
require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';

$db = new Database($GLOBALS['cfg']);
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);

    // --- Keep/replace logic for SUBMIT_SECRET using masked UI text ---
    $existing_secret_row = $db->fetchOne('SELECT `value` FROM kontact_settings WHERE `key`=?', ['SUBMIT_SECRET']);
    $existing_secret = (string)($existing_secret_row['value'] ?? '');

    $masked_existing = '';
    if ($existing_secret !== '') {
        $len = strlen($existing_secret);
        $masked_existing = ($len <= 4)
            ? str_repeat('•', $len)
            : (substr($existing_secret, 0, 2) . str_repeat('•', max(0, $len - 4)) . substr($existing_secret, -2));
    }

    $submitted_secret = isset($_POST['submit_secret']) ? trim((string)$_POST['submit_secret']) : '';
    $final_secret = ($submitted_secret === '' || $submitted_secret === $masked_existing)
        ? $existing_secret
        : $submitted_secret;

    $pairs = [
        'WEBMASTER_EMAIL' => trim($_POST['webmaster_email'] ?? ''),
        'ADDITIONAL_EMAILS' => trim($_POST['additional_emails'] ?? ''),
        'RECAPTCHA_SITE' => trim($_POST['recaptcha_site'] ?? ''),
        'RECAPTCHA_SECRET' => trim($_POST['recaptcha_secret'] ?? ''),
        'EMAIL_THEME' => trim($_POST['email_theme'] ?? ''),
        'RECAPTCHA_PROJECT_ID' => trim($_POST['recaptcha_project_id'] ?? ''),
        'RECAPTCHA_API_KEY' => trim($_POST['recaptcha_api_key'] ?? ''),
        'SUSPICIOUS_EMAIL_NOTIFY' => isset($_POST['suspicious_email_notify']) ? '1' : '0',

        // Site meta
        'SITE_NAME' => trim($_POST['site_name'] ?? ''),
        'SITE_URL'  => trim($_POST['site_url'] ?? ''),
        'SITE_LOGO' => trim($_POST['site_logo'] ?? ''),
        'FORM_TITLE'=> trim($_POST['form_title'] ?? ''),
        'FORM_NAME' => trim($_POST['form_name'] ?? ''),

        // Recipient system
        'RECIPIENT_MODE' => ($_POST['recipient_mode'] ?? 'single') === 'multiple' ? 'multiple':'single',
        'RECIPIENTS_JSON' => trim($_POST['recipients_json'] ?? ''),

        // SMTP
        'ENABLE_SMTP' => isset($_POST['enable_smtp']) ? '1' : '0',
        'SMTP_HOST'   => trim($_POST['smtp_host'] ?? ''),
        'SMTP_PORT'   => trim($_POST['smtp_port'] ?? ''),
        'SMTP_USER'   => trim($_POST['smtp_user'] ?? ''),
        'SMTP_PASS'   => trim($_POST['smtp_pass'] ?? ''),
        'SMTP_SECURE' => trim($_POST['smtp_secure'] ?? 'tls'),
        'FROM_EMAIL'  => trim($_POST['from_email'] ?? ''),
        'FROM_NAME'   => trim($_POST['from_name'] ?? ''),

        // Auto-reply
        'ENABLE_AUTOREPLY' => isset($_POST['enable_autoreply']) ? '1' : '0',
        'AUTOREPLY_TEMPLATE' => trim($_POST['autoreply_template'] ?? 'auto_reply_ack'),
        'AUTOREPLY_THROTTLE_MIN' => (string)max(1, (int)($_POST['autoreply_throttle_min'] ?? 60)),

        // Form Submit Gate
        'MIN_SUBMIT_SECONDS' => (string)max(0, (int)($_POST['min_submit_seconds'] ?? 4)),
        'SUBMIT_SECRET' => $final_secret,
    ];
    foreach ($pairs as $k=>$v) {
        $db->exec('INSERT INTO kontact_settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [$k,$v]);
    }
    $flash = tr('settings_saved','Settings saved.');
}

$rows = $db->fetchAll('SELECT `key`,`value` FROM kontact_settings');
$s = []; foreach ($rows as $r) { $s[$r['key']] = $r['value']; }

// Precompute masked secret for UI field
$masked_secret_ui = '';
if (!empty($s['SUBMIT_SECRET'])) {
    $sec = (string)$s['SUBMIT_SECRET'];
    $len = strlen($sec);
    $masked_secret_ui = ($len <= 4)
        ? str_repeat('•', $len)
        : (substr($sec, 0, 2) . str_repeat('•', max(0, $len - 4)) . substr($sec, -2));
}

$themes = []; foreach (glob(__DIR__ . '/../themes/*_theme.php') as $f) { $themes[] = basename($f); } sort($themes);
$tpls = $db->fetchAll('SELECT name FROM kontact_templates ORDER BY name');

Kontact\Admin\header_nav(tr('settings','Settings'),'settings');
?>
  <?php if ($flash): ?><div id="snotifications" class="flash"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

<div class="card">
 <div class="card logo">
  <class="url-logo" style="position:relative;">
    <?php
  $__surl = trim((string)($s['SITE_URL'] ?? ''));
  $__slogo = trim((string)($s['SITE_LOGO'] ?? ''));
  $__haslogo = ($__surl !== '' && $__slogo !== '');
?>
    <?php
      $__site_url = trim((string)($s['SITE_URL'] ?? ''));
      $__site_logo = trim((string)($s['SITE_LOGO'] ?? ''));
      if ($__site_url !== '' && $__site_logo !== ''):
    ?>
      <a href="<?php echo htmlspecialchars($__site_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"
         style="position:absolute; top:2px; right:12px;">
        <img src="<?php echo htmlspecialchars($__site_logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php _e('site_logo','Your Site logo'); ?>" title="<?php _e('site_logo','Your Site logo'); ?>"
             style="height:25px;">
      </a>
    <?php endif; ?>
    <details open id="site-information"><summary><?php _e('site_info','Site Information'); ?></summary>
    <form method="post">
      <div class="input-row">
        <label><?php _e('site_name','Site Name'); ?> <input name="site_name" value="<?php echo htmlspecialchars($s['SITE_NAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label><?php _e('site_url','Site URL'); ?> <input name="site_url" value="<?php echo htmlspecialchars($s['SITE_URL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label><?php _e('site_logo','Site Logo Path'); ?> <input name="site_logo" value="<?php echo htmlspecialchars($s['SITE_LOGO'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
      </div>
      <div class="input-row">
        <label><?php _e('form_title','Form Title'); ?> <input name="form_title" value="<?php echo htmlspecialchars($s['FORM_TITLE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label><?php _e('form_name','Form Name'); ?> <input name="form_name" value="<?php echo htmlspecialchars($s['FORM_NAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
      </div>
     </details>
  </div>

  <div class="card">
    <details open id="email-settings"><summary><?php _e('email_settings','Email Settings'); ?></summary>
      <div class="input-row">
        <label><?php _e('webmaster_email','Webmaster Email'); ?> <input name="webmaster_email" type="email" required value="<?php echo htmlspecialchars($s['WEBMASTER_EMAIL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label><?php _e('additional_emails','Additional Emails'); ?> <input name="additional_emails" value="<?php echo htmlspecialchars($s['ADDITIONAL_EMAILS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
        <label><?php _e('email_theme','Email Theme'); ?>
          <select name="email_theme">
            <?php foreach ($themes as $t): $sel = (($s['EMAIL_THEME'] ?? '')===$t)?'selected':''; ?>
              <option <?php echo $sel; ?>><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
    <div class="input-row recipient-mode">
      <label><?php _e('recipient_mode','Recipient Mode'); ?>
        <select name="recipient_mode">
          <option value="single" <?php echo (($s['RECIPIENT_MODE'] ?? 'single')==='single')?'selected':''; ?>><?php _e('single','Single'); ?></option>
          <option value="multiple" <?php echo (($s['RECIPIENT_MODE'] ?? '')==='multiple')?'selected':''; ?>><?php _e('multiple','Multiple'); ?></option>
        </select>
      </label>
    </div>
    <div class="input-row">
      <label><?php _e('recipients_json','Recipients (JSON)'); ?>
        <textarea name="recipients_json" rows="6" placeholder='[{"label":"Support","email":"support@example.com"}]'><?php echo htmlspecialchars($s['RECIPIENTS_JSON'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
      </label>
    </details>
  </div>

  <div class="card">
    <details open id="smtp-server"><summary><?php _e('smtp_server','SMTP Server'); ?></summary>
    <div class="input-row">
      <label><input type="checkbox" name="enable_smtp" value="1" <?php echo (($s['ENABLE_SMTP'] ?? '0')==='1')?'checked':''; ?>> <?php _e('enable_smtp','Enable SMTP'); ?></label>
      <label><?php _e('from_email','From Email'); ?> <input name="from_email" type="email" value="<?php echo htmlspecialchars($s['FROM_EMAIL'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
      <label><?php _e('from_name','From Name'); ?> <input name="from_name" value="<?php echo htmlspecialchars($s['FROM_NAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
    </div>
    <div class="input-row">
      <label><?php _e('host','Host'); ?> <input name="smtp_host" value="<?php echo htmlspecialchars($s['SMTP_HOST'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
      <label><?php _e('port','Port'); ?> <input name="smtp_port" value="<?php echo htmlspecialchars($s['SMTP_PORT'] ?? '587', ENT_QUOTES, 'UTF-8'); ?>"></label>
      <label><?php _e('username','User'); ?> <input name="smtp_user" value="<?php echo htmlspecialchars($s['SMTP_USER'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
    </div>
    <div class="input-row">
      <label><?php _e('password','Password'); ?> <input name="smtp_pass" type="password" value="<?php echo htmlspecialchars($s['SMTP_PASS'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></label>
      <label><?php _e('secure','Secure'); ?>
        <select name="smtp_secure">
          <?php $sec = $s['SMTP_SECURE'] ?? 'tls'; ?>
          <option value="none" <?php echo ($sec==='none')?'selected':''; ?>><?php _e('none','none'); ?></option>
          <option value="tls"  <?php echo ($sec==='tls')?'selected':''; ?>><?php _e('tls','tls'); ?></option>
          <option value="ssl"  <?php echo ($sec==='ssl')?'selected':''; ?>><?php _e('ssl','ssl'); ?></option>
        </select>
      </label>
    </div>
    <div class="input-row">
      <a class="btn" href="test_smtp.php"><?php _e('send_smtp_test','Send SMTP Test'); ?></a>
      <p class="notice"><?php _e('smtp_test_desc','Sends a theme-rendered test email to the Webmaster using the current SMTP settings.'); ?></p>
    </details>
  </div>

  <div class="card">
    <details open id="auto-reply"><summary><?php _e('auto_reply','Auto Reply'); ?></summary>
    <div class="input-row">
      <label><input type="checkbox" name="enable_autoreply" value="1" <?php echo (($s['ENABLE_AUTOREPLY'] ?? '0')==='1')?'checked':''; ?>> <?php _e('enable_auto_reply','Enable auto-reply to sender'); ?></label>
      <label><?php _e('template','Template'); ?>
        <select name="autoreply_template">
          <?php
            $current = $s['AUTOREPLY_TEMPLATE'] ?? 'auto_reply_ack';
            foreach ($tpls as $t) {
              $name = $t['name'];
              $sel = ($name === $current) ? 'selected' : '';
              echo '<option value="'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'" '.$sel.'>'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'</option>';
            }
          ?>
        </select>
      </label>
      <label><?php _e('throttle','Throttle'); ?> <?php _e('minutes','(minutes)'); ?> <input name="autoreply_throttle_min" type="number" min="1" value="<?php echo htmlspecialchars($s['AUTOREPLY_THROTTLE_MIN'] ?? '60', ENT_QUOTES, 'UTF-8'); ?>"></label>
    </details>
  </div>

  <div class="card">
    <details open id="form-submit-gate"><summary><?php _e('form_submit_gate','Form Submit Gate'); ?></summary>
    <div class="input-row">
      <label><?php _e('submit_secret','Submit Secret'); ?>
        <input name="submit_secret"
               type="text"
               value="<?php echo htmlspecialchars($masked_secret_ui, ENT_QUOTES, 'UTF-8'); ?>"
               autocomplete="off"
               spellcheck="false"
               placeholder="<?php _e('enter_new_secret','Enter new secret to change'); ?>">
      </label>
      <label><?php _e('submit_time','Min submit time'); ?> <?php _e('seconds','(Seconds)'); ?> <input name="min_submit_seconds" type="number" min="0" value="<?php echo htmlspecialchars($s['MIN_SUBMIT_SECONDS'] ?? '4', ENT_QUOTES, 'UTF-8'); ?>"></label>
    </details>
  </div>

  
<div class="card">
    <details open id="google-recaptcha"><summary><?php _e('google_recaptcha','Google reCAPTCHA'); ?></summary>
    <div class="input-row">
      <label><?php _e('site_key','Site Key'); ?>
        <input name="recaptcha_site" value="<?php echo htmlspecialchars($s['RECAPTCHA_SITE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php _e('enterprise_site_key','Enterprise Site Key'); ?>">
      </label>
      <label><?php _e('project_id','Project ID'); ?>
        <input name="recaptcha_project_id" value="<?php echo htmlspecialchars($s['RECAPTCHA_PROJECT_ID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php _e('gcp_project_id','gcp-project-id'); ?>">
      </label>
    </div>
    <div class="input-row">
      <label><?php _e('api_key','API Key'); ?>
        <input class="g-api-field" name="recaptcha_api_key" value="<?php echo htmlspecialchars($s['RECAPTCHA_API_KEY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php _e('google_cloud_api_key','Google Cloud API Key'); ?>">
      </label>
    </details>
   </div>
    <input type="hidden" name="csrf" value="<?php echo \Kontact\csrf_token(); ?>">
    <button class="btn"><?php _e('save_settings','Save Settings'); ?></button>
    </form>
  </div>
<?php Kontact\Admin\footer(); ?>
