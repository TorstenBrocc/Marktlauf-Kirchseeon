# Drittanbieter-Komponenten

## Markdown-Rendering der Sponsorenbriefe

Die editierbaren Sponsorenbriefe (`src/sponsor_brief.php`) rendern Markdown zu HTML.
Standardmäßig geschieht das über einen **projekteigenen, abhängigkeitsfreien
Minimal-Konverter** (`sponsorMiniMarkdown()`) — er beherrscht Absätze, Zeilenumbrüche,
Fett/Kursiv, Überschriften, Listen und Links. Kein externer Code nötig.

## Grafik-Rendering (Share-Card + Postergenerator)

`orga/social_orchestrator.php` (Share-Card) und `orga/poster_generator.php` (Kampagnen-Poster)
erzeugen ihre PNGs clientseitig mit **snapDOM**. Die Lib ist **lokal ins Repo vendored**
(`assets/js/snapdom.js`) — kein CDN, keine externe Laufzeit-Abhängigkeit (robuster auf Strato,
gleiches Muster wie `assets/js/qrcode.js`). Löste html2canvas 1.4.1 ab (bessere Render-Treue bei
Schatten, Verläufen, Icon-Fonts).

- **Version:** 2.1.0 (gepinnt, IIFE-Build → globales `window.snapdom`)
- **Lizenz:** MIT (Juan Martin Muda / zumerlab)
- **Quelle:** https://github.com/zumerlab/snapdom
- **Bezug:** https://unpkg.com/@zumer/snapdom@2.1.0/dist/snapdom.js
- **SHA-256:** `d0aebcd90aa02c1438f8345e2b13669284c4d5b6298d2edf77866080da01f00a`

**Wichtig:** Wie bei Parsedown wird der Server per Deploy aus dem Repo gespiegelt — `snapdom.js`
muss daher eingecheckt bleiben, sonst verschwindet die Datei beim `rsync --delete`.

## Schriften (self-hosted, site-weit)

Die gesamte Website hält ihre Schriften self-hosted — **kein Google-Fonts-CDN** mehr im Prod-Code
(Strato-robust, DSGVO-freundlich, und snapDOM muss die Fonts vor dem Rastern lokal laden können).
Zentrale Deklaration in [`css/fonts.css`](css/fonts.css) (Single Source of Truth, per `<link>` in
`index.html`, `src/layout/head.php`, den Newsletter-Seiten und `orga/poster_generator.php` geladen);
`orga/vorlagen.php` deklariert seine Fonts weiterhin inline (eigene snapDOM-Pipeline). Die
woff2-Dateien liegen unter `assets/fonts/` und müssen — wie snapdom.js — **eingecheckt bleiben**
(`rsync --delete`). Latin-Subset deckt Deutsch vollständig ab.

- **Fredoka** (`--font-display`, Headlines/Badges) — `fredoka-latin.woff2` (variabel, Gewicht 400–700)
- **Poppins** (`--font-main`, Fließtext) — `poppins-{400,500,600,700}-latin.woff2`
- **Montserrat** (`--font-heading`, Überschriften + Poster-Generator) — `montserrat-{500,700,800,900}-latin.woff2`
- **Inter** (Fallback in `--font-main`) — `inter-{400,600,700,800}-latin.woff2`
- **Lizenz:** alle vier unter SIL Open Font License 1.1 (OFL)
- **Quelle:** Google Fonts (`fonts.gstatic.com`), Latin-Subset

---

### Optional: Parsedown (voller Markdown-Umfang)

Ist die Datei `src/Parsedown.php` vorhanden, wird sie automatisch bevorzugt
(`class_exists('Parsedown')`), sonst greift der Fallback.

- **Version:** 1.7.4 (gepinnt)
- **Lizenz:** MIT (Emanuil Rusev)
- **Quelle:** https://github.com/erusev/parsedown
- **SHA-256:** `af4a4b29f38b5a00b003a3b7a752282274c969e42dee88e55a427b2b61a2f38f`

**Wichtig:** Der Server wird per Deploy aus dem Git-Repository gespiegelt — Dateien,
die nicht im Repo liegen, werden dabei entfernt. Parsedown muss daher **ins Repo
eingecheckt** werden, um auf dem Server zu bleiben:

```bash
curl -sSL https://raw.githubusercontent.com/erusev/parsedown/1.7.4/Parsedown.php -o src/Parsedown.php
sha256sum src/Parsedown.php   # muss obigen Hash ergeben
git add -f src/Parsedown.php && git commit -m "chore: vendor parsedown 1.7.4 (MIT)" && git push
```

Sicherheit: In beiden Fällen wird vom Bearbeiter getippter Markdown HTML-escaped
gerendert (Parsedown SafeMode + MarkupEscaped bzw. der Fallback escaped ebenfalls),
sodass kein aktives HTML/JavaScript eingeschleust werden kann. Vertrauenswürdiges
HTML (Paket-Tabelle, Signatur) wird ausschließlich serverseitig über Platzhalter
eingesetzt.
