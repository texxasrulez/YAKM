<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/auth.php';

$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        \Kontact\csrf_verify($_POST['csrf'] ?? null);
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($email === '' || $pass === '') {
            $err = 'Missing credentials';
        } else {
            if (\Kontact\login_attempt($email, $pass)) {
                header('Location: index.php');
                exit;
            } else {
                $err = 'Invalid email or password';
            }
        }
    } catch (\Throwable $e) { $err = $e->getMessage(); }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="../assets/css/admin.css">
<title>Admin Login • Kontact</title>
</head>
<body>
<div class="container">
  <?php if (!empty($err)): ?><div class="flash error"><?=htmlspecialchars($err)?></div><?php endif; ?>
  <div class="card">
    <h2>Sign in</h2>
    <form method="post">
      <div class="input-row">
        <label>Email
          <input type="email" name="email" autocomplete="username" required>
        </label>
        <label>Password
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
      </div>
      <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
      <button class="btn" type="submit">Sign in</button>
    </form>
  </div>
</div>
</body></html>
