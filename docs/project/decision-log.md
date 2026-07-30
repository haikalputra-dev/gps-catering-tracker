# Project Decision Log

This log records approved decisions (AR-01 through AR-15) in concise form. It
complements the ADRs under `docs/decisions/`.

| Ref   | Decision | Status |
|-------|----------|--------|
| AR-01 | Laravel is the authoritative calculation and persistence layer. | Approved |
| AR-02 | Haversine pricing uses the selected kitchen and customer endpoint. | Approved |
| AR-03 | Tracking telemetry never determines the delivery price. | Approved |
| AR-04 | Delivery fee formula: Rp 5,000 + (Haversine km × Rp 2,000). | Approved |
| AR-05 | Prototype supports one active delivery at a time. | Approved |
| AR-06 | Leaflet + OpenStreetMap provide the map interface (with attribution). | Approved |
| AR-07 | Customer tracking auth: receipt number + last four phone digits. | Approved |
| AR-08 | HTTPS compatibility investigation in the MVP. | Rejected |
| AR-09 | Scope change to the proposal's power design. | Rejected |
| AR-10 | ESP32 + NEO-7M + SIM800L is the device stack (later packet). | Approved |
| AR-11 | HTTP device communication is an accepted prototype limitation. | Approved |
| AR-12 | Runtime uses MySQL 8.0; tests use SQLite `:memory:` (see ADR-006). | Approved |
| AR-13 | Application timezone is Asia/Jakarta. | Approved |
| AR-14 | No production feature implemented before its specific task packet. | Approved |
| AR-15 | Runtime baseline: Laravel 13, PHP 8.3.32, MySQL 8.0.46 (see ADR-001). | Approved |
| AR-16 | Role-based session auth: owner/staff/courier on default `web` guard, custom `role` column, no permission package (see ADR-007). | Approved |
| AR-17 | Initial owner is created only via the `app:create-owner` Artisan command with hidden password prompts; no CLI password arguments. | Approved |
| AR-18 | Owner accounts cannot be created, edited, demoted or deactivated through the web UI. | Approved |
| AR-19 | No permanent user delete route; account lifecycle is managed via `is_active` only. | Approved |
| AR-20 | Login errors are generic and rate-limited at 5 attempts / 60s per email+IP. | Approved |

## Explicit Rejections and Constraints

- **AR-08 rejected:** No HTTPS compatibility investigation will be performed in
  the MVP.
- **AR-09 rejected:** No approved scope change to the proposal's power design.
- Hardware must still be physically verified before energizing or charging it.
