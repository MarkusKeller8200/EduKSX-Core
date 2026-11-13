# Changelog – EduKSX-Core

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

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
