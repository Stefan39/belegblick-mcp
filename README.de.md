# lexware-php-mcp

[English](README.md) | [Deutsch](README.de.md)

[![CI](https://github.com/Stefan39/lexware-php-mcp/actions/workflows/ci.yml/badge.svg)](https://github.com/Stefan39/lexware-php-mcp/actions/workflows/ci.yml)
[![Lizenz: MIT](https://img.shields.io/badge/lizenz-MIT-blue.svg)](LICENSE)
![PHP 8.4+](https://img.shields.io/badge/php-8.4%2B-777bb4.svg)

> **Inoffiziell.** Keine Verbindung zur Haufe Group. Lexware und lexoffice sind Marken der Haufe Group.

lexware-php-mcp ist ein inoffizieller, rein lesender [Model-Context-Protocol](https://modelcontextprotocol.io)-Server
für [Lexware Office](https://www.lexware.de/) (früher lexoffice). Er ist in PHP geschrieben und baut auf dem
offiziellen [PHP-MCP-SDK](https://github.com/modelcontextprotocol/php-sdk) auf.

Fragen Sie Claude zum Beispiel *„Welche Rechnungen sind überfällig?“* und erhalten Sie die Antwort direkt aus Ihrem Konto.

## Warum ein PHP-Server?

Die vorhandenen Lexware-MCP-Server sind in TypeScript oder Python geschrieben. Dieser ist die PHP-native
Variante. Das heißt nicht, dass PHP technisch besser wäre. Es geht darum, dass PHP-Betriebe keine zweite
Laufzeitumgebung brauchen sollen, um einem KI-Assistenten Zugriff auf ihre Buchhaltung zu geben.

- **Eine Laufzeit, die Sie ohnehin betreiben.** PHP 8.4 und Composer, mehr nicht. Keine Node.js- oder
  Python-Toolchain, kein `npx`, das beim Start Code nachlädt.
- **Klein genug zum Prüfen.** Der Server hat drei Laufzeit-Abhängigkeiten (`mcp/sdk`, `symfony/http-client`,
  `symfony/clock`) und nur wenige Klassen. Wer einer KI Zugriff auf Buchhaltungsdaten gibt, kann den gesamten
  Code an einem Nachmittag lesen. Geprüft wird er mit PHPStan Level 8 samt Strict Rules.
- **Passend zum deutschen Mittelstands-Stack.** Viele Agenturen, Freiberufler und interne IT-Abteilungen rund um
  Lexware arbeiten mit PHP (Symfony, Laravel, WordPress, Shopware). Die Werkzeuge sind einfache Klassen und
  lassen sich später über `symfony/mcp-bundle` in eine Symfony-Anwendung übernehmen
  (siehe [ADR 0001](docs/adr/0001-architecture.md), englisch).
- **Lexware-Fallstricke sind abgefangen.** In Lexware geschriebene Rechnungen und gebuchte Verkaufsbelege sind
  zwei verschiedene Belegarten; der Server fragt beide ab. Das Limit von 2 Anfragen pro Sekunde wird
  eingehalten, und Fehlermeldungen von Lexware werden durchgereicht, damit ein Fehler erklärbar bleibt.

## Beispielfragen

- *Welche Rechnungen sind überfällig, und um wie viele Tage?*
- *Zeige die offenen Rechnungen von Kunde Müller mit Summen je Währung.*

## Status

v0.2.0, früh, aber nutzbar. Siehe das [Changelog](CHANGELOG.md) (englisch). Aktuell verfügbares Werkzeug:

| Werkzeug | Was es tut |
|---|---|
| `open_items` | Unbezahlte Ausgangsrechnungen (offen und überfällig), älteste Fälligkeit zuerst, mit Tagen überfällig und Summen je Währung. Optionale Filter: `overdueOnly`, `contact`. Beträge sind brutto. |

Geplant: Belegsuche, Beleg-Detail mit Nettobeträgen, Kontaktsuche, Umsatz je Zeitraum.
Siehe [ADR 0001](docs/adr/0001-architecture.md).

## Sicherheit

- **Nur lesend.** Der Server sendet ausschließlich `GET`-Anfragen; es gibt keine Werkzeuge, die Daten anlegen oder ändern.
- Der API-Schlüssel wird nur aus der Umgebung gelesen und erscheint nie in Ausgaben oder Fehlermeldungen.
- Bankdaten (IBAN, BIC, Kontodaten) werden aus jeder Antwort entfernt, bevor sie das Modell erreicht.
- Anfragen werden auf das Lexware-Limit von 2 pro Sekunde gedrosselt, mit Wiederholung und Backoff bei 429/5xx.
- Was ein Werkzeug zurückgibt, geht an den Modellanbieter Ihres MCP-Clients (z. B. Anthropic bei
  Claude Desktop). Nutzen Sie den Server nur mit Konten, deren Daten Sie an diesen Anbieter weitergeben dürfen.

Sicherheitsproblem entdeckt? Bitte melden Sie es vertraulich, siehe [SECURITY.md](SECURITY.md).

Für die Nutzung der Lexware-API gelten die
[Public API Lizenz- und Nutzungsbedingungen](https://agb.lexware.de/lexware-office/public-api-lizenz--und-nutzungsbedingungen).

## Voraussetzungen

- PHP 8.4 oder neuer (CLI) mit `ext-json`
- Composer
- Ein öffentlicher API-Schlüssel für Lexware Office: <https://app.lexware.de/addons/public-api>

## Installation

```bash
git clone --branch v0.2.0 https://github.com/Stefan39/lexware-php-mcp.git
cd lexware-php-mcp
composer install --no-dev
```

Aktualisieren später: `git fetch --tags && git checkout <neuer-tag> && composer install --no-dev`.

## Nutzung mit Claude Desktop

Claude Desktop startet den Server selbst. Sie tragen **einen Eintrag** unter `mcpServers` in die
`claude_desktop_config.json` ein:

- macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`
- Windows: `%APPDATA%\Claude\claude_desktop_config.json`

Die Datei enthält meist schon andere Einstellungen. Hängen Sie keinen zweiten `{ ... }`-Block ans Ende an:
Tragen Sie den Eintrag `"lexware-php-mcp"` in das vorhandene Objekt `"mcpServers"` ein, oder legen Sie
`"mcpServers"` als neuen Schlüssel auf oberster Ebene an, falls er fehlt. Prüfen Sie das Ergebnis mit
`python3 -m json.tool claude_desktop_config.json`, bevor Sie Claude Desktop neu starten.

### macOS (empfohlen): API-Schlüssel aus dem Schlüsselbund

Speichern Sie den Schlüssel einmal; der Befehl fragt ihn ab, er landet also nicht in Ihrer Shell-Historie:

```bash
security add-generic-password -s lexware-php-mcp -a lexware -w
```

Eintrag für `mcpServers` (beide Pfade anpassen; `which php` zeigt Ihren):

```json
"lexware-php-mcp": {
  "command": "/bin/sh",
  "args": ["-c", "LEXWARE_API_KEY=$(security find-generic-password -s lexware-php-mcp -w) exec /opt/homebrew/bin/php /absoluter/pfad/zu/lexware-php-mcp/bin/lexware-php-mcp"]
}
```

### Andere Systeme: API-Schlüssel in der Konfiguration

```json
"lexware-php-mcp": {
  "command": "php",
  "args": ["/absoluter/pfad/zu/lexware-php-mcp/bin/lexware-php-mcp"],
  "env": {
    "LEXWARE_API_KEY": "ihr-api-schluessel"
  }
}
```

Der Schlüssel steht dann im Klartext in dieser Datei; halten Sie sie privat.

Beenden Sie Claude Desktop vollständig, starten Sie es neu und fragen Sie: *„Welche Rechnungen sind überfällig?“*
Erscheint der Server nicht, hilft das Log
(macOS: `~/Library/Logs/Claude/mcp-server-lexware-php-mcp.log`).

## Nutzung mit Claude Code

Registrieren Sie den Server einmal; Claude Code startet ihn bei Bedarf:

```bash
claude mcp add --env LEXWARE_API_KEY=ihr-api-schluessel lexware-php-mcp -- php /absoluter/pfad/zu/lexware-php-mcp/bin/lexware-php-mcp
```

Standardmäßig gilt der Eintrag nur für das aktuelle Projekt. Mit `--scope user` ist er überall verfügbar.
Prüfen Sie mit `claude mcp list` oder mit `/mcp` in einer Sitzung. Der Schlüssel liegt im Klartext in der
Konfiguration von Claude Code; halten Sie sie privat.

## Konfiguration

| Variable | Pflicht | Standard |
|---|---|---|
| `LEXWARE_API_KEY` | ja | keiner |
| `LEXWARE_API_BASE_URL` | nein | `https://api.lexware.io` |

## Entwicklung

```bash
composer install
composer check   # php-cs-fixer (Dry-Run), PHPStan Level 8, PHPUnit
make cpd         # Duplikatprüfung (braucht Node/npx)
```

Den Server interaktiv debuggen Sie mit dem MCP Inspector. Der Inspector startet den Server mit einer
minimalen Umgebung, übergeben Sie den Schlüssel daher mit `-e`; eine in Ihrer Shell gesetzte Variable kommt dort nicht an:

```bash
npx @modelcontextprotocol/inspector -e LEXWARE_API_KEY=ihr-api-schluessel php bin/lexware-php-mcp
```

Die Tests greifen nie auf die echte API zu; sie nutzen `MockHttpClient` und `MockClock` von Symfony.

## Lizenz

MIT, siehe [LICENSE](LICENSE).
