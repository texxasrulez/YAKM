# YAKM (Yet Another Kontact Manager) — Secure, Themed Contact Form + Admin

[![Github License](https://img.shields.io/github/license/texxasrulez/YAKM?style=plastic&logo=github&label=License&labelColor=blue&color=coral)](https://github.com/texxasrulez/YAKM/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/texxasrulez/YAKM?style=plastic&logo=github&label=Stars&labelColor=blue&color=deepskyblue)](https://github.com/texxasrulez/YAKM/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/texxasrulez/YAKM?style=plastic&logo=github&label=Issues&labelColor=blue&color=aqua)](https://github.com/texxasrulez/YAKM/issues)
[![GitHub Contributors](https://img.shields.io/github/contributors/texxasrulez/YAKM?style=plastic&logo=github&logoColor=white&label=Contributors&labelColor=blue&color=orchid)](https://github.com/texxasrulez/YAKM/graphs/contributors)
[![GitHub Forks](https://img.shields.io/github/forks/texxasrulez/YAKM?style=plastic&logo=github&logoColor=white&label=Forks&labelColor=blue&color=darkorange)](https://github.com/texxasrulez/YAKM/forks)
[![Donate Paypal](https://img.shields.io/badge/Paypal-Money_Please!-blue.svg?style=plastic&labelColor=blue&color=forestgreen&logo=paypal)](https://www.paypal.me/texxasrulez)

This is what my original simple kontact manager has evolved into.

A drop‑in PHP contact/email system with a clean admin panel, themes + templates, reCAPTCHA Enterprise, rate‑limiting, CSRF, blocklist + firewall feed, and maintenance tools.

This README is a practical, install‑and‑go guide. No fluff, just the knobs you need.

---

## 1) Requirements

- PHP **8.1+** with: `pdo_mysql`, `mbstring`, `openssl`, `json`, `curl`
- MySQL/MariaDB
- Web server (Apache/nginx) with HTTPS
- Outbound SMTP access to your mail provider
- (Optional) Cron for scheduled maintenance

---

## 2) Install

1. Upload or clone the project into your web root (for example `/var/www/html/kontact/`).
2. Create a MySQL or MariaDB database and user with full privileges.
3. Visit **`https://yourdomain/kontact/install.php`** in your browser.
   - Follow the prompts for DB credentials, site name, and admin credentials.
   - The installer will:
     - Import the schema (`sql/kontact_full_schema.sql`)
     - Create `config/config.inc.php`
     - Seed an admin user into `kontact_admins` (email format required)
4. Once you see *“Installation complete”*, **delete `install.php`** from your server for security.
5. Log in at **`https://yourdomain/kontact/admin/`** with the admin email and password you created.

---

## 3) Add to your site (frontend)

Place this in your contact page (update the site key). The JS obtains a reCAPTCHA Enterprise token and injects it into a hidden field before submit.

```html
<script src="https://www.google.com/recaptcha/enterprise.js?render=YOUR_SITE_KEY"></script>

<script src="kontact/assets/js/anti_bot.js"></script>
<script src="kontact/assets/js/form_tokens.js"></script>
<script src="kontact/assets/js/multiple_recipients.js"></script>
<script src="kontact/assets/js/recaptcha_enterprise.js"></script>

<form action="kontact/send_mail.php" method="post" id="kontact-form" novalidate data-min-wait-seconds="5">
  <label for="recipient">Choose Recipient</label>
  <select name="recipient_id" id="recipient" required></select>

  <input type="text"   name="name"    id="name"    placeholder="Name (Required)"    required>
  <input type="email"  name="email"   id="email"   placeholder="Email (Required)"   required>
  <input type="text"   name="subject" id="subject" placeholder="Subject (Required)" required>
  <textarea name="message" id="message" placeholder="Message (Required)" required></textarea>

  <input type="hidden" name="recaptcha_token" value="">
  <button type="submit">Send Message</button>
</form>
```

If you use a visible widget instead, remove the hidden token and load the v2 widget script. Enterprise recommended.

---

## 4) reCAPTCHA Enterprise

**Client (frontend)**  
- Load the Enterprise loader with your *Site Key*:
  ```html
  <script src="https://www.google.com/recaptcha/enterprise.js?render=YOUR_SITE_KEY"></script>
  ```
- `kontact/assets/js/recaptcha_enterprise.js` calls:
  ```js
  grecaptcha.enterprise.execute('YOUR_SITE_KEY', {action: 'submit'})
    .then(token => { document.querySelector('[name=recaptcha_token]').value = token; });
  ```

**Server (backend)**  
- Set your Google **API key** in **Admin → Settings → reCAPTCHA** (or `KCFG('RECAPTCHA_API_KEY')`).
- `send_mail.php` posts the token to `recaptchaenterprise.googleapis.com` and enforces:
  - token validity, hostname match, expected action `"submit"`
  - risk score threshold (configurable)
- Typical errors you may see in logs:
  - `captcha_fail:missing-token` → hidden field empty; ensure the loader is present and JS ran
  - `captcha_fail:http-code:403` → API key/key restrictions not valid for reCAPTCHA Enterprise
  - `invalid key type` → using a non‑Enterprise key with the Enterprise API

---

## 5) Email, Themes & Templates

- Configure SMTP and defaults under **Admin → Settings → Email**.
- Choose an **Email Theme** (wrapper) for admin and auto‑reply.
- Edit **Templates** under **Admin → Templates**:
  - Placeholders: `{{site_name}}`, `{{subject}}`, `{{name}}`, `{{email}}`, `{{message}}`, `{{ip}}`, etc.
  - Auto‑reply uses the **Auto Reply** template and the selected theme.
- Test from **Admin → SMTP Test**. Tests respect the selected theme.

---

## 6) Security features

- **CSRF** on all forms (server‑verified).
- **Rate limiting** (minimum seconds between sends per IP/email) — configure in **Security → Rate Limit**.
- **Audit log**: every block/allow/failed‑captcha/CSRF shows up in **Security → Recent Security Events**.
- **Blocklist**: block IPs (single or CIDR). Unblock from the same page.
- **Firewall feed**: expose your blocklist as a tokenized endpoint:
  - Plain list: `kontact/api/blocklist_feed.php?token=...&format=plain`
  - ipset: `kontact/api/blocklist_feed.php?token=...&format=ipset`
  - Point your Control Panel or other firewalls to the URL. Rotate token in **Security → Firewall Feed**.

---

## 7) Admin Pages

### Messages
- Filter by text/email/IP/date.
- **Bulk delete** pulldown:
  - **Current page** — deletes only the rows you’re looking at
  - **Current filter (all pages)** — deletes *everything that matches your filters*
  - **Older than 7/14/30/90 days**
  - **All messages**
- **Export CSV** of the current filter.

### Security
- **Recent Security Events** table with actions.
- **Bulk delete** pulldown:
  - **Current page** — deletes the rows currently listed
  - **Older than 7/14/30/90 days**
  - **All events**
  - *(If you add filters later, “Current filter (all pages)” will delete matching events across pages.)*
- **Blocklist** management, **Firewall Feed** token regen, **Rate‑limit** settings.

---

## 8) Maintenance (optional but recommended)

You can enable auto‑pruning via cron. Example: delete audit events older than 30 days and messages older than 90 days daily.

```
# run daily at 02:10
10 2 * * * /usr/bin/php -d detect_unicode=0 /path/to/webroot/kontact/tools/maintenance.php --prune --audit-older=30 --messages-older=90 >> /var/log/kontact_maint.log 2>&1
```

The maintenance tool also supports manual actions from the **Maintenance** tab (delete >7d, rotate tokens, etc.)

---

## 9) Hardening checklist

- Force HTTPS; set `session.cookie_secure=1` and `session.cookie_samesite=Lax`.
- Restrict **Admin** location with HTTP auth or IP allowlist if possible.
- Keep `storage/secret/` non‑web‑readable.
- Use an SMTP provider that enforces TLS and DKIM/SPF/DMARC on your domain.
- Apply least‑privileged DB user credentials (only this schema).

---

## 10) Troubleshooting

- **CSRF mismatch**: ensure the `csrf` hidden field is present and you’re not posting across hosts.
- **Captcha missing token**: verify the `enterprise.js` loader is present and `recaptcha_enterprise.js` runs before submit.
- **Invalid key type / 403**: double‑check that you created an **Enterprise** site key and the API key has the correct API enabled & restrictions.
- **Theme not applied**: confirm theme selection in **Admin → Settings → Email**; SMTP test page uses the selected theme.
- **Auto‑reply not sent**: check rate‑limit throttling and template enable/checkbox in settings.

---

## 11) Upgrading

- Replace files, keep `storage/` and your database.
- Re‑run `kontact/admin/setup.php` only if schema changes are documented in `sql/migrations/`.
- Always back up the DB before upgrades.

---

## 12) Logs

The **Logs** tab lists files under `storage/logs/` and lets you:

- View the last N lines (default 500), with simple text search.
- Download the raw log file.
- Clear a log (truncates non-gz files).

Typical files include: `recaptcha.log`, `mailer.log`, `security.log`, and rotated variants like `*.log.1`. 
Rotation and retention are managed by **Maintenance** (see that section).## 12) License

## 13) License
GPL (see `LICENSE`). PRs welcome.


---

## Recipients

**Recipient routing is now JSON-only.** The legacy `RECIPIENTS_JSON` keys are removed.

"
"Configure recipients in **Admin → Settings → Email Settings → Recipient Mode: Multiple** and paste JSON into **Recipients JSON**.

"
"Example:

"
"```json
"
"[
"
"  {"label": "Sales",   "email": "sales@example.com"},
"
"  {"label": "Support", "email": "support@example.com"},
"
"  {"label": "Billing", "email": "billing@example.com"}
"
"]
"
"```

"
"- The public form posts `recipient_id`.
"
"- Server resolves `recipient_id → email` from that JSON (order preserved).
"
"- If JSON is empty, we fall back to a single `WEBMASTER_EMAIL` option.
"
"- Routing decisions are logged to `storage/logs/routing.log` (includes `posted_id`, selected `recipient`, and `allowed`).

## Firewall Feed Snapshot

The **Firewall Feed** page now supports both token regeneration and a static snapshot:

"
"- **Regenerate Token** rotates the shared-secret token used by `/kontact/api/blocklist_feed.php`.
"
"- **Regenerate Snapshot** writes `kontact/feeds/blocked_ips.txt` (newline-separated IPs; IPv4/IPv6). The snapshot auto-refreshes whenever blocks are **added**, **deleted**, or **pruned**.

## Database Setup

Run `SQL/mysql.sql` on a new or existing database. It is **idempotent**:

"
"- `CREATE TABLE IF NOT EXISTS` for all tables.
"
"- `INSERT IGNORE` default settings (never overwrites existing values).
"
"
Tables included: `kontact_settings`, `kontact_blocklist`, `kontact_audit`, `kontact_templates`, `kontacts`, `kontact_admins`, `kontact_password_resets`.

