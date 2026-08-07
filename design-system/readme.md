# Marktlauf Kirchseeon — Design System

Marken- und Gestaltungsgrundlage für den **Marktlauf Kirchseeon**, das jährliche
Laufevent des **ATSV Kirchseeon 1906 e. V.** im Landkreis Ebersberg (Bayern).

Renntag 2026: Sonntag, 20. September 2026 · Start 10:00 Uhr · Start & Ziel JEK,
Westring 6, 85614 Kirchseeon · im Rahmen des Energie- & Umwelttags der Gemeinde
unter dem Motto „E-Mobilität & Sport". Distanzen: Bambini 500 m, Schüler 1 km und
2 km, Jugend & Erwachsene 5 km und 10 km.

## Die zwei Oberflächen

| Produkt | Beschreibung | Charakter |
|---|---|---|
| **Event-Website** (öffentlich) | One-Page unter https://www.atsv-kirchseeon-marktlauf.de/ — Hero, Läufe, Zeitplan, Strecken mit GPX-Karten, RaceResult-Anmeldung, Sponsoren, Newsletter & Kontakt, DE/EN | Markenschriften, Verlaufs-Hero, großzügige Abstände |
| **Orga-Dashboard** (`orga/`, login-geschützt) | Interne Zentrale: Helfer, Schichten, Sponsoren-Mini-CRM, Rechnungen, Vereine, Social-Media-Orchestrator, Dateiablage, Live-Ticker | System-Schrift, dicht, Farbe nur als Signal |

Das ist bewusst ein **Bruch**, kein Versehen: der Kommentar im Quellcode formuliert
es als „density-first — das Dashboard ist Verwaltungssoftware, keine
Marketing-Website". Wer intern baut, folgt der Dashboard-Sprache; wer nach außen
gestaltet, der Markensprache.

## Quellen dieses Systems

- **GitHub (Hauptquelle):** https://github.com/TorstenBrocc/Marktlauf-Kirchseeon —
  `css/base.css` (Tokens), `css/layout.css` (Header/Footer), `css/components.css`
  (Hero, Karten, Timeline, Tabs, Formulare, Sponsorenband), `orga/css/orga.css`
  (Dashboard), `orga/_nav.php` (Modul-Registry), `orga/helfer.php` (Tabellenmuster),
  `src/newsletter/01_identity.md` und `02_style.md` (Tonalität), `index.html`.
  Für weitere Arbeiten lohnt es sich, direkt in diesem Repository zu lesen —
  es ist die belastbarste Quelle für alles, was hier beschrieben ist.
- **Lokaler Ordner `Logo u Print/`:** Logo-Dateien, Plakate (Haupt- und Schulplakat),
  Social-Media-Vorlagen (Instagram Feed/Story) und ein bereits bestehendes
  Marktlauf-Design-System-Dokument, aus dem die Markenkern-Formulierungen,
  Farbregeln und Do/Don'ts hier übernommen sind.
- Ein Vereins-Repository `TorstenBrocc/marktlauf-intern` existiert, wurde für dieses
  System aber nicht ausgewertet.

---

## Content-Grundlagen

**Sprache.** Deutsch. Englisch existiert als vollständige Übersetzung
(`lang/en.json`), ist aber Zweitsprache — Deutsch wird zuerst geschrieben.

**Ansprache.** Duzen, im Vereinsumfeld „ihr/euch": *„Melde dich jetzt an und sei
dabei, wenn Kirchseeon läuft."* · *„Wir freuen uns auf euch!"* Formulare und der
Kontaktbereich siezen an einigen Stellen noch (*„Bleiben Sie in Kontakt!"*,
*„Wie können wir Ihnen helfen?"*) — das ist ein gewachsener Bruch, kein Muster;
bei Neuem konsequent duzen.

**Haltung** (aus `src/newsletter/01_identity.md`): bodenständig, herzlich,
ehrenamtlich getragen, lokal verwurzelt. **Kein Marketing-Sprech, keine Superlative.**

**Ton.** Herzlich, klar, sachlich-positiv. Keine Werbefloskeln, keine
Ausrufezeichen-Ketten. Konkret statt allgemein: Datum, Ort, Uhrzeit, Link nennen,
sobald sie feststehen. Keine erfundenen Fakten — was nicht bestätigt ist, wird als
Vorbehalt ausgewiesen: *„Zeitplan ist noch vorläufig und kann sich noch mal
ändern."* · *„* Streckenverlauf unter Vorbehalt - noch in Abstimmung mit Gemeinde"*.
Diese Ehrlichkeit ist Teil des Markenauftritts, nicht ein Mangel.

**Länge.** Newsletter 200–350 Wörter, 2–4 thematische Blöcke mit kleinen
Zwischenüberschriften. Betreffzeilen max. ~60 Zeichen, konkret, kein Clickbait.

**Casing.** Normale Satz-Schreibung. Versalien nur in Kickern, kleinen Labels und
Tabellenköpfen (mit Tracking .05–.16em). Keine Großschreibung ganzer Sätze.

**Zahlen & Einheiten.** Distanzen als „500 m", „1 km & 2 km", „5 km & 10 km"; in
Kompaktflächen auch „500m", „10km". Uhrzeiten „10:00 Uhr", Zeiträume mit
Gedankenstrich („12:30–13:30 Uhr"). Datum ausgeschrieben: „Sonntag, 20. September
2026". Beträge deutsch: „4.200 €".

**Trennzeichen.** Der Mittelpunkt `·` ist das Marken-Trennzeichen für Metazeilen
(„Bambini · 500 m", „Anmeldungen · 3 neu"). Der Gedankenstrich — trennt Gedanken.

**Emoji.** Praktisch nicht. Genau eine Stelle im Produktivcode: 📅 vor „Zum Kalender
hinzufügen". Neue Emoji nicht einführen. ✓/✕ dienen in den Do/Don't-Listen des
Markendokuments als Listenzeichen, nicht als Dekoration.

**Vokabular.** „Läufe" statt „Rennen", „Helfer" statt „Volunteers" (außer in der
Betreffauswahl), „Startplatz", „Siegerehrung", „Startnummern-Ausgabe", „Bambini",
„Schülerläufe", „Orga" für das Team, „Kachel" für Dashboard-Karten.

---

## Visuelle Grundlagen

**Farbe.** Grün trägt die Marke (`#009640`), Gold highlightet Zahlen und Uhrzeiten
(`#f4b81e`), Orange bleibt Aktionen und Warnsignalen vorbehalten (`#ff6b35`).
Flächen sind weiß oder `--gray-50`; Logo-Plakette und Kooperations-Pille sind
beide weiß (`--surface-plakette`). Höchstens zwei Hintergrundfarben pro Seite — Weiß und
Grau-50 im Wechsel markieren die Abschnitte.

**Verlauf.** Genau **ein** Verlauf: `linear-gradient(128deg, #12a877, #5cbd45, #bcd531)`,
groß und nur im Hero bzw. in Story-Hintergründen. Darauf liegen zwei weiche
radiale Blobs (Gold oben rechts, Grün unten links) und ein linkes Kontrast-Overlay
(`rgba(0,0,0,.38)` → transparent), damit weiße Schrift die WCAG-AA-Schwelle hält.
Keine weiteren Verläufe, insbesondere keine blau-violetten.

**Typografie.** Drei Familien mit fester Aufgabe: **Fredoka** (Display — Eventname,
Jahreszahl, Uhrzeiten), **Montserrat** (Überschriften, Kicker, Versal-Labels),
**Poppins** (Fließtext). Das Dashboard nutzt die System-Schrift. Überschriften sind
fluid (`clamp()`), der Hero läuft bis 96px bei `line-height: .92`.

**Abstände.** Die Skala ist bewusst großzügig („increased for more breathing room"):
Abschnitte trennen `6rem`, Karten im Raster liegen `3rem` auseinander. Raster sind
immer `auto-fit` mit `minmax()`, nie feste Spaltenzahlen.

**Hintergründe.** Ruhig. Keine Muster, keine Texturen, keine Bilder hinter Text.
Die einzige Ausnahme ist die diagonale Schraffur, mit der eine noch nicht
genehmigte Streckenkarte gesperrt wird. Bilder sind vollflächige Motive in
eigenen Containern, nie Untergrund für Typografie.

**Bildwelt.** Echte, lokale Laufbilder mit Familien und Kindern — warm, sonnig,
Tageslicht, natürliche Farben. Keine generischen Stockmotive, kein Schwarzweiß,
kein Grain, keine Tech- oder Extremsport-Anmutung. Die Läufer-Silhouette in Gold
ist das einzige illustrative Element.

**Ecken.** 12px für Karten der Website, 16px für Plaketten und die
Kooperations-Pille, 999px für alle CTAs und Chips, 8px für Motto-Chip und
Dashboard-Kacheln, 5px für Dashboard-Controls, 6px für Formularfelder.

**Karten.** Weiß, 1px `--gray-200`, Radius 12px, `--shadow-sm`. Im Hover: 5px
anheben, Schatten auf `--shadow-md`, Rand wird grün. Dashboard-Kacheln haben
keinen Rand, dafür `--shadow-card` und ein 3px-Ampelsignal links.

**Schatten.** Vier Stufen (sm/md/lg/xl), alle weich und nach unten versetzt.
Gold-CTAs tragen einen farbigen Schatten (`0 12px 26px -8px rgba(244,184,30,.7)`).
Innenschatten kommen nicht vor.

**Transparenz & Blur.** Sparsam. Weiße Flächen auf dem Verlauf liegen bei 94 %
Deckkraft, Sekundärbuttons auf dem Verlauf bei 14 % Weiß mit 70 % weißem Rand.
`backdrop-filter: blur(5px)` gibt es genau einmal: hinter dem Karten-Modal.

**Schutz statt Kapsel.** Text auf dem Verlauf wird durch ein Verlaufs-Overlay
geschützt, nicht durch eine Box. Logos dagegen bekommen immer eine Kapsel — die
weiße Plakette.

**Bewegung.** Kurz und funktional: 0,2 s für Controls, 0,3 s für Karten,
0,65 s für Scroll-Reveals, alles `ease`. Hover hebt an (1–5px), drückt nie zusammen;
es gibt keine Bounce-, Skalier- oder Rotationseffekte (Ausnahme: das Schließkreuz
im Modal dreht 90°). Der Hero baut sich in 0,55-s-Schritten gestaffelt auf
(0,00 → 0,68 s), die Läufer-Silhouette und das runde Logo-Badge schweben danach
langsam weiter. Das Sponsorenband läuft 28 s pro Durchlauf und pausiert im Hover.
Alle Animationen sind unter `prefers-reduced-motion: reduce` abgeschaltet.

**Hover- und Druckzustände.** Gefüllte Buttons dunkeln ab (`#009640 → #007230`,
`#f4b81e → #e5aa18`), transparente hellen auf. Links wechseln die Farbe oder
unterstreichen mit 3px Offset. Tabellenzeilen färben sich `#fafafa`. Fokus ist
immer grün: 1px grüner Rand plus `0 0 0 3px rgba(0,150,64,.1)`.

**Feste Elemente.** Nur der Header (82px, mobil 70/60px). Er liegt zunächst
transparent über dem Verlauf und wird beim Scrollen weiß mit `--shadow-sm`; dabei
verliert die Logo-Plakette ihre weiße Fläche und der CTA wechselt von Gold auf Grün.

---

## Ikonografie

**Es gibt kein Icon-System.** Die Website kommt fast ohne Icons aus — Hierarchie
entsteht über Typografie, Farbe und Fläche. Was existiert:

- **Inline-SVG im Feather-/Lucide-Stil**: 24er-Viewbox, `fill: none`,
  `stroke: currentColor`, `stroke-width: 2`, runde Enden. Verwendet für das
  „Externer Link"-Symbol (13×13 px im Hero-Kicker, 14×14 px im Dashboard) und die
  Social-Icons im Footer (28×28 px, weiß auf Plattformfarbe).
  → **Für neue Icons Lucide von CDN einbinden** (`https://unpkg.com/lucide-static`)
  und exakt in diesem Stil setzen. Das ist eine begründete Ergänzung, keine
  Vorgabe aus dem Quellcode — die vorhandenen SVGs sind handgeschrieben.
- **PNG-Icons**: die Sprachflaggen (`allemand.png`, `anglais.png`, 36×24 px) und
  ein externes GPX-Symbol von `cdn-icons-png.flaticon.com` (16×16 px).
- **Marken-SVGs**: `logo-final.svg` (Bildmarke im Kreis, auch Favicon) und
  `logo_ohne_kreis.svg` (Peak-Zeichen, als Wasserzeichen).
- **Geometrische Marker statt Icons**: farbige Punkte (9px) in den Hero-Metazeilen,
  ein 34×3px-Goldbalken vor dem Kicker, der 18px-Punkt an der Zeitplan-Achse,
  ein pulsierender 7px-Punkt im Live-Ticker, tropfenförmige Kartenpins
  (`border-radius: 50% 50% 50% 0`, -45° gedreht).
- **Emoji**: nur 📅 vor „Zum Kalender hinzufügen". Nicht ausweiten.

Alle kopierten Marken- und Bildassets liegen unter `assets/`.

---

## Bekannte Abweichung: Montserrat

Das Repository liefert nur drei Schriftdateien mit: `fredoka-latin.woff2`,
`poppins-400-latin.woff2`, `poppins-600-latin.woff2`. **Montserrat** ist in
`css/base.css` als `--font-heading` deklariert und wird im Marken-Dokument als
Label-Schrift geführt, liegt aber als Datei nicht vor. Karten und UI-Kits laden
Montserrat deshalb von Google Fonts. Poppins fehlen die Schnitte 500 und 700, die
im Quellcode verwendet werden — sie fallen derzeit auf 400/600 zurück.
Montserrat ist inzwischen als `@font-face` in `tokens/fonts.css` deklariert und
zeigt auf denselben Google-Fonts-Ursprung, den auch das Repo verwendet — es ist
also nichts zu tun. Wer die Schriften offline halten will, legt die woff2-Dateien
in `assets/fonts/` ab und tauscht die URL in `tokens/fonts.css`. Beide Familien
stehen unter der SIL Open Font License und sind frei verwendbar.

---

## Inhalt dieses Ordners

| Pfad | Inhalt |
|---|---|
| `styles.css` | Einstiegspunkt — nur `@import`-Zeilen |
| `tokens/` | `colors.css`, `typography.css`, `spacing.css`, `radii.css`, `shadows.css`, `motion.css`, `fonts.css` |
| `assets/fonts/` | Fredoka, Poppins 400/600 (woff2) |
| `assets/images/` | Logos, Wappen, Läufer-Silhouette, Eventfotos, Sponsorenlogo, Flaggen, QR-Code |
| `guidelines/` | 20 Spezimen-Karten für Farben, Typografie, Abstände, Marke |
| `components/` | Wiederverwendbare Bausteine (siehe unten) |
| `ui_kits/website/` | Nachbau der öffentlichen Event-Website |
| `ui_kits/orga_dashboard/` | Nachbau des internen Orga-Dashboards |
| `templates/` | Fertige Startpunkte (siehe unten) |
| `docs/CI-Uebersicht.md` | Alle CI-Werte in Tabellenform — Vorlage für die Dashboard-Seite „CI & Design" |
| `docs/Claude-Code-Ablage.md` | Wie das System außerhalb dieser Umgebung genutzt wird |
| `SKILL.md` | Einstieg für Claude Code / Agent Skills |
| `github.md` | Verknüpfung mit dem Quell-Repository |

## Vorlagen

| Vorlage | Zweck | Varianten |
|---|---|---|
| `templates/event-website/` | Öffentliche Seite mit Verlaufs-Hero, Läufe-Karten, Zeitplan, Footer | — |
| `templates/orga-seite/` | Interne Verwaltungsseite mit Sidebar, KPI-Kacheln, Filterleiste, Tabelle | — |
| `templates/social-post/` | Instagram-Post | Format 1:1 / 4:5 / 9:16 × Variante Verlauf / Editorial |
| `templates/plakat/` | A3-Plakat, druckfertig, mit QR-Code | Hauptplakat / Schulplakat |
| `templates/sponsoren-rechnung/` | A4-Rechnung für Sponsoring-Leistungen | — |

Die Social- und Plakat-Vorlagen folgen den vorhandenen Print- und
Social-Media-Dateien aus `Logo u Print/`; die Rechnung ist die überarbeitete,
filigrane Fassung des dort abgelegten Entwurfs (`gold-final.pdf`) — gleiche
Angaben, ruhigeres Satzbild: Haarlinien statt Rahmen, leichte Schnitte im
Fließtext, Fredoka nur für Titel und Endbetrag.

## Komponenten

**`components/core/`** — Button, Badge, Chip, Card, Alert
**`components/forms/`** — FormField, Checkbox, Tabs
**`components/site/`** — LogoPlakette, KoopBadge, SectionHeading, RunCard,
TimelineItem, RouteCard, Countdown, LiveTicker, SponsorMarquee
**`components/orga/`** — KpiTile, Kachel, DataTable, OrgaSidebar

Jede Komponente hat eine `.d.ts` (Props) und eine `.prompt.md` (Wann & Wie).

### Bewusste Ergänzungen
Der Quellcode ist reines HTML/CSS ohne Komponentenbibliothek — die Aufteilung oben
ist deshalb eine Zusammenfassung wiederkehrender CSS-Muster, keine Erfindung neuer
Bausteine. Zwei Zusammenfassungen gehen minimal über eine 1:1-Abbildung hinaus:
- **`Card`** bündelt `.run-card`, `.route-card`, `.timeline-content` und
  `.connect-card`, die im Original dieselben Werte wiederholen.
- **`FormField`** vereint `.form-group` der Website und des Dashboards über das
  `dense`-Flag, statt zwei fast gleiche Komponenten zu führen.

### Bewusst nicht gebaut
Tooltip, Toast, Avatar, Dialog (das Karten-Modal ist an Leaflet gebunden),
Accordion und Pagination gibt es im Quellcode nicht — deshalb stehen sie auch
hier nicht. Das ist kein Rückstand, sondern eine Entscheidung: eine Komponente
ohne Vorbild im Produkt sieht im Design-System verbindlich aus, obwohl niemand
sie je gestaltet hat, und Entwickler bauen dann gegen eine erfundene Vorgabe.

**Wenn du eine davon brauchst,** ist der Weg: erst im Produkt entscheiden, wie
das Ding aussehen und sich verhalten soll, dann hier nachziehen. Der wahrscheinlichste
Kandidat ist ein Dialog — sobald das Dashboard eine Bestätigungsabfrage
("Helfer wirklich ablehnen?") braucht, lohnt sich eine echte Komponente statt
`confirm()`. Sag Bescheid, dann baue ich sie auf Basis der Kachel-Werte
(Radius 8px, `--shadow-card`, 1px `--orga-border`).
