# Email Themes

- Themes are PHP files under `kontact/themes/` that wrap the email body.
- They receive variables: `$site_name, $site_url, $site_logo, $form_title, $form_name, $subject, $message_html` and aliases `$message`, `$inquiry` (legacy).
- Use **email‑safe inline CSS** inside theme files only (not in admin/public pages).
- Switch theme in Admin → Settings.
