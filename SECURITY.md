# Security Policy

## Supported versions

lexware-php-mcp is in early development. Only the latest release (or `main` before the first release)
receives security fixes.

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report them privately via GitHub:
**Security → Report a vulnerability** in this repository
(<https://github.com/Stefan39/lexware-php-mcp/security/advisories/new>).

Please include:

- what is affected (version or commit, PHP version, MCP client),
- steps to reproduce,
- the impact you see (for example: data leaving the read-only boundary, bank details in output,
  API key exposure).

You will get a first answer within 7 days. Once a fix is available, the advisory is published
together with the release that contains it.

## Never share your API key

Your Lexware Office API key gives access to your accounting data.

- Never paste it into issues, pull requests, discussions, logs or screenshots, not even partially.
- If you posted it by accident, revoke it immediately at <https://app.lexware.de/addons/public-api>
  and create a new one. Deleting the comment is not enough.
- When you share logs or MCP traffic for a bug report, remove the key and any customer data first.

## Scope

In scope: the code in this repository, in particular the read-only guarantee (only `GET` requests),
the bank-data filter, and handling of the API key.

Out of scope: the Lexware Office API itself (report to Haufe Group / Lexware) and the MCP clients
(Claude Desktop, etc.).
