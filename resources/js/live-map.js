/**
 * Shared live-map module (Packet 12).
 *
 * Renders a Leaflet map that shows:
 *   - a fixed kitchen marker (green),
 *   - a fixed customer marker (red),
 *   - a single moving courier marker (blue) that is updated by
 *     periodic JSON polls (AR-56).
 *
 * The module reads its configuration from a container element's
 * `data-*` attributes. The Blade views wire both a staff surface
 * (`#delivery-live-map` on `deliveries.show`) and a public surface
 * (`#tracking-live-map` on `tracking.status`) through the same code
 * path.
 *
 * Recognised attributes (all coordinates are decimal degrees):
 *   data-endpoint          fully-qualified poll URL, required
 *   data-interval          polling interval in ms, default 3000 (AR-55)
 *   data-kitchen-latitude  kitchen snapshot lat
 *   data-kitchen-longitude kitchen snapshot lng
 *   data-customer-latitude customer snapshot lat
 *   data-customer-longitude customer snapshot lng
 *   data-tile-url          OpenStreetMap tile template
 *   data-tile-attribution  attribution string
 *   data-tile-max-zoom     max tile zoom (int)
 *
 * There is no breadcrumb polyline, no historical playback, and no
 * WebSocket / SSE / push channel: polling only (AR-55, AR-56). The
 * module is exported as an initialiser and invoked by `app.js` on
 * DOMContentLoaded.
 */

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Re-wire Leaflet's default marker icon paths so Vite serves them.
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const DEFAULT_INTERVAL_MS = 3000;
const MIN_INTERVAL_MS = 1000;
const DEFAULT_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const DEFAULT_TILE_ATTRIBUTION = '&copy; OpenStreetMap contributors';
const DEFAULT_TILE_MAX_ZOOM = 19;

function parseCoordinate(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

function parsePositiveInt(value, fallback) {
    if (value === null || value === undefined || value === '') {
        return fallback;
    }
    const parsed = Number(value);
    return Number.isFinite(parsed) && parsed > 0 ? Math.floor(parsed) : fallback;
}

/**
 * Coloured circular divIcon. Leaflet's raster marker icons are used
 * for kitchen/customer endpoints; the moving courier marker uses a
 * lightweight SVG so a single DOM update per poll is sufficient.
 */
function makeDotIcon(colour, label) {
    return L.divIcon({
        className: 'live-map-dot',
        html: `<span class="live-map-dot-inner" style="background:${colour}" title="${label}"></span>`,
        iconSize: [18, 18],
        iconAnchor: [9, 9],
        popupAnchor: [0, -9],
    });
}

function readConfig(container) {
    const ds = container.dataset;
    return {
        endpoint: ds.endpoint || null,
        interval: Math.max(
            MIN_INTERVAL_MS,
            parsePositiveInt(ds.interval, DEFAULT_INTERVAL_MS),
        ),
        kitchenLat: parseCoordinate(ds.kitchenLatitude),
        kitchenLng: parseCoordinate(ds.kitchenLongitude),
        customerLat: parseCoordinate(ds.customerLatitude),
        customerLng: parseCoordinate(ds.customerLongitude),
        tileUrl: ds.tileUrl || DEFAULT_TILE_URL,
        tileAttribution: ds.tileAttribution || DEFAULT_TILE_ATTRIBUTION,
        tileMaxZoom: parsePositiveInt(ds.tileMaxZoom, DEFAULT_TILE_MAX_ZOOM),
        statusEl: ds.statusTarget
            ? document.getElementById(ds.statusTarget)
            : null,
    };
}

function fitBoundsFromPoints(map, points) {
    const valid = points.filter((p) => p !== null);
    if (valid.length === 0) {
        return;
    }
    if (valid.length === 1) {
        map.setView(valid[0], 15);
        return;
    }
    const bounds = L.latLngBounds(valid);
    map.fitBounds(bounds, { padding: [32, 32] });
}

function setStatus(el, message) {
    if (el) {
        el.textContent = message;
    }
}

/**
 * Initialise a live-map instance mounted on the given container. The
 * container element is expected to carry a `data-endpoint` attribute
 * pointing at the JSON polling URL.
 */
export function initLiveMap(container) {
    if (!container) {
        return null;
    }

    const cfg = readConfig(container);
    if (!cfg.endpoint) {
        // Without a polling URL, the live-map cannot function; render
        // nothing rather than a half-configured map.
        return null;
    }

    // Kitchen and customer snapshot coordinates are used to seed the
    // initial map view. If either is missing the map still initialises
    // and simply waits for a first poll to reveal the courier.
    const hasKitchen = cfg.kitchenLat !== null && cfg.kitchenLng !== null;
    const hasCustomer = cfg.customerLat !== null && cfg.customerLng !== null;

    const seedCenter = hasKitchen
        ? [cfg.kitchenLat, cfg.kitchenLng]
        : (hasCustomer ? [cfg.customerLat, cfg.customerLng] : [-6.9175, 106.9270]);

    const map = L.map(container, {
        center: seedCenter,
        zoom: 14,
        zoomControl: true,
    });

    L.tileLayer(cfg.tileUrl, {
        maxZoom: cfg.tileMaxZoom,
        attribution: cfg.tileAttribution,
    }).addTo(map);

    if (hasKitchen) {
        L.marker([cfg.kitchenLat, cfg.kitchenLng], {
            icon: makeDotIcon('#16a34a', 'Kitchen'),
            title: 'Kitchen',
        }).addTo(map).bindPopup('Kitchen');
    }
    if (hasCustomer) {
        L.marker([cfg.customerLat, cfg.customerLng], {
            icon: makeDotIcon('#dc2626', 'Customer'),
            title: 'Customer',
        }).addTo(map).bindPopup('Customer');
    }

    // Fit the initial view to the two fixed endpoints if both exist so
    // the map does not open zoomed too tight on the kitchen alone.
    fitBoundsFromPoints(map, [
        hasKitchen ? [cfg.kitchenLat, cfg.kitchenLng] : null,
        hasCustomer ? [cfg.customerLat, cfg.customerLng] : null,
    ]);

    let courierMarker = null;
    let lastLatLng = null;
    let inFlight = false;
    let stopped = false;

    const updateCourier = (lat, lng) => {
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }
        const latLng = L.latLng(lat, lng);
        if (courierMarker === null) {
            courierMarker = L.marker(latLng, {
                icon: makeDotIcon('#2563eb', 'Courier'),
                title: 'Courier',
                zIndexOffset: 500,
            }).addTo(map).bindPopup('Courier');
            // On the first fix, recentre so the courier is visible.
            map.panTo(latLng);
        } else {
            courierMarker.setLatLng(latLng);
        }
        lastLatLng = latLng;
    };

    const poll = async () => {
        if (stopped || inFlight) {
            return;
        }
        inFlight = true;
        try {
            const response = await fetch(cfg.endpoint, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 401) {
                stopped = true;
                setStatus(cfg.statusEl, 'Session expired. Refresh to sign back in.');
                return;
            }
            if (response.status === 403) {
                stopped = true;
                setStatus(cfg.statusEl, 'Not authorised to view this delivery.');
                return;
            }
            if (response.status === 429) {
                // Back off silently; the next tick will retry.
                setStatus(cfg.statusEl, 'Polling paused briefly (rate limit).');
                return;
            }
            if (!response.ok) {
                setStatus(cfg.statusEl, 'Live update unavailable.');
                return;
            }

            const payload = await response.json();
            const latest = payload && payload.latest ? payload.latest : null;
            if (latest && typeof latest.latitude === 'number' && typeof latest.longitude === 'number') {
                updateCourier(latest.latitude, latest.longitude);
                setStatus(cfg.statusEl, 'Live position updated.');
            } else if (payload && payload.status && payload.status !== 'in_transit') {
                setStatus(cfg.statusEl, 'Courier is not on the road right now.');
            } else {
                setStatus(cfg.statusEl, 'Waiting for the first live position.');
            }
        } catch (err) {
            setStatus(cfg.statusEl, 'Live update unavailable.');
        } finally {
            inFlight = false;
        }
    };

    // Kick off an immediate poll so the first courier fix arrives
    // without waiting a full interval, then start the interval timer.
    poll();
    const timerId = window.setInterval(poll, cfg.interval);

    // Halt the poll loop when the page is hidden to avoid burning
    // requests against the throttle when the tab is in the background.
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopped = true;
            window.clearInterval(timerId);
        }
    });

    return {
        stop: () => {
            stopped = true;
            window.clearInterval(timerId);
        },
        lastLatLng: () => lastLatLng,
    };
}

function initAllLiveMaps() {
    document.querySelectorAll('[data-live-map]').forEach((el) => {
        initLiveMap(el);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllLiveMaps);
} else {
    initAllLiveMaps();
}
