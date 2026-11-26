# EduKSX-Core – Projektstand

> Stand 26.11.2026: Release 0.3.0 – Stack PHP 8.5 (#29) und die Bausteine Einstellungen (#31), Protokollierung
> (#33, #35) und Sicherheitsprotokoll (#36). Release 0.4.0: Rechte und Rollen (#39), Anmeldung Stufe 1 (#41), Text mit
> Muster in den Einstellungen (#44). Als Nächstes aus #28: ci:check nach KONZEPT-QUALITAET, DB 1.4/1.5, eduksx:module-init;
> Core 0.3 (OIDC, Token-Prüfung, Standardtabellen) nach dem Zitadel-PoC (EduKSX-Architektur #36). Module:
> [EduKSX-Glossary](https://github.com/MarkusKeller8200/EduKSX-Glossary) und
> [EduKSX-Ident](https://github.com/MarkusKeller8200/EduKSX-Ident) (Ident #8–#13).

## Umgesetzt

| Bereich | Stand | Seit Version | Konzept |
|---|---|---|---|
| Grundgerüst Paket | im Beta-Test | 0.1.0 | EduKSX-Architektur `docs/KONZEPT-CORE.md` |
| HTTP-Basis (problem+json, Request-ID, Kopfzeilen, Abruflimit) | im Beta-Test | 0.1.0 | API-Standard A6, C5–C7 |
| DB-Basis (StrictSchema, lock_version) | im Beta-Test | 0.1.0 | DB-Standard A4, A6 |
| Listen (Cursor, ETag, CORS) | im Beta-Test | 0.1.0 | API-Standard A5, B2, B5, C6 |
| Prüfungen (Spectral-Regelsatz, Konformitätstest) | im Beta-Test | 0.1.0 | API-Standard D3, D8; DB E5 |
| Steckbrief und /health (#13) | veröffentlicht | 0.2.0 | Plattform S5 |
| Empfang S4-Ereignisse, Signer, CloudEvent (#14) | veröffentlicht | 0.2.0 | Plattform S4, SP6 |
| Historie data_changes mit Datenklassen (#15) | veröffentlicht | 0.2.0 | DB-Standard A7, B2 |
| Plattform-Schemas aus spec/ (#16) | veröffentlicht | 0.2.0 | Core C11 |
| Einstellungen und Feature-Schalter (#31) | im Beta-Test | 0.3.0 | KONZEPT-STACK ST5 |
| Protokollierung: JSON, Maskierung, traceparent (#33) | im Beta-Test | 0.3.0 | KONZEPT-PROTOKOLLIERUNG §6, §7 |
| Befristetes Debug je Organisation, Protokollpaket (#35) | im Beta-Test | 0.3.0 | KONZEPT-PROTOKOLLIERUNG §5, §9 |
| Sicherheitsprotokoll, hash-verkettet (#36) | im Beta-Test | 0.3.0 | DB-Standard A7, DB-E7 |
| Rechte und Rollen, Matrix eduksx:rollen-rechte (#39) | im Beta-Test | 0.4.0 | KONZEPT-ROLLEN RO1, RO2, §8 |
| Anmeldung Stufe 1: TOTP, Codes, Passwortregeln, Versuche, Sitzung (#41) | im Beta-Test | 0.4.0 | KONZEPT-ANMELDUNG §3, AN5 |

## Offen bzw. geplant

- 0.1.0, 0.1.1 und 0.2.0 veröffentlicht; Meilenstein 0.2.0 geschlossen
- Lizenz der Schemas geklärt: `spec/` steht zusätzlich unter MIT (Architektur PR #56)
- Core 0.3 (nach PoC #36): OIDC-Client und Abmelden, JWT-/Scope-Prüfung, Standardtabellen (C7); Ausnahmeregister im Konformitätstest

## Technik

- Namensraum `EduKSX\Core\`, Quellcode in `src/`, Konfiguration `config/eduksx.php`, Tests in `tests/` (Testbench).
- Prüfen: `composer ci:check` (inkl. `php bin/spec.php check`); PostgreSQL: `DB_CONNECTION=pgsql composer test`. Workflow `tests` (PHP 8.5 × SQLite/PostgreSQL 18).
