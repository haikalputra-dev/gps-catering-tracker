# GPS Catering Tracker — ESP32 Firmware (Wokwi, mock GPS)

## What this is

This directory contains a Wokwi-simulated ESP32 firmware that POSTs
mock GPS telemetry to the deployed API at
`https://gps-catering.duckdns.org/api/telemetry`. The waypoints are
hardcoded in `src/main.cpp` — one Sukabumi corridor — and the
timestamp comes from NTP instead of a GPS module. For real hardware,
replace the waypoint array with TinyGPS++ NMEA parsing on UART2; git
history has the earlier NEO-6M version if you want to see the wiring
pattern.

## Files in this directory

- `platformio.ini` — PlatformIO build config. Only used by the VS Code
  Wokwi extension; the browser IDE uses its own Library Manager.
- `src/main.cpp` — firmware source.
- `src/config.example.h` — secrets template. Copy to `config.h`
  locally and paste your device token.
- `diagram.json` — Wokwi wiring: ESP32 + LED (no GPS module needed).
- `wokwi.toml` — build artifact paths for the VS Code extension.
- `README.md` — this file.

## Setup: get a device token

1. Open `https://gps-catering.duckdns.org` and log in as an owner.
2. Go to `Devices` → `New device`. Name it (e.g. `wokwi-dev-1`) and
   save.
3. On the device detail page (`/devices/{id}`), copy the Bearer
   token. This is the only time it's shown in full.
4. Locally:

   ```
   cp firmware/src/config.example.h firmware/src/config.h
   ```

5. Edit `firmware/src/config.h` and replace
   `PASTE_YOUR_DEVICE_TOKEN_HERE` with your real token. `config.h` is
   gitignored.

## Set up a delivery so telemetry persists

The ingester only writes to `telemetry_records` when the posting
device is assigned to a courier who has an in-transit delivery. If
that chain is missing, the API accepts the payload but nothing
appears on the live map.

1. As owner: assign the device to a courier user at
   `/devices/{id}/assign`.
2. As staff: create a delivery for that courier's kitchen and any
   customer.
3. As the courier: dispatch the delivery (state becomes `in_transit`).

Now firmware POSTs will land in `telemetry_records` and the courier
marker will move.

## Run in Wokwi (browser)

1. Go to `wokwi.com` and create a new "ESP32 (Arduino)" project.
2. Paste the contents of `firmware/src/main.cpp` into `sketch.ino`.
3. Replace the contents of the project's `diagram.json` with
   `firmware/diagram.json`.
4. Add a new project file `config.h`. Paste the contents of
   `firmware/src/config.example.h` and set your token.
5. Open the Library Manager tab and install: **ArduinoJson**.
6. Click Save (top-left) to get a permanent project URL.
7. Click the green ▶ to start the simulation. Open the Serial
   Monitor pane on the right.

## Run in Wokwi (VS Code extension, optional)

For hardware-parity development:

1. Install "Wokwi for VS Code" and "PlatformIO IDE" extensions.
2. Open the `firmware/` directory in VS Code.
3. Copy `src/config.example.h` to `src/config.h` and set your token.
4. Run `pio run` in a terminal to build the firmware.
5. `Ctrl+Shift+P` → `Wokwi: Start Simulator`.

## Expected serial output

```
[boot] GPS Catering Tracker firmware (mock GPS)
[wifi] connecting to 'Wokwi-GUEST'...
[time] NTP synced: 2026-07-31T09:34:33Z
[post 1/18] lat=-6.9241 lng=106.9269 204 body=
[post 2/18] lat=-6.9243 lng=106.9285 204 body=
[post 3/18] lat=-6.9245 lng=106.9302 204 body=
...
```

The LED blinks while waiting for Wi-Fi or NTP, and goes solid once
both are up. Waypoints loop after the last entry, so the simulation
runs indefinitely.

## Troubleshooting

- `[post] 204 body=` but nothing on the live map — the device isn't
  assigned to a courier with an in-transit delivery. See the "Set up
  a delivery" section above.
- `[post] 401` — the token is missing, wrong, or the device was
  deactivated. Re-check `DEVICE_TOKEN` in `config.h`.
- `[post] 422` — payload validation failed. The response body names
  the offending field; most often it's `gps_timestamp` (must be
  `YYYY-MM-DDTHH:MM:SSZ`).
- `[post] 429` — rate limiter tripped. The firmware auto-backs-off
  60 seconds before its next attempt.
- `NTP sync fails repeatedly` — Wokwi Wi-Fi didn't come up cleanly.
  Stop and restart the simulation.

## Path to real hardware

The `WAYPOINTS[]` array is the only meaningful difference between
this firmware and a hardware build. To move to a real ESP32 + NEO-6M
GPS module: remove the array and `currentIndex`; add
`HardwareSerial gpsSerial(2)` on GPIO16/17; parse incoming NMEA with
TinyGPS++; and read `gps.location.lat()`, `gps.location.lng()`,
`gps.speed.kmph()`, and `gps.course.deg()` in place of the array
lookups. Also switch `buildIsoTimestamp()` to use `gps.date` and
`gps.time` instead of NTP (NEO-6M reports UTC natively). Everything
else — Wi-Fi bring-up, HTTPS POST, LED status, 429 backoff — carries
over unchanged.
