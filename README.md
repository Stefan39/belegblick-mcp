# lexware-php-mcp

[English](README.md) | [Deutsch](README.de.md)

[![CI](https://github.com/Stefan39/lexware-php-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/Stefan39/lexware-php-mcp/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
![PHP 8.4+](https://img.shields.io/badge/php-8.4%2B-777bb4.svg)

> **Unofficial.** Not affiliated with Haufe Group. Lexware and lexoffice are trademarks of Haufe Group.

lexware-php-mcp is an unofficial, read-only [Model Context Protocol](https://modelcontextprotocol.io) server for
[Lexware Office](https://www.lexware.de/) (formerly lexoffice), written in PHP on top of the
official [PHP MCP SDK](https://github.com/modelcontextprotocol/php-sdk).

Ask Claude things like *"Which invoices are overdue?"* and get answers straight from your account.

## Why a PHP server?

The existing Lexware MCP servers are written in TypeScript or Python. This one is the PHP-native option, and
that is the point: it is not a claim that PHP is technically better, but that PHP shops should not need a second
runtime to give an AI assistant access to their bookkeeping.

- **One runtime you already run.** PHP 8.4 and Composer, nothing else. No Node.js or Python toolchain, no
  `npx` downloading code at startup.
- **Small enough to audit.** The server has three runtime dependencies (`mcp/sdk`, `symfony/http-client`,
  `symfony/clock`) and a handful of classes. Anyone handing an AI access to accounting data can read all of it
  in an afternoon. It is checked with PHPStan level 8 including strict rules.
- **Built for the German Mittelstand stack.** Many agencies, freelancers and in-house IT teams around Lexware
  work in PHP (Symfony, Laravel, WordPress, Shopware). Tools are plain classes, so they can later move into a
  Symfony application via `symfony/mcp-bundle` (see [ADR 0001](docs/adr/0001-architecture.md)).
- **Lexware pitfalls handled, not left to you.** Invoices written in Lexware and booked sales vouchers are two
  different voucher types; the server queries both. Lexware's 2 requests per second limit is throttled, and
  Lexware's error messages are passed through so a failure is explainable.

## Example questions

- *Which invoices are overdue, and by how many days?*
- *Show open invoices for customer Müller, with totals per currency.*

## Status

v0.2.0, early but usable. See the [changelog](CHANGELOG.md). Currently available tool:

| Tool | What it does |
|---|---|
| `open_items` | Unpaid sales invoices (open and overdue), oldest due date first, with days overdue and totals per currency. Optional filters: `overdueOnly`, `contact`. Amounts are gross. |

Planned: voucher search, voucher detail with net amounts, contact search, revenue per period.
See [ADR 0001](docs/adr/0001-architecture.md).

## Safety

- **Read-only.** The server only sends `GET` requests; there are no tools that create or change data.
- The API key is read from the environment only and never appears in output or error messages.
- Bank details (IBAN, BIC, account data) are stripped from every response before it reaches the model.
- Requests are throttled to the Lexware limit of 2 per second, with retry and backoff on 429/5xx.
- Data returned by a tool is sent to the model provider of your MCP client (e.g. Anthropic for
  Claude Desktop). Only use it with accounts whose data you may share with that provider.

Found a security problem? Please report it privately, see [SECURITY.md](SECURITY.md).

Use of the Lexware API is governed by the
[Public API Lizenz- und Nutzungsbedingungen](https://agb.lexware.de/lexware-office/public-api-lizenz--und-nutzungsbedingungen).

## Requirements

- PHP 8.4 or newer (CLI) with `ext-json`
- Composer
- A Lexware Office public API key: <https://app.lexware.de/addons/public-api>

## Installation

```bash
git clone --branch v0.2.0 https://github.com/Stefan39/lexware-php-mcp.git
cd lexware-php-mcp
composer install --no-dev
```

To update later: `git fetch --tags && git checkout <new-tag> && composer install --no-dev`.

## Use with Claude Desktop

Claude Desktop starts the server itself. You add **one entry** under `mcpServers` in
`claude_desktop_config.json`:

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`

The file usually contains other settings already. Do not paste a second `{ ... }` block at the end:
add the `"lexware-php-mcp"` entry inside the existing `"mcpServers"` object, or create
`"mcpServers"` as a new top-level key if it is missing. Check the result with
`python3 -m json.tool claude_desktop_config.json` before restarting Claude Desktop.

### macOS (recommended): API key from the Keychain

Store the key once; the command prompts for it, so it never ends up in your shell history:

```bash
security add-generic-password -s lexware-php-mcp -a lexware -w
```

Entry for `mcpServers` (adjust both paths; `which php` shows yours):

```json
"lexware-php-mcp": {
  "command": "/bin/sh",
  "args": ["-c", "LEXWARE_API_KEY=$(security find-generic-password -s lexware-php-mcp -w) exec /opt/homebrew/bin/php /absolute/path/to/lexware-php-mcp/bin/lexware-php-mcp"]
}
```

### Other systems: API key in the config

```json
"lexware-php-mcp": {
  "command": "php",
  "args": ["/absolute/path/to/lexware-php-mcp/bin/lexware-php-mcp"],
  "env": {
    "LEXWARE_API_KEY": "your-api-key"
  }
}
```

The key is then stored in plain text in that file; keep it private.

Quit Claude Desktop completely, start it again and ask: *"Which invoices are overdue?"*
If the server does not show up, check the log
(macOS: `~/Library/Logs/Claude/mcp-server-lexware-php-mcp.log`).

## Use with Claude Code

Register the server once; Claude Code starts it on demand:

```bash
claude mcp add --env LEXWARE_API_KEY=your-api-key lexware-php-mcp -- php /absolute/path/to/lexware-php-mcp/bin/lexware-php-mcp
```

By default the entry applies to the current project only. Add `--scope user` to make it available
everywhere. Check with `claude mcp list`, or run `/mcp` inside a session. The key is stored in plain text in
Claude Code's configuration; keep it private.

## Configuration

| Variable | Required | Default |
|---|---|---|
| `LEXWARE_API_KEY` | yes | none |
| `LEXWARE_API_BASE_URL` | no | `https://api.lexware.io` |

## Development

```bash
composer install
composer check   # php-cs-fixer (dry run), PHPStan level 8, PHPUnit
make cpd         # duplication check (needs Node/npx)
```

Debug the server interactively with the MCP Inspector. The Inspector starts the server with a
minimal environment, so pass the key with `-e`; a variable set in your shell does not reach it:

```bash
npx @modelcontextprotocol/inspector -e LEXWARE_API_KEY=your-api-key php bin/lexware-php-mcp
```

Tests never touch the real API; they use Symfony's `MockHttpClient` and `MockClock`.

## License

MIT, see [LICENSE](LICENSE).
