<?php
declare(strict_types=1);
use Kontact\Database;

require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/clean_string_polyfill.php';
require_once __DIR__ . '/../lib/theme_admin_boot.php';
require_once __DIR__ . '/inc/layout.php';

$db  = new Database($GLOBALS['cfg']);
$pdo = $db->pdo();

// Ensure templates table exists (upgrade-friendly)
$pdo->exec("CREATE TABLE IF NOT EXISTS kontact_templates (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure columns exist even on legacy installs
$cols = [];
try {
  foreach ($pdo->query("SHOW COLUMNS FROM kontact_templates") as $r) {
    $cols[$r['Field']] = true;
  }
  if (!isset($cols['created_at'])) {
    $pdo->exec("ALTER TABLE kontact_templates ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $cols['created_at'] = true;
  }
  if (!isset($cols['updated_at'])) {
    $pdo->exec("ALTER TABLE kontact_templates ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $cols['updated_at'] = true;
  }
} catch (Throwable $e) {
  // If ALTER not permitted, we will gracefully avoid selecting missing columns below
}

$flash = null; $error = null; $editing = null;
$current = ['id'=>null,'name'=>'','subject'=>'','body_html'=>''];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Seed defaults if table empty
try {
  $cnt = (int)$pdo->query("SELECT COUNT(*) FROM kontact_templates")->fetchColumn();
  if ($cnt === 0) {
    $samples = [
      ['auto_reply_ack', 'We received your message: {{subject}}', '<h2>Hi {{name}},</h2><p>We received your message and a human will get back to you soon.</p><h3>Your submission</h3><ul><li><strong>Name:</strong> {{name}}</li><li><strong>Email:</strong> {{email}}</li><li><strong>Subject:</strong> {{subject}}</li></ul><h3>Message</h3>{{message_html}}<hr><p>{{site_name}} • {{form_name}}</p>'],
      ['notify_webmaster_basic', '[{{site_name}} {{form_name}}] {{subject}}', '<h2>New submission</h2><ul><li><strong>Name:</strong> {{name}}</li><li><strong>Email:</strong> {{email}}</li><li><strong>Subject:</strong> {{subject}}</li></ul><h3>Message</h3>{{message_html}}<hr><p>Sent from {{site_name}}</p>'],
      ['notify_webmaster_rich', 'New Contact from {{name}} — {{subject}}', '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td><h2>{{site_name}} — {{form_name}}</h2><p><strong>From:</strong> {{name}} &lt;{{email}}&gt;</p><p><strong>Subject:</strong> {{subject}}</p><h3>Message</h3><div>{{message_html}}</div><hr><p>This email includes user-provided content.</p></td></tr></table>'],
    ];
    $st = $pdo->prepare("INSERT INTO kontact_templates(name,subject,body_html) VALUES(?,?,?)");
    foreach ($samples as $s) { $st->execute($s); }
  }
} catch (Throwable $e) { /* ignore seed errors */ }

try {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    $action = $_POST['__action'] ?? '';

    if ($action === 'create') {
      $name = trim($_POST['name'] ?? '');
      $subject = trim($_POST['subject'] ?? '');
      $body_html = (string)($_POST['body_html'] ?? '');
      if ($name === '' || $subject === '' || $body_html === '') { throw new \RuntimeException('All fields are required.'); }
      $db->exec('INSERT INTO kontact_templates(name,subject,body_html) VALUES(?,?,?)', [$name,$subject,$body_html]);
      header('Location: templates.php?ok=created'); exit;
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $name = trim($_POST['name'] ?? '');
      $subject = trim($_POST['subject'] ?? '');
      $body_html = (string)($_POST['body_html'] ?? '');
      if ($id<=0 || $name==='' || $subject==='' || $body_html==='') { throw new \RuntimeException('All fields are required.'); }
      $db->exec('UPDATE kontact_templates SET name=?, subject=?, body_html=? WHERE id=?', [$name,$subject,$body_html,$id]);
      header('Location: templates.php?ok=updated'); exit;
    }

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new \RuntimeException('Invalid id');
      $db->exec('DELETE FROM kontact_templates WHERE id=? LIMIT 1', [$id]);
      header('Location: templates.php?ok=deleted'); exit;
    }

    if ($action === 'duplicate') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id<=0) throw new \RuntimeException('Invalid id');
      $row = $db->fetchOne('SELECT name,subject,body_html FROM kontact_templates WHERE id=?', [$id]);
      if (!$row) throw new \RuntimeException('Template not found');
      $newName = $row['name'].'_copy_'.substr(md5((string)microtime(true)),0,4);
      $db->exec('INSERT INTO kontact_templates(name,subject,body_html) VALUES(?,?,?)', [$newName,$row['subject'],$row['body_html']]);
      header('Location: templates.php?ok=duplicated'); exit;
    }
  }
} catch (Throwable $e) {
  $error = $e->getMessage();
}

$flash = null;

$ok = (string)($_GET['ok'] ?? '');
$ok = preg_replace('/[^a-z_]/', '', $ok); // harden input to known tokens

$messages = [
  'created'    => tr('template_created',    'Template created.'),
  'updated'    => tr('template_updated',    'Template updated.'),
  'deleted'    => tr('template_deleted',    'Template deleted.'),
  'duplicated' => tr('template_duplicated', 'Template duplicated.'),
];

$flash = $messages[$ok] ?? null;

$editing = null;
$current = $current ?? ['id'=>null,'name'=>'','subject'=>'','body_html'=>''];
if (isset($_GET['edit'])) {
  $editing = (int)$_GET['edit'];
  $current = $db->fetchOne('SELECT * FROM kontact_templates WHERE id=?', [$editing]) ?: $current;
}

// Theme-wrapped preview
$previewHtml = null;
if (isset($_GET['preview'])) {
  $pid = (int)$_GET['preview'];
  $tpl = $db->fetchOne('SELECT name,subject,body_html FROM kontact_templates WHERE id=?', [$pid]);
  if ($tpl) {
    $site_name  = \Kontact\cfg('SITE_NAME','PreviewCorp');
    $site_url   = \Kontact\cfg('SITE_URL','');
    $site_logo  = \Kontact\cfg('SITE_LOGO','');
    $form_title = \Kontact\cfg('FORM_TITLE','Visitor');
    $form_name  = \Kontact\cfg('FORM_NAME','Contact');
    $name       = 'Ada Lovelace';
    $email      = 'ada@example.com';
    $subject    = $tpl['subject'];
    $message_html = strtr($tpl['body_html'], [
      '{{site_name}}'=>$site_name,
      '{{form_name}}'=>$form_name,
      '{{name}}'=>$name,
      '{{email}}'=>$email,
      '{{subject}}'=>'Hello from Preview',
      '{{message_html}}'=>'<p>This is a <strong>preview</strong> body with variables.</p>',
    ]);
    $message = strip_tags($message_html);
    $date = date('r');
    $ip   = '203.0.113.42'; $user_ip = $ip;
    $ua   = 'Mozilla/5.0 (Preview)';

    $email_body = '';
    $theme_file = \Kontact\cfg('EMAIL_THEME','');
    $theme_path = __DIR__ . '/../themes/' . basename((string)$theme_file);
    if ($theme_file && is_file($theme_path)) {
      ob_start();
      include $theme_path;
      $maybe = (string)ob_get_clean();
      if (isset($email_body) && stripos((string)$email_body,'<html')!==false) {
        // theme provided $email_body
      } elseif ($maybe && stripos($maybe,'<html')!==false) {
        $email_body = $maybe;
      } elseif (isset($message_html) && stripos($message_html,'<html')!==false) {
        $email_body = $message_html;
      }
    }
    if (!$email_body || stripos($email_body,'<html')===false) {
      $email_body = "<html><body>".$message_html."</body></html>";
    }
    $previewHtml = $email_body;
  }
}

// Build listing query that tolerates missing updated_at
$orderCol = isset($cols['updated_at']) ? 'updated_at' : (isset($cols['created_at']) ? 'created_at' : 'id');
$select = ['id','name','subject'];
if (isset($cols['updated_at'])) { $select[] = 'updated_at'; }
elseif (isset($cols['created_at'])) { $select[] = 'created_at'; }
$fields = '`' . implode('`,`', $select) . '`';
$list = $db->fetchAll("SELECT $fields FROM kontact_templates ORDER BY `$orderCol` DESC, `id` DESC");

Kontact\Admin\header_nav(tr('templates','Templates'), 'templates');
?>
  <?php if (!empty($flash)): ?><div id="snotifications" class="flash"><?=h($flash)?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div id="snotifications" class="flash error"><?=h($error)?></div><?php endif; ?>

<div class="card">
  <div class="card">
    <details open id="section"><summary><?php _e('new_template','New Template'); ?></summary>
    <form method="post">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$current['id'] ?>"><?php endif; ?>
      <div class="input-row">
        <label><?php _e('name','Name'); ?> <input name="name" required value="<?=h($current['name'] ?? '')?>"></label>
        <label><?php _e('subject','Subject'); ?> <input name="subject" required value="<?=h($current['subject'] ?? '')?>"></label>
      </div>
      <div class="input-row">
        <label style="width:100%;"><?php _e('html_body','HTML Body'); ?>
          <textarea name="body_html" rows="14";"><?=h($current['body_html'] ?? '')?></textarea>
        </label>
      </div>
      <div class="input-row">
        <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
        <?php if ($editing): ?>
          <input type="hidden" name="__action" value="update">
          <button class="btn"><?php _e('save_changes','Save Changes'); ?></button>
          <a class="btn" href="templates.php?preview=<?= (int)$current['id'] ?>"><?php _e('preview','Preview'); ?></a>
          <a class="btn" href="templates.php"><?php _e('cancel','Cancel'); ?></a>
        <?php else: ?>
          <input type="hidden" name="__action" value="create">
          <button class="btn"><?php _e('create_template','Create Template'); ?></button>
        <?php endif; ?>
      </div>
    </form>
    <p class="notice"><?php _e('template_variable','Variables'); ?> <code>{{site_name}}</code>, <code>{{form_name}}</code>, <code>{{name}}</code>, <code>{{email}}</code>, <code>{{subject}}</code>, <code>{{message_html}}</code>.</p>
  </div>
</details>

  <?php if ($previewHtml): ?>
  <div class="card">
    <h2><?php _e('theme_wrapped','Theme-wrapped Preview'); ?></h2>
    <iframe style="width:100%;min-height:500px;border:1px solid #ddd;background:#fff" srcdoc="<?=h($previewHtml)?>"></iframe>
  </div>
  <?php endif; ?>

  <details class="card" open id="existing-templates"><summary><?php _e('existing_templates','Existing Templates'); ?></summary>
    <div class="table-scroll">
      <table class="table">
        <thead>
  <tr>
    <th><?php _e('id','ID'); ?></th>
    <th><?php _e('name','Name'); ?></th>
    <th><?php _e('subject','Subject'); ?></th>
    <?php if (isset($cols['updated_at'])): ?>
      <th><?php _e('updated','Updated'); ?></th>
    <?php elseif (isset($cols['created_at'])): ?>
      <th><?php _e('created','Created'); ?></th>
    <?php endif; ?>
    <th><?php _e('actions','Actions'); ?></th>
  </tr>
</thead>
        <tbody>
          <?php foreach ($list as $row): ?>
            <tr>
              <td><?= (int)$row['id'] ?></td>
              <td><?= h($row['name']) ?></td>
              <td><?= h($row['subject']) ?></td>
              <?php if (isset($cols['updated_at'])): ?><td><?= h($row['updated_at']) ?></td>
              <?php elseif (isset($cols['created_at'])): ?><td><?= h($row['created_at']) ?></td><?php endif; ?>
              <td class="actions">
                <a class="btn" href="templates.php?edit=<?= (int)$row['id'] ?>"><?php _e('edit','Edit'); ?></a>
                <a class="btn" href="templates.php?preview=<?= (int)$row['id'] ?>"><?php _e('preview','Preview'); ?></a>
                <form method="post" style="display:inline" onsubmit="return confirm('<?php _e('delete_template','Delete template'); ?> ‘<?=h($row['name'])?>’?');">
                  <input type="hidden" name="__action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn danger"><?php _e('delete','Delete'); ?></button>
                </form>
                <form method="post" style="display:inline">
                  <input type="hidden" name="__action" value="duplicate">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="csrf" value="<?=\Kontact\csrf_token()?>">
                  <button class="btn"><?php _e('duplicate','Duplicate'); ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
   </details>
  </div>
</div>
<?php Kontact\Admin\footer(); ?>
