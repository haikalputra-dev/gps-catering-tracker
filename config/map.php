<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default map centre and zoom levels
    |--------------------------------------------------------------------------
    |
    | These values control the initial position of the coordinate-selection map
    | when no coordinate has been supplied. They are UI defaults only, never
    | authoritative kitchen data.
    |
    */

    'default_latitude' => env('MAP_DEFAULT_LATITUDE', -6.9175),
    'default_longitude' => env('MAP_DEFAULT_LONGITUDE', 106.9270),
    'default_zoom' => env('MAP_DEFAULT_ZOOM', 13),
    'selection_zoom' => env('MAP_SELECTION_ZOOM', 16),

    /*
    |--------------------------------------------------------------------------
    | Tile layer configuration
    |--------------------------------------------------------------------------
    |
    | The tile URL, attribution and maximum zoom for the raster map tiles.
    | The default is OpenStreetMap's standard tile service. Change these
    | values to switch to a different OpenStreetMap-compatible provider.
    |
    */

    'tile_url' => env(
        'MAP_TILE_URL',
        'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
    ),

    'tile_attribution' => env(
        'MAP_TILE_ATTRIBUTION',
        '&copy; OpenStreetMap contributors'
    ),

    'tile_max_zoom' => env('MAP_TILE_MAX_ZOOM', 19),

];
