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
    gpx: "assets/Routes/5km.gpx",
  },
  "elite-10km": {
    gpx: "assets/Routes/10km (8,6km).gpx",
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
      previewEl.innerHTML = `<span data-i18n=\"strecke.placeholder\">Strecke folgt in Kürze</span>`;
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
    attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors',
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

    const startIcon = L.divIcon({
      html: "<span>S</span>",
      className: "map-pin map-pin-start",
      iconSize: [30, 30],
      iconAnchor: [15, 30],
    });

    const endIcon = L.divIcon({
      html: "<span>Z</span>",
      className: "map-pin map-pin-end",
      iconSize: [30, 30],
      iconAnchor: [15, 30],
    });

    const gpxLayer = new L.GPX(config.gpx, {
      async: true,
      marker_options: {
        startIcon: startIcon,
        endIcon: endIcon,
        wptIcon: transparentIcon,
        shadowUrl: null,
      },
      polyline_options: {
        color: "#009640",
        weight: 5,
      },
    });

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
          // ATTENTION: The line below is a GUESS. Please check the console output of 'e'
          // and replace 'e.track_info.ascent' with the correct property path.
          const ascentInMeters = e.track_info.ascent; // EXAMPLE - PLEASE VERIFY

          if (ascentInMeters !== undefined) {
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
            console.error("FEHLER: Die Eigenschaft für den Anstieg (z.B. e.track_info.ascent) wurde im Event-Objekt nicht gefunden. Bitte Konsole prüfen!");
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
      modalMap.fitBounds(gpx.getBounds());

      // Handle Start/End-Icons for round trips
      const layers = gpx.getLayers();
      if (layers.length > 0) {
        const firstLayer = layers[0];
        if (firstLayer.getLatLngs) {
          const latlngs = firstLayer.getLatLngs();
          if (latlngs.length > 0) {
            const startPoint = latlngs[0];
            const endPoint = latlngs[latlngs.length - 1];
            if (startPoint.distanceTo(endPoint) < 10) {
              gpx.removeLayer(gpx.getLayers()[0]);
              gpx.removeLayer(gpx.getLayers()[gpx.getLayers().length - 1]);
              const szIcon = L.divIcon({
                html: "<span>S/Z</span>",
                className: "map-pin map-pin-sz",
                iconSize: [30, 30],
                iconAnchor: [15, 30],
              });
              L.marker(startPoint, { icon: szIcon }).addTo(modalMap);
            }
          }
        }
      }

      // Third, load the elevation data. This will trigger the 'eledata_loaded' event.
      elevationControl.load(config.gpx);
    });

    gpxLayer.addTo(modalMap);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: '&copy; <a href=\"https://www.openstreetmap.org/copyright\">OpenStreetMap</a> contributors',
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
