# ADR-013: Haversine Distance and Fee Formula

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 08
- Related: ADR-002 (pricing and distance authority),
  ADR-011 (delivery snapshots and receipt),
  AR-04, AR-29 (revised), AR-30, AR-31, AR-32, AR-33

## Context

Deliveries in the catering tracker prototype need a monetary fee that
scales with how far the food travels. The fee must be a single frozen
value on the delivery row so the receipt matches what the operator quoted
at scheduling time and does not drift if the kitchen or customer address
is later edited or the price sheet changes.

Two independent decisions had to be made:

1. How to compute the distance between two coordinates.
2. How to convert that distance into a rupiah fee.

Both had to be small, deterministic, testable, and free of external
service dependencies suitable for a prototype.

## Decision

### Distance

Use the Haversine great-circle formula with a fixed Earth radius of
`6371.0088 km` (IUGG mean). The radius lives on
`DistanceCalculator::EARTH_RADIUS_KM` as a class constant and is not
runtime-configurable.

The calculator clamps the intermediate `a` value to `[0, 1]` before the
arcsine so antipodal or floating-point-degenerate inputs cannot return
`NaN`. It validates that both latitudes lie in `[-90, 90]` and both
longitudes in `[-180, 180]` and throws `InvalidArgumentException` on
violation.

The result is a `float` in kilometres. The scheduler rounds it to 3
decimal places (`PHP_ROUND_HALF_UP`) before storing it in the
`decimal(8, 3)` column so the persisted value matches the pricing
input.

### Fee

Given the rounded distance in kilometres and three configurable inputs:

```
raw       = distance_km * rate_per_km_rupiah
rounded   = round(raw / step, 0, HALF_UP) * step
fee       = max(minimum_fee_rupiah, rounded)
```

Defaults: minimum `5000`, rate `2000` per km, rounding step `100`.

The three inputs are read from `config/pricing.php` on every call, which
in turn reads environment variables. Tests override them with
`config()->set(...)`.

### Immutability

Both `distance_km` and `fee_rupiah` are set exactly once, in the same
transaction that captures the address snapshot and generates the receipt
number. No route or service recomputes them. Cancellation preserves them.

## Alternatives considered

- **Vincenty / geodesic ellipsoid distance.** More accurate for long
  distances but overkill for local catering deliveries where errors of
  0.5% cost less than a rounding step. Haversine is standard, easy to
  audit, and does not require a maths library.
- **Straight kilometre-linear pricing without rounding.** Rejected
  because rupiah values with three-digit tails are awkward for cash
  handling. The rounding step is configurable.
- **Distance-band pricing (0-3 km flat, 3-10 km flat, etc.).** More
  operator-friendly at first glance but produces sharp cliffs at band
  boundaries and requires a table of tiers we do not yet have signed
  off. A single linear rate plus a floor is easier to reason about now
  and can be replaced later without touching persisted rows.
- **Routing service (OSRM, GraphHopper, Google).** Rejected for the
  prototype: adds an external dependency, requires network at scheduling
  time, adds latency to the transaction, and is not needed to prove the
  workflow. May be revisited if operators complain that geodesic
  distance underestimates realistic drive distance.
- **Storing only distance and recomputing fee on display.** Rejected
  because the fee is the receipted figure. Recomputation on display
  would silently repay old deliveries at new rates whenever the price
  sheet changes.

## Consequences

Positive:

- Fee is deterministic, testable, and free of external services.
- Fee is auditable: given the snapshot coordinates on a delivery row,
  anyone can reproduce the number.
- Configuration is env-driven, so operators can change the price sheet
  without a code change.
- Frozen values give the receipt genuine legal weight.

Negative:

- Geodesic distance underestimates real driving distance, especially in
  urban Jakarta where straight lines cross unbridged rivers. Operators
  who need a driving-distance fee will need routing later.
- Changing the formula requires an ADR and a new task packet, not a
  configuration change.

Neutral:

- The Earth radius is fixed. That is intentional and cheap to revisit
  if we ever adopt a different reference model.

## Implementation pointers

- `app/Domain/Delivery/DistanceCalculator.php` — Haversine.
- `app/Domain/Delivery/PricingCalculator.php` — fee formula.
- `app/Domain/Delivery/DeliveryScheduler.php` — integration point,
  inside the scheduling transaction after the concurrency check and
  before the receipt is generated.
- `config/pricing.php` — three keys.
- `database/migrations/2026_07_30_141932_add_distance_and_fee_to_deliveries_table.php`
  — schema.
- `docs/deliveries/pricing-and-distance.md` — user-facing description.
