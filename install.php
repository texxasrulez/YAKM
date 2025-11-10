<?php
declare(strict_types=1);

/**
 * Fixed admin seed (avoids PDO HY093 by using distinct placeholders)
 * This is a drop-in replacement for your current install.php (i18n-aware or not).
 */

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Minimal i18n stubs to keep this file self-contained if you drop it in alone
$L_DEFAULT = [
  'installer.title' => 'YAKM Kontact Installer',
  'installer.locked.title' => 'Installer locked',
  'installer.locked.msg' => 'config/config.inc.php already exists. Append ?force=1 to re-run.',
  'status.connected' => 'Connected to database.',
  'status.schema_imported' => 'Schema imported or already present.',
  'status.admin_ensured' => 'Admin user ensured: %s',
  'status.config_written' => 'Wrote config/config.inc.php',
  'status.locales_updated' => 'Locales updated.',
  'status.done' => 'Installation complete. Remove install.php from your server.',
  'error.db.connect' => 'DB connect failed: %s',
  'error.schema' => 'Schema import failed: %s',
  'error.admin' => 'Admin seed failed: %s',
  'error.config' => 'Failed to write config: %s',
  'error.admin_email' => 'Admin email must be a valid address.',
  'error.admin_password' => 'Admin password must be at least 6 characters.',
  'error.db_required' => 'Database name and user are required.',
  'db.host' => 'DB Host',
  'db.port' => 'DB Port',
  'db.name' => 'DB Name',
  'db.user' => 'DB User',
  'db.pass' => 'DB Password',
  'site.name' => 'Site Name',
  'site.url' => 'Site URL',
  'admin.email' => 'Admin Email (login)',
  'admin.password' => 'Admin Password',
  'admin.password.note' => 'Will be stored as bcrypt hash.',
  'locale.update' => 'Update localization files from en_US.inc (fill missing keys)',
  'btn.reset' => 'Reset',
  'btn.run' => 'Run Installer',
  'requirements.note' => 'Requirements: PHP 8.1+, extensions: pdo_mysql, openssl, mbstring, intl.',
];
function t(string $k, ...$a){ global $L_DEFAULT; $v=$L_DEFAULT[$k]??$k; return $a?vsprintf($v,$a):$v; }

if (file_exists(__DIR__ . '/config/config.inc.php') && !isset($_GET['force'])) {
  http_response_code(409);
  echo "<h2>".e(t('installer.locked.title'))."</h2><p>".e(t('installer.locked.msg'))."</p>";
  exit;
}

$errors=[]; $ok=[];

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
  $db_port = trim($_POST['db_port'] ?? '3306');
  $db_name = trim($_POST['db_name'] ?? '');
  $db_user = trim($_POST['db_user'] ?? '');
  $db_pass = (string)($_POST['db_pass'] ?? '');
  $site_name = trim($_POST['site_name'] ?? '');
  $site_url  = trim($_POST['site_url'] ?? '');
  $admin_email = trim($_POST['admin_email'] ?? '');
  $admin_password = (string)($_POST['admin_password'] ?? '');
  $locale_update = !empty($_POST['locale_update']);

  if (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) $errors[] = t('error.admin_email');
  if (strlen($admin_password) < 6) $errors[] = t('error.admin_password');
  if ($db_name === '' || $db_user === '') $errors[] = t('error.db_required');

  // Connect
  if (!$errors) {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    try {
      $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
      $ok[] = t('status.connected');
    } catch (Throwable $t) { $errors[] = t('error.db.connect', $t->getMessage()); }
  }

  // Import schema
  if (!$errors) {
    $sql = @file_get_contents(__DIR__ . '/sql/kontact_full_schema.sql') ?: '';
    try { if ($sql!=='') $pdo->exec($sql); $ok[] = t('status.schema_imported'); }
    catch (Throwable $t) { $errors[] = t('error.schema', $t->getMessage()); }
  }

  // Seed admin (distinct placeholders fix for HY093)
  if (!$errors) {
    $hash = password_hash($admin_password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO `kontact_admins` (`email`,`password_hash`,`role`,`is_active`)
                           SELECT :email1, :hash, 'admin', 1
                           WHERE NOT EXISTS (SELECT 1 FROM `kontact_admins` WHERE `email` = :email2)");
    try {
      $stmt->execute([':email1'=>$admin_email, ':email2'=>$admin_email, ':hash'=>$hash]);
      $ok[] = t('status.admin_ensured', e($admin_email));
    } catch (Throwable $t) {
      $errors[] = t('error.admin', $t->getMessage());
    }
  }

  // Write config
  if (!$errors) {
    $cfg = [
      'DB_HOST'=>$db_host,'DB_PORT'=>(string)$db_port,'DB_NAME'=>$db_name,'DB_USER'=>$db_user,'DB_PASS'=>$db_pass,
      'DB_CHARSET'=>'utf8mb4','DB_COLLATION'=>'utf8mb4_unicode_ci','DATABASE_TABLE'=>'kontacts',
      'SITE_NAME'=>$site_name,'SITE_URL'=>$site_url,'SITE_LOGO'=>'',
      'FORM_TITLE'=>'Visitor','FORM_NAME'=>'Contact',
      'WEBMASTER_EMAIL'=>'webmaster@domain.com','RECIPIENT_MODE'=>'single','RECIPIENTS_JSON'=>'[]','EMAIL_THEME'=>'stylish_theme.php',
      'AUTO_REPLY_ENABLE'=>'0','AUTO_REPLY_TEMPLATE'=>'auto_reply_ack',
      'ENABLE_SMTP'=>'0','SMTP_HOST'=>'','SMTP_PORT'=>'587','SMTP_USER'=>'','SMTP_PASS'=>'','SMTP_SECURE'=>'tls',
      'FROM_EMAIL'=>'webmaster@domain.com','FROM_NAME'=>'',
      'MIN_SUBMIT_SECONDS'=>'5','SUBMIT_SECRET'=>'','ALLOW_MAIL_FUNCTION'=>'0',
      'SUBMIT_THROTTLE_PER_MIN'=>'5','SUBMIT_THROTTLE_WINDOW_MIN'=>'10','MAX_LINKS'=>'3','SPAM_KEYWORDS'=>'',
      'LOG_DIR'=>__DIR__ . '/storage/logs','CACHE_DIR'=>__DIR__ . '/storage/cache',
    ];
    $export = var_export($cfg, true);
    $php = "<?php\nreturn " . $export . ";\n\n" . "\$GLOBALS['cfg'] = " . $export . ";\n";
    if (!is_dir(__DIR__ . '/config')) @mkdir(__DIR__ . '/config', 0775, true);
    if (!is_dir(__DIR__ . '/storage/logs')) @mkdir(__DIR__ . '/storage/logs', 0775, true);
    if (!is_dir(__DIR__ . '/storage/cache')) @mkdir(__DIR__ . '/storage/cache', 0775, true);
    try { file_put_contents(__DIR__ . '/config/config.inc.php', $php); $ok[] = t('status.config_written'); }
    catch (Throwable $t) { $errors[] = t('error.config', $t->getMessage()); }
  }
}
?>
<!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?php echo e(t('installer.title')); ?></title>
<link rel="stylesheet" href="assets/install.css"/>
</head><body>
<div class="wrap"><div class="card">
<div class="hd"><h1><?php echo e(t('installer.title')); ?></h1></div>
<div class="bd">
<?php if ($_SERVER['REQUEST_METHOD']==='POST'){ foreach($errors as $m){ echo '<div class=\'alert err\'>❌ '. $m .'</div>'; } foreach($ok as $m){ echo '<div class=\'alert ok\'>✅ '. $m .'</div>'; } if(!$errors){ echo '<div class=\'alert ok\'>🎉 '.e(t('status.done')).'</div>'; } echo '<hr style=\"border-color:#1e2a44;margin:20px 0\"/>'; } ?>
<form method="post" class="grid">
  <div class="row"><label><?php echo e(t('db.host')); ?></label><input name="db_host" value="<?php echo e($_POST['db_host'] ?? '127.0.0.1'); ?>" required /></div>
  <div class="row"><label><?php echo e(t('db.port')); ?></label><input name="db_port" value="<?php echo e($_POST['db_port'] ?? '3306'); ?>" required /></div>
  <div class="row"><label><?php echo e(t('db.name')); ?></label><input name="db_name" value="<?php echo e($_POST['db_name'] ?? 'yakm'); ?>" required /></div>
  <div class="row"><label><?php echo e(t('db.user')); ?></label><input name="db_user" value="<?php echo e($_POST['db_user'] ?? 'yakm_user'); ?>" required /></div>
  <div class="row"><label><?php echo e(t('db.pass')); ?></label><input type="password" name="db_pass" value="<?php echo e($_POST['db_pass'] ?? ''); ?>" /></div>
  <div class="row"><label><?php echo e(t('site.name')); ?></label><input name="site_name" value="<?php echo e($_POST['site_name'] ?? 'Your App Name'); ?>" /></div>
  <div class="row"><label><?php echo e(t('site.url')); ?></label><input name="site_url" value="<?php echo e($_POST['site_url'] ?? 'https://www.domain.com'); ?>" /></div>
  <div class="row"><label><?php echo e(t('admin.email')); ?></label><input name="admin_email" value="<?php echo e($_POST['admin_email'] ?? 'admin@example.com'); ?>" required /></div>
  <div class="row"><label><?php echo e(t('admin.password')); ?></label><input type="password" name="admin_password" value="<?php echo e($_POST['admin_password'] ?? 'password'); ?>" required /><div class="note"><?php echo e(t('admin.password.note')); ?></div></div>
  <div class="row" style="grid-column:1/-1"><label><input type="checkbox" name="locale_update" <?php echo isset($_POST['locale_update'])?'checked':''; ?> /> <?php echo e(t('locale.update')); ?></label></div>
  <div class="actions" style="grid-column:1/-1"><button class="btn btn-secondary" type="reset"><?php echo e(t('btn.reset')); ?></button><button class="btn btn-primary" type="submit"><?php echo e(t('btn.run')); ?></button></div>
</form>
<div class="note"><?php echo e(t('requirements.note')); ?></div>
</div></div></div>
</body></html>
