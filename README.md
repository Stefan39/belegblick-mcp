# Belegblick

> **Unofficial.** Not affiliated with Haufe Group. Lexware and lexoffice are trademarks of Haufe Group.

Belegblick is a read-only [Model Context Protocol](https://modelcontextprotocol.io) server for
[Lexware Office](https://www.lexware.de/) (formerly lexoffice), written in PHP on top of the
official [PHP MCP SDK](https://github.com/modelcontextprotocol/php-sdk).

Ask Claude things like *"Which invoices are overdue?"* and get answers straight from your account.

## Status

v0.1.0, early but usable. See the [changelog](CHANGELOG.md). Currently available tool:

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
git clone --branch v0.1.0 https://github.com/Stefan39/belegblick-mcp.git
cd belegblick-mcp
composer install --no-dev
```

To update later: `git fetch --tags && git checkout <new-tag> && composer install --no-dev`.

## Use with Claude Desktop

Claude Desktop starts the server itself. You add **one entry** under `mcpServers` in
`claude_desktop_config.json`:

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`

The file usually contains other settings already. Do not paste a second `{ ... }` block at the end:
add the `"belegblick"` entry inside the existing `"mcpServers"` object, or create
`"mcpServers"` as a new top-level key if it is missing. Check the result with
`python3 -m json.tool claude_desktop_config.json` before restarting Claude Desktop.

### macOS (recommended): API key from the Keychain

Store the key once; the command prompts for it, so it never ends up in your shell history:

```bash
security add-generic-password -s belegblick -a lexware -w
```

Entry for `mcpServers` (adjust both paths; `which php` shows yours):

```json
"belegblick": {
  "command": "/bin/sh",
  "args": ["-c", "LEXWARE_API_KEY=$(security find-generic-password -s belegblick -w) exec /opt/homebrew/bin/php /absolute/path/to/belegblick-mcp/bin/belegblick-mcp"]
}
```

### Other systems: API key in the config

```json
"belegblick": {
  "command": "php",
  "args": ["/absolute/path/to/belegblick-mcp/bin/belegblick-mcp"],
  "env": {
    "LEXWARE_API_KEY": "your-api-key"
  }
}
```

The key is then stored in plain text in that file; keep it private.

Quit Claude Desktop completely, start it again and ask: *"Welche Rechnungen sind überfällig?"*
If the server does not show up, check the log
(macOS: `~/Library/Logs/Claude/mcp-server-belegblick.log`).

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

Debug the server interactively with the MCP Inspector. The Inspector starts the server with a
minimal environment, so pass the key with `-e`; a variable set in your shell does not reach it:

```bash
npx @modelcontextprotocol/inspector -e LEXWARE_API_KEY=your-api-key php bin/belegblick-mcp
```

Tests never touch the real API; they use Symfony's `MockHttpClient` and `MockClock`.

## License

MIT, see [LICENSE](LICENSE).
