# Übergabe an einen neuen Chat – EduKSX-Core

Stand: **26.11.2026** – Release 0.4.0 (Rechte und Rollen, Anmeldung Stufe 1); 0.3.0 mit PHP 8.5, Einstellungen, Protokollierung, Sicherheitsprotokoll.
[PROJEKTSTAND.md](PROJEKTSTAND.md) (aktuell), [PROJEKTSTAND-ARCHIV.md](PROJEKTSTAND-ARCHIV.md) (Verlauf). Konzept liegt im
Repo EduKSX-Architektur (`docs/KONZEPT-CORE.md`). Bei jeder Übergabe nachführen.

## Start nächster Chat (Übergabe 26.11.2026)

**Zuerst lesen:** CLAUDE.md → AGENTS.md → dieser Abschnitt → PROJEKTSTAND.md → KONZEPT-CORE.md (Architektur-Repo).

**Stand Code und CI**
- Gemergt seit 0.2.1: Stack PHP 8.5 (#29), Einstellungen (#31, PR #32), Protokollierung (#33, PR #34), Debug je
  Organisation, Protokollpaket und Sicherheitsprotokoll (#35, #36, PR #37).
- Lokal läuft nur PHP 8.3 – Tests (Symfony 8 braucht PHP ≥ 8.4) laufen in der CI (`tests`, SQLite und PostgreSQL 18).
- 0.3.0 im Chat freigegeben und veröffentlicht (26.11.2026); Korrekturen aus dem Beta-Test als 0.3.x.
- 0.4.0 (Freigabe im Chat 26.11.2026): Rechte und Rollen (#39, PR #40), Anmeldung Stufe 1 (#41, PR #42), Text mit Muster (#44, PR #45).
- Glossary nutzt Core 0.3 (Protokollformat, Glossary #521); Einstellungen auf Core 0.4 (Glossary #523).

**Offene Issues und ihr Status**

| Issue | Status | Inhalt |
|---|---|---|
| #22, #24, #27, #29, #31, #33, #35, #36, #39, #41, #44 | `_3 Beta-Test` | Abnahme durch Projektverantwortliche:n |
| #28 | `triage` | Abgleich 0.4.0; erledigt bis Anmeldung Stufe 1, offen: ci:check (QUALITAET), DB 1.4/1.5, module-init |

**Hinweise für die Arbeit**
- Nur herauslösen, was im Glossary erprobt ist (API G3); Glossary-Bezüge entfernen, Modulschlüssel aus `eduksx.module`.
- Tests immer mit SQLite **und** PostgreSQL.

**Arbeitsregeln** (siehe AGENTS.md)
- Jedes Thema aus dem Chat → Issue mit Testliste; Releases nur nach ausdrücklicher Freigabe; Löschen nur nach Rückfrage.

## Sitzungen

### Sitzung 14.11.2026 – Core 0.2

- #13 Steckbrief und /health, #14 Empfang S4 (Signer, CloudEvent, Idempotenz), #15 Historie data_changes, #16 Schemas
  aus `spec/` (Architektur v0.3.0) mit `opis/json-schema`; Konformitätstest erweitert. 105 Tests, SQLite und PostgreSQL.
- Release 0.2.0 freigegeben und veröffentlicht (PR #18, #19); #11, #13–#16 und Meilenstein 0.2.0 geschlossen.
- Lizenz von `spec/` geklärt: zusätzlich MIT (Architektur PR #56).

### Sitzung 13.11.2026 (17:05–17:45) – Start

- Blueprint angewendet (MIT, öffentlich, eigener Workflow `tests`), Issues #2–#6 im Meilenstein 0.1.0.
- Grundgerüst, HTTP-Basis und DB-Basis umgesetzt (aus dem Glossary übernommen), Tests SQLite/PostgreSQL grün.

## Entscheide

- Entscheid 13.11.2026: Eigenes Repo, MIT, PHP 8.4 / Laravel 13 (KONZEPT-CORE C2–C4).
- Entscheid 13.11.2026: Eigener Workflow `tests` statt «pruefungen» (PHP 8.4 und PostgreSQL nötig).
