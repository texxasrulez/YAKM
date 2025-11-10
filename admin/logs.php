<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';

$logDir = realpath(__DIR__ . '/../storage/logs') ?: (__DIR__ . '/../storage/logs');
@mkdir($logDir, 0775, true);

function safe_join(string $base, string $name): string {
  $p = str_replace(['..','\\'], ['','/'], $name);
  $full = rtrim($base, '/').'/'.ltrim($p,'/');
  $real = realpath(dirname($full));
  if ($real === false) { return $full; }
  if (strpos($real, realpath($base)) !== 0) { throw new RuntimeException('Invalid path'); }
  return $full;
}

function list_logs(string $dir): array {
  $out = [];
  if (!is_dir($dir)) return $out;
  foreach (scandir($dir) ?: [] as $f) {
    if ($f === '.' || $f === '..') continue;
    if (!preg_match('/\.log(\.[0-9]+)?(\.gz)?$/', $f)) continue;
    $path = $dir . '/' . $f;
    if (is_file($path)) {
      clearstatcache(true, $path);
      $out[] = [
        'name' => $f,
        'size' => filesize($path) ?: 0,
        'mtime'=> filemtime($path) ?: 0,
        'gz'   => str_ends_with($f, '.gz')
      ];
    }
  }
  usort($out, function($a,$b){
    return $a['name'] <=> $b['name'];
  });
  return $out;
}

// Actions: download, clear
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    Kontact\csrf_verify($_POST['csrf'] ?? null);
    $action = (string)($_POST['__action'] ?? '');
    $file = (string)($_POST['file'] ?? '');
    if ($action === 'clear' && $file !== '') {
      $path = safe_join($logDir, $file);
      if (!is_file($path)) throw new RuntimeException('Not a file');
      if (substr($path, -3) === '.gz') throw new RuntimeException('Cannot clear gzipped log');
      $fh = @fopen($path, 'w');
      if (!$fh) throw new RuntimeException('Failed to truncate');
      fclose($fh);
      $flash = tr('cleared','Cleared') . ' ' . \Kontact\Admin\h($file);
    }
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

$file = (string)($_GET['f'] ?? '');
$lines = max(10, min(5000, (int)($_GET['lines'] ?? 50)));
$q = (string)($_GET['q'] ?? '');
$logFiles = list_logs($logDir);

Kontact\Admin\header_nav(tr('logs','Logs'),'logs'); ?>

<div class="card">
 <div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
    <h2 style="margin:0"><?php _e('logs','Logs'); ?></h2>
    
<form method="get" class="input-row" style="margin:0">
  <label>
    <span><?php _e('file','File'); ?></span>
     <select name="f">
      <option value=""><?php _e('choose','(choose)'); ?></option>
      <?php foreach ($logFiles as $lf): ?>
        <option value="<?=\Kontact\Admin\h($lf['name'])?>" <?= $file===$lf['name']?'selected':'' ?>><?=\Kontact\Admin\h($lf['name'])?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>
    <span><?php _e('lines','Lines'); ?></span>
    <input type="number" name="lines" min="10" max="5000" value="<?=\Kontact\Admin\h((string)$lines)?>">
  </label>
  <label>
    <span><?php _e('search','Search'); ?></span>
    <input type="text" name="q" value="<?=\Kontact\Admin\h($q)?>" placeholder="<?php _e('contains','contains...'); ?>">
  </label>
  <button class="btn"><?php _e('view','View'); ?></button>
</form>
</div>
</div>

  <?php if (!empty($flash)): ?><div id="snotifications" class="flash success"><?=\Kontact\Admin\h($flash)?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div id="snotifications" class="flash error"><?=\Kontact\Admin\h($error)?></div><?php endif; ?>
 
 <div class="card">
  <div class="table-scroll">
   <details id="manage-logs"><summary><?php _e('manage_logs','Manage Logs'); ?></summary>
     <table class="sortable" style="width:100%; table-layout:auto;">
      <thead><tr align="left">
        <th><?php _e('file','File'); ?></th>
        <th data-type="number"><?php _e('size','Size'); ?> <?php _e('bytes','(bytes)'); ?></th>
        <th data-type="number"><?php _e('modified','Modified'); ?> <?php _e('unix','(unix)'); ?></th>
        <th><?php _e('modified','Modified'); ?></th>
        <th><?php _e('actions','Actions'); ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($logFiles as $lf): ?>
        <tr>
          <td><?=\Kontact\Admin\h($lf['name'])?></td>
          <td><?=\Kontact\Admin\h((string)$lf['size'])?></td>
          <td><?=\Kontact\Admin\h((string)$lf['mtime'])?></td>
          <td><?= date('Y-m-d H:i:s', (int)$lf['mtime']) ?></td>
          <td style="white-space:nowrap; display:flex; align-items:center; gap:8px;">
            <a class="btn" href="?f=<?=\Kontact\Admin\h($lf['name'])?>&lines=<?=\Kontact\Admin\h((string)$lines)?>"><?php _e('view','View'); ?></a>
            <?php if (!$lf['gz']): ?>
              <form method="post" onsubmit="return confirm('<?php _e('clear_this_log','Clear this log?'); ?>');" style="margin:0">
                <input type="hidden" name="csrf" value="<?php echo \Kontact\Admin\h(Kontact\csrf_token()); ?>">
                <input type="hidden" name="__action" value="clear">
                <input type="hidden" name="file" value="<?=\Kontact\Admin\h($lf['name'])?>">
                <button class="btn danger"><?php _e('clear','Clear'); ?></button>
              </form>
            <?php endif; ?>
            <a class="btn" href="../storage/logs/<?=\Kontact\Admin\h($lf['name'])?>" download><?php _e('download','Download'); ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($file) {
  $path = safe_join($logDir, $file);
  if (!is_file($path)) { ?>
    <div class="card"><div class="flash error"><?php _e('file_not_found','File not found.'); ?></div></div>
  <?php } elseif (substr($path,-3)==='.gz') { ?>
    <div class="card"><div class="flash"><?php _e('gzipped_log','Gzipped log cannot be previewed here. Use Download.'); ?></div></div>
  <?php } else {
    // Tail last N lines safely (max 2 MiB read)
    $maxBytes = 2 * 1024 * 1024;
    $size = filesize($path) ?: 0;
    $read = min($size, $maxBytes);
    $fh = fopen($path, 'rb');
    $buf = '';
    if ($fh) {
      $pos = max(0, $size - $read);
      fseek($fh, $pos);
      $buf = stream_get_contents($fh) ?: '';
      fclose($fh);
    }
    $linesArr = preg_split("/\r?\n/", $buf);
    // If we read from middle, keep last N lines
    if (count($linesArr) > $lines) {
      $linesArr = array_slice($linesArr, -$lines);
    }
    if ($q !== '') {
      $qq = mb_strtolower($q);
      $linesArr = array_values(array_filter($linesArr, function($ln) use ($qq){
        return strpos(mb_strtolower($ln), $qq) !== false;
      }));
    }
    $preview = implode("\n", $linesArr);
  ?>
<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0">Viewing: <?=\Kontact\Admin\h($file)?></h3>
    <div>
      <a class="btn" href="?f=<?=\Kontact\Admin\h($file)?>&lines=<?=\Kontact\Admin\h((string)$lines)?>">Refresh</a>
      <?php if ($q!==''): ?><a class="btn" href="?f=<?=\Kontact\Admin\h($file)?>&lines=<?=\Kontact\Admin\h((string)$lines)?>">Clear search</a><?php endif; ?>
    </div>
  </div>
  <pre style="white-space:pre-wrap;word-break:break-word;max-height:60vh;overflow:auto;background:#0b1020;color:#d1e7ff;padding:12px;border-radius:8px;border:1px solid #1f2a44;"><?=\Kontact\Admin\h($preview)?></pre>
</div>
  <?php } } ?>
</div>
<?php Kontact\Admin\footer();
