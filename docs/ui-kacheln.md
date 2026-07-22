# UI-Pattern: Kacheln (responsive Karten)

**Verbindliches Layout-Pattern für das Orga-Dashboard.** Inhalte (Listen,
Objekte, Übersichten) werden **nicht seitenbreit** dargestellt, sondern in
responsiven Karten ("Kacheln"), die automatisch umbrechen und auf schmalen
Bildschirmen einspaltig untereinander stehen (nur vertikales Scrollen).

## Verwendung

Markup:

```html
<div class="kachel-grid">
    <div class="kachel"> … Inhalt einer Karte … </div>
    <div class="kachel"> … </div>
</div>
```

CSS ist zentral in `orga/css/orga.css` definiert — **nicht** pro Seite
duplizieren:

- `.kachel-grid` — responsives Raster
  (`grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))`),
  bricht automatisch um, mobil einspaltig.
- `.kachel` — einzelne Karte (Fläche, Rand, Radius, Schatten).

Seitenspezifisches Innenleben (z. B. `.schicht-kachel`, `.beitrag-kachel`)
ergänzt nur den Inhalt **innerhalb** einer `.kachel` und wird im `<style>`-Block
der jeweiligen Seite gehalten.

## Gruppieren

Längere Listen nach fachlichem Kriterium gruppieren, je Gruppe eine Überschrift
mit grüner Trennlinie und darunter ein eigenes `.kachel-grid`:

```html
<h2 class="tag-heading">Sonntag · 20.09.2026</h2>
<div class="kachel-grid"> … </div>
```

## Wo bereits im Einsatz

- **Cockpit** (`orga/index.php`): KPI-Variante `.dashboard-grid` + `.card`
  (Ampel-Signal im linken Rand). Das ist die Kachel-Optik für Kennzahlen.
- **Einsatzplan** (`orga/schichten.php`): Schichten nach Tag gruppiert, je
  Schicht eine Kachel mit zugeteilten/gemeldeten Helfern.
- **Kuchen & Sonstiges** (`orga/beitraege.php`): je Beitrag eine Kachel.

## Regel für neue Seiten

Neue Orga-Seiten mit Listen/Objekten verwenden `.kachel-grid` + `.kachel`.
Keine seitenbreiten Tabellen für Objektlisten mehr anlegen; Tabellen nur noch
für echte tabellarische Daten (viele gleichartige Spalten), und dann in einem
`overflow-x: auto`-Container.
