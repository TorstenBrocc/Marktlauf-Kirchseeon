/**
 * Marktlauf Kirchseeon 2026 - Map Logic
 * Handles: GPX Preview Maps, Modal with Elevation Profile
 */

document.addEventListener('DOMContentLoaded', () => {
    initRouteMaps();
});

const routesConfig = {
    'bambini-500m': {
        gpx: null,
    },
    'schueler-1km': {
        gpx: null,
    },
    'schueler-2km': {
        gpx: null,
    },
    'elite-5km': {
        gpx: 'assets/Routes/5km.gpx',
    },
    'elite-10km': {
        gpx: 'assets/Routes/10km (8,6km).gpx',
    },
};

let modalMap = null;

function initRouteMaps() {
    const routeCards = document.querySelectorAll('.route-card');
    routeCards.forEach(card => {
        const routeId = card.dataset.routeId;
        const config = routesConfig[routeId];
        const previewEl = card.querySelector('.route-map-preview');

        if (config && config.gpx) {
            const mapId = `preview-map-${routeId}`;
            previewEl.id = mapId;
            createPreviewMap(mapId, config.gpx);
            previewEl.addEventListener('click', () => openMapModal(routeId));
        } else {
            previewEl.innerHTML = `<span data-i18n="strecke.placeholder">Strecke folgt in Kürze</span>`;
            previewEl.style.cursor = 'default';
        }
    });

    // Modal close logic
    const modal = document.getElementById('map-modal');
    const closeBtn = document.getElementById('modal-close-btn');
    if(modal && closeBtn) {
        closeBtn.addEventListener('click', closeMapModal);
        modal.addEventListener('click', (e) => {
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

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    new L.GPX(gpxFile, {
        async: true,
        marker_options: {
            startIconUrl: null,
            endIconUrl: null,
            shadowUrl: null,
        },
        polyline_options: {
            color: '#009640',
            weight: 5,
        },
    }).on('loaded', function(e) {
        map.fitBounds(e.target.getBounds());
    }).addTo(map);
}

function openMapModal(routeId) {
    const modal = document.getElementById('map-modal');
    const config = routesConfig[routeId];

    if (!modal || !config || !config.gpx) return;

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Destroy previous map instance if it exists
    if (modalMap) {
        modalMap.remove();
        modalMap = null;
    }

    // Delay initialization to allow for modal transition and layout calculation
    setTimeout(() => {
        modalMap = L.map('modal-map-container', {
            zoomControl: true, // Enable zoom control for the modal map
        });

        const elevationControl = L.control.elevation({
            container: '#modal-elevation-container',
            theme: 'steelblue-theme',
            detached: true, // Render elevation in its own container
            elevationDiv: '#modal-elevation-container',
            autohide: false,
            polyline: {
                color: '#009640',
                weight: 4,
            },
            marker: "#ff6b35",
            summary: 'inline'
        });
        elevationControl.addTo(modalMap);

        const gpxLayer = new L.GPX(config.gpx, {
            async: true,
            marker_options: {
                startIconUrl: 'https://cdn-icons-png.flaticon.com/512/1077/1077041.png', // Example start icon
                endIconUrl: 'https://cdn-icons-png.flaticon.com/512/3237/3237443.png',     // Example end icon
                shadowUrl: null,
                iconSize: [32, 32],
                iconAnchor: [16, 32]
            },
            polyline_options: {
                color: '#009640',
                weight: 5,
            },
        });

        gpxLayer.on('loaded', function(e) {
            modalMap.fitBounds(e.target.getBounds());
            elevationControl.load(config.gpx);
        }).addTo(modalMap);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(modalMap);

        // Invalidate size after the modal is fully visible and rendered
        setTimeout(() => {
            modalMap.invalidateSize(true);
        }, 300); // Increased delay to ensure CSS transition is complete
    }, 50); // Reduced initial delay
}

function closeMapModal() {
    const modal = document.getElementById('map-modal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
    if (modalMap) {
        modalMap.remove();
        modalMap = null;
    }
    // Clear elevation container
    const elevationContainer = document.getElementById('modal-elevation-container');
    if (elevationContainer) {
        elevationContainer.innerHTML = '';
    }
}