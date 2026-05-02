# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0-beta.2] - 2026-05-02

### Added

- `Resources\Models` for `GET /v1/models` (paginated) and `GET /v1/models/{id}` with typed `ModelInfo` and `ModelList` DTOs. `ModelInfo::toModel()` bridges back to the existing `Model` value object.
- `Http\Middleware\LoggingMiddleware` — optional PSR-3 logger emitting one entry per request and one per response (or error) sharing a correlation id, with latency in ms. Bodies are **not** logged by default; opt in with `logBodies: true`. Wired through `ClientBuilder::withLogger($logger, $logBodies = false)`.
- `Auth\RequestTransformingAuthProvider` sub-interface — `AuthProvider`s that need to mutate the entire request (URL / body / headers), not just inject headers. `AuthMiddleware` checks for it and hands over the whole request when an auth provider opts in. Backwards-compatible with all existing `AuthProvider` implementations.
- `Auth\BedrockAuth` — first-class AWS Bedrock auth provider. Loads credentials via the AWS default chain, rewrites the URL to `bedrock-runtime.{region}.amazonaws.com/model/{modelId}/invoke` (or `invoke-with-response-stream` for streaming), transforms the body to Bedrock's expected shape (drops `model`/`stream`, injects `anthropic_version: bedrock-2023-05-31`), strips Anthropic-only headers, and signs with AWS SigV4. Requires `aws/aws-sdk-php` (suggest).
- `Auth\VertexAuth` — first-class Google Vertex AI auth provider. Acquires OAuth tokens via Google ADC (`cloud-platform` scope), rewrites the URL to `{region}-aiplatform.googleapis.com` with the publisher path and `rawPredict`/`streamRawPredict` suffix, transforms the body (drops `model`/`stream`, injects `anthropic_version: vertex-2023-10-16`). Requires `google/auth` (suggest).
- `examples/11-bedrock.php` and `examples/12-vertex.php` runnable smokes against the cloud backends.

### Changed

- README: new "Alternative deployments" section with Bedrock and Vertex usage; feature bullets flipped to "shipped" for Files / Batches / MCP / Models / PSR-3 logging / Bedrock / Vertex; roadmap collapsed to reflect what beta.2 actually contains.
- `composer.json`: `aws/aws-sdk-php` and `google/auth` added under `require-dev` (so PHPStan and the test suite can resolve their symbols) and under `suggest` (so consumers see the install hint without pulling them by default).

## [2.0.0-beta.1] - 2026-05-02

First public beta of the v2 line.

### Added

**Core**
- Complete v2.0 rewrite under the `AlleAI\Anthropic\` namespace.
- `Client` and `ClientBuilder` for fluent construction; `Client::fromApiKey()`, `Client::fromEnvironment()`.
- Typed exception hierarchy: `AnthropicException` → `ApiException` (per-status subclasses), `RequestException`, `ConnectionException`, `TimeoutException`, `StreamException`, `DeprecationException`. `ExceptionFactory` maps non-2xx PSR-7 responses to the right class with status, error type, request id, and headers attached.

**Auth**
- `Auth\AuthProvider` interface with `ApiKeyAuth` (env-loader included) and `BearerAuth` (literal token or refresh callback) implementations.

**HTTP**
- PSR-18 / PSR-17 transport with auto-discovery via `php-http/discovery`. Bring any HTTP client.
- Middleware stack: `AuthMiddleware`, `UserAgentMiddleware`, `IdempotencyMiddleware` (auto UUIDv7 on POST), `RetryMiddleware`, optional `LoggingMiddleware` (PSR-3).
- `RetryPolicy` with exponential backoff, jitter, and `Retry-After` honoring.
- `CurlStreamTransport` for SSE streaming (PSR-18 cannot stream; cURL multi-handle drives chunk yielding).

**Messages**
- `Resources\Messages` for `POST /v1/messages`, `POST /v1/messages/count_tokens`, and SSE streaming via `stream()`.
- Typed content blocks: `TextBlock`, `ImageBlock` (URL/file/base64 with MIME detection), `DocumentBlock` (file_id/url/base64), `ToolUseBlock`, `ToolResultBlock`, `ThinkingBlock`, `RedactedThinkingBlock`, `McpToolUseBlock`, `McpToolResultBlock`, `Citation`, `CacheControl`, `UnknownBlock` (forward-compat fallback).
- `ThinkingConfig::enabled(budgetTokens:)` for extended thinking on reasoning models.
- `Model` value object with constants for known Claude IDs and a `Model::of()` escape hatch for new ones.
- `Resources\Models` for `GET /v1/models` (paginated) and `GET /v1/models/{id}`.

**Streaming**
- 8 typed event classes (`MessageStart`, `ContentBlockStart`/`Delta`/`Stop`, `MessageDelta`/`Stop`, `Ping`, `Error`) plus `UnknownEvent` fallback.
- 6 typed delta classes (`TextDelta`, `InputJsonDelta`, `ThinkingDelta`, `SignatureDelta`, `CitationsDelta`, `UnknownDelta`).
- `EventParser` (stateful SSE buffer handling arbitrary chunk boundaries / CRLF / comments), `Aggregator` (assembles partial JSON for tool inputs into a final `MessageResponse`), `EventStream` (single-pass `IteratorAggregate` Generator with `toMessage()`).

**Tools**
- `Tool` interface; `ClosureTool` for inline tools; `ClassTool` abstract base where subclass `runTool()` parameter signatures drive the JSON Schema via reflection. `#[Param(description:)]` and `#[Enum(...)]` attributes refine the schema; backed enums are detected automatically.
- `ToolChoice` value object (auto / any / tool('name') / none, with `disable_parallel_tool_use`); `ToolSet` collection; `ToolLoop` automatic round-trip executor with configurable `maxIterations` and `catchToolErrors`.

**Files API (beta — auto-attaches `files-api-2025-04-14` header)**
- `Resources\Files` with `upload()` (multipart/form-data), `get()`, `list()`, `delete()`, `downloadTo()`.
- `FileUpload` factories from path (with `finfo` MIME detection) and from raw bytes; `FileResource` and `FileList` typed DTOs.

**Batches API (beta — auto-attaches `message-batches-2024-09-24` header)**
- `Resources\Batches` with `create()`, `get()`, `cancel()`, `list()`, `pollUntilDone()`, and `results()` that streams JSONL line-by-line.
- `BatchEntry`, `BatchResponse`, `BatchResult`, `BatchStatus` enum.

**MCP connector (beta — caller attaches `mcp-client-2025-04-04` header)**
- `Beta\Mcp\McpServer::url()` typed builder; `Beta\Mcp\McpToolApproval` (always / never / unless_disallowed / custom mode + allow/deny lists).
- `Beta\BetaHeaders` registry with constants for the well-known `anthropic-beta` values.

**Util**
- `Util\Json` safe encode/decode that throws `AnthropicException` on failure.
- `Util\Clock` and `Util\Sleeper` interfaces for testable retries.

**Repository / DX**
- Repository hygiene: `.gitignore`, `.gitattributes`, `.editorconfig`, `phpstan.neon.dist` (level 9, strict + deprecation rules), `phpunit.xml.dist` (PHPUnit 11), `pint.json`.
- GitHub Actions CI workflow (lint + phpstan + tests on PHP 8.2/8.3/8.4, lowest+highest deps).
- Nightly integration workflow that smoke-tests against the live API (`ANTHROPIC_API_KEY` secret).
- 11 runnable examples covering simple, streaming, tool use, tool loop, vision, prompt caching, extended thinking, MCP, files, batches.
- Full PHPUnit suite: 145+ unit + contract tests with JSON/SSE fixtures and a programmable `FakePsr18Client`.
- Rewritten `README.md`; new `UPGRADING.md`, `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`.

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
