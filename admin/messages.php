<?php
declare(strict_types=1);
use Kontact\Database;

require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function cfgv(string $k, $d=null){ return \Kontact\cfg($k,$d); }

$db = new Database($GLOBALS['cfg']);
$pdo = $db->pdo();
$table = cfgv('DATABASE_TABLE','kontacts');
$table = preg_replace('/[^A-Za-z0-9_]/','', (string)$table);
if ($table==='') $table='kontacts';

$flash = null;
$error = null;

/* Discover columns for display */
$cols = [];
try { foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) { $cols[$r['Field']] = true; } }
catch (Throwable $e) { $error = "Cannot read columns from `$table`: " . $e->getMessage(); }

$show = [];
foreach (['id','created_at','name','subject','email','message','user_ip','user_id'] as $c) { if (isset($cols[$c])) $show[] = $c; }
if (empty($show)) { $show = array_keys($cols); }

/* Handle delete (single row, duplicate-safe) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'delete') {
  try {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    // Discover id existence
    $has_id = isset($cols['id']);
    if ($has_id && isset($_POST['id'])) {
      $id = (int)$_POST['id'];
      $st = $pdo->prepare("DELETE FROM `$table` WHERE `id` = ? LIMIT 1");
      $st->execute([$id]);
      $flash = tr('deleted','Deleted') . ' message #' . $id . '.';
    } else {
      // Match by available fields, LIMIT 1
      $candidates = ['name','subject','email','message','user_ip','user_id','created_at'];
      $where = []; $vals = [];
      foreach ($candidates as $c) {
        if (isset($cols[$c]) && array_key_exists($c, $_POST)) {
          $where[] = "`$c` = ?";
          $vals[] = $_POST[$c];
        }
      }
      if (!empty($where)) {
        $sql = "DELETE FROM `$table` WHERE " . implode(" AND ", $where) . " LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute($vals);
        $flash = tr('deleted','Deleted') . ' ' . tr('one_matching_message','one matching message.') ;
      } else {
        $error = "Delete failed: insufficient keys for this table shape.";
      }
    }
  } catch (Throwable $e) {
    $error = tr('delete_error','Delete error:') . ' ' . $e->getMessage();
  }
}


/* Bulk delete (messages) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['__action'] ?? '') === 'bulk_delete')) {
  try {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $mode = (string)($_POST['mode'] ?? '');
    if ($mode === 'current') {
      $ids = array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))));
      if (!empty($ids) && isset($cols['id'])) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("DELETE FROM `$table` WHERE id IN ($in)");
        $st->execute($ids);
        $flash = tr('deleted','Deleted') . ' ' . count($ids) . ' ' . tr('messages_from_current_page','messages from current page.') ;
      } else {
        $error = tr('no_message_ids_current_page','No message IDs to delete for current page.');
      }
    } elseif ($mode === 'all') {
      $st = $pdo->prepare("DELETE FROM `$table`");
      $st->execute();
      $flash = tr('deleted','Deleted') . ' ' . tr('all_messages','all messages.');
    } elseif ($mode === 'filter_all') {
      // Delete everything matching the current filter (all pages)
      $where = []; $vals = [];
      $q = (string)($_GET['q'] ?? $_POST['q'] ?? '');
      $email = (string)($_GET['email'] ?? $_POST['email'] ?? '');
      $ip = (string)($_GET['ip'] ?? $_POST['ip'] ?? '');
      $from = (string)($_GET['from'] ?? $_POST['from'] ?? '');
      $to = (string)($_GET['to'] ?? $_POST['to'] ?? '');
      if ($q !== '') {
        $or = [];
        if (isset($cols['subject'])) { $or[] = "`subject` LIKE ?"; $vals[] = "%$q%"; }
        if (isset($cols['message'])) { $or[] = "`message` LIKE ?"; $vals[] = "%$q%"; }
        if (!empty($or)) { $where[] = '(' . implode(' OR ', $or) . ')'; }
      }
      if ($email !== '' && isset($cols['email'])) { $where[] = "`email` LIKE ?"; $vals[] = "%$email%"; }
      if ($ip !== '' && isset($cols['user_ip'])) { $where[] = "`user_ip` LIKE ?"; $vals[] = "%$ip%"; }
      if ($from !== '' && isset($cols['created_at'])) { $where[] = "`created_at` >= ?"; $vals[] = $from . " 00:00:00"; }
      if ($to !== ''   && isset($cols['created_at'])) { $where[] = "`created_at` <= ?"; $vals[] = $to   . " 23:59:59"; }
      $where_sql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : '';
      $st = $pdo->prepare("DELETE FROM `$table` $where_sql");
      $st->execute($vals);
      $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('messages_matching_current_filter','messages matching current filter.') ;
    } elseif (preg_match('/^older_(\d+)$/', $mode, $m)) {
      $days = (int)$m[1];
      if ($days <= 0) { $days = 7; }
      if (!isset($cols['created_at'])) { throw new \RuntimeException('created_at not available for age delete'); }
      $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
      $st = $pdo->prepare("DELETE FROM `$table` WHERE `created_at` < ?");
      $st->execute([$cutoff]);
      $flash = tr('deleted','Deleted') . ' ' . (int)$st->rowCount() . ' ' . tr('messages_older_than','messages older than') . ' ' . (int)$days . ' ' . tr('days','days') . '.' ;
    } else {
      $error = tr('unknown_delete_scope','Unknown delete scope.');
    }
  } catch (\Throwable $e) {
    $error = 'Bulk delete error: ' . $e->getMessage();
  }
}
/* Filtering */
$where = []; $vals = [];
$q = trim((string)($_GET['q'] ?? ''));
$email = trim((string)($_GET['email'] ?? ''));
$ip = trim((string)($_GET['ip'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to'] ?? ''));

if ($q !== '') {
  $or = [];
  if (isset($cols['subject'])) { $or[] = "`subject` LIKE ?"; $vals[] = "%$q%"; }
  if (isset($cols['message'])) { $or[] = "`message` LIKE ?"; $vals[] = "%$q%"; }
  if (!empty($or)) { $where[] = '(' . implode(' OR ', $or) . ')'; }
}
if ($email !== '' && isset($cols['email'])) { $where[] = "`email` LIKE ?"; $vals[] = "%$email%"; }
if ($ip !== '' && isset($cols['user_ip'])) { $where[] = "`user_ip` LIKE ?"; $vals[] = "%$ip%"; }
if ($from !== '' && isset($cols['created_at'])) { $where[] = "`created_at` >= ?"; $vals[] = $from." 00:00:00"; }
if ($to   !== '' && isset($cols['created_at'])) { $where[] = "`created_at` <= ?"; $vals[] = $to." 23:59:59"; }

$where_sql = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : '';

$order = isset($cols['created_at']) ? "ORDER BY `created_at` DESC" : (isset($cols['id']) ? "ORDER BY `id` DESC" : "");

/* CSV export */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $fields = "`" . implode("`,`", $show) . "`";
  $sql = "SELECT $fields FROM `$table` $where_sql $order";
  $rows = $db->fetchAll($sql, $vals);
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=kontact_messages.csv');
  $out = fopen('php://output', 'w');
  fputcsv($out, $show);
  foreach ($rows as $r) {
    $line = [];
    foreach ($show as $c) {
      $cell = (string)($r[$c] ?? '');
      if ($cell !== '' && in_array($cell[0], ['=','+','-','@'], true)) { $cell = "'".$cell; } // CSV formula injection guard
      $line[] = $cell;
    }
    fputcsv($out, $line);
  }
  fclose($out);
  exit;
}

/* Pagination */
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 25;
$off  = ($page-1)*$per;

/* Count total */
try {
  $cnt_sql = "SELECT COUNT(*) FROM `$table` $where_sql";
  $st = $pdo->prepare($cnt_sql);
  $st->execute($vals);
  $total = (int)$st->fetchColumn();
} catch (Throwable $e) {
  $total = 0; $error = "Count failed: " . $e->getMessage();
}

/* Fetch page */
$fields = "`" . implode("`,`", $show) . "`";
$sql = "SELECT $fields FROM `$table` $where_sql $order LIMIT $per OFFSET $off";
$rows = [];
try {
  $st = $pdo->prepare($sql);
  $st->execute($vals);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $error = "Query failed: " . $e->getMessage();
}
$pages = ($per>0) ? max(1, (int)ceil($total/$per)) : 1;

Kontact\Admin\header_nav(tr('messages','Messages'),'messages');
?>
  <?php if ($flash): ?><div id="snotifications" class="flash"><?=h($flash)?></div><?php endif; ?>
  <?php if ($error): ?><div id="snotifications" class="flash error"><?=h($error)?></div><?php endif; ?>

<div class="card">
  <div class="card">
    <h2><?php _e('search_filter','Search / Filter'); ?></h2>
    <form method="get" class="input-row">
      <label><?php _e('text','Text'); ?> <input name="q" value="<?=h($q)?>" placeholder="<?php _e('subject_or_message','subject or message contains'); ?>"></label>
      <label><?php _e('email','Email'); ?> <input name="email" value="<?=h($email)?>"></label>
      <label><?php _e('ip','IP'); ?> <input name="ip" value="<?=h($ip)?>"></label>
      <?php if (isset($cols['created_at'])): ?>
      <label><?php _e('from','From'); ?> <input name="from" type="date" value="<?=h($from)?>"></label>
      <label><?php _e('to','To'); ?> <input name="to" type="date" value="<?=h($to)?>"></label>
      <?php endif; ?>
      <button class="btn"><?php _e('apply','Apply'); ?></button>
      <a class="btn" href="messages.php"><?php _e('clear','Clear'); ?></a>
      <a class="btn" href="messages.php?<?=h(http_build_query(array_merge($_GET, ['export'=>'csv'])))?>"><?php _e('export_csv','Export CSV'); ?></a>
    </form>
  </div>

  <div class="card">
    <div class="input-row">
      <div><strong><?php _e('table','Table'); ?>:</strong> <?=h($table)?></div>
      <div><strong><?php _e('total','Total'); ?>:</strong> <?=$total?></div>
      <div><strong><?php _e('page','Page'); ?>:</strong> <?=$page?> / <?=$pages?></div>
    </div>
    
    <div class="input-row" style="justify-content:flex-end;align-items:center;gap:8px">
      <form method="post" style="margin:0;display:flex;gap:8px;align-items:center"
            onsubmit="return confirm('<?php _e('delete_scope','Delete selected scope? This cannot be undone.'); ?>');">
        <input type="hidden" name="__action" value="bulk_delete">
        <input type="hidden" name="csrf" value="<?php echo h(\Kontact\csrf_token()); ?>">
        <?php $msg_ids = isset($rows) ? array_map(function($r){ return (int)($r['id'] ?? 0); }, $rows) : []; $msg_ids = array_filter($msg_ids); ?>
        <input type="hidden" name="ids" value="<?php echo h(implode(',', $msg_ids)); ?>">
        <select id="msg_scope" name="mode" style="padding:6px 8px;border:1px solid #d1d5db;border-radius:6px;">
		  <option value="current"><?php _e('current_page','Current page'); ?></option>
		  <option value="older_7"><?php _e('older_7','Older than 7 days'); ?></option>
		  <option value="older_14"><?php _e('older_14','Older than 14 days'); ?></option>
		  <option value="older_30" selected><?php _e('older_30','Older than 30 days'); ?></option>
		  <option value="older_90"><?php _e('older_90','Older than 90 days'); ?></option>
		  <option value="filter_all"><?php _e('filter_all','Current filter (all pages)'); ?></option>
		  <option value="all"><?php _e('all_events','All events'); ?></option>
        </select>
        <button class="btn danger" style="white-space:nowrap"><?php _e('delete','Delete'); ?></button>
      </form>
    </div>
   <details class="collapsible" open id="recent-messages"><summary><?php _e('recent_msgs','Recent Messages'); ?></summary>
   <div class="table-scroll">
      <table class="table">
        <thead>
          <tr>
            <?php foreach ($show as $c): ?><th><?=h($c)?></th><?php endforeach; ?>
            <th><?php _e('actions','Actions'); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="<?=count($show)+1?>"><?php _e('no_messages_found','No messages found.'); ?></td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($show as $c): ?>
                <td><?php
                  $v = (string)($r[$c] ?? '');
                  if ($c==='message') {
                    $short = mb_substr($v, 0, 200);
                    echo nl2br(h($short));
                    if (mb_strlen($v) > 200) echo '…';
                  } else {
                    echo h($v);
                  }
                ?></td>
              <?php endforeach; ?>
              <td>
                <form method="post" onsubmit="return confirm('<?php _e('delete_message','Delete this message?'); ?>');">
                  <input type="hidden" name="__action" value="delete">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <?php if (in_array('id', $show)): ?>
                    <input type="hidden" name="id" value="<?=h($r['id'])?>">
                  <?php else: ?>
                    <?php foreach (['name','subject','email','message','user_ip','user_id','created_at'] as $k): if (isset($r[$k])): ?>
                      <input type="hidden" name="<?=$k?>" value="<?=h($r[$k])?>">
                    <?php endif; endforeach; ?>
                  <?php endif; ?>
                  <button class="btn danger"><?php _e('delete','Delete'); ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
   </div>

    <?php if ($pages>1): ?>
      <div class="pagination">
        <?php for ($i=1;$i<=$pages;$i++): ?>
          <?php if ($i===$page): ?><span class="page current"><?=$i?></span>
          <?php else: ?><a class="page" href="?<?=h(http_build_query(array_merge($_GET, ['page'=>$i])))?>"><?=$i?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php Kontact\Admin\footer(); ?>
