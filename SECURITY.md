# Security Policy

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security issue in this library, email [dickson@alle-ai.com](mailto:dickson@alle-ai.com) with:

- A description of the issue
- Steps to reproduce
- The version(s) of `alle-ai/anthropic-api-php` affected
- Any suggested mitigation

You'll receive an acknowledgement within **72 hours**. We aim to triage, fix, and disclose within **30 days** of acknowledgement, depending on severity and complexity.

## Supported versions

| Version | Status |
|---|---|
| 2.x | Active development — security fixes |
| 1.x | Maintenance — security fixes only, on the `1.x` branch |
| < 1.0 | Unsupported |

## Out of scope

This library is a thin client for the Anthropic API. The following are out of scope here and should be reported to the relevant party:

- Vulnerabilities in the Anthropic API itself — report to Anthropic.
- Vulnerabilities in dependencies (Guzzle, Nyholm/PSR7, php-http/discovery, etc.) — report to those projects.
- Issues caused by storing API keys in source code or VCS — please use environment variables.

## Disclosure

After a fix ships, we credit the reporter (with permission) in the release notes and `CHANGELOG.md`.
