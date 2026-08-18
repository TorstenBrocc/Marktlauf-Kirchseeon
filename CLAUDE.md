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

## Aktueller Stand / Übergabe (Stand 2026-08-18)

Erledigt am 2026-08-18 (lokale Session, alles auf `main` deployt):
- **Sponsor-Aufgaben bearbeitbar:** ✎-Button je Aufgabe in der Sponsor-Maske klappt ein
  vorausgefülltes Edit-Formular auf (`orga/sponsor_form.php`), nutzt den vorhandenen
  `action=update` in `orga/api/aufgabe_orga_crud.php`. ACHTUNG Muster: `update` schreibt
  ALLE Felder — jedes Edit-Formular muss `notiz` und `status` mitsenden, sonst leert das
  Speichern sie (gleiche Falle wie einst `einstellungen_update.php`).
- **Digest-Routing:** Einträge ohne Zuständigen gehen NUR noch an
  `TODO_HERRENLOS_EMPFAENGER_EMAIL` (TT; `src/offene_todos.php`), nicht mehr an alle
  Admins. Zugewiesene Einträge unverändert an die Person; info@ liest jede Mail per
  BCC mit (`mailBccAddress()`).
- **Reminder-Frequenz einstellbar:** Einstellungen → „Erinnerungs-Mails" →
  `reminder_frequenz` (täglich/werktags/Di+Fr/freitags; Default täglich). Gate
  `reminderVersandtagHeute()` greift nur im `--modus=auto` des Digests; der Cron im
  Workflow bleibt täglich. `bin/aufgaben_erinnerung.php` bleibt BEWUSST täglich
  (feuert nur exakt am Fälligkeitstag — Drosselung würde Erinnerungen verschlucken).
- **Branch-Aufräumung:** Die 6 fertigen Commits des Session-Branches
  `claude/sponsor-seite-vorbereiten-yay6o0` (Überlauf-Fix sponsoren.php, Migration 076,
  Doku) waren NICHT auf `main` — jeder main-Deploy hat den TT-bestätigten Live-Fix
  zurückgerollt. Per Cherry-pick auf `main` geholt; der Branch selbst blieb unangetastet.
  Lehre: fertige Branch-Arbeit sofort nach `main` bringen, sonst rollt der nächste
  Deploy sie zurück.

## Vorheriger Stand / Übergabe (Stand 2026-08-14)

Fördergruppen-Feature steht und ist deployt: Reiter + **empfänger-getriebene** Vorlagen-
Varianten im **Erst- und Folgeanschreiben**, Kern-Hinweis je Gruppe unter den Reitern
(Anschreiben **und** Stammdaten). CSV-Export-Button oben in der Sponsoren-Übersicht.
Angewandte Migrationen: **073** (KJR), **074** (BSJ + Strategie-Notizen an 75/78/104/112),
**075** (Konzern-Tag „Kreissparkasse (KSK)").

Erledigt in der lokalen DB-Session am 2026-08-14:
- **UI-Bug rechter Rand — behoben & live (von TT an der echten Seite bestätigt).** Echte
  DevTools-Messung war entscheidend: `.table-wrap.grouped` stand auf `overflow:visible` und fing
  die breite Tabelle NICHT ein → der Überlauf landete auf `.main-content`/der Seite (kaputter
  rechter Rand, v. a. schmal < 769px ohne fixierten Kopf). Fix: `.table-wrap.grouped
  { overflow-x: auto }` (`orga/sponsoren.php` ~498) — die Tabelle scrollt in ihrer Karte, Kopf/
  Filter/Reiter bleiben voll breit; wirkt an allen Breiten. **Lehre:** der frühere `left:0`-Ansatz
  war nur im Desktop-Media-Query aktiv und wurde nur am Mockup „verifiziert" → bei schmaler Ansicht
  wirkungslos; nie wieder Optik ohne Blick auf die ECHTE Seite als erledigt melden. Trade-off
  bewusst akzeptiert (TT ok): der desktop-vertikale Sticky-Spaltenkopf friert nicht mehr ein
  (`overflow-x:auto` koppelt `overflow-y` auf auto). Bei Bedarf per Scroll-Box-Muster nachrüstbar.
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
- **76 DSGV — Fördergruppe korrigiert (Migration 076, angewandt):** zurück auf `ueber_dritte`
  (gegroundet gegen die Gruppen-Vorgaben: kein eigener Antragsweg, läuft über den
  Sparkassen-Verbund/die KSK — so auch 072). Ist-Wert `foerderantrag` kam aus manueller Bearbeitung.

Noch offen (dein Ok nötig):
- **Kern-Hinweis Live-Pixel:** Code deployt + im Mockup bestätigt, aber ohne Admin-Login nicht
  am Live-Pixel geprüft. Fehlt er auf der echten Seite: OPcache-Reset (aktuell keine OPcache-SAPI
  nachweisbar + Datei frisch gestempelt → sehr wahrscheinlich sichtbar).
- **Test-Datensätze** `98 _torsten`, `102 _Anja Jost GmbH` (beide angefragt, in der Übersicht
  sichtbar) sowie `65 Testfirma` (abgelehnt → nur bei Status-Filter „Abgelehnt" sichtbar) —
  Löschen ist destruktiv, daher Rückfrage.
- **78 VK-Stiftung:** Hebel = Ehrenamtspreis (kein Sport-Projektantrag). **112 Sportjugendstiftung:**
  nur überregional → jährlich/regionsübergreifend argumentieren. **BSJ/BLSV** (074):
  `jugendfoerderung@blsv.de`.
