# Übergabe an einen neuen Chat – EduKSX-Core

Stand: **13.11.2026 (17:45)** – noch kein Release; Meilenstein «0.1.0 – Grundbausteine» in Arbeit.
[PROJEKTSTAND.md](PROJEKTSTAND.md) (aktuell), [PROJEKTSTAND-ARCHIV.md](PROJEKTSTAND-ARCHIV.md) (Verlauf). Konzept liegt im
Repo EduKSX-Architektur (`docs/KONZEPT-CORE.md`). Bei jeder Übergabe nachführen.

## Start nächster Chat (Übergabe 13.11.2026, 17:45)

**Zuerst lesen:** CLAUDE.md → AGENTS.md → dieser Abschnitt → PROJEKTSTAND.md → KONZEPT-CORE.md (Architektur-Repo).

**Stand Code und CI**
- PR #7 (Grundgerüst, #2–#4) gemergt; Listen #5 gemergt (PR #8); Prüfungen #6 im PR (Branch `pruefungen-konformitaet`). Workflow `tests` ist Pflichtprüfung und Voraussetzung für Releases.
- Prüfen: `composer ci:check` (40 Tests); PostgreSQL wie oben.
- Repository-Einstellungen und Rulesets liessen sich aus der Cloud-Sitzung nicht setzen: lokal
  `python3 ../my-github-blueprint/.github/blueprint/apply.py --ziel . --nur-github` ausführen.

**Offene Issues und ihr Status**

| Issue | Meilenstein | Status | Inhalt |
|---|---|---|---|
| #2, #3, #4 | 0.1.0 – Grundbausteine | `_3 Beta-Test` | Grundgerüst, HTTP-Basis, DB-Basis (PR #7 gemergt) |
| #5 | 0.1.0 – Grundbausteine | `_3 Beta-Test` | Cursor-Pagination, ETag, CORS-Voreinstellung (PR #8 gemergt) |
| #6 | 0.1.0 – Grundbausteine | `_2 in Arbeit` | Spectral-Regelsatz, Konformitätstest (PR offen) |

**Hinweise für die Arbeit**
- Nur herauslösen, was im Glossary erprobt ist (API G3); Glossary-Bezüge entfernen, Modulschlüssel aus `eduksx.module`.
- Tests immer mit SQLite **und** PostgreSQL.

**Arbeitsregeln** (siehe AGENTS.md)
- Jedes Thema aus dem Chat → Issue mit Testliste; Releases nur nach ausdrücklicher Freigabe; Löschen nur nach Rückfrage.

## Sitzungen

### Sitzung 13.11.2026 (17:05–17:45) – Start

- Blueprint angewendet (MIT, öffentlich, eigener Workflow `tests`), Issues #2–#6 im Meilenstein 0.1.0.
- Grundgerüst, HTTP-Basis und DB-Basis umgesetzt (aus dem Glossary übernommen), Tests SQLite/PostgreSQL grün.

## Entscheide

- Entscheid 13.11.2026: Eigenes Repo, MIT, PHP 8.4 / Laravel 13 (KONZEPT-CORE C2–C4).
- Entscheid 13.11.2026: Eigener Workflow `tests` statt «pruefungen» (PHP 8.4 und PostgreSQL nötig).
