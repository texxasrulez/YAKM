# Upgrades

- All tables created with `CREATE TABLE IF NOT EXISTS`.
- New changes are delivered as SQL files in `SQL/migrations/`.
- Apply migrations, then visit Admin to verify the overlayed settings.

**Variable conventions**:
- Canonical message variable is `$message`. Old `$inquiry` references are still fed using `$inquiry` alias inside the renderer for compatibility.
- Canonical ID variable is `$id`. DB column for user id remains `user_id` in `kontacts`.
