# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Complete v2.0 rewrite under the `AlleAI\Anthropic\` namespace.
- `Client` and `ClientBuilder` for fluent construction.
- `Resources\Messages` for `POST /v1/messages` and `POST /v1/messages/count_tokens`.
- Typed content blocks: `TextBlock`, `ImageBlock`, `ToolUseBlock`, `ToolResultBlock`, `ThinkingBlock`, `RedactedThinkingBlock`, `UnknownBlock`.
- `CacheControl` block annotation for prompt caching.
- `ThinkingConfig` for extended thinking on reasoning models.
- `Model` value object with constants for known models and a `Model::of()` escape hatch for new ids.
- Typed exception hierarchy: `AnthropicException` → `ApiException` (per-status subclasses), `RequestException`, `ConnectionException`, `TimeoutException`, `StreamException`, `DeprecationException`.
- `ExceptionFactory` mapping non-2xx PSR-7 responses to the right exception class with status, error type, request id, and headers attached.
- `Auth\AuthProvider` interface with `ApiKeyAuth` and `BearerAuth` implementations.
- HTTP middleware stack: `AuthMiddleware`, `UserAgentMiddleware`, `IdempotencyMiddleware`, `RetryMiddleware`.
- `RetryPolicy` with exponential backoff, jitter, and `Retry-After` honoring.
- PSR-18 / PSR-17 transport with auto-discovery via `php-http/discovery`.
- `Util\Json` safe encode/decode that throws `AnthropicException` on failure.
- Repository hygiene: `.gitignore`, `.gitattributes`, `.editorconfig`, `phpstan.neon.dist`, `phpunit.xml.dist`, `pint.json`, GitHub Actions CI workflow.
- Full README rewrite, `UPGRADING.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`.

### Deprecated

- `Alle_AI\Anthropic\AnthropicAPI` is now a thin compatibility shim that emits `E_USER_DEPRECATED`. Migrate to `AlleAI\Anthropic\Client`. The shim will be removed in 3.0.0. Set `ALLE_AI_ANTHROPIC_FAIL_ON_DEPRECATED=1` to convert deprecation notices into thrown `DeprecationException`s during migration.

### Changed

- Minimum PHP version is now **8.2** (was 7.4).
- Package autoload roots now include `AlleAI\Anthropic\` and the legacy `Alle_AI\Anthropic\` (for the shim).
- The `version` field in `composer.json` has been removed; releases are driven by git tags.

## [1.3] - 2024

### Added

- `api_type` parameter on `generateText()` to route between `complete` and `messages` endpoints.

## [1.2] - 2023

- `anthropic-version` header.

## [1.1] - 2023

- Initial release.
