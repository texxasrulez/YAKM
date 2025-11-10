<?php
declare(strict_types=1);
use Kontact\Database;

require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/../lib/Mailer.php';

$db  = new Database($GLOBALS['cfg']);
$pdo = $db->pdo();

// Ensure admins table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS kontact_admins (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS kontact_password_resets (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  admin_id BIGINT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (admin_id),
  FOREIGN KEY (admin_id) REFERENCES kontact_admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$flash = null; $error = null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $action = $_POST['__action'] ?? '';

    if ($action === 'create') {
      $email = trim($_POST['email'] ?? '');
      $pass  = (string)($_POST['password'] ?? '');
      $role  = in_array(($_POST['role'] ?? 'admin'), ['admin','owner'], true) ? $_POST['role'] : 'admin';
      if ($email==='' || $pass==='') throw new \RuntimeException('Email and password are required');
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Invalid email');
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $db->exec('INSERT INTO kontact_admins(email,password_hash,role) VALUES(?,?,?)', [$email,$hash,$role]);
      $flash = tr('created_admin','Created admin') . ' ' . $email;
    }

    if ($action === 'passwd') {
      $id = (int)($_POST['id'] ?? 0);
      $pass = (string)($_POST['password'] ?? '');
      if ($id<=0 || $pass==='') throw new \RuntimeException('Invalid input');
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $db->exec('UPDATE kontact_admins SET password_hash=? WHERE id=?', [$hash,$id]);
      $flash = tr('password_updated','Password updated.');
    }

    if ($action === 'toggle') {
      $id = (int)($_POST['id'] ?? 0);
      $state = (int)($_POST['state'] ?? 1) ? 1 : 0;
      if ($id<=0) throw new \RuntimeException('Invalid id');
      $db->exec('UPDATE kontact_admins SET is_active=? WHERE id=?', [$state,$id]);
      $flash = $state ? tr('user_activated','User activated.') : tr('user_deactivated','User deactivated.');
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new \RuntimeException('Invalid id');
      // Prevent deleting the last active admin
      $cnt = (int)$pdo->query("SELECT COUNT(*) FROM kontact_admins WHERE is_active=1")->fetchColumn();
      if ($cnt <= 1) throw new \RuntimeException('Cannot delete the last active admin.');
      $db->exec('DELETE FROM kontact_admins WHERE id=? LIMIT 1', [$id]);
      $flash = tr('user_deleted','User deleted.');
    }

    if ($action === 'send_reset') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new \RuntimeException('Invalid id');
      $admin = $db->fetchOne('SELECT id,email FROM kontact_admins WHERE id=?', [$id]);
      if (!$admin) throw new \RuntimeException('Admin not found');
      $token = bin2hex(random_bytes(24));
      // expire in 60 minutes
      $db->exec('INSERT INTO kontact_password_resets(admin_id,token,expires_at) VALUES(?,?, DATE_ADD(NOW(), INTERVAL 60 MINUTE))', [$admin['id'],$token]);

      // Build link
      $site_url = \Kontact\cfg('SITE_URL','');
      $reset_link = rtrim($site_url?:'', '/') . '/kontact/admin/reset.php?token=' . urlencode($token);

      $site_name = \Kontact\cfg('SITE_NAME','Kontact');
      $subject = "Password reset — $site_name";
      $html = "<html><body><p>Use the link below to reset your password. This link expires in 60 minutes.</p><p><a href=\"$reset_link\"><?php _e('reset_password','Reset password'); ?></a></p><p>If you did not request this, you can ignore this email.</p></body></html>";
      $text = "Use the link below to reset your password (expires in 60 minutes):\n$reset_link\n\nIf you did not request this, ignore this email.\n";

      \Kontact\Mailer::send($admin['email'], $subject, $html, $text, [
        'enable_smtp' => (\Kontact\cfg('ENABLE_SMTP','0')==='1'),
        'host' => \Kontact\cfg('SMTP_HOST',''),
        'port' => (int)\Kontact\cfg('SMTP_PORT',587),
        'user' => \Kontact\cfg('SMTP_USER',''),
        'pass' => \Kontact\cfg('SMTP_PASS',''),
        'secure' => \Kontact\cfg('SMTP_SECURE','tls'),
        'from_email' => \Kontact\cfg('FROM_EMAIL', \Kontact\cfg('WEBMASTER_EMAIL','no-reply@localhost')),
        'from_name'  => \Kontact\cfg('FROM_NAME', $site_name),
        'reply_email'=> \Kontact\cfg('WEBMASTER_EMAIL','')
      ]);
      $flash = tr('reset_email_sent_to','Reset email sent to') . ' ' . $admin['email'];
    }
  }
} catch (Throwable $e) {
  $error = $e->getMessage();
}

$users = $db->fetchAll('SELECT id,email,role,is_active,created_at,last_login_at FROM kontact_admins ORDER BY id ASC');

Kontact\Admin\header_nav(tr('users','Users'),'users');
?>
  <?php if ($flash): ?><div id="snotifications" class="flash"><?=h($flash)?></div><?php endif; ?>
  <?php if ($error): ?><div id="snotifications" class="flash error"><?=h($error)?></div><?php endif; ?>

<div class="card">
  <div class="card">
    <h2><?php _e('add_admin','Create Admin'); ?></h2>
    <div class="input-row">
    <form method="post" class="input-row">
      <label><?php _e('email','Email'); ?> <input name="email" type="email" required></label>
      <label><?php _e('password','Password'); ?> <input name="password" type="password" required></label>
      <label><?php _e('role','Role'); ?>
        <select name="role">
          <option value="admin">admin</option>
          <option value="owner">owner</option>
        </select>
      </label>
      <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
      <input type="hidden" name="__action" value="create">
      <button class="btn"><?php _e('create','Create'); ?></button>
    </form>
  </div>
  </div>

  <div class="card">
   <details open id="admins"><summary><?php _e('admin','Admins'); ?></summary>
    <div class="table-scroll">
	 <div class="input-row">
      <table class="table users-table">
      <colgroup>
        <col class="col-id">
        <col class="col-email">
        <col class="col-role">
        <col class="col-status">
        <col class="col-created">
        <col class="col-lastlogin">
        <col class="col-actions">
      </colgroup>
        <thead><tr><th><?php _e('id','ID'); ?></th><th><?php _e('email','Email'); ?></th><th><?php _e('role','Role'); ?></th><th><?php _e('status','Status'); ?></th><th><?php _e('created','Created'); ?></th><th><?php _e('last_login','Last Login'); ?></th><th><?php _e('actions','Actions'); ?></th></tr></thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="7"><?php _e('no_admins_yet','No admins yet'); ?>.</td></tr>
          <?php else: foreach ($users as $u): ?>
            <tr>
              <td><?= (int)$u['id'] ?></td>
              <td><?= h($u['email']) ?></td>
              <td><?= h($u['role']) ?></td>
              <td><?= h((int)$u['is_active'] ? tr('active','Active') : tr('disabled','Disabled')) ?></td>
              <td><?= h($u['created_at']) ?></td>
              <td><?= h($u['last_login_at']) ?></td>
              <td class="actions">
                <form method="post" style="display:inline">
                  <input type="hidden" name="__action" value="passwd">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="password" name="password" placeholder="<?php _e('new_password','New password'); ?>" required>
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn"><?php _e('set_password','Set Password'); ?></button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="__action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="state" value="<?= (int)$u['is_active'] ? 0 : 1 ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn"> <?= h((int)$u['is_active'] ? tr('disable','Disable') : tr('enable','Enable')) ?></button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="__action" value="send_reset">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn"><?php _e('send_reset','Send Reset'); ?></button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('<?php _e('delete_admin','Delete admin'); ?> <?=h($u['email'])?>?');">
                  <input type="hidden" name="__action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn danger"><?php _e('delete','Delete'); ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  </div>
</div>
</details>
<?php Kontact\Admin\footer(); ?>
