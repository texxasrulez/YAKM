<?php
/**
 * Include this from your existing Admin → Security page:
 *   include __DIR__ . '/inc/partials/security_submit_gate.php';
 *
 * It will render a small form to edit SUBMIT_SECRET and MIN_SUBMIT_SECONDS
 * and will update kontact/config/config.inc.php in-place (with .bak backup).
 */

$__KONTACT_CFG_FILE = dirname(__DIR__,2) . '/config/config.inc.php';
if (!is_file($__KONTACT_CFG_FILE)) {
  echo '<div style="color:#d33;">Missing config.inc.php at '.htmlspecialchars($__KONTACT_CFG_FILE).'</div>';
  return;
}

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (empty($_SESSION['admin_csrf'])) { $_SESSION['admin_csrf'] = bin2hex(random_bytes(16)); }
$__csrf = $_SESSION['admin_csrf'];

function __kontact_mask_secret($s){
  $s = (string)$s; if ($s==='') return '';
  $len = strlen($s); if ($len <= 6) return str_repeat('•',$len);
  return substr($s,0,2) . str_repeat('•',$len-4) . substr($s,-2);
}

function __kontact_update_config_kv($path, $newSecret, $newMin, &$err){
  $err = '';
  $orig = @file_get_contents($path);
  if ($orig === false) { $err = 'Unable to read config file'; return false; }
  $content = str_replace(["\r\n","\r"], "\n", $orig);
  $changed = false;

  // Replace existing keys (single or double quotes)
  $secret_q = str_replace(['\\', '$'], ['\\\\', '\$'], (string)$newSecret);
  $reSecret = '/([\t ]*['"]SUBMIT_SECRET['"][\t ]*=>[\t ]*)(['"]).*?\2([\t ]*,?)/i';
  $newSecretLine = "\1'".$secret_q."'\3";
  $tmp = preg_replace($reSecret, $newSecretLine, $content, 1, $secretCount);
  if ($tmp === null) { $err='Regex error while updating SUBMIT_SECRET'; return false; }
  if ($secretCount>0) { $content=$tmp; $changed=true; }

  $reMin = '/([\t ]*['"]MIN_SUBMIT_SECONDS['"][\t ]*=>[\t ]*)-?\d+([\t ]*,?)/i';
  $newMinLine = "\1".(int)$newMin."\2";
  $tmp = preg_replace($reMin, $newMinLine, $content, 1, $minCount);
  if ($tmp === null) { $err='Regex error while updating MIN_SUBMIT_SECONDS'; return false; }
  if ($minCount>0) { $content=$tmp; $changed=true; }

  // Insert missing keys before final '];'
  if ($secretCount===0 || $minCount===0) {
    $pos = strrpos($content, '];');
    if ($pos===false) { $err='Could not locate array closing bracket in config.inc.php'; return false; }
    $before = substr($content,0,$pos);
    $after  = substr($content,$pos);
    $ins=[];
    if ($secretCount===0) $ins[] = "    'SUBMIT_SECRET' => '".addslashes((string)$newSecret)."',";
    if ($minCount===0)    $ins[] = "    'MIN_SUBMIT_SECONDS' => ".(int)$newMin.",";
    $content = $before . "\n" . implode("\n",$ins) . "\n" . $after;
    $changed = true;
  }

  if ($changed && $content !== $orig) {
    @copy($path, $path.'.bak');
    $tmpPath = $path.'.tmp';
    if (@file_put_contents($tmpPath, $content) === false) { $err='Failed to write temp file'; return false; }
    if (!@rename($tmpPath, $path)) { $err='Failed to replace config file'; return false; }
  }
  return true;
}

// Load current
$__cfg = include $__KONTACT_CFG_FILE;
$__cur_secret = (string)($__cfg['SUBMIT_SECRET'] ?? $__cfg['submit_secret'] ?? '');
$__cur_min    = (string)($__cfg['MIN_SUBMIT_SECONDS'] ?? $__cfg['min_submit_seconds'] ?? '4');

$__saved_notice = '';
$__errors = [];

if (($_POST['__action'] ?? '') === 'save_submit_gate') {
  if (!hash_equals($__csrf, (string)($_POST['csrf'] ?? ''))) {
    $__errors[] = 'CSRF validation failed.';
  } else {
    $newSecret = trim((string)($_POST['SUBMIT_SECRET'] ?? ''));
    $newMin = max(0, min(60, (int)($_POST['MIN_SUBMIT_SECONDS'] ?? 4)));
    if ($newSecret === '') { $newSecret = $__cur_secret !== '' ? $__cur_secret : bin2hex(random_bytes(16)); }
    $ok = __kontact_update_config_kv($__KONTACT_CFG_FILE, $newSecret, $newMin, $err);
    if ($ok) {
      $__saved_notice = 'Security settings saved.';
      // refresh for display
      $ref = include $__KONTACT_CFG_FILE;
      $__cur_secret = (string)($ref['SUBMIT_SECRET'] ?? '');
      $__cur_min    = (string)($ref['MIN_SUBMIT_SECONDS'] ?? '4');
    } else {
      $__errors[] = $err ?: 'Unknown error while saving.';
    }
  }
}

// Render (unstyled so it inherits your admin CSS)
?>
<section id="kontact-security-submit-gate" class="kontact-card">
  <h2 style="margin:0 0 .5rem;">Form Submit Gate</h2>
  <p style="margin:.25rem 0 .75rem;color:#888;">Controls for the timing token and secret used by your public contact form.</p>

  <?php if (!empty($__errors)): ?>
    <div class="kontact-alert kontact-alert-bad">
      <?php foreach($__errors as $e): ?><div><?= htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($__saved_notice): ?>
    <div class="kontact-alert kontact-alert-ok"><?= htmlspecialchars($__saved_notice, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <input type="hidden" name="__action" value="save_submit_gate">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">

    <div class="kontact-grid">
      <div class="kontact-field">
        <label for="SUBMIT_SECRET">Submit Secret</label>
        <input id="SUBMIT_SECRET" name="SUBMIT_SECRET" type="text" value="" placeholder="Leave blank to keep current">
        <div class="kontact-hint">Current: <code><?= htmlspecialchars(__kontact_mask_secret($__cur_secret), ENT_QUOTES, 'UTF-8') ?></code></div>
      </div>

      <div class="kontact-field">
        <label for="MIN_SUBMIT_SECONDS">Minimum submit time (seconds)</label>
        <input id="MIN_SUBMIT_SECONDS" name="MIN_SUBMIT_SECONDS" type="number" min="0" max="60" step="1" value="<?= htmlspecialchars($__cur_min, ENT_QUOTES, 'UTF-8') ?>">
        <div class="kontact-hint">Set <code>0</code> to disable. Match your form’s <code>data-min-wait-seconds</code>.</div>
      </div>
    </div>

    <div class="kontact-actions" style="margin-top:.75rem;">
      <button type="submit">Save</button>
      <span class="kontact-hint">Config: <code><?= htmlspecialchars($__KONTACT_CFG_FILE, ENT_QUOTES, 'UTF-8') ?></code></span>
    </div>
  </form>
</section>
