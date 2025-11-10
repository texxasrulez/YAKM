<?php
/**
 * Admin partial: Security → Submit Gate
 * Drop-in file to manage SUBMIT_SECRET and MIN_SUBMIT_SECONDS in config/config.inc.php
 *
 * Usage (inside your Admin Security page):
 *   include __DIR__ . '/security_submit_gate.php';
 */

declare(strict_types=1);

$__CFG_FILE = dirname(__DIR__, 3) . '/config/config.inc.php';
if (!is_file($__CFG_FILE)) {
  echo '<div class="kontact-error">Missing config: <code>' . htmlspecialchars($__CFG_FILE, ENT_QUOTES, 'UTF-8') . '</code></div>';
  return;
}

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['__kontact_csrf'])) {
  $_SESSION['__kontact_csrf'] = bin2hex(random_bytes(16));
}
$__CSRF = $_SESSION['__kontact_csrf'];

// Load current config (expects a PHP file returning an array)
$__cfg = include $__CFG_FILE;
if (!is_array($__cfg)) { $__cfg = []; }
// For legacy code paths that rely on $GLOBALS['cfg']
if (!isset($GLOBALS['cfg']) || !is_array($GLOBALS['cfg'])) {
  $GLOBALS['cfg'] = $__cfg;
}

// Helpers
function __khtml($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function __kint($v, int $min = 0): int {
  if (!is_numeric($v)) return $min;
  $n = (int)$v;
  return ($n < $min) ? $min : $n;
}

// Handle save
$__msg_ok = [];
$__msg_err = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['__csrf']) && hash_equals($__CSRF, (string)$_POST['__csrf'])) {
  $new_secret = (string)($_POST['SUBMIT_SECRET'] ?? '');
  $new_min    = __kint($_POST['MIN_SUBMIT_SECONDS'] ?? 0, 0);

  // Basic validation
  if ($new_secret === '') {
    $new_secret = bin2hex(random_bytes(12)); // generate a secret if empty
    $__msg_ok[] = 'Generated a new SUBMIT_SECRET.';
  }
  if ($new_min < 0) $new_min = 0;

  // Merge into config array
  $__cfg['SUBMIT_SECRET'] = $new_secret;
  $__cfg['MIN_SUBMIT_SECONDS'] = (string)$new_min;

  // Write backup and save
  $bak = $__CFG_FILE . '.' . date('Ymd-His') . '.bak';
  @copy($__CFG_FILE, $bak);

  // Ensure directories for logs/cache exist if present in config
  if (!empty($__cfg['LOG_DIR']) && !is_dir($__cfg['LOG_DIR'])) @mkdir($__cfg['LOG_DIR'], 0775, true);
  if (!empty($__cfg['CACHE_DIR']) && !is_dir($__cfg['CACHE_DIR'])) @mkdir($__cfg['CACHE_DIR'], 0775, true);

  $export = var_export($__cfg, true);
  $php = "<?php\nreturn " . $export . ";\n\n" . "\$GLOBALS['cfg'] = " . $export . ";\n";
  $ok = @file_put_contents($__CFG_FILE, $php);

  if ($ok !== false) {
    $__msg_ok[] = 'Configuration saved.';
    // Reload in-memory cfg
    $GLOBALS['cfg'] = include $__CFG_FILE;
    $__cfg = $GLOBALS['cfg'];
  } else {
    $__msg_err[] = 'Failed to write config file.';
  }
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $__msg_err[] = 'Invalid CSRF token.';
}

// Current values
$__cur_secret = (string)($__cfg['SUBMIT_SECRET'] ?? '');
$__cur_min = (string)($__cfg['MIN_SUBMIT_SECONDS'] ?? '4');

?>
<section class="kontact-card">
  <style>
    .kontact-card{background:#111826;color:#e6eeff;border:1px solid #21304a;border-radius:12px;padding:16px;margin:12px 0}
    .kontact-card h3{margin:0 0 12px 0;font-size:16px}
    .kontact-field{margin:10px 0}
    .kontact-field label{display:block;font-size:12px;color:#9db0d8;margin-bottom:6px}
    .kontact-field input[type="text"], .kontact-field input[type="number"]{width:420px;max-width:100%;padding:8px;border-radius:8px;border:1px solid #21304a;background:#0e1627;color:#e6eeff}
    .kontact-actions{display:flex;gap:12px;align-items:center}
    .kontact-hint{font-size:12px;color:#9db0d8}
    .kontact-msg-ok{background:#0f2a16;border:1px solid #2f9e44;color:#c7ffd7;padding:8px 10px;border-radius:8px;margin:6px 0}
    .kontact-msg-err{background:#351616;border:1px solid #d64545;color:#ffd7d7;padding:8px 10px;border-radius:8px;margin:6px 0}
    code{background:#0e1627;padding:1px 4px;border-radius:4px}
  </style>

  <h3>Submit Gate</h3>

  <?php foreach ($__msg_ok as $m): ?>
    <div class="kontact-msg-ok">✅ <?= __khtml($m) ?></div>
  <?php endforeach; ?>
  <?php foreach ($__msg_err as $m): ?>
    <div class="kontact-msg-err">❌ <?= __khtml($m) ?></div>
  <?php endforeach; ?>

  <form method="post" autocomplete="off">
    <input type="hidden" name="__csrf" value="<?= __khtml($__CSRF) ?>">

    <div class="kontact-field">
      <label for="SUBMIT_SECRET">Submit secret</label>
      <input id="SUBMIT_SECRET" name="SUBMIT_SECRET" type="text"
             placeholder="random secret string"
             value="<?= __khtml($__cur_secret) ?>">
      <div class="kontact-hint">Used to verify legitimate form submissions server-side.</div>
    </div>

    <div class="kontact-field">
      <label for="MIN_SUBMIT_SECONDS">Minimum submit time (seconds)</label>
      <input id="MIN_SUBMIT_SECONDS" name="MIN_SUBMIT_SECONDS" type="number" min="0" step="1"
             value="<?= __khtml($__cur_min) ?>">
      <div class="kontact-hint">Set <code>0</code> to disable. Match your form’s <code>data-min-wait-seconds</code>.</div>
    </div>

    <div class="kontact-actions" style="margin-top:.75rem;">
      <button type="submit">Save</button>
      <span class="kontact-hint">Config: <code><?= __khtml($__CFG_FILE) ?></code></span>
    </div>
  </form>
</section>
