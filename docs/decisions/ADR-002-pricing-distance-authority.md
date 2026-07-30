# ADR-002 — Pricing and Distance Authority

## Status

Accepted

## Context

The system will later calculate a delivery fee based on distance between a
kitchen and a customer endpoint. It is critical to define which component is
authoritative for this calculation and which inputs are used, so that live GPS
telemetry cannot manipulate pricing.

## Decision

- The Laravel server is the authoritative calculation and persistence layer.
- Pricing uses the endpoint Haversine distance.
- The origin is the selected kitchen.
- The destination is the admin/staff-selected customer map coordinate.
- The fee is frozen before tracking begins.
- Tracking telemetry is used only for monitoring and evaluation and never
  determines the delivery price.

The future fee formula is:

```text
Rp 5,000 + (Haversine distance in km × Rp 2,000)
```

## Consequences

- Pricing is deterministic and reproducible from stored endpoints.
- Live device telemetry cannot alter a quoted or frozen fee.
- No implementation exists yet; this ADR only records the agreed authority model.
