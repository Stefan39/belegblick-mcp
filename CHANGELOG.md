# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project uses [Semantic Versioning](https://semver.org/). Until 1.0, minor versions may
contain breaking changes.

## [Unreleased]

## [0.2.0] - 2026-10-11

### Changed

- Project renamed to **lexware-php-mcp** (package `jacomeit/lexware-php-mcp`, repository
  `Stefan39/lexware-php-mcp`). Breaking for existing installations:
  - The binary is now `bin/lexware-php-mcp`; update the path in your MCP client configuration.
  - The MCP server reports its name as `lexware-php-mcp`; rename the `mcpServers` entry accordingly.
  - The PHP namespace is now `Jacomeit\LexwarePhpMcp`.
  - The README uses the Keychain service `lexware-php-mcp`; store the key again under that name or
    keep your own service name in the config.

## [0.1.0] - 2026-10-10

First release.

### Added

- MCP server over stdio for Claude Desktop and other MCP clients, built on the official PHP MCP SDK (`mcp/sdk` 0.8).
- Tool `open_items`: unpaid invoices (written in Lexware and booked sales vouchers), oldest due date
  first, with overdue flag, days overdue and totals per currency. Filters `overdueOnly` and `contact`.
  Amounts are gross.
- Read-only API client: GET only, throttled to 2 requests per second, retry with backoff on 429, 5xx
  and network errors, Lexware error messages surfaced in tool errors.
- Bank details (IBAN, BIC, account data) are removed from every response.
- API key only via the `LEXWARE_API_KEY` environment variable; setup with the macOS Keychain documented.
- Architecture decision record, security policy, CI (PHP 8.4 and 8.5, PHPStan level 8, php-cs-fixer, jscpd).

[Unreleased]: https://github.com/Stefan39/lexware-php-mcp/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/Stefan39/lexware-php-mcp/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Stefan39/lexware-php-mcp/releases/tag/v0.1.0
