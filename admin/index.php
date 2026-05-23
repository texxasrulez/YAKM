<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
Kontact\require_admin();
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/inc/layout.php';

Kontact\Admin\header_nav(tr('dashboard','Dashboard'),'dashboard');
?>
<div class="card">
  <div class="card">
    <h2><?php _e('welcome','Welcome'); ?></h2>
    <p><?php _e('index_desc','Use the navigation above to manage settings, view messages, templates, users, security, and test email delivery.'); ?></p>
    <ul>
      <li><strong><?php _e('settings','Settings'); ?>: </strong> <?php _e('settings_desc','SMTP, themes, recipients, site metadata, reCAPTCHA, auto-reply, anti-bot.'); ?></li>
      <li><strong><?php _e('security','Security'); ?>: </strong> <?php _e('security_desc','Blocklist, rate limits, suspicious activity notifications.'); ?></li>
      <li><strong><?php _e('messages','Messages'); ?>: </strong> <?php _e('messages_desc','Browse and delete contact submissions.'); ?></li>
      <li><strong><?php _e('templates','Templates'); ?>: </strong> <?php _e('templates_desc','Create and edit outgoing email templates.'); ?></li>
      <li><strong><?php _e('users','Users'); ?>: </strong> <?php _e('users_desc','Manage admin accounts and roles.'); ?></li>
      <li><strong><?php _e('logs','Logs'); ?>: </strong> <?php _e('logs_desc','View logs.'); ?></li>
      <li><strong><?php _e('maintenance','Maintenance'); ?>: </strong> <?php _e('maintenance_desc','Maintains logs.'); ?></li>
      <li><strong><?php _e('smtp_test','SMTP Test'); ?>: </strong> <?php _e('smtp_test_desc','Sends a theme-rendered test email using the current SMTP settings.'); ?></li>
    </ul>
  </div>

  <details class="card" id="quick-instructions"><summary><?php _e('quick_instructions','Quick Instructions'); ?></summary>
  <div class="input-row" style="display:block">
    <p><?php _e('copy_code_inst','Copy each code section below exactly as shown.'); ?></p>
    <div class="code-block">
      <div class="row" style="display:flex;align-items:center;gap:8px;justify-content:space-between;">
        <strong><?php _e('place_in','Place in'); ?> &lt;head&gt;&lt;/head&gt;</strong>
        <button type="button" class="btn" data-copy-target="#code-kontact-head"><?php _e('copy','Copy'); ?></button>
      </div>
        <pre id="code-kontact-head"><code>&lt;script src="https://www.google.com/recaptcha/enterprise.js?render=YOUR_SITE_ID"&gt;&lt;/script&gt;
&lt;script src="kontact/assets/js/recaptcha_enterprise.js"&gt;&lt;/script&gt;
&lt;script src="kontact/assets/js/anti_bot.js"&gt;&lt;/script&gt;
&lt;script src="kontact/assets/js/form_tokens.js"&gt;&lt;/script&gt;
&lt;script src="kontact/assets/js/multiple_recipients.js"&gt;&lt;/script&gt;</code></pre>
    </div>

    <p><?php _e('kontact_form_body','This is the kontact form in the body of your contact page'); ?></p>
    <div class="code-block">
      <div class="row" style="display:flex;align-items:center;gap:8px;justify-content:space-between;">
        <strong><?php _e('form_markup','Form Markup'); ?></strong>
        <button type="button" class="btn" data-copy-target="#code-form-markup"><?php _e('copy','Copy'); ?></button>
      </div>
      <pre id="code-form-markup"><code>&lt;form action="kontact/send_mail.php" method="post" id="kontact-form" novalidate&gt;
  &lt;label for="recipient"&gt;Choose Recipient&lt;/label&gt;
  &lt;select name="recipient_id" id="recipient" required&gt;&lt;/select&gt;

    &lt;input type="text" name="name" id="name" placeholder="Name (Required)" required&gt;
    &lt;input type="email" name="email" id="email" placeholder="Email (Required)" required&gt;
    &lt;input type="text" name="subject" id="subject" placeholder="Subject (Required)" required&gt;
    &lt;textarea name="message" id="message" placeholder="Message (Required)" required&gt;&lt;/textarea&gt;

  &lt;input type="hidden" name="recaptcha_token" value=""&gt;
  &lt;button type="submit" id="kontact-submit"&gt;Send Message&lt;/button&gt;
&lt;/form&gt;</code></pre>
    </div>

  </div>
</details>
<a href="../readme.html" target="_blank"><?php _e('readme','Readme'); ?></a>
</div>
<?php Kontact\Admin\footer(); ?>
