# Security & Anti‑Abuse

- CSRF tokens protect admin actions.
- Sessions are regenerated on login; legacy hashes upgraded to modern on first successful login.
- **Honeypot** and **min submit seconds** (set in Settings) add friction for bots.
- **Blocklist**: add IPs; duplicates are handled; expiry optional.
- **Audit**: events table `kontact_audit` (type, ip, UA, detail, created_at).
- **Rate‑limits**: configurable thresholds for alerts.
