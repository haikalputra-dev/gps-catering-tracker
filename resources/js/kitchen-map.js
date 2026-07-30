/**
 * Kitchen coordinate-selection map.
 *
 * Reads its configuration from a `#kitchen-map` element's data attributes
 * and updates the two hidden coordinate inputs (`#kitchen-latitude` and
 * `#kitchen-longitude`) as the user clicks the map or drags the marker.
 *
 * This module assists input only; Laravel is the coordinate authority.
 */

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Ensure Leaflet's default marker icons resolve through Vite. Without this,
// the marker image URLs are undefined because Leaflet's stock code uses a
// runtime path calculation that breaks under a bundler.
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const COORDINATE_DECIMALS = 7;

function parseCoordinate(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function formatCoordinate(value) {
    return Number(value).toFixed(COORDINATE_DECIMALS);
}

function updateDisplay(displayEl, lat, lng) {
    if (!displayEl) {
        return;
    }
    displayEl.textContent = `${formatCoordinate(lat)}, ${formatCoordinate(lng)}`;
}

function updateInputs(latInput, lngInput, lat, lng) {
    latInput.value = formatCoordinate(lat);
    lngInput.value = formatCoordinate(lng);
    latInput.dispatchEvent(new Event('input', { bubbles: true }));
    lngInput.dispatchEvent(new Event('input', { bubbles: true }));
}

function readConfig(container) {
    const cfg = {
        defaultLat: parseCoordinate(container.dataset.defaultLatitude),
        defaultLng: parseCoordinate(container.dataset.defaultLongitude),
        defaultZoom: Number(container.dataset.defaultZoom) || 13,
        selectionZoom: Number(container.dataset.selectionZoom) || 16,
        tileUrl: container.dataset.tileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        tileAttribution: container.dataset.tileAttribution || '&copy; OpenStreetMap contributors',
        tileMaxZoom: Number(container.dataset.tileMaxZoom) || 19,
    };
    if (cfg.defaultLat === null) {
        cfg.defaultLat = -6.9175;
    }
    if (cfg.defaultLng === null) {
        cfg.defaultLng = 106.9270;
    }
    return cfg;
}

function initKitchenMap() {
    const container = document.getElementById('kitchen-map');
    if (!container) {
        return; // No map on this page.
    }

    const latInput = document.getElementById('kitchen-latitude');
    const lngInput = document.getElementById('kitchen-longitude');
    const displayEl = document.getElementById('kitchen-coordinate-display');
    if (!latInput || !lngInput) {
        return;
    }

    const cfg = readConfig(container);

    const initialLat = parseCoordinate(latInput.value);
    const initialLng = parseCoordinate(lngInput.value);
    const hasInitial = initialLat !== null && initialLng !== null;

    const centerLat = hasInitial ? initialLat : cfg.defaultLat;
    const centerLng = hasInitial ? initialLng : cfg.defaultLng;
    const zoom = hasInitial ? cfg.selectionZoom : cfg.defaultZoom;

    const map = L.map(container, {
        center: [centerLat, centerLng],
        zoom,
    });

    L.tileLayer(cfg.tileUrl, {
        maxZoom: cfg.tileMaxZoom,
        attribution: cfg.tileAttribution,
    }).addTo(map);

    let marker = null;
    const setMarker = (lat, lng) => {
        if (marker === null) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                updateInputs(latInput, lngInput, pos.lat, pos.lng);
                updateDisplay(displayEl, pos.lat, pos.lng);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }
        updateInputs(latInput, lngInput, lat, lng);
        updateDisplay(displayEl, lat, lng);
    };

    if (hasInitial) {
        setMarker(initialLat, initialLng);
    } else if (displayEl) {
        displayEl.textContent = 'No coordinate selected.';
    }

    map.on('click', (event) => {
        setMarker(event.latlng.lat, event.latlng.lng);
    });

    // Prevent submission when no coordinate has been selected.
    const form = container.closest('form');
    if (form) {
        form.addEventListener('submit', (event) => {
            const lat = parseCoordinate(latInput.value);
            const lng = parseCoordinate(lngInput.value);
            if (lat === null || lng === null) {
                event.preventDefault();
                if (displayEl) {
                    displayEl.textContent = 'Please select a coordinate on the map before saving.';
                }
            }
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initKitchenMap);
} else {
    initKitchenMap();
}
