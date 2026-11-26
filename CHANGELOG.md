# Changelog – EduKSX-Core

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

### Neu

- **Anmeldung, Stufe 1 (#41):** nach KONZEPT-ANMELDUNG §3/AN5, Quelle Glossary: `Auth\Totp`, `Auth\RecoveryCodes`,
  `Auth\PasswordRules` (15/12 Zeichen, Liste bekannter Passwörter, externer Abgleich nur per Schalter),
  `Auth\LoginThrottle`, Middleware `SessionTimeout` (30 Min./8 Std./12 Std., Einstellungen `auth.*`),
  `LocalLogin::log()` ins Sicherheitsprotokoll; Texte de/en/fr/it.
- **Rechte und Rollen (#39):** `EduKSX\Core\Access\Abilities` nach KONZEPT-ROLLEN RO1/RO2: Rechte mit Scope-Aktion und
  Schutzklasse, Rollen mit Vererbung, Gates mit Begrenzung durch den Token-Scope (API C2), `roles` und `abilities` im
  Steckbrief, Befehl `eduksx:rollen-rechte` (Matrix `docs/ROLLEN-RECHTE.md`, `--check`), Konformitätstest
  `assertRolesConform()`.

## [0.3.0] – 2026-11-26

### Neu

- **Sicherheitsprotokoll (#36):** `EduKSX\Core\Security\SecurityLog` nach DB-Standard A7/DB-E7: Tabelle
  `security_events` (Migration `eduksx-security-migrations`), hash-verkettet mit eindeutigem `previous_hash`,
  maskierte Angaben, Kopie ins Protokoll, `verify()`, Prüfpunkt und Frist 1 Jahr (`eduksx:sicherheitsprotokoll`),
  Pseudonym `actor_ref` für Kontolöschungen; Datenklassen für `settings` und `security_events`.
- **Protokollierung Teil 2 (#35):** befristetes Debug je Organisation (`OrganizationDebug`, `eduksx:debug`, höchstens 60
  Minuten, Ablauf jede Minute, Ein/Aus/Ablauf im Sicherheitsprotokoll, Maskierung bleibt) und Protokollpaket für den
  Support (`LogPackage`, `eduksx:protokollpaket`: maskiert, mit Version, Steckbrief und /health, verschlüsselt mit
  libsodium, Löschung nach 30 Tagen).
- **Protokollierung (#33):** `UseEduksxFormat` (tap für Protokollkanäle: JSON mit Zeit in UTC, Modul, `request_id`,
  `trace_id`) und `MaskPersonalData` (Feldnamen und Muster: E-Mail, Telefon, AHV-Nummer, IBAN, Token); W3C `traceparent`
  übernehmen oder beginnen (`TraceContext`), `request_id` und `trace_id` im Laravel-Context (gehen in Aufträge mit),
  `TraceContext::headers()` für Dienst-zu-Dienst-Aufrufe, Trace aus S4-Ereignissen; Konformitätstest
  `assertLoggingMasksPersonalData()`. Schemas aus EduKSX-Architektur übernommen (S4 `traceparent`, Steckbrief `abilities`).
- **Einstellungen und Feature-Schalter (#31):** `EduKSX\Core\Settings\Settings` nach EduKSX-Architektur KONZEPT-STACK ST5,
  herausgelöst aus dem Glossary: Definitionen je Modul (Konfiguration oder `define()`), Vorrang Umgebung → Administration
  → Vorgabe mit Sperre und Herkunft (`source()`), nur Abweichungen in der Tabelle `settings` (Migration
  `eduksx-settings-migrations`), Geheimnisse verschlüsselt, jede Änderung in `data_changes`; `enabled()` für Schalter.

### Geändert

- **Stack (#29):** PHP 8.5, CI mit PostgreSQL 18 (EduKSX-Architektur KONZEPT-STACK ST2).
- **Projektstand (#27):** Verweis auf das umbenannte Repo EduKSX-Ident, Stand der neuen Bausteine.

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
