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

Erledigt in der lokalen DB-Session am 2026-08-14:
- **UI-Bug rechter Rand — behoben & live.** Ursache im Browser belegt: `.main-content` ist
  `overflow-y:auto` → die Spec macht `overflow-x` implizit zu `auto`; ist die gruppierte
  Tabelle breiter als der Viewport, scrollt main-content horizontal und die nur viewport-breite
  sticky `.kopf-fixzone` legt rechts einen ungedeckten Streifen frei. Fix: `.kopf-fixzone
  { left: 0; }` im Desktop-Media-Query (`orga/sponsoren.php` ~479) — Kopf-Zone horizontal
  gepinnt, deckt die volle sichtbare Breite. Bewusst NICHT die Tabelle auf `overflow-x:auto`
  gestellt (das hätte per Spec `overflow-y:auto` erzwungen und den vertikalen Sticky-Kopf
  zerschossen). Verifiziert an originalgetreuem Mockup (beide Achsen), deployt (Branch-Dispatch).
- **Kontakt-Audit (107 Sponsoren, direkt aus der DB).** Verteilung: sponsoring 80 · foerderantrag 7
  · ueber_dritte 7 · oeffentlichkeitsarbeit 13. Flags: Test-Datensätze `98 _torsten`, `65 Testfirma`,
  `102 _Anja Jost GmbH` (Cleanup destruktiv → offen); mögliche Dublette `30` vs `80` (Allianz
  Waldhör/Schrödinger); 9 ohne Ansprechpartner + 13 ohne E-Mail (fast nur die institutionellen
  Förder-Programme — erwartbar). Zeilen-Detail bleibt außerhalb des Repos (CRM/DSGVO).
- **75 „VR-Förderpreis" — Identität recherchiert:** „VR-Förderpreis" und „Sterne des Sports"
  sind zwei verschiedene VR-Programme; für den Sportverein passt „Sterne des Sports" (DOSB +
  Volksbanken, Bewerbung 1.4.–30.6., lokal bis 1.500 €, Vereine unter Landessportbund → ATSV
  via BLSV), Weg über Raiffeisen-Volksbank Ebersberg (id 7). Ziel 2027. Notiz am Datensatz noch
  „vermutlich" — auf „bestätigt" nachziehen (Prod-Write, offen).
- **104 KSK-Stiftung ↔ Bank — Konzern-Tag verifiziert:** Gruppe `[6] Kreissparkasse (KSK)`;
  Bank `id 8` und Stiftung `id 104` hängen beide an `gruppe_id=6`. 075-Verknüpfung greift,
  kein Handnachziehen nötig.

Noch offen (dein Ok nötig):
- **Kern-Hinweis Live-Pixel:** Code deployt + im Mockup bestätigt, aber ohne Admin-Login nicht
  am Live-Pixel geprüft. Fehlt er auf der echten Seite: OPcache-Reset (aktuell keine OPcache-SAPI
  nachweisbar + Datei frisch gestempelt → sehr wahrscheinlich sichtbar).
- **Test-Datensätze löschen** (98/65/102) — destruktiv, daher Rückfrage.
- **76 DSGV Fördergruppen-Einordnung** (foerderantrag vs. ueber_dritte) — von dir vorbehalten.
- **78 VK-Stiftung:** Hebel = Ehrenamtspreis (kein Sport-Projektantrag). **112 Sportjugendstiftung:**
  nur überregional → jährlich/regionsübergreifend argumentieren. **BSJ/BLSV** (074):
  `jugendfoerderung@blsv.de`.
