<?php
declare(strict_types=1);
use Kontact\Database;

require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Mailer.php';

$db  = new Database($GLOBALS['cfg']);
$pdo = $db->pdo();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$token = (string)($_GET['token'] ?? '');
$flash = null; $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = (string)($_POST['token'] ?? '');
  $pass1 = (string)($_POST['password'] ?? '');
  $pass2 = (string)($_POST['password2'] ?? '');
  try {
    if ($token==='' || $pass1==='' || $pass2==='') throw new \RuntimeException('All fields required.');
    if ($pass1 !== $pass2) throw new \RuntimeException('Passwords do not match.');
    // find reset by token that's still valid
    $row = $db->fetchOne('SELECT r.id, r.admin_id FROM kontact_password_resets r WHERE r.token=? AND r.expires_at > NOW()', [$token]);
    if (!$row) throw new \RuntimeException('Invalid or expired token.');
    $hash = password_hash($pass1, PASSWORD_DEFAULT);
    $db->exec('UPDATE kontact_admins SET password_hash=? WHERE id=?', [$hash, $row['admin_id']]);
    $db->exec('DELETE FROM kontact_password_resets WHERE id=?', [$row['id']]);
    $flash = 'Password has been reset. You may now log in.';
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<link rel="stylesheet" href="../assets/css/admin.css"/>
<title>Reset Password • Kontact</title>
</head>
<body>
<div class="container">
  <div class="card">
    <h1>Reset Password</h1>
    <?php if ($flash): ?><div class="flash"><?=h($flash)?></div><?php endif; ?>
    <?php if ($error): ?><div class="flash error"><?=h($error)?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="token" value="<?=h($token)?>">
      <div class="input-row">
        <label>New Password <input type="password" name="password" required></label>
        <label>Confirm Password <input type="password" name="password2" required></label>
      </div>
      <button class="btn">Reset</button>
    </form>
    <p class="notice"><a href="login.php">Back to login</a></p>
  </div>
</div>
</body>
</html>
