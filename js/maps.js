/**
 * ATSV Marktlauf Kirchseeon 2026 - Map Logic
 * Handles: GPX Preview Maps, Modal with Elevation Profile
 *
 * Performance: Leaflet und die Plugins werden NICHT mehr statisch im HTML
 * geladen, sondern erst dynamisch, wenn die Karten-Bereiche in den Viewport
 * scrollen (Vorschau-/Standortkarte) bzw. das Höhenprofil-Modal geöffnet wird
 * (leaflet-elevation). Das senkt die Total Blocking Time beim Seitenaufbau.
 *
 * Datenschutz: Leaflet-Kern + GPX-Plugin werden self-hosted aus assets/vendor/
 * geladen (kein unpkg/cdnjs → kein Besucher-IP-Abfluss an US-CDNs). Ausnahme
 * bis auf Weiteres: leaflet-elevation (nur im Höhenprofil-Modal, klick-getriggert)
 * lädt sich seine schweren Abhängigkeiten (d3, togeojson, leaflet-almostover,
 * leaflet-geometryutil) selbst dynamisch von unpkg nach — sauberes Self-Hosting
 * braucht ein eigenes Vendoring-Paket + Live-Test. Siehe
 * intern/design-system-integration-spec.md (offener Punkt).
 */

// ── Dynamischer Asset-Loader (lädt jede CSS/JS-Datei bei Bedarf, nur einmal) ──
const _assetPromises = {};

function loadStylesheet(href) {
  if (_assetPromises[href]) return _assetPromises[href];
  _assetPromises[href] = new Promise((resolve) => {
    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    link.onload = resolve;
    // Auch bei Fehler auflösen: die Karte soll den Rest der Seite nie blockieren.
    link.onerror = resolve;
    document.head.appendChild(link);
  });
  return _assetPromises[href];
}

function loadScript(src, integrity) {
  if (_assetPromises[src]) return _assetPromises[src];
  _assetPromises[src] = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = src;
    if (integrity) {
      script.integrity = integrity;
      script.crossOrigin = "";
    }
    script.onload = resolve;
    script.onerror = () => reject(new Error("Konnte Skript nicht laden: " + src));
    document.head.appendChild(script);
  });
  return _assetPromises[src];
}

// Leaflet-Kern + GPX-Plugin (für Standort- und Vorschaukarten)
let _leafletCorePromise = null;
function loadLeafletCore() {
  if (_leafletCorePromise) return _leafletCorePromise;
  _leafletCorePromise = (async () => {
    // Self-hosted (assets/vendor/), kein CDN. Same-origin → keine SRI/crossOrigin nötig.
    await loadStylesheet("assets/vendor/leaflet/leaflet.css");
    await loadScript("assets/vendor/leaflet/leaflet.js");
    await loadScript("assets/vendor/leaflet-gpx/gpx.min.js");
  })();
  return _leafletCorePromise;
}

// Höhenprofil-Plugin (nur im Modal benötigt)
let _elevationPromise = null;
function loadElevationPlugin() {
  if (_elevationPromise) return _elevationPromise;
  _elevationPromise = (async () => {
    await loadLeafletCore();
    // TODO Self-Hosting: leaflet-elevation lädt dynamisch d3/togeojson/almostover/
    // geometryutil von unpkg nach → eigenes Vendoring-Paket + Live-Test nötig,
    // bevor diese beiden Zeilen auf assets/vendor/ zeigen können.
    await loadStylesheet("https://unpkg.com/@raruto/leaflet-elevation@2.5.2/dist/leaflet-elevation.css");
    await loadScript("https://unpkg.com/@raruto/leaflet-elevation@2.5.2/dist/leaflet-elevation.js");
  })();
  return _elevationPromise;
}

// ── Lazy-Init: Karten erst aufbauen, wenn ihre Bereiche in Sichtweite kommen ──
document.addEventListener("DOMContentLoaded", () => {
  const targets = [
    document.getElementById("strecke"),
    document.getElementById("location-map"),
  ].filter(Boolean);

  if (targets.length === 0) return;

  const startMaps = () => {
    loadLeafletCore()
      .then(() => {
        initLocationMap();
        initRouteMaps();
      })
      .catch((err) => console.error("Leaflet konnte nicht geladen werden:", err));
  };

  // Fallback für sehr alte Browser ohne IntersectionObserver: sofort laden.
  if (!("IntersectionObserver" in window)) {
    startMaps();
    return;
  }

  let triggered = false;
  const observer = new IntersectionObserver(
    (entries) => {
      if (triggered) return;
      if (entries.some((e) => e.isIntersecting)) {
        triggered = true;
        observer.disconnect();
        startMaps();
      }
    },
    { rootMargin: "300px" } // etwas vor dem Sichtbarwerden vorladen
  );
  targets.forEach((t) => observer.observe(t));
});

// ── Icons (werden erst erzeugt, nachdem Leaflet geladen ist) ──
let transparentIcon = null;
// Marktlauf-Marke als Karten-Pin (assets/images/marktlauf-pin.svg).
// Start/Ziel = volle Größe (60px); km-Marken = 20 % kleiner (48px).
// Spitze im viewBox 0 0 24 24 bei (12,22) → Anker skaliert mit der Größe.
let markePinFull = null; // Start & Ziel
let markePinKm = null; // km-Marken (80 %)
function ensureIcons() {
  if (!transparentIcon) {
    transparentIcon = L.icon({
      iconUrl: "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7",
      iconSize: [0, 0],
      iconAnchor: [0, 0],
    });
  }
  if (!markePinFull) {
    markePinFull = L.icon({
      iconUrl: "assets/images/marktlauf-pin.svg",
      iconSize: [60, 60],
      iconAnchor: [30, 55],
    });
  }
  if (!markePinKm) {
    markePinKm = L.icon({
      iconUrl: "assets/images/marktlauf-pin.svg",
      iconSize: [48, 48], // 20 % kleiner als Start/Ziel
      iconAnchor: [24, 44],
    });
  }
}

function initLocationMap() {
  const container = document.getElementById("location-map");
  if (!container) return;

  // Manuell abgelesen von OSM: Straße "Am Westring" vor dem Vereinsheim
  const lat = 48.080240;
  const lng = 11.855224;

  const map = L.map("location-map", {
    center: [lat, lng],
    zoom: 17,
    scrollWheelZoom: false,
  });

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map);

  L.marker([lat, lng])
    .addTo(map)
    .bindPopup("<strong>Start & Ziel</strong><br>Westring 6<br>85614 Kirchseeon")
    .openPopup();
}

const routesConfig = {
  "bambini-500m": {
    gpx: null,
  },
  "schueler-1km": {
    gpx: "assets/courses/1km.gpx",
  },
  "schueler-2km": {
    gpx: "assets/courses/2km.gpx",
  },
  "elite-5km": {
    gpx: "assets/courses/5km.gpx",
  },
  "elite-10km": {
    gpx: "assets/courses/10km.gpx",
  },
};

let modalMap = null;

function initRouteMaps() {
  const routeCards = document.querySelectorAll(".route-card");
  routeCards.forEach((card) => {
    const routeId = card.dataset.routeId;
    const config = routesConfig[routeId];
    const previewEl = card.querySelector(".route-map-preview");

    if (config && config.blocked) {
      // Karte nicht abbilden: Störer statt Vorschau, kein Klick zum Öffnen.
      previewEl.classList.add("route-map-blocked");
      previewEl.innerHTML = `
        <div class="route-blocked-overlay">
          <span class="route-blocked-icon" aria-hidden="true">🔒</span>
          <span class="route-blocked-text" data-i18n="strecke.approval_pending">Karte noch nicht verfügbar – Genehmigungsverfahren läuft</span>
        </div>`;
      previewEl.style.cursor = "default";
    } else if (config && config.gpx) {
      const mapId = `preview-map-${routeId}`;
      previewEl.id = mapId;
      createPreviewMap(mapId, config.gpx);
      previewEl.addEventListener("click", () => openMapModal(routeId));
    } else {
      previewEl.innerHTML = `<span data-i18n="strecke.placeholder">Strecke folgt in Kürze</span>`;
      previewEl.style.cursor = "default";
    }
  });

  // Modal close logic
  const modal = document.getElementById("map-modal");
  const closeBtn = document.getElementById("modal-close-btn");
  if (modal && closeBtn) {
    closeBtn.addEventListener("click", closeMapModal);
    modal.addEventListener("click", (e) => {
      if (e.target === modal) {
        closeMapModal();
      }
    });
  }
}

function createPreviewMap(mapId, gpxFile) {
  ensureIcons();

  const map = L.map(mapId, {
    scrollWheelZoom: false,
    dragging: false,
    zoomControl: false,
    attributionControl: false,
  });

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
  }).addTo(map);

  new L.GPX(gpxFile, {
    async: true,
    marker_options: {
      // Wie im Modal: Start/Ziel = Marktlauf-Marke voll, km-Marken = Marke 20 % kleiner.
      // (Ohne explizite wptIcons rendert das Plugin sonst sein Default "pin-icon-wpt.png" → 404/„?".)
      startIcon: markePinFull,
      endIcon: markePinFull,
      wptIcons: { "": markePinKm },
      shadowUrl: null,
    },
    polyline_options: {
      color: "#009640",
      weight: 5,
    },
  })
    .on("loaded", function (e) {
      map.fitBounds(e.target.getBounds());
    })
    .addTo(map);
}

function openMapModal(routeId) {
  const modal = document.getElementById("map-modal");
  const config = routesConfig[routeId];

  if (!modal || !config || !config.gpx || config.blocked) return;

  modal.classList.add("active");
  document.body.style.overflow = "hidden";

  // Destroy previous map instance if it exists
  if (modalMap) {
    modalMap.remove();
    modalMap = null;
  }

  // Höhenprofil-Plugin bei Bedarf nachladen, dann Karte aufbauen.
  loadElevationPlugin()
    .then(() => {
      ensureIcons();
      buildModalMap(config);
    })
    .catch((err) => console.error("Höhenprofil konnte nicht geladen werden:", err));
}

function buildModalMap(config) {
  // Delay initialization to allow for modal transition and layout calculation
  setTimeout(() => {
    modalMap = L.map("modal-map-container", {
      zoomControl: true, // Enable zoom control for the modal map
    });

    const elevationControl = L.control.elevation({
      container: "#modal-elevation-container",
      theme: "steelblue-theme",
      detached: true,
      elevationDiv: "#modal-elevation-container",
      autohide: false,
      summary: "inline",
      marker: "#ff6b35",
      // Disable default start/end markers from elevation plugin
      waypoints: false,
      polyline: {
        color: "#009640",
        weight: 5,
      },
      // Custom summary labels are handled via DOM manipulation after data load
    });
    elevationControl.addTo(modalMap);

    const gpxLayer = new L.GPX(config.gpx, {
      async: true,
      marker_options: {
        // Start & Ziel: Marktlauf-Marke voll; km-Marken (wpt): Marke 20 % kleiner.
        startIcon: markePinFull,
        endIcon: markePinFull,
        wptIcons: { "": markePinKm },
        shadowUrl: null,
      },
      polyline_options: {
        color: "#009640",
        weight: 5,
      },
    });

    let totalAscentMeters = null;

    // First, register the event listener for when elevation data is loaded.
    elevationControl.on("eledata_loaded", function (e) {
      // 1. Log event data and control object to find the correct ascent property
      console.log("eledata_loaded event object:", e);
      console.log("elevationControl object:", elevationControl);

      // The leaflet-elevation plugin might build the summary asynchronously.
      // We will try to find the element and if not, wait a bit.
      setTimeout(() => {
        // 2. Use a more robust selector, independent of the plugin's internal structure
        const avgEleSpan = document.querySelector("#modal-elevation-container .avgele");

        if (avgEleSpan) {
          console.log("SUCCESS: .avgele gefunden", avgEleSpan);

          const ascentInMeters = totalAscentMeters;

          if (ascentInMeters !== undefined && ascentInMeters !== null) {
            const ascent = ascentInMeters.toFixed(0) + " m";
            const labelSpan = avgEleSpan.querySelector(".summarylabel");
            const valueSpan = avgEleSpan.querySelector(".summaryvalue");

            if (labelSpan) {
              labelSpan.textContent = "Gesamthöhenmeter: ";
            }
            if (valueSpan) {
              valueSpan.textContent = ascent;
            }
            console.log(`Label und Wert aktualisiert auf: ${ascent}`);
          } else {
            console.error("FEHLER: Die Eigenschaft für den Anstieg konnte nicht berechnet werden. Bitte Konsole prüfen!");
          }
        } else {
          console.error("FEHLER: .avgele Element wurde im DOM nicht gefunden, auch nicht nach kurzer Wartezeit.");
        }
      }, 100); // Wait 100ms to ensure the DOM is updated by the plugin
    });

    // Second, add the GPX layer to the map.
    // This will trigger the 'loaded' event on the GPX layer.
    gpxLayer.on("loaded", function (e) {
      const gpx = e.target;
      const rawAscent = gpx.get_elevation_gain();
      console.log("Original berechneter Gesamtanstieg (gpx.get_elevation_gain):", rawAscent);

      // Berechne geglätteten Anstieg mit Schwellenwert-Filter
      let eleData = gpx.get_elevation_data();
      if (eleData && eleData.length > 0) {
        let smoothedAscent = 0;
        let lastElevation = eleData[0][1]; // Elevation is usually at index 1 in [distance, elevation, ...]

        for (let i = 1; i < eleData.length; i++) {
          let currentElevation = eleData[i][1];
          let diff = currentElevation - lastElevation;

          // Noise Filter: ignoriere Schwankungen unter 1.5m
          if (Math.abs(diff) > 1.5) {
            if (diff > 0) {
              smoothedAscent += diff;
            }
            lastElevation = currentElevation; // Referenzpunkt aktualisieren
          }
        }
        totalAscentMeters = smoothedAscent;
        console.log("Geglätteter Gesamtanstieg (Schwellenwert 1.5m):", totalAscentMeters);
      } else {
        totalAscentMeters = rawAscent;
      }

      modalMap.fitBounds(gpx.getBounds());

      // Third, load the elevation data. This will trigger the 'eledata_loaded' event.
      elevationControl.load(config.gpx);
    });

    gpxLayer.addTo(modalMap);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(modalMap);

    // Invalidate size after the modal is fully visible and rendered
    setTimeout(() => {
      modalMap.invalidateSize(true);
    }, 300); // Increased delay to ensure CSS transition is complete
  }, 50); // Reduced initial delay
}

function closeMapModal() {
  const modal = document.getElementById("map-modal");
  if (modal) {
    modal.classList.remove("active");
    document.body.style.overflow = "";
  }
  if (modalMap) {
    modalMap.remove();
    modalMap = null;
  }
  // Clear elevation container
  const elevationContainer = document.getElementById("modal-elevation-container");
  if (elevationContainer) {
    elevationContainer.innerHTML = "";
  }
}
