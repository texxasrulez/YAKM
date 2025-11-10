# Kontact Quick Start

This cheat sheet helps new admins configure and verify Kontact in minutes.

## 1. Database Setup
- Run `SQL/mysql.sql` against your MySQL/MariaDB database.
- Safe to run on **empty or full** DB (uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE` defaults).
- Tables: `kontact_settings`, `kontact_blocklist`, `kontact_audit`, `kontact_templates`, `kontacts`, `kontact_admins`, `kontact_password_resets`.

## 2. Basic Settings
- In **Admin → Settings**, fill in:
  - **Site Name / URL / Logo**
  - **Webmaster Email**
  - **From Email / From Name**
- SMTP optional (enable + configure if you want relay instead of `mail()`).
- reCAPTCHA optional (fill keys if desired).

## 3. Recipients
- Recipient routing is **JSON-only**.
- Set **Recipient Mode** = Multiple, then paste JSON in **Recipients JSON**:
```json
[
  {"label":"Sales","email":"sales@example.com"},
  {"label":"Support","email":"support@example.com"}
]
```
- Public form posts a `recipient_id`; server resolves via JSON order.
- If JSON empty, falls back to single **Webmaster Email**.

## 4. Firewall & Security
- **Firewall Feed** provides:
  - Tokenized URL (`/kontact/api/blocklist_feed.php?token=...`)
  - Static snapshot: `kontact/feeds/blocked_ips.txt`
- Snapshot auto-refreshes on add/delete/prune of blocks.

## 5. Logs & Debugging
- **Admin → Logs**: view request, routing, and autoloader logs.
- Routing decisions are logged to `storage/logs/routing.log`.
- `<details>` sections remember open/closed state between visits.

## 6. Testing
- Submit a test form to each recipient.
- Verify email delivery and `routing.log` output.
- Adjust JSON or SMTP as needed.

That's it! You're live.
