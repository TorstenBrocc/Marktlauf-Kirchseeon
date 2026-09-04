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

## Aktueller Stand / Übergabe (Stand 2026-09-04)

**Live-Ticker: `orga/ticker.php` repariert (Commit `552cfbe`, deployt, keine Migration).** Die Seite war
auf Prod weiß: der SELECT las `u.vorname, u.nachname`, die Tabelle `users` hat seit `001_init.sql` nur
`name` (PDO-Exception unbehandelt → Fatal ohne Ausgabe). Jetzt `u.name`; Ersteller-Name aus `name`.
- **`data/status.json` → 404 auf der Startseite ist kein Defekt:** der Poller in `index.html` (30 s,
  Live-Ticker-Band) liest eine Datei, die ausschließlich `orga/api/ticker_crud.php` nach der **ersten**
  Ticker-Aktion schreibt. `ticker_posts` auf Prod: 0 Zeilen → Datei nie erzeugt. Der `deploy.yml`-EXCLUDE
  ist richtig (Runtime-State; `--delete` würde Renntag-Meldungen löschen — gleiches Muster wie
  `sponsoren.json`). Kein Workflow erzeugt die Datei, das ist so gewollt.
- **Offen (Inhaber):** ersten Eintrag „Die Anmeldung ist offen" in `orga/ticker.php` anlegen → Datei
  entsteht, Band erscheint auf der Startseite, der 404 verschwindet. Danach Konsole gegenprüfen.
- Verifikation: `php -l` grün, Deploy-Workflow grün, md5 `orga/ticker.php` auf Strato = Repo, der
  korrigierte SELECT read-only auf Prod ausgeführt (ok, 0 Zeilen). Kein Login-Test der Seite.

## Aktueller Stand / Übergabe (Stand 2026-09-03)

**Strecke 5 km LIVE, finale Fassung v3 (Commit `9c807fc`, deployt, keine Migration):** `assets/courses/5km.gpx` =
Orga-Drive „Strecken/5 km-mit-km-Marken.gpx" ohne Wegpunkte (5,44 km, TT 04.09.: an den Schnittstellen deckungsgleich
mit der 10 km; GPX-Name für Läufer neutral „Marktlauf Kirchseeon 2026 – 5 km"). Davor v2 = Gegenrichtung 5,33 km (`fba4316`).
Vorher: 03.09. Ost-Schleife
in Original-Richtung (`dc56f12`), Vorbehalts-Hinweis an der 5-km-Karte entfernt (`dc69e3c`). **10 km bleibt gesperrt**
(`blocked` in `js/maps.js`): TT sortiert die 10-km-Strecke gerade neu; neue GPX kommt in den Orga-Drive, dann auf
TT-Wort einpflegen (Datei ersetzen, `blocked` raus, Excel per `intern/scripts/strecken/run_all.sh` neu). 1/2/10 km tragen
den Vorbehalts-Hinweis weiter — TT 04.09.: Website darüber hinaus nicht anfassen.
Orga-Excel + km-GPX (Original- und Gegenrichtung) im Orga-Drive `Strecken/`. Vault: MKL-T32 + build-log 2026-09-03/04.

**Social-Pipeline Schritt 4 + Instagram wieder automatisch (Commit `05a44e5`, deployt, keine Migration;
Vault-Backlog WP-M9…M13, Spec `intern/social-auto-versand-beste-zeit-spec.md` §4d):** Anlass war Anjas
Mail „wollte gleich posten, geht nach wie vor nicht" — der FB-Post war live, die UI sagte es nicht.
- `orga/api/social_save.php`: Speichern/Freigeben setzt `gesendet`/`terminiert` **nicht** mehr auf
  `approved` zurück (Antwort enthält `status`); Post 11 war so am 02.09. „ungesendet" geworden.
- `orga/social_post.php` Schritt 4: Live-Zeile „Auf Facebook + Instagram veröffentlicht … · Bestätigt HH:MM"
  + Permalink-Buttons; ohne Callback Auto-Reload alle 10 s (max 12×, `sessionStorage` `spWarte<id>`);
  terminierte Posts ohne Callback: „Facebook hat den Beitrag zum Termin veröffentlicht"; Kachel-Titel
  zustandsabhängig; Stichtag-Chip „— erledigt"; **Sofort-Button postet Facebook + Instagram**
  (`sofortPosten()`); „noch einmal posten" nur im Fallback-Details mit Doppelpost-Warnung.
- `src/social_versand.php`: `socialVersandHashtagsAnhaengen()`, `socialVersandBildUrl()` (aus
  `versendePost` extrahiert), **`sendeInstagramNach()`** — der Finalizer postet Instagram sofort, wenn FB
  zum Slot live geht (nur `channels=['instagram']`; make-FB-Kante filtert `array:contain facebook`,
  per Blueprint-GET verifiziert); `socialLiveMail($pdo,$post,$channels)` nennt „Veröffentlicht auf: …"
  + Permalinks. `bin/social_versand.php`: Catch-up sendet FB+IG, terminiert nur FB (IG folgt im Finalizer).
- `src/social_dispatcher.php`: Erfolgsmeldung nennt die Kanäle.
- **Bekannte Make-Lücke:** Callback-HTTP-Module (10/11) hängen hinter dem Kommentar-Filter → terminierte
  FB-Posts (ohne first_comment) melden nie Post-ID/Permalink. Fix nur im Make-Szenario 6642115
  (Callback vor den Filter). Inhaber-Entscheid offen.
- Verifikation: `php -l` grün, Deploy-Workflow grün, md5 der fünf Dateien auf Strato = Repo.
  Kein Runtime-Test mit DB (kein lokales Setup) — erster realer Post/Timer-Lauf beobachten.

## Aktueller Stand / Übergabe (Stand 2026-08-30)

**Social-Insights-Sammler („Stage C") — Dauer-Fehler behoben (Commits `4254212` Migration 091 +
`17225b3` Code, deployt/migriert; Make-Szenario „Social Insights (Stage C)" 7094793 angepasst):**
Das Make-Szenario warf seit 29.08. **täglich** einen Fehler (GraphMethodException 100 „Object …
does not exist") und mailte ihn. Ursache war zweiteilig und ist dauerhaft gelöst:
- **Endlos-Wiedervorlage:** `posts_pending_insights.php` liefert einen Post bis `versand_insights_am`
  gesetzt ist — das wird aber **nur bei Erfolg** gesetzt (`post_status_callback.php`), also nie für
  einen nicht abrufbaren Post. Fix: neue Spalte `post_race_contents.insights_versuche` (Migration
  091); der Callback nimmt jetzt `{"insights_status":"failed"}` aus dem Make-Error-Handler und zählt
  hoch, Erfolg setzt auf 0 zurück; der Pending-Endpoint schließt `insights_versuche >= 3` aus. Schwelle
  3 muss in **beiden** Dateien übereinstimmen (Konstante `INSIGHTS_MAX_VERSUCHE` im Callback).
- **Ein fauler Post kippte den ganzen Lauf:** Modul „Get post insights" hatte keinen Error-Handler.
  Fix im Make-Szenario: Error-Handler → HTTP-POST `{post_id, channel:"instagram",
  insights_status:"failed", secret}` an `post_status_callback.php` → **Skip**. Lauf endet grün,
  keine Fehler-Mail. Verifiziert: Testlauf grün, id=7 `insights_versuche=1`, id=3 weiter Reichweite 76.
- **Ursache des toten Posts id=7** („trainingstipp", 29.08.): Posting-Szenario 6642115 hat die von IG
  gelieferte Media-ID (`17976709392095722`) korrekt gespeichert, der Folge-Kommentar lief erfolgreich
  → Media war beim Posten real; ~8 h später „does not exist" → **IG-Post wurde nach dem Posten
  gelöscht**. Kein Pipeline-Bug (id=3 mit derselben Pipeline lieferte sauber Insights).

## Aktueller Stand / Übergabe (Stand 2026-08-25)

Social-Post-Wirkung (Spec) + make.com-Optimierung — alles auf `main`, deployt, migriert:
- **Wirkungs-Spec** liegt im **VAULT** unter `intern/social-post-wirkung-spec.md` (NICHT im Repo —
  `intern/` ist per `.gitignore` + Deploy-EXCLUDE gesperrt; interne Specs gehören nie ins öffentliche
  Repo). §5 (A–D) gefüllt/abgenickt; Bau-Schnitte S1–S6 wurden umgesetzt (Themen-Katalog schärfen,
  Prompt/Stimme, Seiten-IA „Thema zuerst", Grafik-Regeln, Sponsor-Kopplung `src/social_sponsoren.php`,
  Verstärker-Quelle `src/social_verstaerker.php`).
- **make.com-Rückkanal (#1/#2):** `orga/api/post_status_callback.php` (nur POST, HMAC- bzw.
  secret-verifiziert) nimmt je Kanal `{post_id, channel, permalink, status}`; Migration **083** legt
  `ig_permalink/fb_permalink/versand_bestaetigt_am/versand_callback_info` an; der ausgehende Webhook
  sendet jetzt `post_id`; Post-Detail zeigt die bestätigten Live-Links. **GET auf den Endpoint gibt
  bewusst „POST erwartet." — korrekt, kein Fehler.**
- **#4 Härtung:** `X-Signature: sha256=HMAC(secret, body)` am Webhook; Body-`secret` bleibt kompatibel.
- **Sponsor-Post-Anleitung** (`socialSponsorPostAnleitung`) aufgeklappt auf Sponsor-Themen im Post-Detail.

Make.com-Seite (Inhaber, einmalig): nach dem IG/FB-Post-Modul ein HTTP-POST an
`…/orga/api/post_status_callback.php` mit `{post_id, channel, permalink, status}` + Header
`X-Signature` (HMAC-SHA256 des Bodys mit `make_webhook_secret`) **oder** `secret` im Body.

**Nachtrag 2026-08-25 (Commit `224e7d2`, deployt, keine Migration):** „Post ist live"-Mail
entschärft — EINE Sammel-Mail (To: info@, Orga/Admins in BCC) statt Mail je Empfänger (info@
bekam ~12 BCC-Kopien je Post); neuer Motivator-Betreff/-Text (Inhaber-Wortlaut); IG/FB-
Profil-Links statt `social_post.php?fahrplan=…` (der zeigte den vorgerückten Eintrag und legte
beim Öffnen einen Leer-Draft an); Verstärker-Katalog mit Emoji + echten Umlauten, Panel/Vorschau
nachgezogen. Details: Vault `intern/social-live-mail-flut-handoff.md`.

**Nachtrag Folge-Session 2026-08-25 (Commit `b6ad46b`, deployt + migriert 084/085/086):** Die drei
Vault-Specs sind unter `intern/` abgelegt; **MO1 Insights-Rückkanal, Erster-Kommentar-Automatik und
#3 Auto-Versand am Stichtag sind gebaut** (MVP live — Details/Reste in `intern/VAULT_SNAPSHOT.md`).
Stale-Branch `claude/social-post-impact-spec-0vlwns` ✅ gelöscht. Offen bleibt: make.com-Callback +
Kommentar-Modul + Insights-Lieferung einrichten (Aufgabe 5, Inhaber-Login), Kernkompetenz-CSV, TikTok.

**Nachtrag „Beste Sendezeit"-Timer 2026-08-25 (Commit `7708022` + Migration `089`, deployt/migriert):**
**S1+S2 live** — strukturierte Best-Zeiten je Kanal×Wochentag (Einstellungen-Grid, Key
`beste_sendezeiten_struktur`, Helfer `besteSlotFuer()` in `src/social_anlaesse.php`) + per-Post
**Wunsch-Sendezeit** (`post_race_contents.geplante_uhrzeit`, Vorschlag je Kanal im Post-Detail,
Autosave `api/post_feld.php`). Entscheid: **IG=Meta Business Suite, FB=make-terminiert**.
**Übergabe-Block + Start-Prompt:** Vault `intern/social-auto-versand-beste-zeit-spec.md` (oben).

**Nachtrag S3/S4 + IG-Handoff — GEBAUT + DEPLOYT + MIGRIERT 2026-08-27 (Commit `fb7bfdb`, Migration
`090`):** Voller Status `terminiert` + `terminiert_fuer` (§4b). `socialDispatch()` sendet
`scheduled_time` (ISO 8601 Europe/Berlin) und lässt `first_comment` bei leer weg (make-Filter „Exists";
sonst Kommentar auf unveröffentlichtem Post → Fehler). `versendePost()`: Terminiert-Zweig (keine
Live-Mail), CTA+Link in die FB-Caption; `socialLiveMail()` ausgelagert; `finalisiereTerminiertePosts()`
schaltet zum Slot live + Mail. `bin/social_versand.php` zweiphasig (finalisieren + terminieren), FB-only,
Slot in Europe/Berlin; **Cron stündlich** 06:00–22:00 CEST. IG-Handoff-Kachel + `terminiert`-Status im
Post-Detail. **make-Prod-Szenario 6642115 live angepasst (verifiziert):** FB „Publish date" =
`{{2.scheduled_time}}`, Router-Filter `channels contains instagram`/`facebook`. **Offen nur:**
Live-Beobachtung am ersten realen terminierten FB-Post + Inhaber-Sichtprüfung der IG-Kachel.

Offen / vertagt (drei Vault-Specs im Chat-Transkript zum Ablegen unter `intern/`):
- `make-com-optimierung-spec.md` — **Musik NICHT per API** (muss ins Video eingebettet sein);
  Reichweiten-Automatik: Erster-Kommentar-Link, Insights-Rückkanal (MO1).
- `social-auto-versand-stichtag-spec.md` — #3 Auto-Versand am Stichtag (vertagt).
- `social-tiktok-integration-spec.md` — TikTok (Kollegin hat begonnen), später in die EINE Pipeline einhängen.
- **Kernkompetenz** der bestätigten Sponsoren füllen (Feld existiert, Migration 077): Stammdaten-CSV-
  Export in der Sponsoren-Übersicht → Daten → je Sponsor eine knappe Kernkompetenz (die KI baut daraus
  den Marktlauf-Bezug selbst).
- Stale Remote-Branch `claude/social-post-impact-spec-0vlwns` per GitHub-UI löschen (der Proxy in
  Web-Sessions lässt Ref-Löschung nicht zu).

## Aktueller Stand / Übergabe (Stand 2026-08-18)

Erledigt am 2026-08-18 (lokale Session, alles auf `main` deployt):
- **Ansprechpartner per Drag-and-Drop sortierbar** (Migration **079**, PR #34). Neue Spalte
  `sponsor_ansprechpartner.sortierung` (additiv, Backfill `= id` → heutige Ordnung bleibt; ohne
  Window-Funktion, damit versionsunabhängig). `orga/sponsor_form.php` lädt jetzt
  `ORDER BY sortierung ASC, id ASC`; je Kontaktkarte ein Griff `.ap-drag` (nur der Griff ist
  `draggable`, nicht die ganze Karte — sonst Konflikt mit Doppelklick-Edit), natives HTML5-DnD mit
  Live-Umsortierung, Persist per neuem `action=reorder` in `orga/api/ansprechpartner_save.php`
  (Transaktion, Ownership per `sponsor_id`, normalisiert 1..n). Neuanlage hängt ans Ende (`max+1`).
  Grenze: natives DnD greift nicht auf Touch/Mobil (wie der Datei-Baum in `dateien.php`) — Sortieren
  ist ein Desktop-Vorgang. Reihenfolge beim Livegang eingehalten: Merge → Migration 079 gefahren →
  verifiziert (0 offen).
- **Sponsor-Aufgaben bearbeitbar:** ✎-Button je Aufgabe in der Sponsor-Maske klappt ein
  vorausgefülltes Edit-Formular auf (`orga/sponsor_form.php`), nutzt den vorhandenen
  `action=update` in `orga/api/aufgabe_orga_crud.php`. ACHTUNG Muster: `update` schreibt
  ALLE Felder — jedes Edit-Formular muss `notiz` und `status` mitsenden, sonst leert das
  Speichern sie (gleiche Falle wie einst `einstellungen_update.php`).
- **Digest-Routing:** Einträge ohne Zuständigen gehen NUR noch an
  `TODO_HERRENLOS_EMPFAENGER_EMAIL` (TT; `src/offene_todos.php`), nicht mehr an alle
  Admins. Zugewiesene Einträge unverändert an die Person; info@ liest jede Mail per
  BCC mit (`mailBccAddress()`).
- **Reminder-Zeitplan einstellbar (v2, gleicher Tag):** Einstellungen → „Erinnerungs-Mails" →
  Wochentags-Pillen Mo–So (`reminder_versandtage`, ISO-Tagesliste als CSV; Sonderwert
  `keine` = bewusst aus; Key fehlt/kaputt = täglich) + Schnellwahl-Presets (reine
  Checkbox-Vorbelegung, `REMINDER_TAGE_PRESETS`) + „Pausiert bis einschließlich"
  (`reminder_pause_bis`, Urlaubs-Pause). Gate `reminderVersandtagHeute()` greift nur im
  `--modus=auto` des Digests; Cron bleibt täglich ~08:00. Das v1-Dropdown
  (`reminder_frequenz`) ist ERSETZT; Migration **078** überführt den alten Wert in die
  Tagesliste und löscht den Key. UI-Muster gegroundet an GitHub Scheduled Reminders /
  Slack-DND (Wochentage + Pause-bis; Uhrzeit bewusst weggelassen — bräuchte stündlichen
  Cron). ACHTUNG Endpoint-Muster: unangehakte Checkbox-Gruppen fehlen im POST komplett —
  deshalb Marker-Feld `reminder_versandtage_gesendet`, ohne das der Key nicht geschrieben
  wird. `bin/aufgaben_erinnerung.php` bleibt BEWUSST täglich (feuert nur exakt am
  Fälligkeitstag — Drosselung würde Erinnerungen verschlucken).
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
- **Sponsoren-Kopf beim Scrollen — final gelöst via Scroll-Box/App-Shell (2026-08-18, TT gewählt).**
  Die zwei Vorgänger-Fixes standen in einem Grundkonflikt: Kopf-Pinnen braucht `.main-content` als
  Scroller (`.table-wrap.grouped{overflow:visible}`), aber sobald die Tabelle breiter als der
  Viewport ist, scrollt `.main-content` horizontal und die (nur viewport-breite) Fixzone wandert
  weg → ungedeckter rechter Rand. `left:0` fängt das nur fast (ein ~24px-Leck-Streifen neben der
  Fixzone bleibt, live per Zoom bestätigt). Der Containment-Fix (`overflow-x:auto` auf der Karte)
  erschlug den Streifen, machte die Karte aber zum Sticky-Scrollcontainer → Kopf verlor den
  Viewport-Bezug (`top:var(--fixzone-h)` schob ihn bei Scroll 0 um ~263px nach unten auf
  „Regionale Produkte"). Beides gleichzeitig ist im Seiten-Scroll-Modell nicht sauber möglich.
  **Lösung (TT hat die Scroll-Box gewählt):** Desktop-App-Shell — `.main-content` ist
  `display:flex; flex-direction:column`, Kopf-Zone (`flex:0 0 auto`) und `.action-bar`
  (`flex:0 0 auto`) bleiben fix, die gruppierte Tabelle ist `flex:1 1 auto; min-height:0;
  overflow:auto` und scrollt IN SICH (vertikal: Kopf `sticky top:0` am Box-Rand; horizontal: in der
  Box statt auf der Seite). Kein Seiten-Overflow (0px), Aktionsleiste immer sichtbar. Live in Chrome
  gemessen + Screenshots. Zwei **Lehren:** (1) ein Sticky-Element stickt relativ zum nächsten
  `overflow`-Scrollcontainer, nicht zwingend zum Viewport. (2) `min-height:0` ist Pflicht, damit ein
  Flex-Kind unter seine Inhaltshöhe schrumpfen und intern scrollen kann. (3) Media-Queries erhöhen
  die Spezifität NICHT — der Desktop-Scroll-Box-Block muss in der Quelle NACH der
  Mobil-Basisregel `.table-wrap.grouped{overflow-x:auto}` stehen, sonst gewinnt die Basis.
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
