# Install & Setup

1. Upload the entire `kontact/` directory to your server.
2. Ensure your web server can read/write within `kontact/config/` (to create `config.inc.php` during setup).
3. Browse to `/kontact/admin/setup.php` and complete the wizard:
   - Database connection (host, name, user, pass)
   - Admin email + password (stored hashed)
   - Site/email defaults (logo URL path, site URL, etc.)
4. After success, installer renames itself to `setup.php.sample`.
5. Log in at `/kontact/admin/login.php` and configure **Settings**.
