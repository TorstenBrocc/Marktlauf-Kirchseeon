# Bedien-Notizen für Claude (und Nachfolge-Sessions)

Diese Datei wird von Claude Code beim Start automatisch gelesen. Sie hält das
Betriebswissen fest, das sonst nur in einer einzelnen Session existiert — damit ein
neuer Lauf / ein vergleichbarer Workstream direkt wirksam arbeiten kann.

## Wie diese Datei gepflegt wird (Konvention)

**Am Ende jeder Session:** den Abschnitt „Aktueller Stand / Übergabe" **ersetzen**, nicht
einen weiteren anhängen. Dazu gehört: neue dauerhafte Fallen nach „Fallen & Lehren",
neue/erledigte Aufgaben nach „Offene Punkte". Ohne Rückfrage — dafür gibt es die Konvention.

**Was hier NICHT hingehört:** die Erzählung erledigter Arbeit. Was gebaut wurde, steht in
den Commit-Nachrichten und im Diff; die Datei wird bei jedem Session-Start vollständig in
den Kontext geladen, jede Zeile kostet also dauerhaft. Hier steht nur, was eine neue
Session **nicht** aus Code, Commits oder `git log` herleiten kann: Fallen, Entscheide,
Zugänge, offene Punkte.

Wer die Vorgeschichte braucht: `git log -p -- CLAUDE.md`. Die acht Einzel-Übergabeblöcke
vom 14.08.–11.09.2026 sind dort vollständig erhalten (zusammengeführt am 2026-09-15).

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

**Inhaber-Setup (lokal):** Basisordner `~/Repo/github/Marktlauf-Projekt/` mit `website/`
(Haupt-Checkout `main`), feature-spezifischen Worktrees `website-*` daneben und `intern/`
(privates Repo, NICHT der Website-Code). Auf coreone gleich aufgebaut unter
`~/work/Marktlauf-Projekt/`; der Claudex-Loop arbeitet in eigenen Worktrees von
`~/work/Marktlauf-Projekt/website`.
Merke: kein Ordner heißt „Marktlauf-Kirchseeon" — ein `find ~ -name Marktlauf-Kirchseeon`
findet daher nichts.

## Datenbank-Migrationen anwenden

- Runner: `bin/migrate.php` (`status` zeigt offene, `migrate` wendet an). Tracking in
  Tabelle `schema_migrations`, läuft jede Datei genau einmal.
- **Auf dem Server (Strato) per Knopf:** GitHub → **Actions** → Workflow **„DB-Migration"**
  → **Run workflow** (Branch `main`, Eingabe `befehl` = `status` oder `migrate`).
  Datei: `.github/workflows/migrate.yml` (SSH via ssh-action, gleiche Secrets wie Deploy).
  Bewusst **nur manuell**, **nicht** an den Deploy gekoppelt (DDL committet auf MySQL
  implizit ohne Auto-Rollback).
- Neue Sponsoren/Datensätze werden per Seed-Migration angelegt. INSERTs immer **guarded**
  (`WHERE NOT EXISTS (SELECT ... FROM (…) x)`, `FROM DUAL`) — umgeht MySQL-Fehler 1093 und
  ist gegen bestehende Datensätze robust. Bestehende Zeilen nur **additiv** ändern (z. B.
  Notizen via `CONCAT(COALESCE(...),…)`), nie blind überschreiben — der DB-Stand ist von
  hier aus nicht einsehbar.

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
  Migrationen dürfen Organisations-/Programmnamen enthalten, aber möglichst keine
  privaten Personendaten. **Interne Specs gehören nie ins öffentliche Repo** — sie liegen
  in `intern/` (Regel und Absicherung: Abschnitt „MARKTLAUF = EIN PROJEKT IN ZWEI REPOS").
- Branch-Arbeit: Feature-Branch entwickeln, dann per Fast-Forward nach `main` (Deploy).
  `main` bewegt sich häufig → vor dem Push `git fetch origin main` + rebasen.

## Fallen & Lehren (dauerhaft)

**Git-Identität in Web-Sessions.** Der Default-Git-Autor in der Web-Sandbox ist
`Claude <noreply@anthropic.com>`. Der Guard-Workflow `.github/workflows/no-claude-author.yml`
scannt die **ganze** Historie auf diese Mail und wird sonst **rot** (Deploy läuft trotzdem,
nur der required check „check" bleibt rot). Ein `SessionStart`-Hook in `.claude/settings.json`
setzt die Identität inzwischen automatisch auf `Torsten T
<81263458+TorstenBrocc@users.noreply.github.com>`. **Claude darf `.claude/settings.json` NICHT
selbst schreiben** (Classifier: Self-Modification) — Änderungen daran macht der Inhaber.
Vor einem Commit trotzdem `git config user.name`/`user.email` prüfen.

**`update`-Endpunkte schreiben ALLE Felder.** Jedes Edit-Formular muss sämtliche Felder
mitsenden (z. B. `notiz` **und** `status` bei `orga/api/aufgabe_orga_crud.php`), sonst leert
das Speichern die nicht gesendeten. Gleiche Falle historisch bei `einstellungen_update.php`.

**Unangehakte Checkbox-Gruppen fehlen im POST komplett.** Deshalb braucht jede solche Gruppe
ein Marker-Feld (Muster: `reminder_versandtage_gesendet`), ohne das der Key nicht geschrieben
wird — sonst ist „alles abgewählt" nicht von „Formular kennt das Feld nicht" unterscheidbar.

**Fertige Branch-Arbeit sofort nach `main` bringen.** Sechs fertige Commits lagen einmal nur
auf einem Session-Branch — jeder `main`-Deploy hat den bestätigten Live-Fix zurückgerollt.

**Optik nie ohne Blick auf die ECHTE Seite als erledigt melden.** Ein Fix, der nur am Mockup
oder im Desktop-Media-Query verifiziert war, war bei schmaler Ansicht wirkungslos.

**CSS-Fallen aus dem Sponsoren-Kopf (teuer erkauft):**
1. Ein `position:sticky`-Element stickt relativ zum nächsten `overflow`-Scrollcontainer,
   nicht zwingend zum Viewport.
2. `min-height:0` ist Pflicht, damit ein Flex-Kind unter seine Inhaltshöhe schrumpfen und
   intern scrollen kann.
3. Media-Queries erhöhen die Spezifität **nicht** — ein Desktop-Block muss in der Quelle
   NACH der Mobil-Basisregel stehen, sonst gewinnt die Basis.
4. Der Header ist `position:fixed` (`css/layout.css:11`), nicht sticky — wer das annimmt,
   baut Bänder, die dahinter verschwinden.

**`overflow-wrap` rettet keine zu breite Tabelle.** Es senkt die *min-content*-Breite
einer Auto-Layout-Tabelle nicht — die Spalten bleiben so breit wie das laengste Wort. Die
Aufgaben-Tabelle der Helfer-Anmeldung war dadurch 440 px breit in einer 231 px schmalen
Karte; die Checkbox-Spalte lag ausserhalb und war am Handy unerreichbar (gefixt 2026-09-16,
`table-layout: fixed` unter 600 px). Wer eine Tabelle schmal bekommen muss: feste
Spaltenbreiten, nicht Umbruch-Regeln. Und immer bei 375 px nachmessen, ob die letzte Spalte
noch **innerhalb** ihres Containers liegt — das Dokument meldet dabei *keinen*
horizontalen Ueberlauf.

**Natives HTML5-Drag-and-Drop greift nicht auf Touch/Mobil.** Sortieren (Ansprechpartner,
Datei-Baum) ist ein Desktop-Vorgang.

**Der Deploy löscht auf dem Server alles, was nicht im Repo ist** (`deploy.yml`:
`ARGS: "-rlgoDzvc -i --delete"`). Das trifft auch **selbst angelegte Sicherungen**: eine
`storage/config.php.bak-<zeit>`, am 20.09.2026 vor einem Prod-Config-Schreibzugriff abgelegt,
war nach dem nächsten Push spurlos weg — die EXCLUDE-Liste schützt `storage/config.php`,
aber kein `*.bak*`. Die Rückfalllinie war damit ab dem ersten Deploy wertlos, ohne dass es
auffiel. **Sicherungen vor Prod-Änderungen außerhalb des rsync-Ziels ablegen**, z. B. unter
`~/.db_backups/` im Home. Zweite Begegnung mit demselben Muster (2026-07-12: manuell
abgelegtes `src/Parsedown.php` wurde beim Deploy entfernt).

**Streckenplan: die Quelle ist die PDF im Drive, nicht das JPG im Repo.** Die Helferseite
zeigt `assets/images/strecke/streckenplan-luftbild{,-klein}.jpg`; erzeugt werden beide aus
`Marktlauf Orga/Helfer/Einsatzplan/Streckenplan_Luftbild_A4.pdf` mit
`bin/streckenplan_update.sh`. Bis zum 20.09.2026 gab es diese Verbindung nicht — die JPGs
waren Handkopien, und am Lauftag war die ausgelieferte Karte einen halben Tag älter als der
Plan. Bilder hängen wegen `Cache-Control: … immutable` (.htaccess) an `?v=<filemtime>`;
wer die Masse ändert, muss `width`/`height` im Markup mitziehen.

**`data/status.json` ist Runtime-State, kein Deploy-Artefakt.** Der `deploy.yml`-EXCLUDE ist
richtig: `--delete` würde Renntag-Meldungen löschen. Gleiches Muster wie `sponsoren.json`.

**`INSIGHTS_MAX_VERSUCHE`** muss in `orga/api/post_status_callback.php` und
`orga/api/posts_pending_insights.php` übereinstimmen, sonst läuft die Wiedervorlage endlos.

## MARKTLAUF = EIN PROJEKT IN ZWEI REPOS

*Identischer Block in `intern/CLAUDE.md` und `website/CLAUDE.md` — Änderungen immer an beiden
Stellen. Ersetzt die früheren Einzelfassungen (auch in den `lessons.md`-Dateien). TT 2026-09-25.*

**Aufbau.** `intern/` (privat, `TorstenBrocc/marktlauf-intern`): Orga, Specs, Konzepte,
Sponsoren- und Förderplanung, RaceResult-Protokolle, Übergaben, Verweise auf Zugangsdaten.
`website/` (**öffentlich**, `TorstenBrocc/Marktlauf-Kirchseeon`): nur deploybarer Code und
öffentliche Texte.

**Immer beide.** Keine Arbeit an einem Repo ohne das andere: jede Aufgabe liest ihre Spec und
den Stand in `intern/`, baut in `website/` und schreibt die Rückkopplung nach `intern/` zurück.
Deshalb die Session immer im **Elternordner** starten — dort lädt die `CLAUDE.md` (Loader) beide
Regelwerke:
- Mac: `~/Repo/github/Marktlauf-Projekt/`
- iPhone / claude.ai / coreone: Umgebungswähler → Remote Control → **`Marktlauf-Projekt`**

Der Loader ist nicht versioniert (Elternordner ist kein Repo). Bei Verlust neu anlegen als
`Marktlauf-Projekt/CLAUDE.md` mit genau zwei Import-Zeilen: `@intern/CLAUDE.md` und
`@website/CLAUDE.md`.

**Einbahnstraße intern → website.** Ins öffentliche Repo darf nur, was ausdrücklich öffentlich
sein soll. **Nie:** Daten aus der Datenbank (Sponsoren-, Helfer-, Teilnehmerdatensätze, Beträge,
interne IDs), private Personennamen und -adressen, Zugangsdaten und Benutzernamen externer
Dienste, interne Specs, Notizen und Übergaben. Was die Website bewusst öffentlich zeigt (z. B.
Sponsor-Logos), ist kein Verstoß.

**Vor jedem Commit in `website/`:** bewusst prüfen und benennen, ob Inhalte aus `intern/`
eingeflossen sind. Technische Absicherung: `~/.claude/hooks/intern-leak-guard.sh`
(`claude-config`, Mac + coreone, Benutzer-Ebene — greift egal in welchem Ordner die Session
startet) blockt einen Commit ins Website-Repo, dessen neue Zeilen einen Eintrag aus
`intern/.claude/oeffentlich-verboten.txt` enthalten.
Die Liste wird nur intern gepflegt und fängt nur, was darin steht — der Blick vor dem Commit
bleibt Pflicht.

**Claudex-Loop** (`CLAUDEX_LOOP=1`, Umgebung `marktlauf`): arbeitet ebenfalls im Projektordner
(`~/work/Marktlauf-Projekt/website`, eigene Worktrees), lädt damit beide Regelwerke und liest und
schreibt `intern/` wie jede andere Session. Die Einbahnstraße gilt für ihn genauso, der Leak-Guard
greift auch dort. Ablauf mit Zwei-Go-Schranke: `website/CLAUDE.md`, Abschnitt „Claudex-Loop auf
coreone" (ADR-055).

## Externe Dienste — Konfigurationsstand

**LLM-Provider-Kette** (`src/llm_client.php`, `llmGenerate()`): Fallback-Reihenfolge
`[gemini, groq, mistral]`, aktiver Provider vorn; liefert einer '' (Fehler), springt der
nächste ein.
- Aktiv: **Groq** (`einstellungen.llm_provider=groq`), Modell **`openai/gpt-oss-120b`**
  (Konstante `GROQ_MODEL`). `llama-3.3-70b-versatile` ist nicht auf dem Free-Key → 404.
- **Gemini** `gemini-3.6-flash` + `thinkingConfig.thinkingLevel` (Gemini-3.x-Feld, NICHT
  Legacy-`thinkingBudget` → sonst HTTP 400 INVALID_ARGUMENT). `gemini-2.0/2.5-flash` = 404.
- **Mistral** `mistral-small-latest`: Free = 1 Req/s / 20k TPM → Selbst-Drossel ≥1,1 s
  zwischen zwei Calls (`$letzterMistralCall`).
- cURL-Timeout 90 s (`llmCurlPost`); PHP `max_execution_time` auf Strato = 240 s.
- **GitHub Models** ist zum 30.07.2026 eingestellt — nicht einbauen.

**RaceResult:** Event `412617`. Die ausführliche Setup-Doku liegt intern unter
`intern/docs/raceresult/setup-protokoll.md` — dort stehen Formulare, Keys, Fenster und
die Renntags-Nachmeldung. Für die Website relevant: siehe „Anmeldung & Nachmeldung" unten.

**Mailversand aus RaceResult prüft man in Brevo, nicht in RaceResult** (verifiziert
2026-09-18). RaceResult versendet über eigenen SMTP (Brevo-Relay; Zugangsdaten stehen intern, nicht in
diesem öffentlichen Repo), Absender `info@atsv-kirchseeon-marktlauf.de`. Der **Jobs-Tab
einer Email-Vorlage bleibt leer**, wenn die Mail über „Anmelde-Formulare → <Formular> →
Aktionen nach Speichern → EMAIL SENDEN" ausgelöst wird — er protokolliert nur manuelle
Versände. Leerer Jobs-Tab heißt also **nicht** „nichts versendet".
Der belastbare Nachweis: **Brevo → Transactional → Logs** (`app.brevo.com/transactional/email/logs`),
Filter „Recipient (To)" + Empfängeradresse. Dort stehen Sent/Delivered/Opened/Clicked je Mail.
⚠️ **Retention ~7 Tage** — am 18.09.2026 reichte das Log trotz Datumsfilter bis 2020 nur bis
zum 11.09. zurück. Für ältere Vorgänge ist „0 logs" **kein** Beleg, dass nichts versendet
wurde. Wer einen Versand beweisen können muss, exportiert vorher per „Download CSV".

**Startnummernvergabe steht auf „Erste freie"** (RaceResult → Grundeinstellungen →
Teilnehmerdaten → Startnummern; Blöcke: 1–99 Bambini, 100–199 1 km, 200–499 2 km,
500–699 5 km, 1000–1999 10 km). „Erste freie" **füllt Lücken auf**. Wer also einen
Teilnehmer-Datensatz löscht, gibt dessen Startnummer als niedrigste Lücke wieder frei — die
Renntags-Nachmeldung teilt sie dann als erstes zu, obwohl das gedruckte Nummernblatt noch den
Namen des Abgemeldeten trägt. Eine so freigewordene Nummer gehört deshalb **vor dem Löschen**
in das Feld „Startnummern ausschließen" auf derselben Seite (Komma-/Zeilen-getrennt, Bereiche
wie `1-50,77` erlaubt).

**make.com** Szenario 6642115 (Posting) und 7094793 (Social Insights Stage C).
Bekannte Lücke: die Callback-HTTP-Module (10/11) hängen hinter dem Kommentar-Filter →
terminierte FB-Posts ohne `first_comment` melden nie Post-ID/Permalink. Fix nur im
Szenario (Callback vor den Filter) — Inhaber-Entscheid offen.

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
- Konzern-Tag: Gruppe `[6] Kreissparkasse (KSK)` klammert Bank (`id 8`) und Stiftung
  (`id 104`).

## Anmeldung & Nachmeldung (Website-Seite)

Der Anmeldebereich in `index.html` hat **zwei Zustände** und schaltet per JavaScript anhand
der Konstante `ANMELDESCHLUSS` um — davor die eingebetteten RaceResult-Formulare, danach
„Die Online-Anmeldung ist beendet." plus QR-Code und Button auf
`https://my.raceresult.com/412617/registration`.

⚠️ **`ANMELDESCHLUSS` muss mit „Aktiv bis" der beiden regulären RaceResult-Formulare
übereinstimmen.** Ein Auseinanderlaufen ist real passiert (RaceResult stand auf 13.09.,
die Website auf 14.09.) und hat Fehlalarme ausgelöst. Die Umschaltung ist rein kosmetisch —
was wirklich geht, entscheidet RaceResult serverseitig.

**Der QR liegt als fertige Datei** (`assets/images/qr-nachmeldung.svg`), erzeugt mit der
repo-eigenen `assets/js/qrcode.js`. Ändert sich das Ziel, muss die Datei neu erzeugt werden —
sie wird **nicht** im Browser gerechnet (die Bibliothek sind 55 KB, das Ziel ändert sich nie).
Ziel-URL identisch mit `orga/poster_generator.php`.

**Workflow „Registration Check"** (`.github/workflows/registration-check.yml`) ruft den
RaceResult-Endpoint direkt auf, weil der Uptime-Check nur den HTTP-Status der eigenen Seiten
misst und ein clientseitiger 404 dort unsichtbar bliebe. Er liest `ANMELDESCHLUSS` aus
`index.html` und schläft nach dem Schluss von selbst ein (grün, kein Alarm). Wer den Schluss
verschiebt, ändert **nur** `index.html`; der Check wacht dann von allein wieder auf.

## Aktueller Stand / Übergabe (Stand 2026-09-16)

**Helfer-Anmeldung steht auf Restbedarf.** `in_anmeldung` ist seit Migration 093 dreiwertig:
**0 = nur intern · 1 = buchbar · 2 = sichtbar, aber gesperrt**. Damit laesst sich eine Aufgabe
schliessen, ohne sie zu verstecken — der Helfer sieht weiter, dass es den Termin gab. Der
Riegel gegen gesperrte Buchungen sitzt in `helferAufgabeByKey()` (`in_anmeldung = 1`); das
`disabled` im Formular ist reine Optik. Geschaltet wird im Einsatzplan ueber das bestehende
Dropdown, das jetzt drei Optionen hat — **Datenpflege braucht ab hier keine Migration mehr**.

Migration 094 hat den Stand fuer 2026 gesetzt: offen sind nur noch **Streckenposten
(09:00–12:00)**, **Abbau Laufevent (13:00–15:00)** und die **freie Sonntags-Verfuegbarkeit**;
neu und gesperrt dazu **Begleitradfahrer Laufstrecke (09:00–12:30, Bedarf 2)**. Fr/Sa starten
eingeklappt (`<details>`, ohne JavaScript), innerhalb eines Tages sortieren buchbare Punkte
nach oben. Live geprueft: 3 aktive / 13 gesperrte Checkboxen.

**Anmeldeschluss ist durch** (14.09., 17:00). Die Website zeigt den Nachmelde-Zustand; die
Nachmeldung laeuft am Renntag ueber das RaceResult-Portal (fuenf Formulare je Lauf, im Portal
hinterlegt — **nicht** auf der Website eingebettet).

**Beobachtung fuer den Renntag:** GitHubs Schedule-Drosselung ist erheblich — `*/15` lief real
mit 1,5–5,5 h Abstand. Auf zeitkritische Fenster ist ein GitHub-Cron nicht verlaesslich.

## Offene Punkte

**Website / Strecke**
- **10 km freigeben:** fertig committet auf Branch `claude/strecke-10km-final` (Worktree
  `website-strecke-10km`; GPX + `blocked` raus + Vorbehalts-Hinweis raus). **Push nach `main`
  NUR auf TT-Wort** — dann mechanisch nach `intern/docs/strecken-10km-freigabe-runbook.md`.
  1 km / 2 km tragen den Vorbehalts-Hinweis weiter.

**Sponsoren / CRM** — offene Punkte stehen intern (`intern/CLAUDE.md`, Abschnitt „OFFENE
PUNKTE — SPONSOREN / CRM"), nicht in diesem öffentlichen Repo: Sponsor- und Förderdaten liegen
in der Datenbank und gehören nicht nach draußen.

**Social / make.com**
- make-Callback vor den Kommentar-Filter ziehen (Szenario 6642115) — sonst keine Permalinks
  für terminierte FB-Posts. Inhaber-Entscheid offen.
- **TikTok** in die eine Pipeline einhängen (Kollegin hat begonnen); Spec im Vault.
- Echter GPT-4-Klasse-Tier nur über Azure OpenAI (Azure-Nonprofit-Grant) — der
  OpenAI-ChatGPT-Nonprofit-Grant deckt **keine** API.

## Claudex-Loop auf coreone — Pflichtregeln (ADR-041, ADR-055)

Gilt für Sessions mit `CLAUDEX_LOOP=1` (RC-Umgebung `marktlauf` auf coreone). Mac- und
Web-Sessions sind davon nicht betroffen — dort gilt die normale Freigabe-Kette.

**Zwei-Go-Schranke — PFLICHT, jeder Lauf (ADR-055).** Der Loop hält an genau zwei Stellen an und
wartet auf TTs ausdrückliches „Go" in der Session. Ohne Go kein nächster Schritt.

1. **Plan:** `hyper-plan-loop` (Claude plant, Codex grillt, bis keine Blocker mehr offen sind) —
   **nicht** `hyper-auto` am Stück, das liefe ohne Halt durch. Danach **Go 1** anfordern mit dieser
   Zusammenfassung, in dieser Reihenfolge:
   1. **Lebensweltbezug** — zuerst für TT als Nutzer: was ändert sich für ihn, wo und wann merkt er
      es; danach für die anderen, für die gebaut wird (Läufer, Helfer, Sponsoren, Orga-Team).
   2. **Ziel und Erfolgsmaß** — messbar (z. B. axe 2 → 0, Seite X zeigt Y).
   3. **Produktionsschritte** — was in welcher Reihenfolge wo gebaut wird (Dateien, Seiten, Migrationen).
   4. **Getroffene Entscheidungen** — gewählte Lösung, verworfene Alternativen, warum; was Codex im
      Plan-Grilling beanstandet hat und wie es gelöst wurde.
   5. **Was bewusst nicht gemacht wird** — Scope-Rand.
   6. **Risiken und Berührungspunkte** — Prod-Daten, Migrationen, öffentliche Inhalte
      (Einbahnstraße intern → website), Mails an echte Menschen.
2. **Bau:** nach Go 1 `hyper-implement-loop` + UI/UX-Gate (unten) + `hyper-recap`. Weicht der Bau
   **spürbar** vom freigegebenen Plan ab: anhalten und fragen, nicht weiterbauen.
3. **Go 2** anfordern mit **nur**: Abweichungen von Schritten (1.3) und Entscheidungen (1.4),
   Besonderheiten, Ergebnis gegen das Erfolgsmaß (1.2).
4. **Nach Go 2 merged und deployt der Loop selbst:** `git push origin HEAD:main` (nur Fast-Forward).
   Lehnt GitHub mit „Required status check "check" is expected" ab, läuft der Check auf dem
   `claudex/`-Branch noch — kurz warten, erneut. Danach Prod von außen prüfen (HTTP 200, neuer
   `?v=<sha>`), dann `git push origin --delete claudex/<thema>`.
5. Beide Zusammenfassungen als Datei nach `intern/claudex-laeufe/<JJJJ-MM-TT>-<thema>.md` — Rückkopplung.

**Leitplanken.**
- Arbeiten nur auf `claudex/<thema>`, Start mit `git fetch origin && git switch -c claudex/<thema> origin/main`.
  Push während des Baus ausschließlich `git push -u origin HEAD:claudex/<thema>` — der Push löst
  „Staging Deployment (Buehne S)" aus. Nach `main` nur der eine Fast-Forward nach Go 2.
- Hart (GitHub): `main` lässt sich weder löschen noch per Force-Push umschreiben; der Pflicht-Check
  `check` muss grün sein. Weich: `.claude/hooks/claudex-loop-guard.sh` erlaubt nur die Push-Ziele
  oben, blockt Commits direkt auf `main`, `ssh/scp/sftp/rsync` und jeden Zugriff auf die
  Sensor-Zugangsdaten. Ob das Go wirklich von TT kam, sichert **die Regel**, nicht die Technik (ADR-055).
- **Kein PR, keine Attribution-Zeile** (z. B. `Generated with Claude Code`) in Commits (Lauf 2, 25.09.).
- **Der Plan baut keine eigenen Prüf-/Test-Skripte** — Prüfer sind ausschließlich die vorhandenen
  Sensoren (`axe-check`, `accessibility-agents`, `claude-seo`). Lehre Lauf 1 (24.09.): 605-Zeilen-Plan
  mit selbstgebautem Prüfskript brach im Plan-Loop als Overbuild ab.
- **Review-Brief an Codex eng fassen.** Codex liest den Auftrag sonst breiter als gemeint und sieht
  `.hyperclaude/`-Artefakte nicht als Nachweis (Lauf 2, 25.09.: 3 von 4 Codex-Findings widerlegt).
- Bühne = nur synthetische Daten; nie echte Personendaten erzeugen oder aus Prod holen.
- Den Abschnitt „Aktueller Stand / Übergabe" fasst der Loop nicht an — seine Berichte sind die
  Zusammenfassungen aus der Zwei-Go-Schranke.

**UI/UX-Gate — PFLICHT vor „fertig"/Recap (ADR-041 Festlegung 9).** Sensoren liegen in `~/.local/bin`
(Quelle `coreone-runner/claudex-sensors`); nur sie lesen die Zugangsdaten, der Loop sieht und nennt sie nie.
0. **Baseline vor dem ersten Push:** `axe-check <geänderte Pfade>` (die Bühne zeigt dann noch den
   letzten Stand) — trennt vorbestehende von neuen Verstößen.
1. Push, dann `buehne-wait $(git rev-parse HEAD)` — wartet, bis die Bühne genau diesen Commit ausliefert.
   Die Bühne ist geteilt: sie zeigt den zuletzt gepushten `claudex/**`-Stand.
2. `axe-check <Pfade, z. B. /orga/sponsoren.php>` — axe-core, WCAG 2.2 AA, eingeloggt als Test-Admin;
   Exit 2 = kritische/ernste Verstöße. Report + Ganzseiten-Screenshot unter dem ausgegebenen `out`.
3. **accessibility-agents** auf die geänderten Seiten; **claude-seo** nur bei öffentlichen Seiten (nicht `orga/`).
4. Visuell: `buehne-login` (gibt die Basis-URL aus), dann Playwright-MCP (`browser_navigate` + Screenshot)
   gegen die Ziel-Spec der Aufgabe.
5. Ins Recap: axe-Zusammenfassung (Pfad, Verstöße nach Schwere, Baseline vs. neu), Agenten-Findings,
   Screenshot-Pfade. **Neu eingeführte** kritische/ernste Verstöße müssen behoben sein.

Ohne diese Schritte gilt der Lauf **nicht als fertig**. Automatisierte a11y fängt nur ~57 % — die
Ästhetik bleibt Screenshot-Urteil plus Mensch.

## Bühne S — Staging für Claudex (Stand 2026-09-17)

- **Zweck:** login-gated `orga/`-UI für das UI/UX-Gate des autonomen Bau-Loops sichtbar machen — auf einer
  zweiten Bühne im selben Strato-Paket, **nie** auf Prod. Zugang nur mit Basic-Auth, `noindex`; ausschließlich
  **synthetische** Daten (Prod-Schema ohne Zeilen + ein Test-Admin). Zugangsdaten und Pfade liegen **nicht im
  Repo**, sondern serverseitig unter `storage/` und im privaten Vault-Plan.
- **Deploy:** Push auf einen Branch `claudex/**` → Workflow **„Staging Deployment (Buehne S)"**
  (`.github/workflows/deploy-staging.yml`): Guard gegen den Prod-Ordner, rsync ins Staging-Ziel (eigenes Secret),
  Basic-Auth-Block wird der Repo-`.htaccess` vorangestellt, danach `migrate.php status/migrate`. Prod
  (`main` → `deploy.yml`) bleibt unberührt. **Der Loop bekommt nie den Strato-Key** — nur der Actions-Runner deployt.
- **Schema:** Die Migrationskette ist auf leerer DB **nicht** replaybar (`004` droppt einen Index, den `001` nie
  anlegte — „Run manually on server"). Staging wird deshalb aus einem **Prod-Schema-Dump ohne Daten** aufgesetzt
  und per `migrate.php baseline` markiert; neue Migrationen laufen danach normal über den Staging-Deploy.
- **Seed:** `MARKTLAUF_CLI=1 /bin/php bin/seed_staging.php` im Staging-Ordner — legt nur den Test-Admin an und
  **verweigert** den Lauf, wenn `app.environment !== 'staging'`.
- **Stumm:** Mail/Make/Gemini/Brevo sind auf der Bühne durch leere Config-Keys deaktiviert.
- **Regel (Incident 17.09.):** nach **jeder** Strato-Umleitungsänderung Prod **und** Ziel von außen prüfen.
  MySQL-Client auf Strato liest `~/.my.cnf` (= Prod) — für Staging `--defaults-file` + `DATABASE()`-Guard.
  Die Basic-Auth-Passwortdatei unter `storage/` muss für Apache lesbar sein (Gruppe `www` → Modus 644; bei 600
  antwortet Apache mit **500**, auch bei falschem Passwort). Serverseitig gepflegte Staging-Dateien gehören
  **alle** in die `EXCLUDE`-Liste des Staging-Workflows, sonst löscht `rsync --delete` sie beim nächsten Deploy.
  Datenabhängige Migrationen (Seeds mit Prod-IDs) brechen auf der leeren Bühne → Schema-Resync aus Prod
  (`mysqldump --no-data` → `baseline` → Seed), Prozedur im Vault-Plan.
- **Kanon/Details:** Vault `00_meta/plans/claudex-marktlauf-onboarding.md`.
