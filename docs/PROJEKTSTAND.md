# EduKSX-Core – Projektstand

> Stand 13.11.2026 (20:30): Grundgerüst, HTTP-Basis, DB-Basis gemergt (#2–#4, Beta-Test); Listen (#5) im PR.
> Als Nächstes Prüfungen (Spectral, Konformitätstest, #6), dann Release 0.1.0 nach Freigabe.

## Umgesetzt

| Bereich | Stand | Seit Version | Konzept |
|---|---|---|---|
| Grundgerüst Paket | im Beta-Test | 0.1.0 | EduKSX-Architektur `docs/KONZEPT-CORE.md` |
| HTTP-Basis (problem+json, Request-ID, Kopfzeilen, Abruflimit) | im Beta-Test | 0.1.0 | API-Standard A6, C5–C7 |
| DB-Basis (StrictSchema, lock_version) | im Beta-Test | 0.1.0 | DB-Standard A4, A6 |
| Listen (Cursor, ETag, CORS) | im PR | 0.1.0 | API-Standard A5, B2, B5, C6 |

## Offen bzw. geplant

- Meilenstein «0.1.0 – Grundbausteine»: #6 Prüfungen (`_1 bereit`); #2–#4 im Beta-Test, #5 im PR

## Technik

- Namensraum `EduKSX\Core\`, Quellcode in `src/`, Konfiguration `config/eduksx.php`, Tests in `tests/` (Testbench).
- Prüfen: `composer ci:check`; PostgreSQL: `DB_CONNECTION=pgsql composer test`. Workflow `tests` (PHP 8.4 × SQLite/PostgreSQL).
