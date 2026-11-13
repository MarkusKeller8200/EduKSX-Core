# EduKSX-Core – Projektstand

> Stand 13.11.2026 (20:20): Alle Bausteine von 0.1.0 gemergt (#2–#6 im Beta-Test); Release-PR 0.1.0 bereit.
> Als Nächstes Release 0.1.0 nach Freigabe, dann Umstellung des Glossary (Glossary #193).

## Umgesetzt

| Bereich | Stand | Seit Version | Konzept |
|---|---|---|---|
| Grundgerüst Paket | im Beta-Test | 0.1.0 | EduKSX-Architektur `docs/KONZEPT-CORE.md` |
| HTTP-Basis (problem+json, Request-ID, Kopfzeilen, Abruflimit) | im Beta-Test | 0.1.0 | API-Standard A6, C5–C7 |
| DB-Basis (StrictSchema, lock_version) | im Beta-Test | 0.1.0 | DB-Standard A4, A6 |
| Listen (Cursor, ETag, CORS) | im Beta-Test | 0.1.0 | API-Standard A5, B2, B5, C6 |
| Prüfungen (Spectral-Regelsatz, Konformitätstest) | im Beta-Test | 0.1.0 | API-Standard D3, D8; DB E5 |

## Offen bzw. geplant

- Meilenstein «0.1.0 – Grundbausteine»: #2–#6 im Beta-Test; Release-PR 0.1.0 wartet auf abgehakte Testlisten und Freigabe
- Core 0.2 (mit Ident): OIDC/Token-Prüfung, Standardtabellen, Historie mit Datenklassen; Ausnahmeregister im Konformitätstest

## Technik

- Namensraum `EduKSX\Core\`, Quellcode in `src/`, Konfiguration `config/eduksx.php`, Tests in `tests/` (Testbench).
- Prüfen: `composer ci:check`; PostgreSQL: `DB_CONNECTION=pgsql composer test`. Workflow `tests` (PHP 8.4 × SQLite/PostgreSQL).
