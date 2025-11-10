# Reinstall / Recovery

If setup won’t run or claims it has already run:

1. **Drop tables**: remove any `kontact_*` tables (e.g., `kontact_admins`, `kontact_settings`, `kontact_templates`, `kontact_blocklist`, `kontact_audit`, `kontacts`).
2. **Delete config**: remove `kontact/config/config.inc.php`.
3. **Restore installer**: rename `kontact/admin/setup.php.sample` → `setup.php`.
4. Reload `/kontact/admin/setup.php` and complete the wizard.
