# EduKSX-Core – Projektstand

> Stand 13.11.2026 (17:45): Grundgerüst, HTTP-Basis und DB-Basis im Pull Request (#2, #3, #4); Tests mit SQLite und
> PostgreSQL grün. Als Nächstes Listen (Cursor, ETag, #5) und Prüfungen (Spectral, Konformitätstest, #6).

## Umgesetzt

| Bereich | Stand | Seit Version | Konzept |
|---|---|---|---|
| Grundgerüst Paket | im PR | 0.1.0 | EduKSX-Architektur `docs/KONZEPT-CORE.md` |
| HTTP-Basis (problem+json, Request-ID, Kopfzeilen, Abruflimit) | im PR | 0.1.0 | API-Standard A6, C5–C7 |
| DB-Basis (StrictSchema, lock_version) | im PR | 0.1.0 | DB-Standard A4, A6 |

## Offen bzw. geplant

- Meilenstein «0.1.0 – Grundbausteine»: #5 Listen (`_1 bereit`), #6 Prüfungen (`_1 bereit`)
- CORS-Voreinstellung (Teil von #3) folgt mit #5 für öffentliche Lese-APIs

## Technik

- Namensraum `EduKSX\Core\`, Quellcode in `src/`, Konfiguration `config/eduksx.php`, Tests in `tests/` (Testbench).
- Prüfen: `composer ci:check`; PostgreSQL: `DB_CONNECTION=pgsql composer test`. Workflow `tests` (PHP 8.4 × SQLite/PostgreSQL).
