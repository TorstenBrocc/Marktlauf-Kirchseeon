# Kampagnen-Poster-Generator — Doku & Ausbaustufen

**Datei:** `orga/poster_generator.php`
**Erreichbar:** Orga-Bereich → Social-Media-Seite (`orga/social_orchestrator.php`) → Modul 3 → Button **„📣 Kampagnen-Poster … erstellen →"**
**Zweck:** On-Brand-Poster (z. B. „Anmeldung geöffnet") self-service erstellen — editierbare Inhalte, Logos/Sponsoren auf Kacheln, QR-Code, Export als PNG. Marketing-Qualität aus dem Tool, ohne externes Design-Programm.

> **Wie referenziere ich eine Ausbaustufe?** Über **„Stufe N"** und den zugehörigen **Commit-SHA** (siehe Tabelle unten). Beispiel: „Bitte auf Stand **Stufe 3 (`87830e0`)** aufsetzen." Die vollständige Historie: `git log --oneline -- orga/poster_generator.php`.

---

## Funktionsumfang (aktuell = Stufe 3)

- **Formate:** Portrait 1080×1350, Quadrat 1080×1080, Story 1080×1920. Export in 2× (scharf).
- **Editierbare Inhalte (Panel links):** Headline, Subline, Button-Text, 3 Feature-Zeilen (Titel/Zusatz), Datum, Ort, Familie, Domain, QR-Ziel-URL.
- **Logos auf weißen Kacheln (fix):** Marktlauf + ATSV (oben links), Gemeinde „IN KOOPERATION MIT" (oben rechts).
- **Sponsoren (variabel):** automatisch aus `assets/images/sponsoren/` geladen, je Sponsor eine weiße Kachel, per Häkchen ein-/ausblendbar. Neuer Sponsor = Datei in den Ordner legen.
- **Frei-Editor:** jeden Block anklicken → **ziehen** (verschieben) → **skalieren** per Eck-Handle *oder* Regler.
- **Snapping:** beim Verschieben rasten Kanten/Mittelachsen an anderen Blöcken und an der Poster-Mitte ein; **orangene Fanglinien** zeigen die Ausrichtung.
- **Vorschau-Feld resizable:** unten rechts am Vorschaurahmen ziehen → Poster wächst mit.
- **Eigene Kacheln:** „+ Eigene Kachel" → weiße Kachel mit **Beschriftung** + **Logo** (Dropdown: ATSV/Marktlauf/Gemeinde/Sponsoren), frei verschieb-/skalierbar, löschbar.
- **QR-Code:** aus der QR-Ziel-URL erzeugt (lokal, ohne externe Abhängigkeit), sitzt in der „Scan"-Kachel.
- **Hintergrundfoto:** optionaler Upload (Vollflächen-BG unter grünem Verlauf).
- **„Layout zurücksetzen":** stellt Standard-Positionen her, entfernt eigene Kacheln.

---

## Ausbaustufen (Changelog)

| Stufe | Commit | Inhalt |
|---|---|---|
| **0 – Entwurf** | `38556a2` | Erstes editierbares On-Brand-Poster: Textfelder, Foto-Upload, QR per Klick, PNG-Export 2× (eigene Orga-Seite, isoliert von der Ergebnis-Card in Modul 3). |
| *(Fix)* | `e37c5aa` | Korrekte Orga-Hülle (`orga.css` + `_sidebar.php`) statt öffentlicher Seiten-Hülle — Seite war sonst ungestylt. |
| *(Fix)* | `c5d86e4` | `$pdo`/`$user`/`$isAdmin` für `_sidebar.php` gesetzt + `dashboard-layout`-`<div>` geschlossen — Seite lud sonst nicht (Fatal). |
| **1 – Formate & Kacheln** | `972a345` | Format-Auswahl (Portrait/Quadrat/Story), fixe Vorschaugröße (kein Abschneiden), echte Logos + Sponsoren auf weißen Kacheln (Sponsoren per PHP-glob). |
| **2 – Frei-Editor** | `73047c3` | Blöcke frei verschiebbar (Drag) + skalierbar (Größen-Regler); löst Overflow/Cutoff (Nutzer positioniert selbst). |
| **3 – Editor v2** *(aktuell)* | `87830e0` | Vorschau-Feld resizable (Poster wächst mit), Eck-Handle-Resize direkt am Element, Snapping + Fang-/Ausrichtungslinien, eigene Kacheln (Text + Logo, skalierbar). |

---

## Technik-Notizen

- **Orga-Seiten-Muster:** eigene `<head>` mit `css/orga.css` (relativ) + `<?php $activeNav='…'; require _sidebar.php; ?>` + `<main class="main-content">`; `_sidebar.php` braucht `$pdo`, `$user`, `$isAdmin` und öffnet `<div class="dashboard-layout">`, das die Seite mit `</div>` nach `</main>` selbst schließt. **Nicht** `src/layout/head.php|header.php` verwenden (die sind für die öffentliche Website).
- **QR-Bibliothek:** `assets/js/qrcode.js` (qrcode-generator, MIT, lokal — keine externe Runtime-Abhängigkeit). Rendering via Canvas → PNG-DataURL.
- **Export:** `html2canvas` (CDN) mit `scale:2`; Auswahlrahmen/Fanglinien werden vor dem Export ausgeblendet.
- **Blöcke:** absolut positioniert (`.pb`, Poster-Pixel-Koordinaten), Größe via `transform: scale()`. Standard-Positionen im JS-Objekt `DEFAULTS`.
- **Schrift:** aktuell Montserrat (Google Fonts) als Platzhalter → soll gegen die Marken-Schrift (`.woff2`) getauscht werden.

## Offene Punkte / Roadmap

- **Export-Fidelity prüfen:** html2canvas + `transform: scale` — exportiertes PNG mit der Vorschau abgleichen; falls Abweichung, Skalierung vor Export in echte Maße umrechnen.
- **Marken-Schrift** (`.woff2`) statt Montserrat einsetzen.
- **Lizenziertes Läufer-Foto** hinterlegen (Marketing-Motiv; Lizenz klären).
- **Quadrat/Story-Layout** feintunen (Standard-Positionen sind auf Portrait optimiert).

---

## Im nächsten Chat am Poster weiterarbeiten

1. Neuen Chat öffnen (Claude Code im Repo `Marktlauf-Projekt/website`).
2. Skill `/remote-control` aufrufen **oder** direkt schreiben.
3. Diesen Satz als Einstieg verwenden (Copy-Paste):

   > **„Weiter am Kampagnen-Poster-Generator (`orga/poster_generator.php`). Aktueller Stand: Stufe 3 (`87830e0`), dokumentiert in `website/docs/poster-generator.md`. Bitte Doku + `git log --oneline -- orga/poster_generator.php` lesen, dann: [dein Anliegen]."**

4. Sinnvolle nächste Themen stehen unter **„Offene Punkte / Roadmap"** (Export-Fidelity prüfen, Marken-Schrift `.woff2`, lizenziertes Foto, Quadrat/Story-Feintuning).

> Merke: Poster-Arbeit = **Website-Repo** (dieser Ordner). Die **RR14-Timing-/Anmelde-Themen (Phase 4 usw.)** laufen getrennt über das RaceResult-Setup — dafür gibt es einen eigenen Einstieg („Weiter mit RR14 Phase 4").

---

*Verwandt:* QR-Code **auf der Ergebnis-Grafik** (Modul 3 der Social-Seite) sowie das **Anlass/Thema-Dropdown „Contentplan 2026"** sind separat in `orga/social_orchestrator.php` umgesetzt (Commits `37d9484`, `3b089af`).
