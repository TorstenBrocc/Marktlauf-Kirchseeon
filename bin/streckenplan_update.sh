#!/usr/bin/env bash
#
# Streckenplan aus der Drive-PDF in die beiden JPGs der Helferseite rendern.
#
# WARUM ES DAS GIBT
# Die Helferseite (helfer/zugang.php) zeigt den Streckenplan aus zwei festen
# Dateien im Repo. Die Wahrheit liegt aber in der PDF im Shared Drive, und die
# aendert sich bis kurz vor dem Lauf. Bis zum 20.09.2026 waren die JPGs
# Handkopien ohne Verbindung zur Quelle -- am Lauftag war die ausgelieferte
# Karte dadurch einen halben Tag aelter als der Plan. Dieses Skript schliesst
# die Luecke: Quelle aendern, ein Befehl, deployen.
#
# MASSE SIND NICHT FREI WAEHLBAR
# Die Vorschau steht mit width="452" height="640" im Markup. Wer hier andere
# Masse rendert, muss das Markup mitziehen -- sonst springt das Layout.
#
# AUFRUF
#   bin/streckenplan_update.sh              # Standardquelle im Shared Drive
#   bin/streckenplan_update.sh /pfad/zu.pdf # andere Quelle
#
# Danach: git add assets/images/strecke && commit && push (Deploy laeuft dann).
# Cache-Busting passiert von selbst -- zugang.php haengt ?v=<filemtime> an.

set -euo pipefail

QUELLE="${1:-/Users/torsten/Library/CloudStorage/GoogleDrive-t.tyras@atsv-kirchseeon-marktlauf.de/Shared drives/Marktlauf Orga/Helfer/Einsatzplan/Streckenplan_Luftbild_A4.pdf}"
ZIEL="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/assets/images/strecke"
GROSS="$ZIEL/streckenplan-luftbild.jpg"
KLEIN="$ZIEL/streckenplan-luftbild-klein.jpg"
GROSS_W=1871; GROSS_H=2647
KLEIN_W=452;  KLEIN_H=640

for w in pdftoppm sips; do
    command -v "$w" >/dev/null || { echo "FEHLT: $w (pdftoppm kommt aus poppler: brew install poppler)" >&2; exit 1; }
done
[ -f "$QUELLE" ] || { echo "Quelle nicht gefunden: $QUELLE" >&2; exit 1; }

echo "Quelle: $QUELLE"
echo "        $(stat -f '%Sm  %z Bytes' -t '%Y-%m-%d %H:%M:%S' "$QUELLE")"

# In einen Temp-Ordner rendern und erst am Ende ueberschreiben. Bricht das
# Rendern ab, bleiben die alten Bilder unangetastet -- lieber eine alte Karte
# als eine halbe.
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

pdftoppm -jpeg -jpegopt quality=88 -r 300 -scale-to-x "$GROSS_W" -scale-to-y "$GROSS_H" \
         -f 1 -l 1 -singlefile "$QUELLE" "$TMP/gross"
[ -s "$TMP/gross.jpg" ] || { echo "Rendern fehlgeschlagen." >&2; exit 1; }

cp "$TMP/gross.jpg" "$TMP/klein.jpg"
sips -z "$KLEIN_H" "$KLEIN_W" "$TMP/klein.jpg" --out "$TMP/klein.jpg" >/dev/null

masse() { sips -g pixelWidth -g pixelHeight "$1" | awk '/pixelWidth/{w=$2} /pixelHeight/{h=$2} END{print w"x"h}'; }
[ "$(masse "$TMP/gross.jpg")" = "${GROSS_W}x${GROSS_H}" ] || { echo "Grosses Bild hat $(masse "$TMP/gross.jpg") statt ${GROSS_W}x${GROSS_H}." >&2; exit 1; }
[ "$(masse "$TMP/klein.jpg")" = "${KLEIN_W}x${KLEIN_H}" ] || { echo "Kleines Bild hat $(masse "$TMP/klein.jpg") statt ${KLEIN_W}x${KLEIN_H}." >&2; exit 1; }

for paar in "$TMP/gross.jpg:$GROSS" "$TMP/klein.jpg:$KLEIN"; do
    neu="${paar%%:*}"; alt="${paar##*:}"
    if [ -f "$alt" ] && cmp -s "$neu" "$alt"; then
        echo "  unveraendert: $(basename "$alt")"
    else
        vorher="$([ -f "$alt" ] && stat -f '%z' "$alt" || echo 0)"
        cp "$neu" "$alt"
        echo "  aktualisiert: $(basename "$alt")  $(masse "$alt")  ${vorher} -> $(stat -f '%z' "$alt") Bytes"
    fi
done

echo "Fertig. Jetzt: git add assets/images/strecke && git commit && git push"
