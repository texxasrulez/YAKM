# Contributing

Thanks for helping improve this project!

## Development setup
1. Clone and create a feature branch.
2. Copy `config/config.inc.php.dist` to `config/config.inc.php` and adjust locally.
3. Ensure writable directories exist (`storage/logs`, `storage/cache`).

## Coding standards
- PHP: PSR-12. Use 4-space indentation.
- Keep functions small and focused; prefer early returns.
- Add docblocks where types aren’t obvious.

## Commit & PR
- Write clear commit messages (`type(scope): summary` if possible).
- Include repro steps and environment in PR description.
- CI must pass before merge.

## Tests (optional but encouraged)
- Add unit tests with PHPUnit when feasible.
- For i18n changes, keep placeholders (`%s`, `{name}`) in sync across locales.

## No secrets
- Never commit real credentials, emails, or personal domains.
- Use `*.dist` templates for configuration.
