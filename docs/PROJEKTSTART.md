# Projektstart – EduKSX-Core

Stand: 2026-11-13 · Status: **OFFEN** – Grundgerüst erst nach Bestätigung durch Projektverantwortliche:r

Steckbrief aus der Start-Checkliste (`start.py --ziel …` im GitHub-Blueprint). Er ist die Grundlage für das Grundgerüst
und für die Konfiguration in `.github/blueprint.toml`. Änderungen hier festhalten und `start.py` erneut ausführen.

## Ziel

Gemeinsame PHP-Bibliothek eduksx/core für alle Module der EduKSX-Plattform: Bausteine für Fehler, Sicherheitskopfzeilen, Abruflimits, Datenbank-Helfer, Pagination, ETag sowie Konformitätstests nach DB- und API-Standard – für Entwickler:innen der Module.

**Nichtziele:**

- kein Dienst, keine eigene Datenbank und keine eigene Oberfläche
- keine Stammdaten von Personen oder Organisationen (gehören zu Ident)
- kein Fachcode einzelner Module

## Datenschutz

- Zielgruppe: nur intern
- Anwendbares Recht: DSG
- Daten: keine Personendaten · Schutzbedarf: **öffentlich**

Die Zuordnung ist eine Arbeitshilfe, keine Rechtsberatung; bei öffentlichen Organen die zuständige
Datenschutzstelle einbeziehen (z. B. Vorabkontrolle bzw. Datenschutz-Folgenabschätzung).

## Antworten

| Frage | Antwort |
|---|---|
| Wie heisst das Projekt (voller Name)? | EduKSX-Core |
| Was soll das Projekt leisten, und für wen? (ein bis zwei Sätze) | Gemeinsame PHP-Bibliothek eduksx/core für alle Module der EduKSX-Plattform: Bausteine für Fehler, Sicherheitskopfzeilen, Abruflimits, Datenbank-Helfer, Pagination, ETag sowie Konformitätstests nach DB- und API-Standard – für Entwickler:innen der Module. |
| Was gehört ausdrücklich nicht dazu (Nichtziele)? | kein Dienst, keine eigene Datenbank und keine eigene Oberfläche, keine Stammdaten von Personen oder Organisationen (gehören zu Ident), kein Fachcode einzelner Module |
| Welcher Kurzname soll in den Release-Titeln stehen? | EduKSX-Core |
| Ist es eine Web-Anwendung, eine Desktop-Anwendung oder beides? | web |
| Wer nutzt die Anwendung? | nur intern |
| Ist das Repository neu oder gibt es es schon? | neu |
| Wer gibt Issues und Releases frei (Rolle genügt)? | Projektverantwortliche:r |
| Arbeitest du allein oder im Team? | allein |
| Ist das Repository öffentlich oder privat? | öffentlich |
| Unter welcher Lizenz steht der Code? | MIT |
| Auf wen lautet die Lizenz? | Markus Keller |
| In welchen Programmiersprachen wird entwickelt? | PHP |
| Welcher Grundsatz gilt für Abhängigkeiten? | geprüfte Pakete |
| Wo läuft die Web-Anwendung? | – |
| Wie kommt die Anwendung zu den Nutzer:innen? | nur Quellcode |
| Welches Framework bzw. welche Grundlage? | Laravel 13 (Paket, PHP 8.4) |
| Welche Daten verarbeitet die Anwendung? | keine Personendaten |
| Welcher Schutzbedarf gilt für die Daten (Klassifizierung)? | öffentlich |
| Welche Anmeldungen und Zugänge braucht es? | keine |
| In welchen Sprachen wird die Oberfläche ausgeliefert? | de-CH |
| Gibt es Plugins oder Module mit eigener Version? | nein |
| Mit welchem Befehl laufen alle Prüfungen? | composer ci:check |
| In welcher Datei steht die Versionsnummer? | VERSION |
| Mit welcher Version startet das Projekt? | 0.1.0 |
| Mit welchen KI-Agenten wird gearbeitet? | Claude |
| Wie heisst der erste Meilenstein? | 0.1.0 – Grundbausteine |
| Übergabe und Projektstand auch als Kopie im Claude-Projekt führen? | nein |
| Welche ersten Funktionen gehören in diesen Meilenstein? | HTTP-Basis: problem+json, Request-ID, Sicherheitskopfzeilen, Abruflimit, CORS, DB-Basis: StrictSchema und Trait lock_version, Listen: Cursor-Pagination und starker ETag, Prüfungen: Spectral-Regelsatz eduksx und Konformitätstest |

## Grundgerüst

Vorschlag für Aufbau, Ordner und erste Dateien – wird vor dem Anlegen mit Projektverantwortliche:r
abgestimmt (Status je Punkt: BESCHLOSSEN, EMPFOHLEN, OFFEN, ZU PRÜFEN):

| Punkt | Vorschlag | Status |
|---|---|---|
| Technik | PHP · Laravel 13 (Paket, PHP 8.4) | OFFEN |
| Ordner | Quellcode, `tests/`, `docs/` | OFFEN |
| Prüfungen | `composer ci:check` (Workflow «pruefungen») | OFFEN |
| Version | `VERSION` mit 0.1.0 | OFFEN |
| Auslieferung | nur Quellcode | OFFEN |

## Erster Meilenstein: 0.1.0 – Grundbausteine

- [ ] HTTP-Basis: problem+json, Request-ID, Sicherheitskopfzeilen, Abruflimit, CORS
- [ ] DB-Basis: StrictSchema und Trait lock_version
- [ ] Listen: Cursor-Pagination und starker ETag
- [ ] Prüfungen: Spectral-Regelsatz eduksx und Konformitätstest

Diese Punkte werden nach Bestätigung als Issues mit Testliste angelegt.
