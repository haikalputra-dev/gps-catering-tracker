#pragma once

// Copy this file to `config.h` and fill in DEVICE_TOKEN with the
// Bearer token from your device's page in the web app
// (/devices/{id}). `config.h` is gitignored so real tokens never
// land in the repo.

#define WIFI_SSID        "Wokwi-GUEST"
#define WIFI_PASS        ""
#define DEVICE_TOKEN     "PASTE_YOUR_DEVICE_TOKEN_HERE"
#define TELEMETRY_URL    "https://gps-catering.duckdns.org/api/telemetry"
#define POST_INTERVAL_MS 3000
