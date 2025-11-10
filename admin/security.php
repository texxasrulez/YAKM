<?php
declare(strict_types=1);
use Kontact\Database;

require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/blocklist.php';
require_once __DIR__ . '/inc/layout.php';

$db  = new Database($GLOBALS['cfg']);
$pdo = $db->pdo();

// Ensure tables exist
$pdo->exec("CREATE TABLE IF NOT EXISTS kontact_blocklist (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(64) NOT NULL,
  reason VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  UNIQUE KEY uniq_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS kontact_audit (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type VARCHAR(64) NOT NULL,
  ip VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  detail VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-prune expired blocks on load
$pdo->exec("DELETE FROM kontact_blocklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");

$flash = null; $error = null;

// Save Rate-limit settings via kontact_settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__section'] ?? '') === 'ratelimit') {
  try {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $pairs = [
      'RL_ENABLE'      => isset($_POST['rl_enable']) ? '1' : '0',
      'RL_WINDOW_MIN'  => (string)max(1, (int)($_POST['rl_window_min'] ?? 10)),
      'RL_MAX_PER_IP'  => (string)max(1, (int)($_POST['rl_max_per_ip'] ?? 5)),
      'RL_NOTIFY'      => isset($_POST['rl_notify']) ? '1' : '0',
    ];
    foreach ($pairs as $k=>$v) {
      $db->exec('INSERT INTO kontact_settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)', [$k,$v]);
    }
    $flash = tr('rate_limit_saved','Rate-limit settings saved.');
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

// Handle firewall feed actions (token regen + snapshot)
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['__section'] ?? '') === 'firewall_feed')) {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $action = $_POST['__action'] ?? 'token_regen';

    if ($action === 'feed_snapshot_regen') {
      $n = \Kontact\blocklist_write_snapshot($db);
      // "Regenerated snapshot (N IPs)." — translated words, number literal
      $flash = tr('regenerated_snapshot','Regenerated snapshot')
             . ' ('
             . (int)$n . ' '
             . ((int)$n === 1 ? tr('ip','IP') : tr('ips','IPs'))
             . ').';
    } else {
      $tokFile = __DIR__ . '/../storage/secret/firewall_feed_token.txt';
      if (!is_dir(dirname($tokFile))) { @mkdir(dirname($tokFile), 0775, true); }
      $new = bin2hex(random_bytes(24));
      @file_put_contents($tokFile, $new);
      @chmod($tokFile, 0640);
      $flash = tr('firewall_token_regenerated','Firewall feed token regenerated.');
    }
  }
} catch (\Throwable $e) {
  $error = $e->getMessage();
}

// Handle blocklist + audit actions
// Handle blocklist + audit actions
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__section'] ?? '') === 'blocklist') {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $action = $_POST['__action'] ?? '';

    if ($action === 'block_add') {
      $ip = trim($_POST['ip'] ?? '');
      $reason = trim($_POST['reason'] ?? '');
      $expires = trim($_POST['expires_at'] ?? '');
      if ($ip==='') throw new \RuntimeException('IP is required');
      $expires_at = ($expires!=='') ? $expires : null;
      $db->exec('INSERT INTO kontact_blocklist(ip,reason,expires_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason), expires_at=VALUES(expires_at)', [$ip,$reason,$expires_at]);
      $flash = tr('blocked','Blocked') . ' ' . $ip;
    
      try { \Kontact\blocklist_write_snapshot($db); } catch (\Throwable $e) { /* ignore */ }
}

    if ($action === 'block_delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new \RuntimeException('Invalid id');
      $db->exec('DELETE FROM kontact_blocklist WHERE id=? LIMIT 1', [$id]);
      $flash = tr('block_removed','Block removed.');
    
      try { \Kontact\blocklist_write_snapshot($db); } catch (\Throwable $e) { /* ignore */ }
}

	if ($action === 'audit_block') {
	  $ip = trim($_POST['ip'] ?? '');
	  if ($ip === '') throw new \RuntimeException('Invalid IP');

	  $reason = tr('blocked_from_audit','blocked from audit');
	  $db->exec('INSERT INTO kontact_blocklist(ip,reason) VALUES(?,?)', [$ip, $reason]);
	  $flash = tr('blocked','Blocked') . ' ' . $ip . ' ' . tr('from_audit','from audit') . '.';

	  try {
		\Kontact\blocklist_write_snapshot($db);
	  } catch (\Throwable $e) {
		/* ignore */
	  }
	}

    if ($action === 'prune_now') {
      $pdo->exec("DELETE FROM kontact_blocklist WHERE expires_at IS NOT NULL AND expires_at < NOW()");
      $flash = tr('expired_blocks_pruned','Expired blocks pruned.');
    
      try { \Kontact\blocklist_write_snapshot($db); } catch (\Throwable $e) { /* ignore */ }
}
  }
} catch (Throwable $e) {
  $error = $e->getMessage();
}

// Handle audit row actions
try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__section'] ?? '') === 'audit') {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $action = $_POST['__action'] ?? '';
    if ($action === 'audit_delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new \RuntimeException('Invalid id');
      $db->exec('DELETE FROM kontact_audit WHERE id=? LIMIT 1', [$id]);
      $flash = tr('security_event_deleted','Security event deleted.');
    
  if ($action === 'audit_delete_older') {
    $days = (int)($_POST['days'] ?? 7);
    if ($days <= 0) { $days = 7; }
    $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
    $st = $db->pdo()->prepare('DELETE FROM kontact_audit WHERE created_at < ?');
    $st->execute([$cutoff]);
    $deleted = $st->rowCount();
    $flash = tr('deleted','Deleted') . ' ' . (int)$deleted . ' ' . tr('events_older_than','events older than') . ' ' . (int)$days . ' ' . tr('days','days') . '.';
  }

    elseif ($action === 'audit_delete_bulk') {
      if ($mode === 'filter_all') {
        // Rebuild current filter from query params
        $where = []; $vals = [];
        $q = (string)($_GET['q'] ?? $_POST['q'] ?? '');
        $ip = (string)($_GET['ip'] ?? $_POST['ip'] ?? '');
        $type = (string)($_GET['type'] ?? $_POST['type'] ?? '');
        $from = (string)($_GET['from'] ?? $_POST['from'] ?? '');
        $to = (string)($_GET['to'] ?? $_POST['to'] ?? '');
        if ($q !== '') {
          $or = [];
          $or[] = "`event_type` LIKE ?"; $vals[] = "%$q%";
          $or[] = "`detail` LIKE ?"; $vals[] = "%$q%";
          $or[] = "`user_agent` LIKE ?"; $vals[] = "%$q%";
          if (!empty($or)) { $where[] = '(' . implode(' OR ', $or) . ')'; }
        }
        if ($ip !== '') { $where[] = "`ip` LIKE ?"; $vals[] = "%$ip%"; }
        if ($type !== '') { $where[] = "`event_type` = ?"; $vals[] = $type; }
        if ($from !== '') { $where[] = "`created_at` >= ?"; $vals[] = $from . " 00:00:00"; }
        if ($to   !== '') { $where[] = "`created_at` <= ?"; $vals[] = $to   . " 23:59:59"; }
        $where_sql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : '';
        $st = $db->pdo()->prepare("DELETE FROM kontact_audit $where_sql");
        $st->execute($vals);
        $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('events_matching_current_filter','events matching current filter.');
      } else

      $mode = (string)($_POST['mode'] ?? '');
      if ($mode === 'current') {
        $ids = array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))));
        if (!empty($ids)) {
          $in = implode(',', array_fill(0, count($ids), '?'));
          $db->exec("DELETE FROM kontact_audit WHERE id IN ($in)", $ids);
          $flash = tr('deleted','Deleted') . ' ' . count($ids) . ' ' . tr('events_from_current_page','events from current page.') ;
        } else {
          $error = tr('no_events_to_delete_current_page','No events to delete for current page.');
        }
      } elseif ($mode === 'all') {
        $db->pdo()->exec("DELETE FROM kontact_audit");
        $flash = tr('deleted','Deleted') . ' ' . tr('all_security_events','all security events.');
      } elseif ($mode === 'filter_all') {
        $where = []; $vals = [];
        $q = (string)($_GET['q'] ?? $_POST['q'] ?? '');
        $ip = (string)($_GET['ip'] ?? $_POST['ip'] ?? '');
        $type = (string)($_GET['type'] ?? $_POST['type'] ?? '');
        $from = (string)($_GET['from'] ?? $_POST['from'] ?? '');
        $to = (string)($_GET['to'] ?? $_POST['to'] ?? '');
        if ($q !== '') {
          $or = [];
          $or[] = "`event_type` LIKE ?"; $vals[] = "%$q%";
          $or[] = "`detail` LIKE ?"; $vals[] = "%$q%";
          $or[] = "`user_agent` LIKE ?"; $vals[] = "%$q%";
          if (!empty($or)) { $where[] = '(' . implode(' OR ', $or) . ')'; }
        }
        if ($ip !== '') { $where[] = "`ip` LIKE ?"; $vals[] = "%$ip%"; }
        if ($type !== '') { $where[] = "`event_type` = ?"; $vals[] = $type; }
        if ($from !== '') { $where[] = "`created_at` >= ?"; $vals[] = $from . " 00:00:00"; }
        if ($to   !== '') { $where[] = "`created_at` <= ?"; $vals[] = $to   . " 23:59:59"; }
        $where_sql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : '';
        $st = $db->pdo()->prepare("DELETE FROM kontact_audit $where_sql");
        $st->execute($vals);
        $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('events_matching_current_filter','events matching current filter.');
      } elseif (preg_match('/^older_(\d+)$/', $mode, $m)) {
        $days = (int)$m[1];
        if ($days <= 0) { $days = 7; }
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
        $st = $db->pdo()->prepare('DELETE FROM kontact_audit WHERE created_at < ?');
        $st->execute([$cutoff]);
        $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('events_older_than','events older than') . ' ' . (int)$days . ' ' . tr('days','days') . '.' ;
      } else {
        $error = tr('unknown_delete_scope','Unknown delete scope.');
      }
    }
}
  
    // Fallback: if a 'mode' was posted but __action didn't match, handle here
    if (!isset($flash) && !isset($error) && isset($_POST['mode'])) {
      $mode = (string)($_POST['mode'] ?? '');
      if ($mode === 'current') {
        $ids = array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))));
        if (!empty($ids)) {
          $in = implode(',', array_fill(0, count($ids), '?'));
          $db->exec("DELETE FROM kontact_audit WHERE id IN ($in)", $ids);
          $flash = tr('deleted','Deleted') . ' ' . count($ids) . ' ' . tr('events_from_current_page','events from current page.') ;
        } else {
          $error = tr('no_events_to_delete_current_page','No events to delete for current page.');
        }
      } elseif ($mode === 'all') {
        $db->pdo()->exec("DELETE FROM kontact_audit");
        $flash = tr('deleted','Deleted') . ' ' . tr('all_security_events','all security events.');
      } elseif ($mode === 'filter_all') {
        $where = []; $vals = [];
        $q = (string)($_GET['q'] ?? $_POST['q'] ?? '');
        $ip = (string)($_GET['ip'] ?? $_POST['ip'] ?? '');
        $type = (string)($_GET['type'] ?? $_POST['type'] ?? '');
        $from = (string)($_GET['from'] ?? $_POST['from'] ?? '');
        $to = (string)($_GET['to'] ?? $_POST['to'] ?? '');
        if ($q !== '') {
          $or = [];
          $or[] = "`event_type` LIKE ?"; $vals[] = "%$q%";
          $or[] = "`detail` LIKE ?"; $vals[] = "%$q%";
          $or[] = "`user_agent` LIKE ?"; $vals[] = "%$q%";
          if (!empty($or)) { $where[] = '(' . implode(' OR ', $or) . ')'; }
        }
        if ($ip !== '') { $where[] = "`ip` LIKE ?"; $vals[] = "%$ip%"; }
        if ($type !== '') { $where[] = "`event_type` = ?"; $vals[] = $type; }
        if ($from !== '') { $where[] = "`created_at` >= ?"; $vals[] = $from . " 00:00:00"; }
        if ($to   !== '') { $where[] = "`created_at` <= ?"; $vals[] = $to   . " 23:59:59"; }
        $where_sql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : '';
        $st = $db->pdo()->prepare("DELETE FROM kontact_audit $where_sql");
        $st->execute($vals);
        $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('events_matching_current_filter','events matching current filter.');
      } elseif (preg_match('/^older_(\d+)$/', $mode, $m)) {
        $days = (int)$m[1];
        if ($days <= 0) { $days = 7; }
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
        $st = $db->pdo()->prepare('DELETE FROM kontact_audit WHERE created_at < ?');
        $st->execute([$cutoff]);
        $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('events_older_than','events older than') . ' ' . (int)$days . ' ' . tr('days','days') . '.' ;
      }
    }
}
} catch (\Throwable $e) { $error = $e->getMessage(); }
// Load RL settings
$settings = [];
foreach ($db->fetchAll('SELECT `key`,`value` FROM kontact_settings WHERE `key` IN ("RL_ENABLE","RL_WINDOW_MIN","RL_MAX_PER_IP","RL_NOTIFY")') as $r) {
  $settings[$r['key']] = $r['value'];
}
$rl_enable = ($settings['RL_ENABLE'] ?? '0') === '1';
$rl_window_min = (int)($settings['RL_WINDOW_MIN'] ?? 10);
$rl_max_per_ip = (int)($settings['RL_MAX_PER_IP'] ?? 5);
$rl_notify = ($settings['RL_NOTIFY'] ?? '0') === '1';

// Data
// Blocklist pagination
$bl_page = max(1, (int)($_GET['bl_page'] ?? 1));
$bl_per  = 50;
$bl_off  = ($bl_page - 1) * $bl_per;
$bl_total = (int)$pdo->query('SELECT COUNT(*) FROM kontact_blocklist')->fetchColumn();
$bl_pages = max(1, (int)ceil($bl_total / $bl_per));
$blocks = $db->fetchAll("SELECT * FROM kontact_blocklist ORDER BY created_at DESC, id DESC LIMIT ".$bl_per." OFFSET ".$bl_off);

// Audit pagination (use literal ints to avoid MariaDB placeholder issue)
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25; $off = ($page-1)*$per;
$total = (int)$pdo->query('SELECT COUNT(*) FROM kontact_audit')->fetchColumn();
$sql = "SELECT * FROM kontact_audit ORDER BY created_at DESC, id DESC LIMIT ".$per." OFFSET ".$off;
$audit = $db->fetchAll($sql);
$pages = max(1, (int)ceil($total/$per));

Kontact\Admin\header_nav(tr('security','Security'), 'security');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
  <?php if ($flash): ?><div id="snotifications" class="flash"><?=h($flash)?></div><?php endif; ?>
  <?php if ($error): ?><div id="snotifications" class="flash error"><?=h($error)?></div><?php endif; ?>
<div class="card">
  <div class="card">
    <details open id="rate-limit"><summary><?php _e('rate_limit','Rate Limit'); ?></summary>
    
    <div class="audit-toolbar">
     <form method="post" class="input-row">
      <label><input type="checkbox" name="rl_enable" value="1" <?= $rl_enable ? 'checked' : '' ?>> <?php _e('enable','Enable'); ?></label>
      <label><?php _e('window','Window'); ?> <?php _e('minutes','(Minutes)'); ?><input type="number" name="rl_window_min" min="1" value="<?=h((string)$rl_window_min)?>"></label>
      <label><?php _e('max_sub','Max submissions per IP'); ?> <input type="number" name="rl_max_per_ip" min="1" value="<?=h((string)$rl_max_per_ip)?>"></label>
      <label><input type="checkbox" name="rl_notify" value="1" <?= $rl_notify ? 'checked' : '' ?>> <?php _e('email_webmaster','Email webmaster on violation'); ?></label>
      <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
      <input type="hidden" name="__section" value="ratelimit">
      <button class="btn"><?php _e('save','Save'); ?></button>
    </form>
    <p class="notice"><?php _e('enforcement_hook','Enforcement hook provided in <code>/kontact/lib/security_runtime.php</code>. See docs for the one-line include you add to <code>send_mail.php</code>.'); ?></p>
  </details>
</div>
  <div class="card">
    <details open id="block-ip"><summary><?php _e('block_ip','Block IP'); ?></summary>
    <form method="post" class="input-row">
      <label><?php _e('ip','IP'); ?> <input name="ip" placeholder<?php _e('1_2_3_4','1.2.3.4'); ?>"></label>
      <label><?php _e('reason','Reason'); ?> <input name="reason" placeholder="<?php _e('spam_abuse','spam / abuse'); ?>"></label>
      <label><?php _e('expires','Expires'); ?> <?php _e('expires_optional','YYYY-MM-DD HH:MM:SS)'); ?> <input name="expires_at" placeholder="<?php _e('date_time_stamp','2025-12-31 23:59:59'); ?>"></label>
      <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
      <input type="hidden" name="__section" value="blocklist">
      <input type="hidden" name="__action" value="block_add">
      <button class="btn"><?php _e('add_update','Add / Update'); ?></button>
    </form>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
      <input type="hidden" name="__section" value="blocklist">
      <input type="hidden" name="__action" value="prune_now">
      <button class="btn"><?php _e('prune_ex','Prune expired now'); ?></button>
    </form>
  </div>
 </details>
  
  <div class="card">
    <details open id="firewall-feed"><summary><?php _e('fw_feed','Firewall Feed'); ?></summary>
    <p><?php _e('fw_feed_info','Use this URL as a remote deny-list in servers or control panels that accept an <em>IP list by URL</em>. Format is one IP per line.'); ?></p>
    <?php
      $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
      $feedPath = '/kontact/api/blocklist_feed.php';
      $tokFile = __DIR__ . '/../storage/secret/firewall_feed_token.txt';
      if (!is_dir(dirname($tokFile))) { @mkdir(dirname($tokFile), 0775, true); }
      if (!is_file($tokFile)) { @file_put_contents($tokFile, bin2hex(random_bytes(24))); @chmod($tokFile, 0640); }
      $feedToken = trim((string)@file_get_contents($tokFile));
      $feedUrl = $proto . '://' . $host . $feedPath . '?token=' . urlencode($feedToken);
    ?>
    <div class="input-row">
      <input type="text" class="url-field" readonly value="<?= h($feedUrl) ?>">
      <button type="button" class="btn" onclick="navigator.clipboard.writeText('<?= h($feedUrl) ?>')"><?php _e('copy_url','Copy URL'); ?></button>
      <form method="post" onsubmit="return confirm('<?php _e('regen_token_warning','Regenerate token? All remote servers using the old URL will need to be updated.'); ?>');">
        <input type="hidden" name="__section" value="firewall_feed">
        <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
        <button class="btn danger"><?php _e('regen_token','Regenerate Token'); ?></button>
      </form>
    </div>
    <div>
      <div><?php _e('static_snap','Static snapshot (.txt)'); ?></div>
      <?php
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $snapshotUrl = $proto . '://' . $host . '/kontact/feeds/blocked_ips.txt';
      ?>
      <div class="input-row">
        <input type="text" class="url-field" readonly value="<?= h($snapshotUrl) ?>">
        <button type="button" class="btn" onclick="navigator.clipboard.writeText('<?= h($snapshotUrl) ?>')"><?php _e('copy_url','Copy URL'); ?></button>
        <form method="post">
          <input type="hidden" name="__section" value="firewall_feed">
          <input type="hidden" name="__action" value="feed_snapshot_regen">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(\Kontact\csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <button class="btn"><?php _e('regen_snap','Regenerate Snapshot'); ?></button>
        </form>
      </div>
      <p><?php _e('static_snap_info','Use this URL in Your Control Panel’s <em>"Add IPset IP List"</em>. It’s a plain list of IPs, one per line.'); ?></p>
    </div>
   </details>
  </div>
<details class="card" closed id="blocklist"><summary><?php _e('blocklist','Blocklist'); ?></summary>
    <div class="table-scroll">
      <table class="table">
        <thead><tr><th><?php _e('id','ID'); ?></th><th><?php _e('ip','IP'); ?></th><th><?php _e('reason','Reason'); ?></th><th><?php _e('created','Created'); ?></th><th><?php _e('expires','Expires'); ?></th><th><?php _e('actions','Actions'); ?></th></tr></thead>
        <tbody>
          <?php if (empty($blocks)): ?>
            <tr><td colspan="6"><?php _e('no_blocked_ips','No blocked IPs.'); ?></td></tr>
          <?php else: foreach ($blocks as $b): ?>
            <tr>
              <td><?= (int)$b['id'] ?></td>
              <td><?= h($b['ip']) ?></td>
              <td><?= h($b['reason']) ?></td>
              <td><?= h($b['created_at']) ?></td>
              <td><?= h($b['expires_at']) ?></td>
              <td>
                <form method="post" onsubmit="return confirm('Remove this block?');">
                  <input type="hidden" name="__section" value="blocklist">
                  <input type="hidden" name="__action" value="block_delete">
                  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(\Kontact\csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                  <button class="btn danger"><?php _e('remove','Remove'); ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($bl_pages>1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$bl_pages;$i++): ?>
        <?php if ($i===$bl_page): ?><span class="page current"><?=$i?></span>
        <?php else: ?>
          <a class="page" href="?bl_page=<?=$i?>#blocklist"><?=$i?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    
  </details>

  <details class="card" open id="recent-security-events"><summary><?php _e('recent_security_events','Recent Security Events'); ?></summary>
    <div>
  <form method="post" class="input-row"
        onsubmit="return confirm('<?php _e('delete_scope','Delete the selected scope? This cannot be undone.'); ?>');">
    <input type="hidden" name="__section" value="audit">
    <input type="hidden" name="__action" value="audit_delete_bulk">
    <input type="hidden" name="csrf" value="<?php echo h(\Kontact\csrf_token()); ?>">
    <?php $audit_ids = array_map(function($r){ return (int)$r['id']; }, $audit); ?>
    <input type="hidden" name="ids" value="<?php echo h(implode(',', $audit_ids)); ?>">
    <label for="audit_scope"> </label>
    <select id="audit_scope" name="mode">
      <option value="current"><?php _e('current_page','Current page'); ?></option>
      <option value="older_7"><?php _e('older_7','Older than 7 days'); ?></option>
      <option value="older_14"><?php _e('older_14','Older than 14 days'); ?></option>
      <option value="older_30" selected><?php _e('older_30','Older than 30 days'); ?></option>
      <option value="older_90"><?php _e('older_90','Older than 90 days'); ?></option>
      <option value="filter_all"><?php _e('filter_all','Current filter (all pages)'); ?></option>
      <option value="all"><?php _e('all_events','All events'); ?></option>
    </select>
    <button class="btn danger"><?php _e('delete','Delete'); ?></button>
  </form>
    </div>
	 <div class="table-scroll">
      <table class="table">
        <thead><tr><th><?php _e('id','ID'); ?></th><th><?php _e('created','Created'); ?></th><th><?php _e('type','Type'); ?></th><th><?php _e('ip','IP'); ?></th><th><?php _e('user_agent','User-Agent'); ?></th><th><?php _e('email','Email'); ?></th><th><?php _e('actions','Actions'); ?></th></tr></thead>
        <tbody>
          <?php if (empty($audit)): ?>
            <tr><td colspan="7"><?php _e('no_events','No events.'); ?></td></tr>
          <?php else: foreach ($audit as $a): ?>
            <tr>
              <td><?= (int)$a['id'] ?></td>
              <td><?= h($a['created_at']) ?></td>
              <td><?= h($a['event_type']) ?></td>
              <td><?= h($a['ip']) ?></td>
              <td><?= h($a['user_agent']) ?></td>
              <td><?= h($a['detail']) ?></td>
              <td>
			<div class="action-row" style="display:flex;gap:8px;align-items:center;flex-wrap:nowrap;align-items:center">
			<?php if (!empty($a['ip'])): ?>
				<form method="post" style="margin:0">
                  <input type="hidden" name="__section" value="blocklist">
                  <input type="hidden" name="__action" value="audit_block">
                  <input type="hidden" name="ip" value="<?= h($a['ip']) ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn" style="display:inline-block;margin:0 0 0 0;white-space:nowrap"><?php _e('block_ip','Block IP'); ?></button>
                </form>
				<?php endif; ?>
				<form method="post" style="margin:0" onsubmit="return confirm('<?php _e('delete_this_event','Delete this event?'); ?>');">
                  <input type="hidden" name="__section" value="audit">
                  <input type="hidden" name="__action" value="audit_delete">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn danger" style="display:inline-block;margin:0 0 0 0;white-space:nowrap"><?php _e('delete','Delete'); ?></button>
                </form>
			</div>
			</td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages>1): ?>
    <div class="pagination">
      <?php for ($i=1;$i<=$pages;$i++): ?>
        <?php if ($i===$page): ?><span class="page current"><?=$i?></span>
        <?php else: ?><a class="page" href="?page=<?=$i?>"><?=$i?></a>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </details>
</div>
<?php Kontact\Admin\footer(); ?>
