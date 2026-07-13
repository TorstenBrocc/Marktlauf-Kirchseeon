# Drittanbieter-Komponenten

## Markdown-Rendering der Sponsorenbriefe

Die editierbaren Sponsorenbriefe (`src/sponsor_brief.php`) rendern Markdown zu HTML.
Standardmäßig geschieht das über einen **projekteigenen, abhängigkeitsfreien
Minimal-Konverter** (`sponsorMiniMarkdown()`) — er beherrscht Absätze, Zeilenumbrüche,
Fett/Kursiv, Überschriften, Listen und Links. Kein externer Code nötig.

## Share-Card-Rendering (Social-Media-Orchestrator)

`orga/social_orchestrator.php` lädt **html2canvas** per CDN zur clientseitigen PNG-Erzeugung.

- **Version:** 1.4.1
- **Lizenz:** MIT (Niklas von Hertzen)
- **Quelle:** https://github.com/niklasvh/html2canvas
- **CDN:** https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js

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
