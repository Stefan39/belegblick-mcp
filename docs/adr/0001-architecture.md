# ADR 0001: Architecture of Belegblick, an MCP server for Lexware Office

- Status: accepted
- Date: 2026-10-10
- Ticket: JAC-688 (input from JAC-684)

## Context

We want an unofficial MCP server that lets Claude (and other MCP clients) answer questions such as
"Which invoices are overdue?" or "Net revenue in Q3?" from a Lexware Office (formerly lexoffice) account.
Existing servers are written in TypeScript or Python (e.g. JannikWempe, marselsel). There is no
PHP-native one; this project fills that gap and is meant to be published as open source (MIT).

## Decisions

### 1. Official PHP MCP SDK (`mcp/sdk` ^0.8.1), no framework kernel

- `mcp/sdk` is the official SDK (PHP Foundation + Symfony). 0.8.1 is current; versions
  0.5.0 to <0.7.1 are affected by advisory PKSA-p9gd-j6gr-6f9t, so the floor is 0.8.1.
- The SDK is experimental until 1.0. Renovate is configured to never automerge it; every update
  is reviewed by hand.
- The server is a plain PHP CLI (`bin/belegblick-mcp`) wired by `ServerFactory`. A Symfony
  kernel would add boot time to every Claude Desktop start and buys nothing for a handful of tools.
  `symfony/mcp-bundle` stays an option once HTTP transport or multi-tenant hosting is needed.
- Tools are registered explicitly via `Builder::addTool([$object, '__invoke'], ...)` instead of
  attribute discovery, so dependencies are constructor-injected without a container. The SDK does
  not accept invokable objects directly, hence the `[object, method]` pair.

### 2. Transport: stdio first

Claude Desktop starts local MCP servers as child processes over stdio. That is the v0.1 target.
Streamable HTTP (SDK supports it) is deferred until there is a hosting story (auth, multi-user).
STDOUT is reserved for the protocol; diagnostics go to STDERR.

### 3. Own thin API client instead of an existing library

| Candidate | Verdict |
|---|---|
| `stefan39/lexoffice-api-bundle` (own, 2022) | Bundle skeleton for Symfony 5.4 with no API code. Not usable. |
| `baebeca/lexware-php-api` | Dual-licensed AGPLv3 / commercial. Linking it would force this project under AGPL. Rejected. |
| Own client on `symfony/http-client` | Chosen. Small surface (read-only GETs), full control over throttling, retry and output filtering. |

`LexwareClient` only issues `GET` requests. It honours the documented limit of **2 requests per
second** through `RequestThrottle` (min. 500 ms spacing) and retries 429, 5xx and transport errors
up to 3 attempts with exponential backoff (1 s, 2 s), respecting a numeric `Retry-After` (capped at 30 s).
401/403/404 fail immediately with an actionable message. Base URL: `https://api.lexware.io`
(overridable via `LEXWARE_API_BASE_URL`).

### 4. Security

- The API key is read **only** from the environment (`LEXWARE_API_KEY`). The server refuses to start
  without it. `Configuration` marks it `#[\SensitiveParameter]` and masks it in `__debugInfo()`.
  Error messages never contain the key.
- **No write tools in v0.1.** The HTTP client exposes `GET` only. All tools carry
  `readOnlyHint: true, destructiveHint: false`.
- Every API payload passes `SensitiveDataFilter`, which drops bank details (IBAN, BIC, account
  holder/number, bank code/name, SEPA mandate, creditor ID) at any depth before it reaches the model.

### 5. Known Lexware pitfalls (from operating "Frida") and how they are handled

| Pitfall | Handling |
|---|---|
| Sales invoices have voucher type `salesinvoice` (not `invoice`) in the voucher list | `open_items` queries `voucherType=salesinvoice`. |
| The voucher list only carries **gross** amounts; net amounts and tax breakdown exist only in the voucher detail | `open_items` labels every amount as gross (`note` field). Net figures are the job of `voucher_detail` / `revenue`. |
| Detail endpoints differ per voucher type (`/v1/invoices/{id}`, `/v1/credit-notes/{id}`, `/v1/down-payment-invoices/{id}`, `/v1/vouchers/{id}` for bookkeeping vouchers ...) | Planned `voucher_detail` maps `voucherType` to endpoint in one place. |
| Rate limit 2 req/s, 429 on excess | Throttle + retry (see 3). Pagination with `size=250` (maximum) keeps request counts low; `open_items` stops after 10 pages and reports `complete: false`. |
| Timestamps come with local offset (`+02:00`) | Only the calendar date is used; overdue days are computed date-to-date. |

## Tool cut v0.1 (read-only)

| Tool | Endpoint(s) | Status |
|---|---|---|
| `open_items`: unpaid sales invoices, overdue flag, days overdue, totals per currency, filters `overdueOnly`, `contact` | `GET /v1/voucherlist?voucherType=salesinvoice&voucherStatus=open,overdue` | **implemented** |
| `search_vouchers`: by type, status, date range, contact, number | `GET /v1/voucherlist` | planned |
| `voucher_detail`: line items, net/tax/gross | type-specific detail endpoints | planned |
| `search_contacts`: by name, email, number (no bank data) | `GET /v1/contacts` | planned |
| `revenue`: net revenue per period (invoices minus credit notes) | voucher list + detail per voucher (N requests; throttled, cached per run) | planned |

`revenue` is the expensive one: net amounts need one detail request per voucher, i.e. about
2 vouchers/second. A quarter with 150 invoices takes ~75 s. This needs progress notifications or a
local cache; decided when the tool is built.

### 6. Name: Belegblick

The licence terms forbid using Lexware/Haufe trademarks without consent (II.2), so the product name
carries no mark: **Belegblick** (package `jacomeit/belegblick-mcp`, binary `bin/belegblick-mcp`,
namespace `Jacomeit\Belegblick`), decided 2026-10-10. "Beleg" is Lexware's own umbrella term for
invoices, credit notes and receipts; "Blick" stresses read-only access. "Lexware Office" appears only
descriptively ("an unofficial MCP server for Lexware Office") next to the disclaimer. API-facing
identifiers (`LexwareClient`, `LEXWARE_API_KEY`) name the API they talk to and stay. At decision time
the name was free on Packagist, GitHub and npm.

## Consequences

- No framework, few dependencies, fast start in Claude Desktop.
- We own the client code, including keeping up with API changes.
- SDK updates before 1.0 can break us; CI plus manual Renovate review mitigate this.

## Lexware API license terms

Source: "Public API Lizenz- und Nutzungsbedingungen", Stand August 2026,
<https://agb.lexware.de/lexware-office/public-api-lizenz--und-nutzungsbedingungen>
(the older link developers.lexware.io/license-terms returns 404). Relevant points, as read on 2026-10-10:

- Licence to use the API is non-exclusive, non-transferable and revocable; building own "API Applications"
  is explicitly allowed (I). No registration or approval step for applications is mentioned.
- The API itself must not be passed on, sublicensed or sold (I.1). This project does not do that: every
  user connects their own account with their own key.
- Applications must state their purpose, functions and access permissions clearly (II.1): covered by the
  README sections "Status" and "Safety" and the server instructions.
- Applications must not request other licensees' API keys or collect customer data outside the API (II.1).
- **Trademarks:** names, trademarks, logos of the licensor / Haufe Group must not be used without prior
  consent (II.2). Resolved by naming the project **Belegblick** (decision 6).
- Rate limiting may be imposed without a fixed number (II.1); we keep the documented 2 requests/second.
- API provided as is, use at own risk, licensee indemnifies the licensor (II, V); termination with 90 days'
  notice (V.2). German law, venue Freiburg (VI).
- AI/LLM services are not mentioned. Data reaches whichever model provider the user connects; the README says so.

This is an engineering reading, not legal advice.

## Open before the first public release

- Trademark search for "Belegblick" (DPMA/EUIPO) and domain availability; not done yet.
- README disclaimer: "Unofficial. Not affiliated with Haufe Group. Lexware and lexoffice are
  trademarks of Haufe Group." (in place).
- Packagist name and distribution (Composer global install vs. PHAR).
