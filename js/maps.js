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
      // Custom summary labels
      legend: {
        totalAscent: "Gesamthöhenmeter",
      },
    });
    elevationControl.addTo(modalMap);

    const gpxLayer = new L.GPX(config.gpx, {
      async: true,
      // Disable default GPX markers, we create them manually
      marker_options: {
        startIcon: transparentIcon,
        endIcon: transparentIcon,
        wptIcon: transparentIcon,
        shadowUrl: null,
      },
      polyline_options: {
        color: "transparent", // Make original GPX line invisible
        weight: 0,
      },
    });

    gpxLayer
      .on("loaded", function (e) {
        const gpx = e.target;
        modalMap.fitBounds(gpx.getBounds());
        elevationControl.load(config.gpx);

        // Custom Marker Logic
        const startPoint = gpx.get_start_point();
        const endPoint = gpx.get_end_point();
        const distance =
          startPoint.lat.toFixed(5) === endPoint.lat.toFixed(5) &&
          startPoint.lng.toFixed(5) === endPoint.lng.toFixed(5);

        if (distance) {
          const icon = L.divIcon({
            html: "<span>S/Z</span>",
            className: "map-pin map-pin-sz",
            iconSize: [30, 30],
            iconAnchor: [15, 30],
          });
          L.marker(startPoint, { icon: icon }).addTo(modalMap);
        } else {
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
          L.marker(startPoint, { icon: startIcon }).addTo(modalMap);
          L.marker(endPoint, { icon: endIcon }).addTo(modalMap);
        }
      })
      .addTo(modalMap);

    // Replace Avg Elevation with Total Ascent after data is loaded
    elevationControl.on("eledata_loaded", function (e) {
      const summary = elevationControl._container.querySelector(".summary");
      if (summary) {
        const avgRow = summary.querySelector("tr:nth-child(4)"); // 4th row is usually Avg
        if (avgRow && avgRow.innerHTML.includes("Avg")) {
          const ascent = e.ascent.toFixed(0) + " m";
          avgRow.innerHTML = `<td>Gesamthöhenmeter</td><td>${ascent}</td>`;
        }
      }
    });

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
