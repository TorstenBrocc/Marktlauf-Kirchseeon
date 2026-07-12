# Drittanbieter-Komponenten

Dieses Projekt nutzt die folgende externe Open-Source-Komponente. Sie ist bewusst
**nicht** im Git-Repository eingecheckt (dependency-freie Philosophie) und wird auf
dem Server manuell abgelegt (siehe unten).

## Parsedown

- **Zweck:** Markdown → HTML für die editierbaren Sponsorenbriefe (`src/sponsor_brief.php`).
- **Version:** 1.7.4 (gepinnt)
- **Lizenz:** MIT (Emanuil Rusev)
- **Quelle:** https://github.com/erusev/parsedown
- **Datei:** `src/Parsedown.php`
- **SHA-256:** `af4a4b29f38b5a00b003a3b7a752282274c969e42dee88e55a427b2b61a2f38f`

Installation (auf dem Server, im Projekt-Root):

```bash
curl -sSL https://raw.githubusercontent.com/erusev/parsedown/1.7.4/Parsedown.php -o src/Parsedown.php
sha256sum src/Parsedown.php   # muss obigen Hash ergeben
```

Fehlt die Datei, greift automatisch ein projekteigener Minimal-Markdown-Konverter
(`sponsorMiniMarkdown()` in `src/sponsor_brief.php`) mit reduziertem Funktionsumfang.
Vom Bearbeiter getippter Markdown wird in beiden Fällen HTML-escaped gerendert
(SafeMode + MarkupEscaped), sodass kein aktives HTML/JS eingeschleust werden kann.
