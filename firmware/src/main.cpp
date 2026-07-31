#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <TinyGPS++.h>
#include <ArduinoJson.h>

#include "config.h"

// ---- Pins ------------------------------------------------------
static constexpr int PIN_GPS_RX = 16;   // ESP32 UART2 RX  <- GPS TX
static constexpr int PIN_GPS_TX = 17;   // ESP32 UART2 TX  -> GPS RX (unused)
static constexpr int PIN_LED    = 2;    // External LED via 220R

// ---- Globals ---------------------------------------------------
TinyGPSPlus gps;
HardwareSerial gpsSerial(2);            // UART2

unsigned long lastPostMs  = 0;
unsigned long lastBlinkMs = 0;
bool          ledState    = false;

// ---- Helpers ---------------------------------------------------
static bool wifiReady() {
    return WiFi.status() == WL_CONNECTED;
}

// Build "YYYY-MM-DDTHH:MM:SSZ" from TinyGPS++ date+time.
// NEO-6M reports UTC, so no timezone conversion is needed.
static String buildIsoTimestamp() {
    char buf[24];
    snprintf(buf, sizeof(buf),
             "%04u-%02u-%02uT%02u:%02u:%02uZ",
             gps.date.year(), gps.date.month(), gps.date.day(),
             gps.time.hour(), gps.time.minute(), gps.time.second());
    return String(buf);
}

// Returns HTTP status code, or negative on connection failure.
static int postTelemetry() {
    WiFiClientSecure client;
    client.setInsecure();  // AR-63: skip cert verification (thesis scope)

    HTTPClient http;
    if (!http.begin(client, TELEMETRY_URL)) {
        Serial.println("[post] http.begin failed");
        return -1;
    }
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", String("Bearer ") + DEVICE_TOKEN);
    http.setTimeout(5000);

    JsonDocument doc;
    doc["latitude"]      = gps.location.lat();
    doc["longitude"]     = gps.location.lng();
    doc["gps_timestamp"] = buildIsoTimestamp();
    if (gps.speed.isValid())  doc["speed_kmh"]        = gps.speed.kmph();
    if (gps.course.isValid()) doc["heading_degrees"]  = gps.course.deg();

    String payload;
    serializeJson(doc, payload);

    int code = http.POST(payload);
    if (code > 0) {
        String body = http.getString();
        Serial.printf("[post] %d  body=%s\n", code, body.c_str());
    } else {
        Serial.printf("[post] connection error: %s\n",
                      http.errorToString(code).c_str());
    }
    http.end();
    return code;
}

static void updateLed() {
    bool ready = wifiReady()
              && gps.location.isValid()
              && gps.date.isValid()
              && gps.time.isValid();

    if (ready) {
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
    Serial.println("\n[boot] GPS Catering Tracker firmware");

    pinMode(PIN_LED, OUTPUT);
    digitalWrite(PIN_LED, LOW);

    gpsSerial.begin(9600, SERIAL_8N1, PIN_GPS_RX, PIN_GPS_TX);

    Serial.printf("[wifi] connecting to '%s'...\n", WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.persistent(true);
    WiFi.begin(WIFI_SSID, WIFI_PASS);
}

void loop() {
    // 1. Feed GPS characters to TinyGPS++.
    while (gpsSerial.available()) {
        gps.encode(gpsSerial.read());
    }

    // 2. Bail if Wi-Fi is down. ESP32 core handles auto-reconnect.
    if (!wifiReady()) {
        updateLed();
        return;
    }

    // 3. Bail if GPS is not fixed yet.
    bool ready = gps.location.isValid()
              && gps.date.isValid()
              && gps.time.isValid();
    if (!ready) {
        updateLed();
        return;
    }

    // 4. Cadence gate.
    unsigned long now = millis();
    if (now - lastPostMs < POST_INTERVAL_MS) {
        updateLed();
        return;
    }
    lastPostMs = now;

    int code = postTelemetry();

    // 5. Rate-limit backoff (AR-64): if 429, pause 60s.
    if (code == 429) {
        Serial.println("[post] 429 rate-limited, backing off 60s");
        lastPostMs = now + 60000 - POST_INTERVAL_MS;
    }

    updateLed();
}
