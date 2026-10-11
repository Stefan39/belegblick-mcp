# CLAUDE.md: lexware-php-mcp

lexware-php-mcp: unofficial read-only MCP server for Lexware Office in PHP (`mcp/sdk`). Architecture: `docs/adr/0001-architecture.md`.

## Gates (run before handing back any change)
- `composer check` = php-cs-fixer dry run + PHPStan level 8 (strict rules) + PHPUnit. All green.
- `make cpd` (jscpd, threshold 1 %).
- CI (`.github/workflows/ci.yml`) runs the same on PHP 8.4 and 8.5 plus `composer validate --strict` and `composer audit`.

## Rules
- Read-only: `LexwareClient` only does GET. No write tools without a new ADR.
- Every API payload goes through `SensitiveDataFilter` before it is returned to the model.
- API key only via env `LEXWARE_API_KEY`; never log or echo it.
- STDOUT is the MCP protocol channel; diagnostics to STDERR only.
- Domain exceptions extend `\RuntimeException`; never throw/catch `\RuntimeException`/`\Exception` directly. Tools convert `LexwareApiException` into `Mcp\Exception\ToolCallException`.
- Tests: final classes, no network (`MockHttpClient`, `MockClock`), fixtures in `tests/Fixtures/`.
- Register tools in `ServerFactory` with `[$object, '__invoke']` (SDK does not accept invokable objects).
- Naming: product/package/binary/server name is `lexware-php-mcp` (package `jacomeit/lexware-php-mcp`, namespace `Jacomeit\LexwarePhpMcp`). Always keep the disclaimer "Unofficial, not affiliated with Haufe Group" in README and package description; never use Lexware/Haufe logos (ADR 0001, decision 6).
- `mcp/sdk` is pre-1.0: review every update manually.
