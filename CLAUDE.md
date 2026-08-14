# Bedien-Notizen für Claude (und Nachfolge-Sessions)

Diese Datei wird von Claude Code beim Start automatisch gelesen. Sie hält das
Betriebswissen fest, das sonst nur in einer einzelnen Session existiert — damit ein
neuer Lauf / ein vergleichbarer Workstream direkt wirksam arbeiten kann.

## Umgebung (Claude Code on the web)

- Web-Sessions laufen in einem **frischen Klon ohne Datenbank**: `storage/config.php`
  ist **nicht** vorhanden, es gibt **kein MySQL** und **kein SSH** im Sandbox.
- Folge daraus:
  - **In die DB schreiben** geht nur über **Migrationen**, die auf dem Server angewandt
    werden (siehe unten). Lokales `php bin/migrate.php` scheitert mangels `config.php`.
  - **Aus der DB lesen** geht aus der Web-Session **nicht** direkt. **Niemals** CRM-/
    Personendaten (Namen, E-Mails der Sponsoren) in **öffentliche** GitHub-Actions-Logs
    oder -Artefakte schreiben — das Repo ist öffentlich, die Logs damit auch. Für einen
    Stammdaten-Blick den **CSV-Export** nutzen (Sponsoren-Übersicht → Export, hinter Login)
    und vom Nutzer geben lassen.

## Datenbank-Migrationen anwenden

- Runner: `bin/migrate.php` (`status` zeigt offene, `migrate` wendet an). Tracking in
  Tabelle `schema_migrations`, läuft jede Datei genau einmal.
- **Auf dem Server (Strato) per Knopf:** GitHub → **Actions** → Workflow **„DB-Migration"**
  → **Run workflow** (Branch `main`, Eingabe `befehl` = `status` oder `migrate`).
  Datei: `.github/workflows/migrate.yml` (SSH via ssh-action, gleiche Secrets wie Deploy).
  Bewusst **nur manuell**, **nicht** an den Deploy gekoppelt (DDL committet auf MySQL
  implizit ohne Auto-Rollback).
- Neue Sponsoren/Datensätze werden per Seed-Migration angelegt (Beispiele: `073` KJR,
  `074` BSJ). INSERTs immer **guarded** (`WHERE NOT EXISTS (SELECT ... FROM (…) x)`,
  `FROM DUAL`) — umgeht MySQL-Fehler 1093 und ist gegen bestehende Datensätze robust.
  Bestehende Zeilen nur **additiv** ändern (z. B. Notizen via `CONCAT(COALESCE(...),…)`),
  nie blind überschreiben — der DB-Stand ist von hier aus nicht einsehbar.

## Deployment

- Push auf `main` → Workflow „SFTP Deployment" rsynct die Dateien auf Strato
  (kein Auto-Migrate). PHP-Lint (`php -l`) läuft als eigener Workflow bei jedem Push.
- Es gibt **kein** PHP/MySQL im Sandbox zum Laufzeit-Test von UI. Vor dem Deploy immer
  `php -l` auf geänderte PHP-Dateien (PHP-CLI ist vorhanden) und, wo möglich, die Logik
  DB-frei per `php -r` dry-runnen.

## Repo-Konventionen

- **Commit-Trailer:** Ein PreToolUse-Hook (`.claude/settings.json`) blockiert
  `Co-Authored-By: Claude`. Diesen Trailer **nicht** setzen.
- **Öffentliches Repo, Datenhygiene:** `sponsor-data/`, `*.sponsor.csv`, `intern/`-Specs
  sind per `.gitignore` bewusst ausgeschlossen. Keine CRM-/Personendaten committen;
  Migrationen dürfen Organisations-/Programmnamen enthalten (wie 072), aber möglichst
  keine privaten Personendaten.
- Branch-Arbeit: Feature-Branch entwickeln, dann per Fast-Forward nach `main` (Deploy).
  `main` bewegt sich häufig → vor dem Push `git fetch origin main` + rebasen.

## Sponsoren-/Fördergruppen-Modell

- Vier Fördergruppen (`src/sponsor_status.php`, `SPONSOR_FOERDERGRUPPE`): **sponsoring**,
  **foerderantrag**, **ueber_dritte**, **oeffentlichkeitsarbeit** — sortieren nach dem
  *Weg der Unterstützung*, nicht nach der Firma (dieselbe Bank-Familie kann in mehreren
  Gruppen liegen). Kern-Definition je Gruppe: `SPONSOR_FOERDERGRUPPE_HINWEIS` +
  `sponsorFoerdergruppeHinweis()`; erscheint als Hinweis unter den Reitern der
  Erstanschreiben-Seite.
- **Anschreiben-Vorlagen je Fördergruppe** (empfänger-getrieben): `SPONSOR_BRIEF_VARIANTEN`
  + `sponsorBriefEffektiverSlug()` in `src/sponsor_brief.php`. Der Versand
  (`sendSponsorAnschreiben`) wählt den Vorlagentext automatisch nach der Fördergruppe des
  Empfängers; `anschreiben_typ` (ENUM) und Anhänge bleiben Basis `erstanschreiben` — kein
  Schema-/Enum-Eingriff. Die Erstanschreiben-Seite (`orga/_anschreiben_seite.php`) schaltet
  über die Fördergruppen-Reiter (`?zielgruppe=fg_<gruppe>`) Empfänger UND Variantentext um.
- Zielgruppen/Empfänger-Filter je Anschreiben-Seite: `src/sponsor_zielgruppen.php`.
