# Belegblick

> **Unofficial.** Not affiliated with Haufe Group. Lexware and lexoffice are trademarks of Haufe Group.

Belegblick is a read-only [Model Context Protocol](https://modelcontextprotocol.io) server for
[Lexware Office](https://www.lexware.de/) (formerly lexoffice), written in PHP on top of the
official [PHP MCP SDK](https://github.com/modelcontextprotocol/php-sdk).

Ask Claude things like *"Which invoices are overdue?"* and get answers straight from your account.

## Status

Early development (v0.1). Currently available tool:

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

Use of the Lexware API is governed by the
[Public API Lizenz- und Nutzungsbedingungen](https://agb.lexware.de/lexware-office/public-api-lizenz--und-nutzungsbedingungen).

## Requirements

- PHP 8.4 or newer (CLI) with `ext-json`
- Composer
- A Lexware Office public API key: <https://app.lexware.de/addons/public-api>

## Installation

```bash
git clone <repository-url> belegblick-mcp
cd belegblick-mcp
composer install --no-dev
```

## Use with Claude Desktop

Add the server to `claude_desktop_config.json`
(macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "belegblick": {
      "command": "php",
      "args": ["/absolute/path/to/belegblick-mcp/bin/belegblick-mcp"],
      "env": {
        "LEXWARE_API_KEY": "your-api-key"
      }
    }
  }
}
```

Use the absolute path to `php` (`which php`) if Claude Desktop does not find it. Restart Claude
Desktop, then ask: *"Welche Rechnungen sind überfällig?"*

### Configuration

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

Debug the server interactively with the MCP Inspector:

```bash
LEXWARE_API_KEY=... npx @modelcontextprotocol/inspector php bin/belegblick-mcp
```

Tests never touch the real API; they use Symfony's `MockHttpClient` and `MockClock`.

## License

MIT, see [LICENSE](LICENSE).
