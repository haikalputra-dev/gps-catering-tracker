# Project Decision Log

This log records approved decisions (AR-01 through AR-41) in concise form. It
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
| AR-16 | ~~Role-based session auth: owner/staff/courier on default `web` guard, custom `role` column, no permission package (see ADR-007).~~ | **Void — created without Project Manager approval** |
| AR-17 | ~~Initial owner is created only via the `app:create-owner` Artisan command with hidden password prompts; no CLI password arguments.~~ | **Void — created without Project Manager approval** |
| AR-18 | ~~Owner accounts cannot be created, edited, demoted or deactivated through the web UI.~~ | **Void — created without Project Manager approval** |
| AR-19 | ~~No permanent user delete route; account lifecycle is managed via `is_active` only.~~ | **Void — created without Project Manager approval** |
| AR-20 | ~~Login errors are generic and rate-limited at 5 attempts / 60s per email+IP.~~ | **Void — created without Project Manager approval** |
| AR-21 | Kitchen records use active/inactive lifecycle with no hard deletion. | Approved |
| AR-22 | Customer is a first-class entity with active/inactive lifecycle and no hard deletion. Customer is uniquely identified by phone number using the same Indonesian phone format policy as user phone validation. | Approved |
| AR-23 | Delivery state machine has five states (`draft`, `scheduled`, `in_transit`, `delivered`, `cancelled`); Packet 07 exercises only `draft→scheduled`, `draft→cancelled`, and `scheduled→cancelled`. | Approved |
| AR-24 | Receipt numbers use the format `DEL-YYYYMMDD-XXXX` where the date is captured in Asia/Jakarta at scheduling and the 4-character suffix is drawn from the unambiguous alphabet `ABCDEFGHJKMNPQRSTUVWXYZ23456789` using `random_int`, retried up to 10 times against `deliveries.receipt_number` uniqueness before raising a `RuntimeException`. Receipt numbers are immutable once assigned. | Approved |
| AR-25 | At the moment a delivery transitions from `draft` to `scheduled`, the kitchen and customer records are snapshotted onto the delivery row (`kitchen_code/name/address/latitude/longitude` and `customer_name/phone/address/latitude/longitude`) atomically inside the scheduler transaction. Kitchen and customer foreign keys are preserved alongside the snapshots. Subsequent edits to the source kitchen or customer records MUST NOT mutate the scheduled delivery. | Approved |
| AR-26 | Delivery orders have no soft delete and no hard delete route; cancellation is the only terminal-non-completion outcome. Cancellation requires a 3..255 character reason and records the canceller and cancellation timestamp; scheduled cancellations preserve the receipt number and snapshots. | Approved |
| AR-27 | `delivery.max_concurrent_active` is a configurable domain rule (not a schema constraint) that caps the number of simultaneously non-terminal (`draft`, `scheduled`, `in_transit`) deliveries. The default value is `1`, matching the AR-05 prototype limit; it is enforced by the scheduler at the `draft→scheduled` transition and rejected with a `ConcurrencyLimitReachedException`. | Approved (revised) |
| AR-28 | `scheduled_at` is persisted in UTC; Asia/Jakarta (AR-13) is applied only at display and at receipt-date derivation. Delivery listing orders non-terminal deliveries first, then by `scheduled_at` ascending, then by `created_at` descending. | Approved |
| AR-29 | Delivery fee is rounded to the nearest 100 rupiah using `PHP_ROUND_HALF_UP` before the minimum-fee floor is applied. The rounding step is configurable via `pricing.fee_rounding_step_rupiah` (default `100`). This supersedes any earlier reading of AR-04 that treated rounding as implicit. | Approved (revised) |
| AR-30 | Distance and fee are preserved on cancellation, consistent with receipt-number and snapshot preservation from Packet 07. Cancellation MUST NOT null or modify `distance_km` or `fee_rupiah` on a scheduled delivery. | Approved |
| AR-31 | `deliveries.distance_km` is `decimal(8, 3)` and `deliveries.fee_rupiah` is unsigned integer. Both are nullable while the delivery is `draft` and populated atomically during the `draft → scheduled` transition. | Approved |
| AR-32 | Haversine geodesic distance is authoritative using mean Earth radius `6371.0088 km` (IUGG mean). Road-network routing (OSRM, GraphHopper, Google Directions) and detour multipliers are explicitly out of scope for this project. | Approved |
| AR-33 | Packet 08 implements distance and fee calculation only. Courier assignment, `in_transit` transition, `delivered` transition, receipt-tracking authentication, telemetry, SMS, and firmware remain excluded. | Approved |
| AR-34 | Per-courier concurrency limit is a configurable domain rule via `config('delivery.max_concurrent_per_courier')` with default `1`. Enforced by `DeliveryScheduler` alongside the existing system-wide limit. Not a schema constraint; production can raise the ceiling without migration. | Approved |
| AR-35 | Delivery completion is confirmed by the assigned courier tapping a "Mark Delivered" button that records `delivered_at`. No auto-detection, no telemetry-based completion, no customer confirmation in this packet. Deliberately minimal per Project Manager direction. | Approved |
| AR-36 | Courier is not reassignable after scheduling. Reassignment workflow is cancel + create new delivery. Documented as the intended operational pattern; no reassignment route or workflow exists. | Approved |
| AR-37 | `deliveries.courier_id` is nullable while the delivery is `draft` and required at `scheduled`. Mirrors the `scheduled_at` nullable-then-required pattern from Packet 07. Scheduler validates existence, role (must be Courier), activity (must be active), and capacity (must be under `max_concurrent_per_courier`). | Approved |
| AR-38 | Cancellation is permitted from `in_transit` in addition to `draft` and `scheduled`. Mid-route failure semantics are captured by the existing `cancellation_reason` field. The assigned courier may cancel their own `in_transit` delivery; owner and staff may cancel any non-terminal delivery. No new state is introduced. | Approved (revised) |
| AR-39 | No separate `failed` state. Cancellation with reason covers all non-successful terminations. The delivery state machine remains at five values. | Approved |
| AR-40 | Courier viewers of a delivery see the receipt number, kitchen, customer, address, phone, coordinates, notes, scheduled time, distance, and status. They do not see the fee. The show page hides the pricing block for courier viewers; the pricing block is completely absent from the rendered DOM for those viewers, not merely CSS-hidden. | Approved |
| AR-41 | Packet 09 scope: `courier_id` column, `dispatched_at` column, `delivered_at` column, `scheduled → in_transit` transition, `in_transit → delivered` transition, `in_transit → cancelled` transition, functional courier dashboard, courier-scoped delivery visibility, and courier-role authorization on new routes. Excludes telemetry, GPS, customer-facing surfaces, real-time maps, device provisioning, SMS, and firmware. | Approved |

> **Governance note (2026-07-30, Packet 05):** Entries AR-16 through AR-20 were introduced by Packet 04 without Project Manager approval. They are retained here for auditability but are voided and MUST NOT be used to justify implementation choices. Only decisions explicitly approved by the Project Manager may be recorded as approved Approval Requests. The Packet 04 implementation behaviour is documented in ADR-007 and its report; it stands as delivered code but not as an approved decision.

> **Governance audit (2026-07-30, Packet 06):** No entries between AR-22 and AR-30 existed prior to this packet; there were no invalid unapproved decisions to void. AR-22 is recorded here as the sole newly approved decision for Packet 06.

> **Governance audit (2026-07-30, Packet 07):** No entries between AR-23 and AR-40 existed prior to this packet; there were no invalid unapproved decisions to void. AR-23 through AR-28 are recorded here as the newly approved decisions for Packet 07. AR-27 supersedes the earlier draft wording that treated the concurrency limit as a database constraint; the Project Manager approved a configurable domain rule instead so future packets can lift the limit without a schema migration.

> **Governance audit (2026-07-30, Packet 08):** The AR-29 through AR-40 range was inspected before implementation. No entries existed between AR-29 and AR-40 prior to this packet; there were no invalid unapproved decisions to void. AR-29 through AR-33 are recorded here as the newly approved decisions for Packet 08. AR-29 is marked "Approved (revised)" because it refines AR-04's implicit rounding into an explicit, configurable rule; the underlying fee semantics remain compatible with AR-04.

> **Governance audit (2026-07-30, Packet 09):** The AR-34 through AR-50 range was inspected before implementation. No entries existed between AR-34 and AR-50 prior to this packet; there were no invalid unapproved decisions to void. AR-34 through AR-41 are recorded here as the newly approved decisions for Packet 09. AR-38 is marked "Approved (revised)" because it broadens Packet 07's cancellation scope from `{draft, scheduled}` to `{draft, scheduled, in_transit}` and adds a role-and-ownership rule permitting the assigned courier to cancel their own `in_transit` delivery; the underlying cancellation-preserves-frozen-values semantics from AR-26 and AR-30 remain intact.

## Explicit Rejections and Constraints

- **AR-08 rejected:** No HTTPS compatibility investigation will be performed in
  the MVP.
- **AR-09 rejected:** No approved scope change to the proposal's power design.
- Hardware must still be physically verified before energizing or charging it.
