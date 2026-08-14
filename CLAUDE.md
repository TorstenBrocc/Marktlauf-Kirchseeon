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

## Aktueller Stand / Übergabe (Stand 2026-08-14)

Fördergruppen-Feature steht und ist deployt: Reiter + **empfänger-getriebene** Vorlagen-
Varianten im **Erst- und Folgeanschreiben**, Kern-Hinweis je Gruppe unter den Reitern
(Anschreiben **und** Stammdaten). CSV-Export-Button oben in der Sponsoren-Übersicht.
Angewandte Migrationen: **073** (KJR), **074** (BSJ + Strategie-Notizen an 75/78/104/112),
**075** (Konzern-Tag „Kreissparkasse (KSK)").

Offene Punkte — am besten aus einer **lokalen** Session mit DB-Zugriff (`storage/config.php`):
- **Kontakt-Audit:** Stammdaten (~107 Sponsoren) Zeile für Zeile prüfen. Export oben in der
  Übersicht („CSV-Export (alle)").
- **75 „VR-Förderpreis":** vermutlich „Sterne des Sports" (Volksbanken/Raiffeisen + DOSB),
  direkte Bewerbung über die lokale Volksbank. 2026-Frist (30.06.) vorbei → Ziel 2027
  (~Apr–Jun). Identität am Datensatz bestätigen.
- **78 VK-Stiftung:** kein Sport-Projektantrag; Hebel = Ehrenamtspreis.
- **104 KSK-Stiftung Ebersberg:** lokal, beste Passung; 2027 früh Antrag
  (stiftungen@kskmse.de). Konzern-Tag verknüpft Bank + Stiftung — Bank ggf. manuell taggen,
  falls ihr Firmenname nicht mit „Kreissparkasse" beginnt.
- **112 Sportjugendstiftung:** nur überregional förderfähig → jährlich/regionsübergreifend
  argumentieren.
- **BSJ/BLSV** (Migration 074): direkter Jugendsport-Weg, `jugendfoerderung@blsv.de`.
- **Bekannter UI-Bug:** Sponsoren-Übersicht rechts „kaputt" (horizontaler Überlauf). Verdacht:
  die gruppierte Tabelle (`.table-wrap.grouped` / `.data-table.grouped` = `overflow:visible`)
  wird breiter als der Viewport → die sticky `.kopf-fixzone` deckt den rechten Bereich nicht.
  Im Browser (DevTools) prüfen, wer den horizontalen Überlauf erzeugt, dann entweder die
  gruppierte Tabelle horizontal scrollen lassen oder die Kopf-Zone auf volle Breite bringen.
  Datei: `orga/sponsoren.php` (Sticky-CSS ~475–520). Blind nicht gefixt (Regressionsrisiko).
