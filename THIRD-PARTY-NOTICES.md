# Drittanbieter-Komponenten & Lizenzhinweise

Vollständige Übersicht aller im Projekt genutzten Fremdkomponenten — Code-Bibliotheken
(vendored und via CDN), Schriften sowie externe Dienste/Datenverarbeiter. Stand: geprüft
gegen den Code-Bestand des Repos.

Gliederung:

1. [Client-seitige JS-Bibliotheken (CDN, on-demand)](#1-client-seitige-js-bibliotheken-cdn-on-demand)
2. [Client-seitige JS-Bibliotheken (lokal vendored)](#2-client-seitige-js-bibliotheken-lokal-vendored)
3. [Server-seitige PHP-Bibliotheken (lokal vendored)](#3-server-seitige-php-bibliotheken-lokal-vendored)
4. [Schriften (self-hosted)](#4-schriften-self-hosted)
5. [Eingebettete Fremd-Widgets](#5-eingebettete-fremd-widgets)
6. [Externe Dienste & Datenverarbeiter](#6-externe-dienste--datenverarbeiter)
7. [Optional: Parsedown](#7-optional-parsedown-voller-markdown-umfang)
8. [Eigenentwicklung ohne Fremdcode](#8-eigenentwicklung-ohne-fremdcode)

---

## 1. Client-seitige JS-Bibliotheken (CDN, on-demand)

Die Streckenkarten (`js/maps.js`) laden ihre Kartenbibliotheken **erst bei Bedarf** nach
(kein Blocking im `<head>`, senkt die Total Blocking Time). Alle Bezüge über öffentliche CDNs.

| Komponente | Version | Lizenz | Bezug | Beleg |
|---|---|---|---|---|
| **Leaflet** | 1.9.4 | BSD-2-Clause | `unpkg.com/leaflet@1.9.4` | `js/maps.js:49,51` |
| **leaflet-gpx** | 1.7.0 | BSD-2-Clause (Maxime Petazzoni) | `cdnjs.cloudflare.com/.../leaflet-gpx/1.7.0/gpx.min.js` | `js/maps.js:54` |
| **@raruto/leaflet-elevation** | 2.5.2 | **GPL-3.0** (Copyleft) | `unpkg.com/@raruto/leaflet-elevation@2.5.2` | `js/maps.js:65,66` |
| **OpenStreetMap-Kacheln** | – | Kartendaten: **ODbL** · Kacheln: OSMF Tile-Usage-Policy | `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png` | `js/maps.js:136,219` |

**Hinweis leaflet-elevation (GPL-3.0):** Die Bibliothek steht unter der Copyleft-Lizenz
GPL-3.0. Sie wird ausschließlich **zur Laufzeit vom fremden CDN nachgeladen** und ist **nicht
Teil dieses Repositories** (wird also nicht mit-verteilt) — das bloße Einbinden per
`<script src>` auf einer öffentlichen Website löst die Copyleft-Pflichten für den eigenen
Website-Code nicht aus. Sollte die Bibliothek jemals **lokal ins Repo vendored** werden,
ist die GPL-3.0-Kompatibilität mit dem restlichen Code neu zu bewerten (dann würde der
kombinierte Auslieferungsstand betroffen sein).

**Hinweis OpenStreetMap:** Die Attribution ist auf den Karten sichtbar hinterlegt.
Nutzung der Standard-Tiles unterliegt der [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/)
der OpenStreetMap Foundation; bei steigender Last ist ein eigener/kommerzieller Tile-Server
vorzusehen.

## 2. Client-seitige JS-Bibliotheken (lokal vendored)

Diese Libs liegen **lokal im Repo** (`assets/js/`) — kein CDN, keine externe Laufzeit-Abhängigkeit
(robuster auf Strato Shared Hosting). Da der Server per Deploy aus dem Repo gespiegelt wird
(`rsync --delete`), **müssen** diese Dateien eingecheckt bleiben.

### snapDOM

DOM-zu-Bild-Rendering für Share-Cards und Poster (`orga/social_orchestrator.php`,
`orga/poster_generator.php`, `orga/vorlagen.php`). Löste html2canvas 1.4.1 ab (bessere
Render-Treue bei Schatten, Verläufen, Icon-Fonts).

- **Datei:** `assets/js/snapdom.js` (IIFE-Build → globales `window.snapdom`)
- **Version:** 2.1.0 (gepinnt)
- **Lizenz:** MIT (Juan Martin Muda / zumerlab)
- **Quelle:** https://github.com/zumerlab/snapdom
- **Bezug:** https://unpkg.com/@zumer/snapdom@2.1.0/dist/snapdom.js
- **SHA-256:** `d0aebcd90aa02c1438f8345e2b13669284c4d5b6298d2edf77866080da01f00a`

### QR Code Generator (qrcode.js)

QR-Code-Erzeugung für Share-/Poster-Grafiken (in denselben Seiten wie snapDOM eingebunden).

- **Datei:** `assets/js/qrcode.js`
- **Version:** keine Versionsnummer im Header (d-project-Referenzimplementierung)
- **Lizenz:** MIT (Copyright © 2009 Kazuhiko Arase)
- **Quelle:** http://www.d-project.com/
- **Hinweis:** „QR Code" ist eine eingetragene Marke von DENSO WAVE INCORPORATED.

## 3. Server-seitige PHP-Bibliotheken (lokal vendored)

### FPDF

PDF-Erzeugung (u. a. Rechnungen, `src/rechnung_pdf.php`). Reines PHP, kein Composer.

- **Verzeichnis:** `lib/fpdf/` (inkl. `makefont/ttfparser.php`, Font-Metriken unter `lib/fpdf/font/`)
- **Version:** FPDF 1.86 · TTFParser 1.11
- **Lizenz:** permissiv/MIT-artig — eigene FPDF-Lizenz (freie Nutzung, Modifikation und
  Weitergabe ohne Gebühr; „AS IS", keine Gewährleistung). Volltext: [`lib/fpdf/license.txt`](lib/fpdf/license.txt)
- **Autor:** Olivier Plathey
- **Quelle:** http://www.fpdf.org/

## 4. Schriften (self-hosted)

Die gesamte Website hält ihre Schriften **self-hosted** — **kein Google-Fonts-CDN** im
Prod-Code (Strato-robust, DSGVO-freundlich, und snapDOM muss die Fonts vor dem Rastern lokal
laden können). Zentrale Deklaration in [`css/fonts.css`](css/fonts.css) (Single Source of
Truth, per `<link>` u. a. in `index.html`, `src/layout/head.php`, den Newsletter-Seiten und
`orga/poster_generator.php`); `orga/vorlagen.php` deklariert seine Fonts weiterhin inline
(eigene snapDOM-Pipeline). Die woff2-Dateien liegen unter `assets/fonts/` und müssen — wie
snapdom.js — **eingecheckt bleiben** (`rsync --delete`). Latin-Subset deckt Deutsch vollständig ab.

- **Fredoka** (`--font-display`, Headlines/Badges) — `fredoka-latin.woff2` (variabel, Gewicht 400–700)
- **Poppins** (`--font-main`, Fließtext) — `poppins-{400,500,600,700}-latin.woff2`
- **Montserrat** (`--font-heading`, Überschriften + Poster-Generator) — `montserrat-{500,700,800,900}-latin.woff2`
- **Inter** (Fallback in `--font-main`) — `inter-{400,600,700,800}-latin.woff2`
- **Lizenz:** alle vier unter **SIL Open Font License 1.1 (OFL)**
- **Quelle:** Google Fonts (`fonts.gstatic.com`), Latin-Subset

## 5. Eingebettete Fremd-Widgets

### RaceResult-Registrierungs-Widget

Externes Anmelde-Widget des Zeitmess-/Anmeldedienstleisters, per `<script src>` eingebunden.

- **Bezug:** `https://events2.raceresult.com/registrations/init.js?lang=de-de`
- **Eingebunden in:** `index.html:563` (Einzelanmeldung), `anmeldung-familie.php` (Sammelanmeldung)
- **Art:** proprietäres SaaS-Widget des Anbieters (RACE RESULT AG). Datenschutz siehe `datenschutz.html`.

## 6. Externe Dienste & Datenverarbeiter

Serverseitig angebundene Dienste. Zugangsdaten liegen ausschließlich in der nicht
eingecheckten `storage/config.php` (Vorlage: `storage/config.sample.php`). Diese Dienste sind
zugleich datenschutzrechtlich relevant (Auftragsverarbeiter / Empfänger) — siehe `datenschutz.html`.

| Dienst | Zweck | Beleg |
|---|---|---|
| **Brevo** (Sendinblue GmbH) | Newsletter-Double-Opt-in & Versand | `index.html` (Formular), `datenschutz.html` |
| **RaceResult** (RACE RESULT AG) | Anmeldung & Ergebnis-Abruf (Simple API) | `src/raceresult_client.php`, `index.html` |
| **Google Gemini** (Google) | LLM-Provider (Default), Modell `gemini-2.0-flash` | `src/llm_client.php` |
| **Mistral AI** | LLM-Provider (Alternative), Modell `mistral-small-latest` | `src/llm_client.php` |
| **Google Drive API v3** | Datei-Backend der Orga-Dateiablage (OAuth, Refresh-Token) | `src/google_drive.php`, `bin/gdrive_auth.php` |
| **SMTP (Strato)** | Transaktions-Mailversand über nativen `SmtpMailer` | `src/mailer.php` |

## 7. Optional: Parsedown (voller Markdown-Umfang)

Die editierbaren Sponsoren-/Vereinsbriefe rendern Markdown zu HTML. Ist die Datei
`src/Parsedown.php` vorhanden, wird sie automatisch bevorzugt (`class_exists('Parsedown')`),
sonst greift der projekteigene, abhängigkeitsfreie Minimal-Konverter (siehe Abschnitt 8).
**Parsedown ist derzeit nicht ins Repo eingecheckt** — es läuft der Fallback.

- **Version:** 1.7.4 (gepinnt, falls nachgerüstet)
- **Lizenz:** MIT (Emanuil Rusev)
- **Quelle:** https://github.com/erusev/parsedown
- **SHA-256:** `af4a4b29f38b5a00b003a3b7a752282274c969e42dee88e55a427b2b61a2f38f`

Nachrüsten (bleibt sonst beim `rsync --delete` nicht auf dem Server):

```bash
curl -sSL https://raw.githubusercontent.com/erusev/parsedown/1.7.4/Parsedown.php -o src/Parsedown.php
sha256sum src/Parsedown.php   # muss obigen Hash ergeben
git add -f src/Parsedown.php && git commit -m "chore: vendor parsedown 1.7.4 (MIT)" && git push
```

**Sicherheit:** In beiden Fällen wird vom Bearbeiter getippter Markdown HTML-escaped
gerendert (Parsedown SafeMode + MarkupEscaped bzw. der Fallback escaped ebenfalls), sodass
kein aktives HTML/JavaScript eingeschleust werden kann. Vertrauenswürdiges HTML (Paket-Tabelle,
Signatur) wird ausschließlich serverseitig über Platzhalter eingesetzt.

## 8. Eigenentwicklung ohne Fremdcode

Der Vollständigkeit halber — hier wird bewusst **kein** Fremdcode genutzt:

- **Markdown-Minimal-Konverter** (`sponsorMiniMarkdown()` in `src/sponsor_brief.php`):
  projekteigener, abhängigkeitsfreier Konverter (Absätze, Zeilenumbrüche, Fett/Kursiv,
  Überschriften, Listen, Links) — Fallback, wenn Parsedown fehlt.
- **Kein Frontend-Framework:** kein React/Vue/Bootstrap — Vanilla HTML5/CSS3/ES6+.
- **Kein Composer:** die serverseitigen Bausteine (`src/`) sind Eigenentwicklung; die einzige
  vendored PHP-Lib ist FPDF (Abschnitt 3).
