#!/usr/bin/env python3
"""Rendert die Social-Icons der Website als PNG fuer den E-Mail-Versand.

Warum es dieses Skript gibt
---------------------------
Im Web liegen die Icons als Inline-SVG im Footer von index.html, eingefaerbt ueber
CSS-Klassen in css/layout.css. E-Mail-Clients koennen das nicht darstellen (Gmail und
Outlook zeigen Inline-SVG ueberwiegend nicht, CSS-Hintergruende auf Links ebenso wenig) --
eine Mail braucht verlinkte Bitmaps.

Damit Web und Mail nicht auseinanderlaufen, wird hier nichts nachgebaut: Glyphe und
Hintergrund werden aus genau diesen beiden Dateien gelesen. Aendert sich das Icon im Web,
erzeugt ein erneuter Lauf deckungsgleiche Mail-Icons.

Aufruf:  python3 bin/render_social_icons.py
Ergebnis: assets/images/social/social-<kanal>.png (je 128x128, transparenter Rand)

Benoetigt Google Chrome (headless) -- dasselbe Verfahren wie beim Rendern der Urkunden.
"""
import html
import json
import os
import re
import subprocess
import sys
import tempfile

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC_HTML = os.path.join(ROOT, "index.html")
SRC_CSS = os.path.join(ROOT, "css", "layout.css")
OUT_DIR = os.path.join(ROOT, "assets", "images", "social")
CHROME = "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
SIZE = 128          # Kantenlaenge in px; in der Mail auf 46 px dargestellt
RADIUS = 28         # entspricht 12 px bei 46 px Darstellung
GLYPH = 74          # Glyphengroesse, entspricht 28 px bei 46 px (vgl. layout.css)


def read(path):
    with open(path, encoding="utf-8", errors="replace") as fh:
        return fh.read()


def extract():
    """Liest Glyphe und Hintergrund je Kanal aus den Website-Quellen."""
    page, css = read(SRC_HTML), read(SRC_CSS)
    icons = {}
    for m in re.finditer(
        r'<a[^>]*class="footer-social-link social-(\w+)"[^>]*>\s*(<svg.*?</svg>)', page, re.S
    ):
        icons[m.group(1)] = re.sub(r"\s+", " ", m.group(2)).strip()
    if not icons:
        sys.exit("Keine .footer-social-link-Icons in index.html gefunden -- Markup geaendert?")

    for key in list(icons):
        rule = re.search(r"\.footer-social-link\.social-" + key + r"\s*\{([^}]*)\}", css)
        bg = re.search(r"background(?:-color)?\s*:\s*([^;]+);", rule.group(1)) if rule else None
        if not bg:
            sys.exit("Kein Hintergrund fuer social-%s in layout.css gefunden." % key)
        icons[key] = {"svg": icons[key], "bg": bg.group(1).strip()}
    return icons


def render(key, spec, tmp):
    # currentColor der Website-SVGs -> weiss, wie im Web (Kommentar in layout.css:
    # "Offizielle Plattform-Farben, Icon weiss")
    svg = re.sub(r'(<svg\b)', r'\1 width="%d" height="%d"' % (GLYPH, GLYPH), spec["svg"], count=1)
    doc = (
        "<!DOCTYPE html><html><head><meta charset='utf-8'><style>"
        "html,body{margin:0;padding:0;background:transparent}"
        ".b{width:%dpx;height:%dpx;border-radius:%dpx;background:%s;color:#fff;"
        "display:flex;align-items:center;justify-content:center}"
        "</style></head><body><div class='b'>%s</div></body></html>"
        % (SIZE, SIZE, RADIUS, spec["bg"], svg)
    )
    src = os.path.join(tmp, key + ".html")
    with open(src, "w", encoding="utf-8") as fh:
        fh.write(doc)

    out = os.path.join(OUT_DIR, "social-%s.png" % key)
    subprocess.run(
        [CHROME, "--headless", "--disable-gpu", "--hide-scrollbars",
         "--default-background-color=00000000", "--force-device-scale-factor=1",
         "--window-size=%d,%d" % (SIZE, SIZE), "--virtual-time-budget=4000",
         "--screenshot=" + out, "file://" + src],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=False,
    )
    if not os.path.isfile(out):
        sys.exit("Chrome hat %s nicht erzeugt." % out)
    return out


def main():
    if not os.path.isfile(CHROME):
        sys.exit("Google Chrome nicht gefunden: " + CHROME)
    os.makedirs(OUT_DIR, exist_ok=True)
    icons = extract()
    with tempfile.TemporaryDirectory() as tmp:
        for key, spec in sorted(icons.items()):
            path = render(key, spec, tmp)
            print("%-10s %s  (bg: %s)" % (key, os.path.relpath(path, ROOT), spec["bg"][:58]))
    print("\nFertig. In Mail-HTML einbinden als:")
    print("  https://atsv-kirchseeon-marktlauf.de/assets/images/social/social-<kanal>.png")


if __name__ == "__main__":
    main()
