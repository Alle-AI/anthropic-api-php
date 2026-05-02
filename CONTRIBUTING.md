# Contributing

Thanks for your interest in contributing to `alle-ai/anthropic-api-php`!

## Quick start

```bash
git clone https://github.com/Alle-AI/anthropic-api-php.git
cd anthropic-api-php
composer install
composer ci   # runs lint + phpstan + tests
```

## Development

- **PHP 8.2+** is required.
- All new code must be in `src/Anthropic/` under the `AlleAI\Anthropic\` namespace. The `src/Legacy/` directory is frozen.
- Add `declare(strict_types=1);` at the top of every PHP file.
- Use `readonly` for DTOs.
- Public APIs need PHPDoc.

## Running checks

```bash
composer test:unit       # unit tests
composer test:contract   # contract tests against fixtures (no network)
composer stan            # PHPStan level 9
composer lint            # check style
composer fix             # apply Pint fixes
composer ci              # everything
```

Integration tests (live API) run only when `ANTHROPIC_API_KEY` is set:

```bash
ANTHROPIC_API_KEY=sk-ant-... vendor/bin/phpunit --testsuite=Integration
```

## Pull requests

1. Open a branch from `main`.
2. Add tests. New `Resources\*` methods need contract tests against a fixture; bug fixes need a regression test.
3. Update `CHANGELOG.md` under `[Unreleased]`.
4. Run `composer ci` locally — CI runs the same checks against PHP 8.2 / 8.3 / 8.4.

## Releasing

Maintainers only:

1. Bump the entry in `CHANGELOG.md` from `[Unreleased]` to `[X.Y.Z] - YYYY-MM-DD`.
2. Tag the commit: `git tag vX.Y.Z && git push origin vX.Y.Z`.
3. Packagist auto-syncs via webhook.

## Code of Conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md). By participating you agree to its terms.
