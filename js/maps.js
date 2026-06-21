/**
 * Marktlauf Kirchseeon 2026 - Map Logic
 * Handles: GPX Preview Maps, Modal with Elevation Profile
 */

document.addEventListener("DOMContentLoaded", () => {
  initRouteMaps();
});

const transparentIcon = L.icon({
  iconUrl: "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7",
  iconSize: [0, 0],
  iconAnchor: [0, 0],
});

const routesConfig = {
  "bambini-500m": {
    gpx: null,
  },
  "schueler-1km": {
    gpx: null,
  },
  "schueler-2km": {
    gpx: null,
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

    if (config && config.gpx) {
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
      startIcon: transparentIcon,
      endIcon: transparentIcon,
      wptIcon: transparentIcon,
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

  if (!modal || !config || !config.gpx) return;

  modal.classList.add("active");
  document.body.style.overflow = "hidden";

  // Destroy previous map instance if it exists
  if (modalMap) {
    modalMap.remove();
    modalMap = null;
  }

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

    const szIcon = L.divIcon({
      className: "",
      // Finale Pixelgröße: 60x60
      iconSize: [60, 60],
      // Die Spitze liegt im viewBox 0 0 24 24 bei y=22 (9 + 13 = 22). 
      // Skaliert auf 60x60 ergibt das: X = 60 * (12/24) = 30, Y = 60 * (22/24) = 55
      iconAnchor: [30, 55],
      html: `
        <svg viewBox="0 0 24 24" width="60" height="60" style="display: block; overflow: visible;">
          <defs>
            <filter id="pin-shadow" x="-20%" y="-20%" width="140%" height="140%">
              <feDropShadow dx="0" dy="1" stdDeviation="1" flood-color="#000" flood-opacity="0.3"/>
            </filter>
          </defs>
          <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" 
                fill="white" stroke="#d1d5db" stroke-width="0.5" filter="url(#pin-shadow)" />
          <!-- Zentriertes Berg-Symbol im runden Kopf (Mittelpunkt bei 12,9 im 24er Raster) -->
          <!-- 9x9 Units nimmt ca. 64% des Kreisdurchmessers von 14 Units ein -->
          <svg x="7.5" y="4.5" width="9" height="9" viewBox="16 37 180 184">
            <path fill="#009640" d="M128.93 113.65 c-7.76 -2.47 -10.19 -12.24 -4.50 -18 4.16 -4.21 10.78 -4.23 14.92 -0.09 5.85 5.85 3.51 15.66 -4.30 18.07 -1.82 0.56 -4.41 0.58 -6.12 0.02z"/>
            <path fill="#009640" d="M70 168.62 c-1.91 -0.97 -2.95 -3.13 -2.50 -5.18 0.18 -0.83 2.99 -7.56 14.96 -35.98 1.82 -4.32 5.09 -12.06 7.22 -17.17 2.14 -5.11 4.14 -9.63 4.45 -10.06 1.62 -2.12 5.06 -2.20 6.91 -0.20 0.34 0.36 3.76 7.09 7.63 14.94 3.85 7.85 7.16 14.44 7.34 14.63 0.27 0.27 1.17 -0.54 7 -6.46 6.16 -6.21 6.80 -6.80 7.76 -7.04 2.09 -0.54 4.23 0.45 5.24 2.45 0.29 0.61 2.09 5.06 4.01 9.94 1.91 4.88 5.76 14.69 8.55 21.83 5.51 14.04 5.56 14.15 4.68 16.07 -0.83 1.85 -2.92 3.02 -4.86 2.70 -1.35 -0.20 -2.72 -1.13 -3.35 -2.20 -0.31 -0.52 -1.58 -3.58 -2.84 -6.80 -2.84 -7.31 -9.38 -24.08 -10.80 -27.67 -0.58 -1.46 -1.17 -2.72 -1.28 -2.74 -0.11 -0.05 -2.97 2.72 -6.34 6.12 -3.38 3.40 -6.46 6.37 -6.84 6.57 -0.47 0.25 -1.26 0.38 -2.23 0.41 -1.30 0 -1.62 -0.09 -2.47 -0.68 -0.92 -0.65 -1.26 -1.28 -7.47 -13.77 -3.58 -7.22 -6.59 -13.12 -6.68 -13.12 -0.09 0 -0.52 0.83 -0.94 1.87 -0.43 1.01 -1.66 3.91 -2.74 6.46 -2.52 5.96 -15.03 35.78 -16.42 39.15 -1.49 3.62 -2 4.55 -2.90 5.36 -1.46 1.28 -3.24 1.49 -5.06 0.58z"/>
          </svg>
        </svg>
      `
    });

    const gpxLayer = new L.GPX(config.gpx, {
      async: true,
      marker_options: {
        startIcon: szIcon,
        endIcon: transparentIcon,
        wptIcon: transparentIcon,
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
