<?php
declare(strict_types=1);

/* Simple read-only introspection:
 * - shows which DB host/name the app is using (from config.inc.php)
 * - connects with PDO and prints DATABASE(), USER()
 * - lists any kontact_* tables anywhere this user can see
 * - checks kontact_admins count if present
 */

header('Content-Type: text/plain; charset=utf-8');
echo "=== Kontact DB Probe ===\n\n";

$here = __DIR__;
$cfgPath = realpath($here . '/../config/config.inc.php');
echo "Probe file:      {$here}/db_probe.php\n";
echo "Config path:     " . ($cfgPath ?: 'NOT FOUND') . "\n";

if (!$cfgPath) {
  echo "\nNo config.inc.php found. The installer should show a wizard.\n";
  exit;
}

require $cfgPath;
$cfg = $GLOBALS['cfg'] ?? [];
$redact = function($k,$v){ return in_array($k,['DB_PASS','SMTP_PASS'],true) ? '********' : $v; };

echo "\n[config.inc.php]\n";
foreach (['DB_HOST','DB_NAME','DB_USER','DB_PASS','DATABASE_TABLE'] as $k) {
  $v = $cfg[$k] ?? '(unset)';
  if ($k === 'DB_PASS') $v = '********';
  echo str_pad($k,16) . ": " . $v . "\n";
}

try {
  $dsn = "mysql:host=".$cfg['DB_HOST'].";dbname=".$cfg['DB_NAME'].";charset=utf8mb4";
  $pdo = new PDO($dsn, (string)$cfg['DB_USER'], (string)($cfg['DB_PASS'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  echo "\n[connection]\n";
  echo "DATABASE(): " . ($pdo->query("SELECT DATABASE()")->fetchColumn() ?: '(null)') . "\n";
  echo "USER():     " . ($pdo->query("SELECT USER()")->fetchColumn() ?: '(null)') . "\n";

  echo "\n[current DB tables named kontact_%]\n";
  $rows = $pdo->query("SHOW TABLES LIKE 'kontact\\_%'")->fetchAll(PDO::FETCH_NUM);
  if (!$rows) { echo "(none)\n"; }
  else { foreach ($rows as $r) echo $r[0]."\n"; }

  echo "\n[info_schema scan for kontact_* across all DBs]\n";
  $sql = "SELECT TABLE_SCHEMA, TABLE_NAME, TABLE_ROWS
          FROM information_schema.TABLES
          WHERE TABLE_NAME LIKE 'kontact\\\\_%' ESCAPE '\\\\'
          ORDER BY TABLE_SCHEMA, TABLE_NAME";
  foreach ($pdo->query($sql) as $r) {
    echo "{$r['TABLE_SCHEMA']}.{$r['TABLE_NAME']}  rows={$r['TABLE_ROWS']}\n";
  }

  echo "\n[kontact_admins row count]\n";
  try {
    $c = $pdo->query("SELECT COUNT(*) FROM kontact_admins")->fetchColumn();
    echo "kontact_admins rows: " . (int)$c . "\n";
  } catch (Throwable $e) {
    echo "(no kontact_admins in current DB)\n";
  }

} catch (Throwable $e) {
  echo "\n[connection ERROR]\n" . $e->getMessage() . "\n";
}

echo "\n=== End ===\n";
