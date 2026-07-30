# Delivery Pricing and Distance

Status: Implemented (Task Packet 08)
Governance: AR-04, AR-29 (revised), AR-30, AR-31, AR-32, AR-33
Related ADRs: ADR-002 (pricing/distance authority), ADR-013 (Haversine and fee formula)

## What this document covers

This document describes how delivery fees are calculated in the catering
tracker prototype. It is the reference for owners and staff who need to
understand what the numbers in the deliveries UI mean, and for engineers
who need to change or extend the pricing surface.

Out of scope for this document: courier assignment, in-transit or
delivered state, customer-facing fee display, telemetry, GPS, and
firmware. Those are handled (or explicitly deferred) elsewhere.

## Storage

Two columns on the `deliveries` table hold the frozen values:

| Column        | Type                | Nullability | Notes                              |
|---------------|---------------------|-------------|------------------------------------|
| `distance_km` | `decimal(8, 3)`     | nullable    | Straight-line kilometres, 3 dp     |
| `fee_rupiah`  | `unsigned int`      | nullable    | Rupiah, whole units, no decimals   |

Both are null while a delivery is in `draft` status. They are populated
in the same transaction that moves the delivery to `scheduled` and are
never rewritten afterwards, including on cancellation.

## Formula

Given the kitchen coordinates `(kLat, kLng)` and the customer coordinates
`(cLat, cLng)` captured on the delivery row at scheduling time, the fee
is computed as:

```
distance_km  = Haversine(kLat, kLng, cLat, cLng, R = 6371.0088)
raw_fee      = distance_km * rate_per_km_rupiah
rounded      = round(raw_fee / step, 0, HALF_UP) * step
fee_rupiah   = max(minimum_fee_rupiah, rounded)
```

`distance_km` is rounded to 3 decimal places (`PHP_ROUND_HALF_UP`) before
being stored, so the database value matches what the calculator saw.

The Earth radius `R = 6371.0088 km` is the IUGG mean radius. It lives on
`DistanceCalculator::EARTH_RADIUS_KM` as a class constant and is not
runtime-configurable. The three fee inputs are runtime-configurable.

## Configuration

Three keys are read from `config/pricing.php`, which in turn reads
environment variables at call time (so tests can override them):

| Env variable                       | Config key                          | Default |
|------------------------------------|-------------------------------------|---------|
| `PRICING_MINIMUM_FEE_RUPIAH`       | `pricing.minimum_fee_rupiah`        | 5000    |
| `PRICING_RATE_PER_KM_RUPIAH`       | `pricing.rate_per_km_rupiah`        | 2000    |
| `PRICING_FEE_ROUNDING_STEP_RUPIAH` | `pricing.fee_rounding_step_rupiah`  | 100     |

Overrides take effect on the next scheduling transaction only. Any
delivery scheduled before the override retains its original frozen fee.

## Truth table

Assuming the defaults `min=5000, rate=2000, step=100`:

| Distance (km) | Raw fee (rate * km) | Rounded to step | Applied floor  | Final fee |
|---------------|---------------------|------------------|----------------|-----------|
| 0.000         | 0                   | 0                | 5000            | **5000**  |
| 0.500         | 1000                | 1000             | 5000            | **5000**  |
| 2.500         | 5000                | 5000             | 5000            | **5000**  |
| 3.000         | 6000                | 6000             | 5000            | **6000**  |
| 3.567         | 7134                | 7100             | 5000            | **7100**  |
| 3.599         | 7198                | 7200             | 5000            | **7200**  |
| 5.025         | 10050               | 10100            | 5000            | **10100** |
| 5.075         | 10150               | 10200            | 5000            | **10200** |
| 100.000       | 200000              | 200000           | 5000            | **200000**|

## Where it happens

The distance and fee are computed inside `DeliveryScheduler` in the same
transaction that:

1. Locks the kitchen and customer rows.
2. Verifies the delivery is a draft with a valid `scheduled_at`.
3. Verifies the concurrency cap.
4. Captures the immutable kitchen and customer snapshot.
5. Computes distance from the snapshot coordinates.
6. Applies the fee formula.
7. Generates the receipt number.
8. Writes all of the above in a single `forceFill` + `save`.

Because the calculation uses the locked snapshot coordinates and not the
live kitchen/customer rows, the priced value is guaranteed to match
what the receipt shows even if the source rows change later.

## Immutability

Once a delivery is scheduled, `distance_km` and `fee_rupiah` are treated
the same way as the receipt number and the address snapshots:

- Cancellation does not clear or rewrite them.
- Editing the source kitchen or customer does not recompute them.
- No route or service exists to recompute a fee after scheduling.

Recalculation would require a new delivery, by design.

## Display

The values render only to authenticated owner and staff users:

- `deliveries/index.blade.php` shows a `Fee` column formatted as
  `Rp {number_format(fee_rupiah, 0, ',', '.')}`. Draft rows show `-`.
- `deliveries/show.blade.php` shows a dedicated Pricing card with
  `Distance` (3 dp, `km`), `Fee` (Indonesian rupiah), and a short note
  explaining that the value was frozen at scheduling and is not
  recalculated.

There is no customer-facing surface for these values in this packet.

## Error handling

`DistanceCalculator::between()` throws `InvalidArgumentException` for
out-of-range latitude (outside `[-90, 90]`) or longitude (outside
`[-180, 180]`). `PricingCalculator::feeForDistanceKm()` throws for a
negative distance or a non-positive rounding step. These are programmer
errors; user input in this packet is limited to selecting a valid
kitchen and customer, both of which are validated elsewhere.

## Not implemented

- No courier or driver assignment.
- No `in_transit` or `delivered` state.
- No customer-facing fee page or notification.
- No fee recalculation, discount, tax, or surcharge logic.
- No routing distance (only geodesic Haversine).
- No telemetry, GPS, or firmware integration.
