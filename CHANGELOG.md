# Changelog – EduKSX-Core

Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/), Versionen nach
[Semantic Versioning](https://semver.org/lang/de/) (MAJOR.MINOR.PATCH). Neue Einträge kommen laufend unter
**Unveröffentlicht**; `python3 .github/blueprint/release.py set X.Y.Z --commit` macht daraus eine Version.

## [Unveröffentlicht]

### Neu

- **Grundgerüst (#2):** Paket `eduksx/core` (MIT, PHP 8.4, Laravel 13), Service-Provider, Konfiguration `eduksx`, Modulschlüssel mit Prüfung, Tests mit SQLite und PostgreSQL.
- **HTTP-Basis (#3):** Fehler als `application/problem+json` mit `code`, `request_id` und Modulschlüssel im Typ; `X-Request-Id`; Sicherheitskopfzeilen; Abruflimit `api` je Token bzw. Adresse.
- **DB-Basis (#4):** `StrictSchema` (STRICT, CHECK, Fremdschlüssel) und optimistisches Sperren mit `lock_version`, aus dem Glossary übernommen.
