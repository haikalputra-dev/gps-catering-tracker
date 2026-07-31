# GPS Catering Tracker — ESP32 Firmware (Wokwi)

## What this is

This directory contains a Wokwi-simulated ESP32 firmware for the GPS
Catering Tracker. It reads NMEA sentences from a simulated NEO-6M GPS
module, then POSTs JSON telemetry to the deployed API at
`https://gps-catering.duckdns.org/api/telemetry` every three seconds
once a GPS fix is available. The firmware here is the same code that
will later flash to real hardware — the simulator only stands in for
the physical breadboard.

## Files in this directory

- `platformio.ini` — PlatformIO build config (ESP32 DevKit v1, Arduino
  framework, TinyGPSPlus + ArduinoJson dependencies).
- `wokwi.toml` — points Wokwi at the compiled `firmware.bin` / `.elf`
  produced by PlatformIO.
- `diagram.json` — Wokwi wiring: ESP32 + NEO-6M GPS + red LED with a
  220Ω resistor on GPIO2.
- `route.gpx` — GPS playback data. A ~2.5 km corridor from Sukabumi
  Alun-Alun toward Cikole, played back at 25 km/h by Wokwi.
- `src/main.cpp` — firmware source. Wi-Fi bring-up, NMEA parsing,
  HTTPS POST, LED status, 429 backoff.
- `src/config.example.h` — template for secrets. Copy to `src/config.h`
  and paste your device token. `config.h` is gitignored.
- `README.md` — this file.

## Setup: get a device token

The API rejects telemetry from unknown devices, so you need a Bearer
token bound to a device row in the database.

1. Open `https://gps-catering.duckdns.org` and log in as an owner.
2. Go to `Devices` → `New device`. Give it a name (e.g. `wokwi-dev-1`)
   and save.
3. On the device's detail page (`/devices/{id}`), copy the Bearer
   token shown after creation. This is the only time the token is
   revealed in full — treat it like a password.
4. Locally, copy the template and paste the token:

   ```
   cp firmware/src/config.example.h firmware/src/config.h
   ```

5. Edit `firmware/src/config.h` and replace
   `PASTE_YOUR_DEVICE_TOKEN_HERE` with the token from step 3. The
   `config.h` file is gitignored — real tokens must never land in
   the repo.

## Run in Wokwi (browser)

1. Sign in at `https://wokwi.com` and create a new PlatformIO project
   for ESP32.
2. Replace the default `platformio.ini`, `src/main.cpp`, and
   `diagram.json` with the copies from this directory. Upload
   `route.gpx` as a project file (Wokwi's file uploader button, top
   of the sidebar).
3. Create a new file `src/config.h` inside the Wokwi project — do NOT
   copy your real `config.h` into the shared project if you plan to
   share the Wokwi URL. Paste the contents of `config.example.h` and
   fill in your device token.
4. Click `Start Simulation`. Open the Serial Monitor panel.
5. Within a few seconds you should see:

   ```
   [boot] GPS Catering Tracker firmware
   [wifi] connecting to 'Wokwi-GUEST'...
   [post] 204  body=
   [post] 204  body=
   ```

## Run in Wokwi (VS Code extension)

1. Install the "Wokwi for VS Code" extension.
2. Install PlatformIO Core (`pip install platformio`) or the
   PlatformIO IDE extension.
3. Open the `firmware/` directory in VS Code.
4. Copy `src/config.example.h` to `src/config.h` and paste your token.
5. Run `pio run` in a terminal to build the firmware. This produces
   `.pio/build/esp32doit-devkit-v1/firmware.bin` and `.elf`, which
   `wokwi.toml` points at.
6. Press `Ctrl+Shift+P` → `Wokwi: Start Simulator`. The simulator
   picks up the wiring from `diagram.json` and the freshly built
   firmware.

## End-to-end demo flow

To see the courier marker move on the live map:

1. Log in as an owner. Assign the device to a courier user at
   `/devices/{id}/assign`.
2. Log in as staff. Create a delivery for that courier's kitchen and
   any customer. Note the delivery ID or receipt token.
3. Log in as the courier user. Open the delivery and dispatch it.
4. Start the Wokwi simulation. Wait ~10–20 seconds for the first GPS
   fix, then telemetry POSTs begin.
5. Open the delivery's detail page as staff or owner, or open `/track`
   as the customer with the receipt token + last 4 digits of the
   phone number, and watch the courier marker move along the Sukabumi
   corridor.

## Troubleshooting

Expected Serial Monitor output for each state:

- `[wifi] connecting to 'Wokwi-GUEST'...` — normal on boot. The ESP32
  will auto-reconnect if the link drops.
- LED blinking, no `[post]` lines yet — GPS is still acquiring a fix.
  Wait 10–20 seconds after simulation start; the NEO-6M model needs
  time to output valid NMEA even in the simulator.
- `[post] 204` — telemetry accepted. No response body is expected.
- `[post] 401 body=...` — the device token is missing, wrong, or
  belongs to a revoked device. Double-check `DEVICE_TOKEN` in
  `src/config.h` and confirm the device row is still active in the
  web app.
- `[post] 422 body=...` — the payload failed validation. Most often
  this is a malformed `gps_timestamp` (must be
  `YYYY-MM-DDTHH:MM:SSZ`) or missing lat/lng. Check the JSON printed
  by adding a `Serial.println(payload)` before the POST call.
- `[post] 429 body=...` — the API rate limiter tripped. The firmware
  automatically pauses for 60 seconds before its next POST attempt.
- `[post] connection error: connection refused` — Wi-Fi came up but
  the API host is unreachable, or the URL in `TELEMETRY_URL` is
  wrong. Confirm `https://gps-catering.duckdns.org` loads in a
  browser first.

If the LED never turns solid, check the wiring in `diagram.json` and
confirm that both the GPS module and the ESP32 share ground.

## Swapping the route

Any GPX with a single `<trkseg>` and 20–30 `<trkpt>` waypoints works
in place of `route.gpx`. Trace a corridor in any OSM-based GPX editor
(e.g. `gpx.studio`), export as GPX 1.1, and drop it in with the same
filename. Keep the point count under 30 or the simulator will loop
too slowly for a demo. Do not add `<time>` elements — playback speed
is controlled by the `speed` attribute on the `wokwi-neo-6m-gps` part
in `diagram.json`.
