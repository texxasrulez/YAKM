# Database & Migrations

- Base schema: `SQL/mysql.sql` (idempotent).
- Migrations live under `SQL/migrations/`. Apply with your MySQL/MariaDB client.

Example (shell):
```sql
SOURCE /path/to/kontact/SQL/mysql.sql;
-- then each migration:
SOURCE /path/to/kontact/SQL/migrations/<file>.sql;
```

**Key tables**
- `kontact_admins` (id, email, password_hash, role, is_active, created_at, last_login_at)
- `kontact_settings` (key, value) — settings overlay (wins over file config)
- `kontact_templates` (id, name, subject, body_html, created_at, updated_at)
- `kontact_blocklist` (id, ip, reason, created_at, expires_at)
- `kontact_audit` (id, event_type, ip, user_agent, detail, created_at)
- `kontacts` (id, name, subject, email, message, user_ip, user_id, created_at)
