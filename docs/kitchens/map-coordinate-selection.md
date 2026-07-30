# Map Coordinate Selection

How the kitchen coordinate selector works and how to configure the tile
layer.

## Library

- Leaflet 1.9.4, installed locally via npm (`leaflet` in `package.json`
  with `save-exact`).
- Bundled through Vite along with the rest of the application JS/CSS.
- Marker icons, marker shadow, and Leaflet's stylesheet are imported
  from the local package so they resolve through Vite. There is no CDN
  dependency.

## Tile layer

Default tile provider is OpenStreetMap's standard raster tile service:

```
https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

Attribution is rendered by Leaflet's control from the configured value.
The default is:

```
&copy; OpenStreetMap contributors
```

Attribution MUST remain accurate to the tile source in use.

## Configuration keys

Defined in `config/map.php`; overridable via environment variables:

| Env variable            | config/map.php key   | Default                                            |
|-------------------------|----------------------|----------------------------------------------------|
| `MAP_DEFAULT_LATITUDE`  | `default_latitude`   | `-6.9175`                                          |
| `MAP_DEFAULT_LONGITUDE` | `default_longitude`  | `106.9270`                                         |
| `MAP_DEFAULT_ZOOM`      | `default_zoom`       | `13`                                               |
| `MAP_SELECTION_ZOOM`    | `selection_zoom`     | `16`                                               |
| `MAP_TILE_URL`          | `tile_url`           | `https://tile.openstreetmap.org/{z}/{x}/{y}.png`   |
| `MAP_TILE_ATTRIBUTION`  | `tile_attribution`   | `&copy; OpenStreetMap contributors`                |
| `MAP_TILE_MAX_ZOOM`     | `tile_max_zoom`      | `19`                                               |

These are UI-only defaults. Changing them does not affect any stored
kitchen record.

## Workflow

- New kitchen:
  1. Map opens centered on the configured default with no marker.
  2. Coordinate display shows "No coordinate selected."
  3. First click places the marker and populates `latitude` and
     `longitude` hidden inputs.
  4. Further clicks move the same marker.
  5. Marker is draggable.
  6. Submitting without a coordinate is prevented client-side.
- Edit kitchen:
  1. Map opens centered on the saved coordinate at
     `MAP_SELECTION_ZOOM`.
  2. Draggable marker is placed on the saved coordinate.
  3. Clicking or dragging changes the value.
- Validation error: submitted latitude and longitude come back through
  `old(...)` so the marker is restored to the last selection.

## Server-side authority

Laravel is the coordinate authority. The map only assists input.

The following server validations always run before persistence:

- `latitude`: required, numeric, between -90 and 90.
- `longitude`: required, numeric, between -180 and 180.

Coordinates are stored as `decimal(10, 7)`. Values are cast to
`decimal:7` on the model.

## Not implemented and out of scope

- No browser geolocation (no `navigator.geolocation` call).
- No geocoding or reverse geocoding.
- No address search.
- No routing or road drawing.
- No Google Maps or other paid map provider.
- No Nominatim requests.
- No bulk tile download or offline caching.
- No Leaflet Draw or clustering plugins.
- No user-facing setting to change the tile provider at runtime.

## Rate limits and provider policy

The default OSM standard tile service is a shared community resource
with no application SLA. For anything beyond low-volume prototype use,
switch `MAP_TILE_URL` and `MAP_TILE_ATTRIBUTION` to a provider whose
terms permit your traffic pattern. The tile URL can be replaced without
touching any kitchen data.

## Files

- `resources/js/kitchen-map.js` - Leaflet initialization.
- `resources/css/app.css` - map container and coordinate-display
  styling; Leaflet CSS is imported inside `kitchen-map.js`.
- `resources/views/kitchens/_form.blade.php` - map container and hidden
  coordinate inputs.
- `config/map.php` - configuration source.
