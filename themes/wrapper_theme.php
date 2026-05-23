<?php declare(strict_types=1);?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($subject ?? ($site_name ?? 'Message'), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { background:transparent; margin:0; padding:24px; font:14px/1.5 -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color:#152f40; }
    .container { max-width:680px; margin:0 auto; background:#deeff5; border-radius:14px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7); overflow:hidden; }
    .header { background:#151a40; color:#fff; height:30px; padding:20px 24px; }
    .header h1 { margin:0; font-size:18px; }
    .body { padding:24px; }
    .footer { padding:16px 24px; color:#fff; font-size:12px; background:#151a40; }
    a { color:#fff; text-decoration:none; }
    .muted { color:#fffff2; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1><img src=<?= htmlspecialchars($site_url.$site_logo) ?> align="left" height="25px"></h1>
    </div>
    <div class="body">
      <?php if (!empty($message_html)) { echo $message_html; } else { ?>
        <p>Hello<?= isset($name) ? ' '.htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : '' ?>,</p>
        <p>We received your message and will get back to you shortly.</p>
        <?php if (!empty($message)): ?>
          <h3>Your message</h3>
          <p><?= nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
      <?php } ?>
    </div>
    <div class="footer">
      <div>
        <?= htmlspecialchars($site_name ?? '', ENT_QUOTES, 'UTF-8') ?>
        <?php if (!empty($site_url)): ?> • <a href="<?= htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($site_url, ENT_QUOTES, 'UTF-8') ?></a><?php endif; ?>
      </div>
      <div class="muted">
        Sent via <?= htmlspecialchars($form_title ?? 'Kontact', ENT_QUOTES, 'UTF-8') ?><?php if (!empty($user_ip)): ?> • IP: <?= htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
