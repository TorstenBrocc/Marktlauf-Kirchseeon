# Kampagnen-Poster-Generator — Doku & Ausbaustufen

**Datei:** `orga/poster_generator.php`
**Erreichbar:** Orga-Bereich → Social-Media-Seite (`orga/social_orchestrator.php`) → Modul 3 → Button **„📣 Kampagnen-Poster … erstellen →"**
**Zweck:** On-Brand-Poster (z. B. „Anmeldung geöffnet") self-service erstellen — editierbare Inhalte, Logos/Sponsoren auf Kacheln, QR-Code, Export als PNG. Marketing-Qualität aus dem Tool, ohne externes Design-Programm.

> **Wie referenziere ich eine Ausbaustufe?** Über **„Stufe N"** und den zugehörigen **Commit-SHA** (siehe Tabelle unten). Beispiel: „Bitte auf Stand **Stufe 3 (`87830e0`)** aufsetzen." Die vollständige Historie: `git log --oneline -- orga/poster_generator.php`.

---

## Funktionsumfang (aktuell = Stufe 5)

- **Einzelne Elemente statt Sammel-Blöcke:** Marktlauf- & ATSV-Logo sind **getrennte** Elemente; die drei Info-Kacheln (Datum/Ort/Familie) sind **einzeln** verschieb-/skalier-/gruppierbar (behebt „3 Kacheln nicht trennbar").
- **Icon-Bibliothek (~26 Icons):** je Info-Kachel, je eigenem Element und je Feature-Zeile ein Icon **auswählen/tauschen** (Feature-Icons im linken Panel, Kachel-/Element-Icons im Auswahl-Panel mit Live-Vorschau).
- **Bild-/Logo-Bibliothek:** je Logo-/Gemeinde-/eigenem Element das Bild aus der Bibliothek wählen **oder eigenes Bild hochladen** (Upload landet in der Bibliothek und ist wiederverwendbar). Damit ist das Marktlauf-Logo **austauschbar**, wenn es sich ändert. Jedes Bild ist frei skalierbar.
- **Kachel-Hintergrund an/aus („Schrift & Kachel trennen"):** pro Logo-/Info-/eigenem Element lässt sich die weiße Kachel abschalten → Text/Icon/Logo steht frei auf dem Poster (Text wird dann automatisch weiß).
- **Zoom:** Zoom-Leiste über der Vorschau (−/+/Einpassen) **und Strg/Cmd + Mausrad**; bei >100 % wird die Bühne scrollbar.
- **Textüberlauf behoben:** Info-Kacheln sind Einzelelemente mit Titel · Zusatz (Split am „·"), Kartenhöhe wächst mit dem Inhalt.

<details><summary>Funktionsumfang Stufe 4 (weiterhin enthalten)</summary>

- **Formate:** Portrait 1080×1350, Quadrat 1080×1080, Story 1080×1920. Export in 2× (scharf).
- **Editierbare Inhalte (Panel links):** Headline, Subline, Button-Text, 3 Feature-Zeilen (Titel/Zusatz), Datum, Ort, Familie, Domain, QR-Ziel-URL.
- **Logos auf weißen Kacheln (fix):** Marktlauf + ATSV (oben links), Gemeinde „IN KOOPERATION MIT" (oben rechts).
- **Sponsoren (variabel):** automatisch aus `assets/images/sponsoren/` geladen, je Sponsor eine weiße Kachel, per Häkchen ein-/ausblendbar. Neuer Sponsor = Datei in den Ordner legen.
- **Frei-Editor:** jeden Block anklicken → **ziehen** (verschieben) → **skalieren** per Eck-Handle *oder* Regler.
- **Arbeitsfläche (Pasteboard):** rund um das Poster liegt eine graue Arbeitsfläche (PAD = 340 px allseitig). Blöcke lassen sich **neben das Poster ziehen und dort „ablegen"** — abgelegte Elemente erscheinen **nicht** im Export (nur der Poster-Bereich wird ausgeschnitten). Poster-Optik (`#pg-art`, geclippt) ist von den Blöcken (Szene `#pg-scene`) getrennt.
- **Gruppieren:** mit **Shift-Klick** mehrere Blöcke wählen → **„🔗 Gruppieren"**. Gruppen werden gemeinsam verschoben und (über das Eck-Handle) gemeinsam skaliert; gestrichelte Umrandung markiert Gruppenmitglieder. **„Gruppierung lösen"** hebt sie wieder auf.
- **Justierbarer Marken-Verlauf:** Hintergrund-Verlauf an/aus, **Winkel** (0–360°), **zwei Farben** (Default Marken-Grün `#00562a` / `#007230`) und **Foto-Durchsicht am Rand** (Transparenz des Endstopps). Liegt als Overlay über dem optionalen Hintergrundfoto.
- **Snapping:** beim Verschieben rasten Kanten/Mittelachsen (der Auswahl-Bounding-Box) an anderen Blöcken und an Poster-Kanten/-Mitte ein; **orangene Fanglinien** zeigen die Ausrichtung.
- **Vorschau-Feld resizable:** unten rechts am Vorschaurahmen ziehen → Szene (Poster + Arbeitsfläche) wächst mit.
- **Eigene Kacheln:** „+ Eigene Kachel" → weiße Kachel mit **Beschriftung** + **Logo** (Dropdown: ATSV/Marktlauf/Gemeinde/Sponsoren), frei verschieb-/skalierbar, löschbar.
- **QR-Code:** aus der QR-Ziel-URL erzeugt (lokal, ohne externe Abhängigkeit), sitzt in der „Scan"-Kachel.
- **Hintergrundfoto:** optionaler Upload (Vollflächen-BG unter dem Verlauf).
- **„Layout zurücksetzen":** stellt Standard-Positionen her, entfernt eigene Kacheln, Gruppierungen — und setzt Icons/Bilder/Kachel-Optionen zurück.

</details>

---

## Ausbaustufen (Changelog)

| Stufe | Commit | Inhalt |
|---|---|---|
| **0 – Entwurf** | `38556a2` | Erstes editierbares On-Brand-Poster: Textfelder, Foto-Upload, QR per Klick, PNG-Export 2× (eigene Orga-Seite, isoliert von der Ergebnis-Card in Modul 3). |
| *(Fix)* | `e37c5aa` | Korrekte Orga-Hülle (`orga.css` + `_sidebar.php`) statt öffentlicher Seiten-Hülle — Seite war sonst ungestylt. |
| *(Fix)* | `c5d86e4` | `$pdo`/`$user`/`$isAdmin` für `_sidebar.php` gesetzt + `dashboard-layout`-`<div>` geschlossen — Seite lud sonst nicht (Fatal). |
| **1 – Formate & Kacheln** | `972a345` | Format-Auswahl (Portrait/Quadrat/Story), fixe Vorschaugröße (kein Abschneiden), echte Logos + Sponsoren auf weißen Kacheln (Sponsoren per PHP-glob). |
| **2 – Frei-Editor** | `73047c3` | Blöcke frei verschiebbar (Drag) + skalierbar (Größen-Regler); löst Overflow/Cutoff (Nutzer positioniert selbst). |
| **3 – Editor v2** | `87830e0` | Vorschau-Feld resizable (Poster wächst mit), Eck-Handle-Resize direkt am Element, Snapping + Fang-/Ausrichtungslinien, eigene Kacheln (Text + Logo, skalierbar). |
| **4 – Arbeitsfläche + Gruppen + Verlauf** | `bae18ec` | **Arbeitsfläche (Pasteboard)** rund ums Poster (Elemente ablegen, nicht im Export — Trennung `#pg-scene`/`#pg-art`, Szenen-Koordinaten mit PAD-Offset, Export croppt Poster-Bereich per `drawImage`); **Gruppieren** per Shift-Klick (bbox-basiertes gemeinsames Verschieben/Skalieren); **justierbarer Marken-Verlauf** (an/aus, Winkel, 2 Farben, Rand-Durchsicht). |
| **5 – Element-Editor + Bibliotheken + Zoom** *(aktuell)* | `870b5da` | Feste Sammel-Blöcke in **Einzelelemente** aufgelöst (Logos einzeln, 3 Info-Kacheln einzeln → behebt Überlauf & „nicht trennbar"); **Icon-Bibliothek** (~26) + **Bild-/Logo-Bibliothek mit Upload** je Element (setzen/tauschen/skalieren, Marktlauf-Logo austauschbar); **Kachel-Hintergrund an/aus** je Element (`.pg-notile`, Text→weiß) = „Schrift & Kachel trennen"; **Zoom** (Leiste + Strg/Cmd+Mausrad, scrollbare Bühne via `#pg-canvas`); Content-/Editier-Metadaten in JS-Objekt `meta[id]`. |

---

## Technik-Notizen

- **Orga-Seiten-Muster:** eigene `<head>` mit `css/orga.css` (relativ) + `<?php $activeNav='…'; require _sidebar.php; ?>` + `<main class="main-content">`; `_sidebar.php` braucht `$pdo`, `$user`, `$isAdmin` und öffnet `<div class="dashboard-layout">`, das die Seite mit `</div>` nach `</main>` selbst schließt. **Nicht** `src/layout/head.php|header.php` verwenden (die sind für die öffentliche Website).
- **QR-Bibliothek:** `assets/js/qrcode.js` (qrcode-generator, MIT, lokal — keine externe Runtime-Abhängigkeit). Rendering via Canvas → PNG-DataURL.
- **Export:** `html2canvas` (CDN) mit `scale:2`; Auswahlrahmen/Fanglinien werden vor dem Export ausgeblendet.
- **Szene vs. Artboard (ab Stufe 4):** `#pg-scene` ist die skalierte Bühne (Poster + Arbeitsfläche, Größe `curW/curH + 2·PAD`). `#pg-art` ist das geclippte Poster-Rechteck bei `(PAD,PAD)` und trägt Hintergrund/Verlauf. Blöcke (`.pb`), Selbox und Fanglinien liegen als Kinder von `#pg-scene` in **Szenen-Koordinaten** (Poster-Koordinate + PAD). `DEFAULTS` bleiben in Poster-Koordinaten und werden via `baseDefaults()` um PAD verschoben. `scene._scale` hält den Fit-Faktor.
- **Blöcke:** absolut positioniert (`.pb`), Größe via `transform: scale()`. Standard-Positionen im JS-Objekt `DEFAULTS` (Poster-Koordinaten). Jeder Block trägt `data-kind` (`logo`/`coop`/`text`/`features`/`dcard`/`sponsors`/`scan`/`custom`), das steuert, welche Editier-Zeilen im Auswahl-Panel erscheinen.
- **Element-Metadaten (ab Stufe 5):** `meta[id] = {icon, img, tile, cap}` hält die editierbaren Content-Eigenschaften (Geometrie bleibt in `pos[id]`). `renderEl(id)` rendert je nach `kind`. Bibliotheken: `ICONS`/`ICON_LIST` (Icon-SVGs, erben Stroke-Farbe aus dem Kontext-CSS) und `LOGOS` (Key→URL; Uploads werden als `Eigenes Bild N` = DataURL ergänzt). Feature-Icons stehen in `featIcons[]`.
- **Kachel-Hintergrund (ab Stufe 5):** Klasse `.pg-notile` schaltet weiße Kachel + Schatten + Padding ab und färbt Text/Icon weiß — pro Element via `meta[id].tile` togglebar.
- **Zoom (ab Stufe 5):** effektiver Maßstab = `fitScale · zoom`. `refit()` = `stage.clientWidth / sceneW` (Einpassen), `applyView()` setzt Transform + `#pg-canvas`-Maße + Stage-Höhe/Overflow. Bei `zoom>1` wird die Bühne horizontal scrollbar (vertikal wächst die Stage → Seiten-Scroll). Overflow-Y bleibt aus → kein Rückkopplungs-Loop mit dem ResizeObserver.
- **Gruppen (ab Stufe 4):** `groupOf[blockId] = gid`; Auswahl (`selIds[]`) expandiert beim Anklicken auf alle Gruppenmitglieder. Verschieben/Skalieren rechnen über die gemeinsame Bounding-Box (`bbox()`).
- **Verlauf (ab Stufe 4):** `applyGrad()` baut den `linear-gradient` für `#pg-ov` aus den Reglern (Winkel/Farben/Rand-Alpha). Feste Marken-Stopps 0.92/0.78 bei 0 %/46 %, Endstopp variabel.
- **Export-Crop (ab Stufe 4):** `html2canvas(scene, {scale:2})` rendert die ganze Szene, dann kopiert `drawImage` nur das Poster-Rechteck (`PAD·2 … curW·2`) in das Ausgabe-Canvas — abgelegte Pasteboard-Elemente fallen weg.
- **Schrift:** aktuell Montserrat (Google Fonts) als Platzhalter → soll gegen die Marken-Schrift (`.woff2`) getauscht werden.

## Offene Punkte / Roadmap

- **Export-Fidelity prüfen:** html2canvas rendert die Szene bei `scale:2`, der Crop schneidet den Poster-Bereich exakt aus — exportiertes PNG stichprobenartig mit der Vorschau abgleichen (v. a. Schrift-Kerning/Schatten).
- **Marken-Schrift** (`.woff2`) statt Montserrat einsetzen.
- **Lizenziertes Läufer-Foto** hinterlegen (Marketing-Motiv; Lizenz klären).
- **Quadrat/Story-Layout** feintunen (Standard-Positionen sind auf Portrait optimiert).

---

## Im nächsten Chat am Poster weiterarbeiten

1. Neuen Chat öffnen (Claude Code im Repo `Marktlauf-Projekt/website`).
2. Skill `/remote-control` aufrufen **oder** direkt schreiben.
3. Diesen Satz als Einstieg verwenden (Copy-Paste):

   > **„Weiter am Kampagnen-Poster-Generator (`orga/poster_generator.php`). Aktueller Stand: Stufe 5 (`870b5da`), dokumentiert in `website/docs/poster-generator.md`. Bitte Doku + `git log --oneline -- orga/poster_generator.php` lesen, dann: [dein Anliegen]."**

4. Sinnvolle nächste Themen stehen unter **„Offene Punkte / Roadmap"** (Export-Fidelity prüfen, Marken-Schrift `.woff2`, lizenziertes Foto, Quadrat/Story-Feintuning).

> Merke: Poster-Arbeit = **Website-Repo** (dieser Ordner). Die **RR14-Timing-/Anmelde-Themen (Phase 4 usw.)** laufen getrennt über das RaceResult-Setup — dafür gibt es einen eigenen Einstieg („Weiter mit RR14 Phase 4").

---

*Verwandt:* QR-Code **auf der Ergebnis-Grafik** (Modul 3 der Social-Seite) sowie das **Anlass/Thema-Dropdown „Contentplan 2026"** sind separat in `orga/social_orchestrator.php` umgesetzt (Commits `37d9484`, `3b089af`).
