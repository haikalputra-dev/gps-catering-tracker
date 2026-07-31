#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <time.h>

#include "config.h"

// ---- Pins ------------------------------------------------------
static constexpr int PIN_LED = 2;

// ---- Waypoints (Sukabumi corridor, ~3km eastward from Alun-Alun) ----
// Mock GPS: this array replaces what would be TinyGPS++ readings on
// real hardware. For a physical NEO-6M swap this out for serial NMEA
// parsing on UART2 (see the pre-Wokwi version of this file in git
// history). The API contract stays identical either way.
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

// ---- Globals ---------------------------------------------------
unsigned long lastPostMs   = 0;
unsigned long lastBlinkMs  = 0;
bool          ledState     = false;
size_t        currentIndex = 0;
bool          timeSynced   = false;

// ---- Helpers ---------------------------------------------------
static bool wifiReady() {
    return WiFi.status() == WL_CONNECTED;
}

static bool syncTimeIfNeeded() {
    if (timeSynced) return true;
    if (!wifiReady()) return false;

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
    if (!http.begin(client, TELEMETRY_URL)) {
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

static void updateLed() {
    // Solid on = Wi-Fi connected + NTP time synced.
    if (wifiReady() && timeSynced) {
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

// ---- Arduino entrypoints --------------------------------------
void setup() {
    Serial.begin(115200);
    delay(200);
    Serial.println("\n[boot] GPS Catering Tracker firmware (mock GPS)");

    pinMode(PIN_LED, OUTPUT);
    digitalWrite(PIN_LED, LOW);

    Serial.printf("[wifi] connecting to '%s'...\n", WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
}

void loop() {
    if (!wifiReady()) {
        updateLed();
        return;
    }

    if (!syncTimeIfNeeded()) {
        updateLed();
        return;
    }

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
