<?php
declare(strict_types=1);
use Kontact\Database;

require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/Maintenance.php';
require_once __DIR__ . '/inc/layout.php';

$cfg = $GLOBALS['cfg'] ?? [];

$ran = false; $summary = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    \Kontact\csrf_verify($_POST['csrf'] ?? null);
    // Optional overrides
    $cfg['LOG_MAX_SIZE_BYTES']   = max(0, (int)($_POST['LOG_MAX_SIZE_BYTES'] ?? ($cfg['LOG_MAX_SIZE_BYTES'] ?? 1048576)));
    $cfg['LOG_RETENTION_DAYS']   = max(0, (int)($_POST['LOG_RETENTION_DAYS'] ?? ($cfg['LOG_RETENTION_DAYS'] ?? 30)));
    $cfg['AUDIT_RETENTION_DAYS'] = max(0, (int)($_POST['AUDIT_RETENTION_DAYS'] ?? ($cfg['AUDIT_RETENTION_DAYS'] ?? 30)));
    $summary = \Kontact\run_maintenance($cfg);
    $ran = true;
  } catch (\Throwable $e) {
    $error = $e->getMessage();
  }
}

Kontact\Admin\header_nav(tr('maintenance','Maintenance'), 'maintenance'); ?>

<div class="card">
 <div class="card">
  <h2><?php _e('maintenance','Maintenance'); ?></h2>
  <p><?php _e('maintenance_info','Rotate logs, purge old audit rows, and ensure DB indexes. You can run it here or via cron (<code>tools/maintenance.php</code>).'); ?></p>
  <div class="input-row">
  <form method="post" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px;">
    <input type="hidden" name="csrf" value="<?= \Kontact\csrf_token() ?>"/>

    <label>
      <div><?php _e('log_rotate','Log rotate threshold'); ?> <?php _e('bytes','(bytes)'); ?></div>
      <input type="number" name="LOG_MAX_SIZE_BYTES" value="<?= (int)($cfg['LOG_MAX_SIZE_BYTES'] ?? 1048576) ?>"/>
    </label>

    <label>
      <div><?php _e('log_retention','Log retention'); ?> <?php _e('days','(days)'); ?></div>
      <input type="number" name="LOG_RETENTION_DAYS" value="<?= (int)($cfg['LOG_RETENTION_DAYS'] ?? 30) ?>"/>
    </label>

    <label>
      <div><?php _e('audit_retention','Audit retention'); ?> <?php _e('days','(days)'); ?></div>
      <input type="number" name="AUDIT_RETENTION_DAYS" value="<?= (int)($cfg['AUDIT_RETENTION_DAYS'] ?? 30) ?>"/>
    </label>

    <div style="align-self:end">
      <button class="btn primary"><?php _e('run_maintenance','Run Maintenance Now'); ?></button>
    </div>
  </form>
</div>
</div>
</div>
<div>
  <?php if ($error): ?>
    <div id="snotifications" style="background:#fee2e2;border:1px solid #fecaca;color:#7f1d1d;padding:10px;border-radius:6px;margin:12px 0;"><?php _e('error','Error'); ?>: <?= Kontact\Admin\h($error) ?></div>
  <?php elseif ($ran): ?>
    <div id="snotifications" style="background:#e6ffed;border:1px solid #bbf7d0;color:#14532d;padding:10px;border-radius:6px;margin:12px 0;">
      <strong><?php _e('done','Done.'); ?></strong>
      <div><?php _e('rotated','Rotated:'); ?> <?= Kontact\Admin\h(implode(', ', $summary['rotated'] ?? [])) ?></div>
      <div><?php _e('purged_logs','Purged logs:'); ?> <?= (int)($summary['purged_logs'] ?? 0) ?></div>
      <div><?php _e('pruned_audit_rows','Pruned audit rows:'); ?> <?= (int)($summary['pruned_audit'] ?? 0) ?></div>
      <?php if (!empty($summary['indexes'])): ?>
        <div><?php _e('indexes_added','Indexes added:'); ?> <?= Kontact\Admin\h(implode(', ', $summary['indexes'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($summary['errors'])): ?>
        <div class="alert"><?php _e('notes','Notes:'); ?> <?= Kontact\Admin\h(implode(' | ', $summary['errors'])) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>
<?php Kontact\Admin\footer(); ?>
