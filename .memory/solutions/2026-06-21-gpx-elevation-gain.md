# Gesamthöhenmeter aus GPX auslesen mit leaflet-elevation

**Problem:**
Das Event-Objekt `e` in `elevationControl.on("eledata_loaded")` enthielt kein Feld `e.track_info.ascent` (oder ein ähnliches Feld für den Gesamtanstieg), wie ursprünglich angenommen. Dadurch wurde `undefined` angezeigt.

**Ansatz:**
Die `L.GPX` Instanz selbst verfügt über die Methode `get_elevation_gain()`. 
Da das `loaded`-Event des `gpxLayer` aufgerufen wird, *bevor* die Höhendaten geladen sind (denn `elevationControl.load()` wird im `loaded`-Handler getriggert), lässt sich der Wert zwischenspeichern.

**Code-Snippet:**
```javascript
let totalAscentMeters = null;

// Elevation Control Handler (wird als 2. gefeuert, sobald Höhendaten fertig)
elevationControl.on("eledata_loaded", function (e) {
  const ascentInMeters = totalAscentMeters;
  // ... DOM Update Logik
});

// GPX Layer Handler (wird als 1. gefeuert, wenn GPX geladen ist)
gpxLayer.on("loaded", function (e) {
  const gpx = e.target;
  totalAscentMeters = gpx.get_elevation_gain();
  // ...
  elevationControl.load(config.gpx); // Triggert eledata_loaded
});