# Changelog – EduKSX-Core

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

## [0.2.1] – 2026-11-14

### Geändert

- **Lizenz der Schemas:** `resources/spec/` steht unter `GPL-3.0-or-later OR MIT` (EduKSX-Architektur `spec/LICENSE`, PR #56); damit sauber in Core (MIT) ausgeliefert.

### Behoben

- **Fehlertitel übersetzt (#24):** `title` in `problem+json` kommt in der Sprache der Anfrage (de, fr, it, en; Rückfall Englisch), `code` bleibt englisch (API A6). Module ergänzen eigene Codes unter `lang/vendor/eduksx/<sprache>/problems.php`.
- **Konformitätstest (#22):** Routen von Core selbst (`eduksx.*`, z. B. `POST /api/v1/platform-events`) zählen beim Abgleich mit der OpenAPI des Moduls nicht mit; sie beschreibt `spec/`.

## [0.2.0] – 2026-11-14

### Neu

- **Steckbrief und /health (#13):** `GET /.well-known/eduksx-module.json` aus `config/eduksx.php` (`manifest`, `version`) und `GET /health` mit den Prüfungen database, migrations, queue, storage; eigene Prüfungen über `Health::register()`; Antworten nach `spec/v1` (S5).
- **Empfang von S4-Ereignissen (#14):** `POST /api/v1/platform-events` mit Signaturprüfung `EduKSX-Signature` (300 s, konstante Zeit, Schlüsselwechsel), Prüfung gegen `event.schema.json`, Idempotenz über die Ereignis-ID (Tabelle `received_events`, tägliches Aufräumen) und Laravel-Events `eduksx:<art>`. `Signer` und `CloudEvent` auch für Absender (Ident); Test-Absender `SendsPlatformEvents`.
- **Historie data_changes (#15):** `RecordsDataChanges`, `DataChanges`, `DataClasses` und `DataClass` aus dem Glossary herausgelöst; handelnde Person und Quelle durch das Modul bestimmbar; Migration zum Veröffentlichen.
- **Plattform-Schemas (#16):** `spec/v1` aus EduKSX-Architektur v0.3.0 unverändert unter `resources/spec/` mit Herkunft und Prüfsummen; `Spec\Schemas` prüft dagegen (neue Abhängigkeit `opis/json-schema`, Apache-2.0); Werkzeug `bin/spec.php`.
- **Konformitätstest:** `assertPlatformEndpointsConform()` (Steckbrief und /health gegen die Schemas) und `assertDataClassesComplete()` (DB B2).

### Geändert

- **Modulschlüssel:** höchstens 16 Zeichen (vorher 20), damit jeder gültige Schlüssel auch dem Muster in `spec/v1` entspricht.
- **`ProblemException`** nimmt Feldfehler (`errors`) entgegen.

## [0.1.1] – 2026-11-13

### Behoben

- **OpenAPI mit servers-Präfix (#11):** Spectral-Regel und Konformitätstest werten Pfade relativ zur ersten `servers`-URL (z. B. `…/api` + `/v1/terms`).
- **Fremdschlüssel über eindeutigen Index (#11):** Der Konformitätstest erkennt Fremdschlüssel als indiziert, wenn ein eindeutiger Index (auch der Primärschlüssel) ihre ersten Spalten abdeckt.

## [0.1.0] – 2026-11-13

### Neu

- **Grundgerüst (#2):** Paket `eduksx/core` (MIT, PHP 8.4, Laravel 13), Service-Provider, Konfiguration `eduksx`, Modulschlüssel mit Prüfung, Tests mit SQLite und PostgreSQL.
- **HTTP-Basis (#3):** Fehler als `application/problem+json` mit `code`, `request_id` und Modulschlüssel im Typ; `X-Request-Id`; Sicherheitskopfzeilen; Abruflimit `api` je Token bzw. Adresse.
- **DB-Basis (#4):** `StrictSchema` (STRICT, CHECK, Fremdschlüssel) und optimistisches Sperren mit `lock_version`, aus dem Glossary übernommen.
- **Listen (#5):** Cursor-Pagination mit `limit`, `sort` und Filterprüfung im Cursor; Objekt-ETag mit `If-Match` (428/412) und Inhalts-ETag mit 304; CORS-Voreinstellung für öffentliches Lesen. Gemeinsame Fehlercodes über `ProblemException::common()`.
- **Prüfungen (#6):** Spectral-Regelsatz `eduksx` (OpenAPI 3.1, Modulschlüssel, Pfade, `operationId`, `security`, Beispiele, snake_case, Problem-Antworten; vorerst Warnungen), gemeinsame OpenAPI-Schemas und Konformitätstest `AssertsConformance` (Namen, STRICT, Index auf Fremdschlüsseln, Fehlerformat, Request-ID, Sicherheitskopfzeilen, Routen ↔ OpenAPI).
