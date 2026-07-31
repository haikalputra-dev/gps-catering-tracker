#include <Arduino.h>
#include <ArduinoJson.h>

#include "config.h"

// ---- Build-mode selection ------------------------------------
// BUILD_MODE is set via `-DBUILD_MODE=N` in `firmware/platformio.ini`.
// See that file (and firmware/README.md) for the two envs.
#define BUILD_WOKWI    1
#define BUILD_HARDWARE 2

#ifndef BUILD_MODE
#error "BUILD_MODE is not defined. Build via `pio run` (Wokwi) or `pio run -e hardware`."
#endif

#if BUILD_MODE != BUILD_WOKWI && BUILD_MODE != BUILD_HARDWARE
#error "BUILD_MODE must be 1 (Wokwi) or 2 (hardware)."
#endif

// ---- Shared pins & globals -----------------------------------
static constexpr int PIN_LED = 2;

unsigned long lastPostMs  = 0;
unsigned long lastBlinkMs = 0;
bool          ledState    = false;

// =============================================================
// WOKWI MODE (BUILD_MODE=1)
//   Preserves Packet 17.3 behavior verbatim: mock GPS waypoints,
//   Wi-Fi bring-up, HTTPS via WiFiClientSecure::setInsecure(),
//   NTP-derived timestamps.
// =============================================================
#if BUILD_MODE == BUILD_WOKWI

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <time.h>

// ---- Waypoints (Sukabumi corridor, ~3km eastward from Alun-Alun) ----
// Mock GPS: this array stands in for what would be TinyGPS++ readings
// on real hardware. The BUILD_HARDWARE branch below reads the same
// four fields (lat/lng/speed/heading) from a NEO-6M via TinyGPS++.
// The API contract is identical either way.
struct Waypoint {
    double latitude;
    double longitude;
    double speed_kmh;
    double heading_degrees;
};

static const Waypoint WAYPOINTS[] = {
    { -6.9241, 106.9269, 25.0, 90.0 },
    { -6.9243, 106.9285, 27.5, 88.0 },
    { -6.9245, 106.9302, 30.0, 92.0 },
    { -6.9247, 106.9320, 28.0, 90.0 },
    { -6.9248, 106.9340, 26.5, 87.0 },
    { -6.9249, 106.9360, 25.0, 91.0 },
    { -6.9250, 106.9380, 24.0, 90.0 },
    { -6.9250, 106.9400, 22.5, 89.0 },
    { -6.9251, 106.9420, 23.0, 92.0 },
    { -6.9252, 106.9440, 24.5, 90.0 },
    { -6.9252, 106.9460, 26.0, 88.0 },
    { -6.9253, 106.9480, 27.0, 91.0 },
    { -6.9253, 106.9500, 25.5, 90.0 },
    { -6.9254, 106.9520, 24.0, 89.0 },
    { -6.9254, 106.9540, 22.0, 90.0 },
    { -6.9255, 106.9550, 15.0, 88.0 },
    { -6.9255, 106.9552,  5.0, 87.0 },
    { -6.9255, 106.9553,  0.0, 87.0 },
};
static constexpr size_t WAYPOINT_COUNT =
    sizeof(WAYPOINTS) / sizeof(WAYPOINTS[0]);

size_t currentIndex = 0;
bool   timeSynced   = false;

static bool netReady() {
    return WiFi.status() == WL_CONNECTED;
}

static void netBegin() {
    Serial.printf("[wifi] connecting to '%s'...\n", WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
}

static bool syncTimeIfNeeded() {
    if (timeSynced) return true;
    if (!netReady()) return false;

    // UTC (offset 0, no DST); NTP servers are Wokwi-reachable.
    configTime(0, 0, "pool.ntp.org", "time.google.com");

    struct tm timeinfo;
    if (getLocalTime(&timeinfo, 2000)) {
        timeSynced = true;
        Serial.printf(
            "[time] NTP synced: %04d-%02d-%02dT%02d:%02d:%02dZ\n",
            timeinfo.tm_year + 1900, timeinfo.tm_mon + 1, timeinfo.tm_mday,
            timeinfo.tm_hour, timeinfo.tm_min, timeinfo.tm_sec);
        return true;
    }
    return false;
}

static bool clockReady() {
    return timeSynced;
}

static String buildIsoTimestamp() {
    time_t nowSec;
    time(&nowSec);
    struct tm* utc = gmtime(&nowSec);
    char buf[24];
    snprintf(buf, sizeof(buf),
             "%04d-%02d-%02dT%02d:%02d:%02dZ",
             utc->tm_year + 1900, utc->tm_mon + 1, utc->tm_mday,
             utc->tm_hour, utc->tm_min, utc->tm_sec);
    return String(buf);
}

static int postTelemetry() {
    const Waypoint& wp = WAYPOINTS[currentIndex];

    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    if (!http.begin(client, TELEMETRY_URL_HTTPS)) {
        Serial.println("[post] http.begin failed");
        return -1;
    }
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + DEVICE_TOKEN);
    http.setTimeout(5000);

    JsonDocument doc;
    doc["latitude"]        = wp.latitude;
    doc["longitude"]       = wp.longitude;
    doc["gps_timestamp"]   = buildIsoTimestamp();
    doc["speed_kmh"]       = wp.speed_kmh;
    doc["heading_degrees"] = wp.heading_degrees;

    String payload;
    serializeJson(doc, payload);

    Serial.printf("[post %u/%u] lat=%.4f lng=%.4f ",
                  (unsigned)(currentIndex + 1),
                  (unsigned)WAYPOINT_COUNT,
                  wp.latitude, wp.longitude);

    int code = http.POST(payload);
    if (code > 0) {
        String body = http.getString();
        Serial.printf("%d body=%s\n", code, body.c_str());
    } else {
        Serial.printf("connection error: %s\n",
                      http.errorToString(code).c_str());
    }
    http.end();

    currentIndex = (currentIndex + 1) % WAYPOINT_COUNT;
    return code;
}

#define BOOT_BANNER "GPS Catering Tracker firmware (mock GPS)"

#endif  // BUILD_MODE == BUILD_WOKWI

// =============================================================
// HARDWARE MODE (BUILD_MODE=2)
//   ESP32 + SIM800L GSM/GPRS + NEO-6M GPS. Plain HTTP POST on port
//   80. Timestamps come from the GPS fix (no NTP).
// =============================================================
#if BUILD_MODE == BUILD_HARDWARE

// TINY_GSM_MODEM_SIM800 is set via platformio.ini build_flags.
#include <TinyGsmClient.h>
#include <TinyGPSPlus.h>
#include <HardwareSerial.h>

// SIM800L on UART1, NEO-6M on UART2.
HardwareSerial gsmSerial(1);
HardwareSerial gpsSerial(2);

TinyGsm       modem(gsmSerial);
TinyGsmClient gsmClient(modem);
TinyGPSPlus   gps;

static bool netReady() {
    return modem.isGprsConnected();
}

static void netBegin() {
    Serial.println("[gsm] powering up SIM800L...");

#if SIM800_PWR_PIN >= 0
    pinMode(SIM800_PWR_PIN, OUTPUT);
    // Toggle PWRKEY high >=1s to boot the module.
    digitalWrite(SIM800_PWR_PIN, LOW);
    delay(100);
    digitalWrite(SIM800_PWR_PIN, HIGH);
    delay(1200);
    digitalWrite(SIM800_PWR_PIN, LOW);
    delay(3000);
#endif

    gsmSerial.begin(SIM800_BAUD, SERIAL_8N1, SIM800_RX_PIN, SIM800_TX_PIN);
    delay(500);

    Serial.println("[gsm] initializing modem...");
    if (!modem.init()) {
        Serial.println("[gsm] modem.init failed; will retry in loop");
        return;
    }

    if (strlen(GPRS_SIM_PIN) > 0 && modem.getSimStatus() != SIM_READY) {
        modem.simUnlock(GPRS_SIM_PIN);
    }

    Serial.print("[gsm] waiting for network...");
    if (!modem.waitForNetwork(60000L)) {
        Serial.println(" FAIL");
        return;
    }
    Serial.println(" OK");

    Serial.printf("[gprs] connecting APN='%s'...", GPRS_APN);
    if (!modem.gprsConnect(GPRS_APN, GPRS_USER, GPRS_PASS)) {
        Serial.println(" FAIL");
        return;
    }
    Serial.println(" OK");
}

// Called every loop iteration when the connection is down. Re-attaches
// to the mobile network and re-opens the GPRS context. Blocking for
// up to ~30s; that's acceptable for defense-day operation.
static bool ensureNet() {
    if (netReady()) return true;
    Serial.println("[gsm] GPRS not connected, retrying...");
    if (!modem.isNetworkConnected() && !modem.waitForNetwork(30000L)) {
        return false;
    }
    if (!modem.gprsConnect(GPRS_APN, GPRS_USER, GPRS_PASS)) {
        return false;
    }
    return true;
}

// Drain the NEO-6M UART into the TinyGPS++ parser. Call this
// frequently -- the module streams NMEA continuously and the ESP32
// UART FIFO is only 128 bytes.
static void pumpGps() {
    while (gpsSerial.available() > 0) {
        gps.encode(gpsSerial.read());
    }
}

static bool clockReady() {
    return gps.date.isValid() && gps.time.isValid();
}

static String buildIsoTimestampFromGps() {
    char buf[24];
    snprintf(buf, sizeof(buf),
             "%04d-%02d-%02dT%02d:%02d:%02dZ",
             gps.date.year(),  gps.date.month(),  gps.date.day(),
             gps.time.hour(),  gps.time.minute(), gps.time.second());
    return String(buf);
}

// Minimal HTTP/1.1 POST over a TinyGsmClient. HTTPClient would drag
// in WiFiClient, which we don't want in the hardware build. Returns
// the parsed HTTP status code, 0 if we deliberately skipped (no fix
// yet), or -1 on transport error.
static int postTelemetry() {
    if (!gps.location.isValid() || !clockReady()) {
        Serial.printf("[post] skip: waiting for GPS fix (sats=%d)\n",
                      gps.satellites.isValid() ? gps.satellites.value() : 0);
        return 0;
    }

    // Parse TELEMETRY_URL_HTTP into host + path. Expects "http://host/path".
    String u = String(TELEMETRY_URL_HTTP);
    if (!u.startsWith("http://")) {
        Serial.println("[post] TELEMETRY_URL_HTTP must start with http://");
        return -1;
    }
    u.remove(0, 7);  // strip "http://"
    int slash = u.indexOf('/');
    String host = (slash < 0) ? u              : u.substring(0, slash);
    String path = (slash < 0) ? String("/")    : u.substring(slash);

    JsonDocument doc;
    doc["latitude"]      = gps.location.lat();
    doc["longitude"]     = gps.location.lng();
    doc["gps_timestamp"] = buildIsoTimestampFromGps();
    // speed_kmh and heading_degrees are only included when the module
    // has flagged them valid, so we never send stale defaults.
    if (gps.speed.isValid()) {
        doc["speed_kmh"] = gps.speed.kmph();
    }
    if (gps.course.isValid()) {
        doc["heading_degrees"] = gps.course.deg();
    }

    String payload;
    serializeJson(doc, payload);

    Serial.printf("[post] lat=%.6f lng=%.6f sats=%d ",
                  gps.location.lat(), gps.location.lng(),
                  gps.satellites.isValid() ? gps.satellites.value() : 0);

    if (!gsmClient.connect(host.c_str(), 80)) {
        Serial.println("connect failed");
        return -1;
    }

    String req;
    req.reserve(256 + payload.length());
    req += "POST " + path + " HTTP/1.1\r\n";
    req += "Host: " + host + "\r\n";
    req += "Authorization: Bearer " + String(DEVICE_TOKEN) + "\r\n";
    req += "Content-Type: application/json\r\n";
    req += "Content-Length: " + String(payload.length()) + "\r\n";
    req += "Connection: close\r\n\r\n";
    req += payload;
    gsmClient.print(req);

    // Parse the "HTTP/1.1 NNN reason\r\n" status line first.
    int code = -1;
    unsigned long deadline = millis() + 8000;
    String statusLine;
    while (millis() < deadline && (gsmClient.connected() || gsmClient.available())) {
        if (!gsmClient.available()) continue;
        char c = (char)gsmClient.read();
        if (c == '\n') break;
        if (c != '\r') statusLine += c;
    }
    int firstSp  = statusLine.indexOf(' ');
    int secondSp = statusLine.indexOf(' ', firstSp + 1);
    if (firstSp > 0 && secondSp > firstSp) {
        code = statusLine.substring(firstSp + 1, secondSp).toInt();
    }

    // Drain remaining headers + body so the socket can close cleanly.
    String rest;
    while (millis() < deadline && (gsmClient.connected() || gsmClient.available())) {
        while (gsmClient.available()) {
            rest += (char)gsmClient.read();
        }
    }
    gsmClient.stop();

    int split = rest.indexOf("\r\n\r\n");
    String bodyOnly = (split >= 0) ? rest.substring(split + 4) : String();

    if (code > 0) {
        Serial.printf("%d body=%s\n", code, bodyOnly.c_str());
    } else {
        Serial.println("no status line parsed");
    }
    return code;
}

#define BOOT_BANNER "GPS Catering Tracker firmware (NEO-6M + SIM800L)"

#endif  // BUILD_MODE == BUILD_HARDWARE

// =============================================================
// SHARED: LED status + Arduino entrypoints
// =============================================================
static void updateLed() {
    // Solid on = network up AND clock (NTP or GPS) has valid time.
    if (netReady() && clockReady()) {
        digitalWrite(PIN_LED, HIGH);
        return;
    }
    unsigned long now = millis();
    if (now - lastBlinkMs >= 500) {
        lastBlinkMs = now;
        ledState = !ledState;
        digitalWrite(PIN_LED, ledState ? HIGH : LOW);
    }
}

void setup() {
    Serial.begin(115200);
    delay(200);
    Serial.println("\n[boot] " BOOT_BANNER);

    pinMode(PIN_LED, OUTPUT);
    digitalWrite(PIN_LED, LOW);

#if BUILD_MODE == BUILD_HARDWARE
    // Start the GPS UART first so a fix can accumulate while the
    // GSM modem is being brought up (which can take 60+ seconds).
    gpsSerial.begin(GPS_BAUD, SERIAL_8N1, GPS_RX_PIN, GPS_TX_PIN);
#endif

    netBegin();
}

void loop() {
#if BUILD_MODE == BUILD_HARDWARE
    pumpGps();
    if (!ensureNet()) {
        updateLed();
        return;
    }
#else
    if (!netReady()) {
        updateLed();
        return;
    }
    if (!syncTimeIfNeeded()) {
        updateLed();
        return;
    }
#endif

    unsigned long now = millis();
    if (now - lastPostMs < POST_INTERVAL_MS) {
        updateLed();
        return;
    }
    lastPostMs = now;

    int code = postTelemetry();

    if (code == 429) {
        Serial.println("[post] 429 rate-limited, backing off 60s");
        lastPostMs = now + 60000 - POST_INTERVAL_MS;
    }

    updateLed();
}
