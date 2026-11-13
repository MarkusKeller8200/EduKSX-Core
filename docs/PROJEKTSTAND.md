# EduKSX-Core – Projektstand

> Stand 14.11.2026: Core 0.2 (#13–#16) umgesetzt, PR offen; Release 0.2.0 nach Freigabe. Danach Glossary #189/#187
> und Ident 0.2 (EdukSX-Ident #8–#13) auf Core 0.2; Core 0.3 (OIDC, Token-Prüfung, Standardtabellen) nach PoC #36.

## Umgesetzt

| Bereich | Stand | Seit Version | Konzept |
|---|---|---|---|
| Grundgerüst Paket | im Beta-Test | 0.1.0 | EduKSX-Architektur `docs/KONZEPT-CORE.md` |
| HTTP-Basis (problem+json, Request-ID, Kopfzeilen, Abruflimit) | im Beta-Test | 0.1.0 | API-Standard A6, C5–C7 |
| DB-Basis (StrictSchema, lock_version) | im Beta-Test | 0.1.0 | DB-Standard A4, A6 |
| Listen (Cursor, ETag, CORS) | im Beta-Test | 0.1.0 | API-Standard A5, B2, B5, C6 |
| Prüfungen (Spectral-Regelsatz, Konformitätstest) | im Beta-Test | 0.1.0 | API-Standard D3, D8; DB E5 |
| Steckbrief und /health (#13) | umgesetzt, PR offen | 0.2.0 | Plattform S5 |
| Empfang S4-Ereignisse, Signer, CloudEvent (#14) | umgesetzt, PR offen | 0.2.0 | Plattform S4, SP6 |
| Historie data_changes mit Datenklassen (#15) | umgesetzt, PR offen | 0.2.0 | DB-Standard A7, B2 |
| Plattform-Schemas aus spec/ (#16) | umgesetzt, PR offen | 0.2.0 | Core C11 |

## Offen bzw. geplant

- 0.1.0 released (#2–#6 geschlossen); 0.1.1 mit #11 (`_3 Beta-Test`)
- Lizenz der Schemas: Architektur-Repo ist GPL-3.0, Core MIT – `spec/` sollte (zusätzlich) unter MIT stehen (offen, Projektverantwortliche:r)
- Core 0.3 (nach PoC #36): OIDC-Client und Abmelden, JWT-/Scope-Prüfung, Standardtabellen (C7); Ausnahmeregister im Konformitätstest

## Technik

- Namensraum `EduKSX\Core\`, Quellcode in `src/`, Konfiguration `config/eduksx.php`, Tests in `tests/` (Testbench).
- Prüfen: `composer ci:check` (inkl. `php bin/spec.php check`); PostgreSQL: `DB_CONNECTION=pgsql composer test`. Workflow `tests` (PHP 8.4 × SQLite/PostgreSQL).
