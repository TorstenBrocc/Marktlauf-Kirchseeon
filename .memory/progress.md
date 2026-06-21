Der GPX-Track wird wieder korrekt angezeigt. Der Fehler wurde durch eine fehlerhafte Icon-Definition verursacht.
- Höhenmeteranzeige korrigiert: Gesamthöhenmeter werden nun direkt über `get_elevation_gain()` aus dem GPX-Objekt bezogen statt fehlerhaft aus `e.track_info.ascent`.- Neue 10km Strecke (10km.gpx) gegen alte getauscht und diese archiviert.
- GPX Ordner von 'Routes' in 'courses' umbenannt.
- Referenzen in index.html und maps.js aktualisiert.
- Disclaimer ("Streckenverlauf unter Vorbehalt - noch in Abstimmung mit Gemeinde") in halber Schriftgröße bei 5km und 10km "Elite Lauf" Kacheln in index.html, de.json und en.json hinzugefügt.
- 2026-06-21: Kombinierten Start/Ziel-Marker für die GPX-Karten eingefügt.
- 2026-06-21: Größe und Ausrichtung des kombinierten Start/Ziel-Markers (Größe des Tropfens und des Berg-Symbols, korrekte senkrechte Ausrichtung) korrigiert.
- 2026-06-21: Feinjustierung des Start/Ziel-Markers (Tropfen oben schmaler gemacht via scaleX, Bergsymbol rotiert und vergrößert).
- 2026-06-21: Kompletten Custom-CSS-Tropfen durch standardisierten Geo-Pin-Pfad ersetzt (inkl. exakter Berechnung des iconAnchor).
- 2026-06-21: Symbolgröße (Bergsymbol) im Start/Ziel-Pin um 100% vergrößert (auf 18x18).