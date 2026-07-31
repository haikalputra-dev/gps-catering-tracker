# GPS Catering Tracker — ESP32 Firmware (dual-mode)

## What this is

A single ESP32 firmware source that builds in two flavors:

- **Wokwi mode** (`BUILD_MODE=1`, default env) — the browser
  simulator. Uses hardcoded Sukabumi-corridor waypoints as a mock
  GPS, connects to Wi-Fi, posts to the API over HTTPS, and gets its
  clock from NTP. This is the demo path and the CI/dev-loop path.
- **Hardware mode** (`BUILD_MODE=2`) — a real ESP32 wired to a
  SIM800L GSM/GPRS modem and a NEO-6M GPS module, mounted on a
  motorcycle for defense day. Uses GPRS instead of Wi-Fi, POSTs to
  the API over plain HTTP on port 80, and takes its timestamps from
  the GPS fix.

Both modes send the same JSON payload to the same endpoint host and
use the same 3-second cadence, 60-second-on-429 backoff, and Bearer
token auth. The API and web app cannot tell them apart.

## Files in this directory

- `platformio.ini` — two build envs (`esp32doit-devkit-v1` for
  Wokwi, `hardware` for real hardware). Sets `BUILD_MODE` and
  per-mode `lib_deps`.
- `src/main.cpp` — single source. Mode-specific code is wrapped in
  `#if BUILD_MODE == BUILD_WOKWI` / `BUILD_HARDWARE` blocks; the
  Arduino entrypoints and LED helper are shared.
- `src/config.example.h` — secrets + pin template. Copy to
  `config.h` locally and fill in the fields for your mode.
- `diagram.json` — Wokwi wiring (ESP32 + LED; unchanged from
  Packet 17.3).
- `wokwi.toml` — Wokwi VS Code extension artifact paths (unchanged).
- `README.md` — this file.

## Setup: get a device token

Both modes need a device token; the API rejects unauthenticated
posts.

1. Open `https://gps-catering.duckdns.org` and log in as an owner.
2. Go to `Devices` → `New device`. Name it (e.g. `wokwi-dev-1` or
   `motorcycle-1`) and save.
3. On the device detail page (`/devices/{id}`), copy the Bearer
   token. This is the only time it's shown in full.
4. Locally:
   ```
   cp firmware/src/config.example.h firmware/src/config.h
   ```
5. Edit `firmware/src/config.h` and replace
   `PASTE_YOUR_DEVICE_TOKEN_HERE` with your real token. `config.h`
   is gitignored.

## Set up a delivery so telemetry persists

The ingester only writes to `telemetry_records` when the posting
device is assigned to a courier who has an in-transit delivery. If
that chain is missing, the API accepts the payload but nothing shows
on the live map.

1. As owner: assign the device to a courier user at
   `/devices/{id}/assign`.
2. As staff: create a delivery for that courier's kitchen and any
   customer.
3. As the courier: dispatch the delivery (state becomes
   `in_transit`).

Now firmware POSTs will land in `telemetry_records` and the courier
marker will move.

---

## Wokwi mode (`BUILD_MODE=1`)

This is the default env. `pio run` with no `-e` flag builds it, and
the Wokwi VS Code extension will pick up
`.pio/build/esp32doit-devkit-v1/firmware.bin` (the env name matches
the board id on purpose so `wokwi.toml` doesn't need to change).

### Run in Wokwi (browser)

1. Go to `wokwi.com` and create a new "ESP32 (Arduino)" project.
2. Paste the contents of `firmware/src/main.cpp` into `sketch.ino`.
3. At the top of the file, add `#define BUILD_MODE 1` before any
   `#include` (the browser IDE doesn't read `platformio.ini`).
   Alternatively, keep the source unchanged and set BUILD_MODE via
   the Library Manager's "Build flags" field.
4. Replace the project's `diagram.json` with `firmware/diagram.json`.
5. Add a new project file `config.h`. Paste the contents of
   `firmware/src/config.example.h` and set your token.
6. Open the Library Manager tab and install: **ArduinoJson**.
7. Click Save to get a permanent project URL.
8. Click ▶ to start the simulation and open the Serial Monitor.

### Run in Wokwi (VS Code extension)

For hardware-parity development:

1. Install "Wokwi for VS Code" and "PlatformIO IDE" extensions.
2. Open `firmware/` in VS Code.
3. Copy `src/config.example.h` to `src/config.h` and set your token.
4. Run `pio run` — builds the default (`esp32doit-devkit-v1`) env.
5. `Ctrl+Shift+P` → `Wokwi: Start Simulator`.

### Expected Wokwi serial output

```
[boot] GPS Catering Tracker firmware (mock GPS)
[wifi] connecting to 'Wokwi-GUEST'...
[time] NTP synced: 2026-07-31T09:34:33Z
[post 1/18] lat=-6.9241 lng=106.9269 204 body=
[post 2/18] lat=-6.9243 lng=106.9285 204 body=
[post 3/18] lat=-6.9245 lng=106.9302 204 body=
...
```

LED blinks while waiting for Wi-Fi or NTP; goes solid once both are
up. Waypoints loop after the last entry.

---

## Hardware mode (`BUILD_MODE=2`)

### Bill of materials

- ESP32 DevKit V1 (or any board with two hardware UARTs and 3.3V IO).
- SIM800L quad-band GSM/GPRS module + antenna. **Needs a stable
  ~4V supply capable of 2A burst** — do NOT power it from ESP32's
  3.3V rail. Use a separate LM2596 or a 2S Li-ion pack with a
  buck-boost.
- NEO-6M GPS module + antenna.
- Activated prepaid SIM with a data plan. See the APN table in
  `config.example.h` for Indonesian carrier settings.
- Motorcycle mount, weatherproof enclosure, and a way to power the
  ESP32 (USB power bank or bike 12V + step-down to 5V).

### Wiring

Defaults in `config.example.h`; adjust the `#define`s if you rewire.

| Peripheral | Signal    | ESP32 pin | Notes                              |
| ---------- | --------- | --------- | ---------------------------------- |
| SIM800L    | TX -> RX  | GPIO 26   | ESP32 RX (SIM800_RX_PIN)           |
| SIM800L    | RX <- TX  | GPIO 27   | ESP32 TX (SIM800_TX_PIN)           |
| SIM800L    | GND       | GND       | Share with ESP32 GND               |
| SIM800L    | VCC       | 4V rail   | External supply, not ESP32 3.3V    |
| SIM800L    | PWRKEY    | (opt)     | Wire to `SIM800_PWR_PIN` if used   |
| NEO-6M     | TX -> RX  | GPIO 16   | ESP32 RX (GPS_RX_PIN)              |
| NEO-6M     | RX <- TX  | GPIO 17   | ESP32 TX (GPS_TX_PIN)              |
| NEO-6M     | VCC / GND | 3.3V / GND | Draw ~50mA, ESP32 rail is fine    |
| Status LED | +         | GPIO 2    | Onboard LED on most devkits        |

Keep the SIM800L antenna away from the GPS antenna to reduce RF
interference.

### Configure

1. Copy `src/config.example.h` to `src/config.h`.
2. Set `DEVICE_TOKEN` to the token from `/devices/{id}`.
3. Pick your carrier's `GPRS_APN` / `GPRS_USER` / `GPRS_PASS` (see
   the table in `config.example.h`).
4. If your SIM has a PIN, set `GPRS_SIM_PIN`; otherwise leave empty.
5. Adjust `SIM800_*_PIN` / `GPS_*_PIN` if you rewire.

### Build & flash

```
cd firmware
pio run -e hardware              # compile
pio run -e hardware -t upload    # flash over USB
pio device monitor -e hardware   # 115200 baud serial monitor
```

### Expected hardware serial output

```
[boot] GPS Catering Tracker firmware (NEO-6M + SIM800L)
[gsm] powering up SIM800L...
[gsm] initializing modem...
[gsm] waiting for network... OK
[gprs] connecting APN='internet'... OK
[post] skip: waiting for GPS fix (sats=3)
[post] skip: waiting for GPS fix (sats=5)
[post] lat=-6.924137 lng=106.926885 sats=7 204 body=
[post] lat=-6.924142 lng=106.926912 sats=7 204 body=
...
```

Time-to-first-fix on a cold-start NEO-6M can be 30-60 seconds under
open sky. LED blinks until both GPRS and GPS are up, then goes
solid.

### Why HTTP instead of HTTPS on hardware

SIM800L's on-chip TLS stack tops out at TLS 1.0 with weak ciphers,
and it cannot validate Let's Encrypt's chain. Making HTTPS work
would mean either pinning a self-signed cert or bit-banging TLS on
the ESP32, both of which are fragile enough to be a defense-day
liability. Instead the VPS Caddy config exposes a port-80 listener
that accepts **only** `POST /api/telemetry` — every other request on
port 80 is redirected to HTTPS. Auth still runs on the token in the
Authorization header, and the payload has no personal data. See
Packet 18 / `deployment/Caddyfile`.

---

## Troubleshooting

### Both modes

- `[post] 204 body=` but nothing on the live map — the device isn't
  assigned to a courier with an in-transit delivery. See "Set up a
  delivery" above.
- `[post] 401` — the token is missing, wrong, or the device was
  deactivated. Re-check `DEVICE_TOKEN` in `config.h`.
- `[post] 422` — payload validation failed. The response body names
  the offending field; most often it's `gps_timestamp` (must be
  `YYYY-MM-DDTHH:MM:SSZ`).
- `[post] 429` — rate limiter tripped. Firmware auto-backs-off 60
  seconds before its next attempt.

### Wokwi mode

- `NTP sync fails repeatedly` — Wokwi Wi-Fi didn't come up cleanly.
  Stop and restart the simulation.

### Hardware mode

- `[gsm] modem.init failed` — usually a power issue. Confirm the
  SIM800L is on its own 4V, 2A-capable rail and the ground is
  shared with the ESP32.
- `[gsm] waiting for network... FAIL` — no cell coverage or SIM
  isn't activated. Test the SIM in a phone.
- `[gprs] connecting APN='...' FAIL` — wrong APN, no data credit,
  or the carrier is blocking machine SIMs. Cross-check the APN
  table in `config.example.h`.
- `[post] skip: waiting for GPS fix` for more than a minute — the
  NEO-6M can't see the sky. Move the antenna outdoors.
- `[post] connect failed` — the port-80 telemetry listener isn't
  running on the VPS. Verify Caddy is up (`systemctl status caddy`
  on the VPS) and that Packet 18's Caddyfile is applied.
