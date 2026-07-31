#pragma once

// Copy this file to `config.h` and fill in the fields relevant to
// your build mode. `config.h` is gitignored so real tokens never
// land in the repo.
//
// Build modes are selected in `firmware/platformio.ini`:
//   BUILD_MODE=1 -- Wokwi simulator. Uses WIFI_* and
//                   TELEMETRY_URL_HTTPS. NTP for timestamps.
//   BUILD_MODE=2 -- Real hardware (ESP32 + SIM800L + NEO-6M). Uses
//                   GPRS_*, SIM800_*, GPS_*, and TELEMETRY_URL_HTTP.
//                   GPS satellite time for timestamps.
//
// You do NOT need to strip out fields for the other mode -- the
// preprocessor already ignores whichever set your build doesn't use.

// ---- Shared (both modes) --------------------------------------
#define DEVICE_TOKEN          "PASTE_YOUR_DEVICE_TOKEN_HERE"
#define POST_INTERVAL_MS      3000

// ---- Wokwi mode (BUILD_MODE=1) --------------------------------
#define WIFI_SSID             "Wokwi-GUEST"
#define WIFI_PASS             ""
#define TELEMETRY_URL_HTTPS   "https://gps-catering.duckdns.org/api/telemetry"

// ---- Hardware mode (BUILD_MODE=2) -----------------------------
// Plain HTTP on port 80. SIM800L's TLS stack is stuck on TLS 1.0 /
// weak ciphers and Let's Encrypt's chain won't validate, so the VPS
// Caddy config exposes a port-80 listener that accepts POST
// /api/telemetry only (everything else redirects to HTTPS). See
// Packet 18 / `deployment/Caddyfile`.
#define TELEMETRY_URL_HTTP    "http://gps-catering.duckdns.org/api/telemetry"

// APN + credentials for your carrier. Common Indonesian defaults:
//   Telkomsel      APN="internet"     user=""       pass=""
//   Indosat        APN="indosatgprs"  user="indosat" pass="indosat"
//   XL / Axis      APN="internet"     user=""       pass=""
//   Tri (Three)    APN="3gprs"        user="3gprs"  pass="3gprs"
#define GPRS_APN              "internet"
#define GPRS_USER             ""
#define GPRS_PASS             ""

// SIM PIN. Leave empty for prepaid SIMs with no PIN lock.
#define GPRS_SIM_PIN          ""

// SIM800L UART1 wiring.
//   SIM800_RX_PIN = ESP32 pin that receives SIM800L TX
//   SIM800_TX_PIN = ESP32 pin that transmits to SIM800L RX
//   SIM800_PWR_PIN = optional PWRKEY pin for boot pulse; set to -1
//                    if the module is always powered.
#define SIM800_RX_PIN         26
#define SIM800_TX_PIN         27
#define SIM800_PWR_PIN        -1
#define SIM800_BAUD           9600

// NEO-6M GPS UART2 wiring. Same RX/TX convention.
#define GPS_RX_PIN            16
#define GPS_TX_PIN            17
#define GPS_BAUD              9600
