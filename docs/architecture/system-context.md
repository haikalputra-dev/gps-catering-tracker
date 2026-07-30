# System Context

This document describes the intended future logical flow of the GPS Catering
Tracker system. None of the components below are implemented yet. This packet
only establishes the project baseline.

## Intended Logical Flow

```text
Owner/Staff
    |
Laravel Web Application
    |
MySQL
    |
Public Customer Tracking Page

ESP32
    |
NEO-7M GPS
    |
SIM800L GPRS/SMS
    |
Laravel Device API
```

## Status

All components shown above are planned. As of this packet:

- The Laravel web application is initialized but contains no business features.
- MySQL integration is not yet configured (development uses SQLite).
- The public customer tracking page does not exist.
- The device API does not exist.
- ESP32, NEO-7M and SIM800L firmware/integration is not implemented.

These components will be implemented only through their specific approved task
packets.
