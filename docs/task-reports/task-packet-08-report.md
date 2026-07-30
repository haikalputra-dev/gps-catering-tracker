# Task Packet 08 Report: Delivery Distance and Fee

- **Packet**: 08
- **Slice**: Delivery distance (Haversine) and rupiah fee, frozen at
  scheduling
- **Starting commit**: `d4ef9b4 feat: add delivery orders with schedule
  and cancel transitions`
- **Branch**: `main`
- **Date completed**: 2026-07-30

## Scope summary

Packet 08 extends the delivery slice (Packet 07) with two frozen values
on the scheduled delivery row:

- `distance_km` (`decimal(8, 3)`): straight-line Haversine distance in
  kilometres between the snapshot kitchen coordinates and the snapshot
  customer coordinates, rounded half-up to 3 decimals.
- `fee_rupiah` (`unsigned int`): rupiah fee computed by a small
  linear-plus-floor formula from `distance_km` and three configurable
  pricing constants.

Both values are computed exactly once, inside the same transaction that
captures the address snapshot and generates the receipt. They are
preserved on cancellation and never recomputed. Only owner and staff can
see them.

Explicitly out of scope: courier assignment; `in_transit` and
`delivered` states; receipt-tracking authorization; customer-facing
surface; SMS; GPS; telemetry; firmware; routing distance.

## Deliverables

### Governance

- `AR-29..AR-33` appended to `docs/project/decision-log.md`.
- `AR-29` marked "Approved (revised)" (formula/rounding wording
  clarified pre-implementation).
- Packet 08 governance-audit note recorded: no invalid entries voided
  between `AR-29` and `AR-40`; that range was empty.

### Configuration

- `config/pricing.php` created with three keys:
  `minimum_fee_rupiah`, `rate_per_km_rupiah`,
  `fee_rounding_step_rupiah`.
- `.env.example` appended with three matching `PRICING_*` keys and
  their defaults (`5000`, `2000`, `100`). `.env` untouched.

### Domain layer (`app/Domain/Delivery`)

- `DistanceCalculator` service. Haversine formula with IUGG mean radius
  `6371.0088 km` as class constant `EARTH_RADIUS_KM`. Validates
  latitude in `[-90, 90]` and longitude in `[-180, 180]` and throws
  `InvalidArgumentException` on violation. Clamps intermediate `a` to
  `[0, 1]` to keep antipodal inputs from returning `NaN`.
- `PricingCalculator` service. Reads `config('pricing.*')` on each
  call. Formula:
  `fee = max(minimum, round(distance * rate / step) * step)` using
  `PHP_ROUND_HALF_UP`. Rejects negative distance and non-positive
  rounding step with `InvalidArgumentException`.
- `DeliveryScheduler` constructor now injects both calculators
  alongside the existing `ReceiptNumberGenerator`. Distance and fee are
  computed from the locked snapshot coordinates and written in the
  same `forceFill` that captures the snapshot and receipt.
- `DeliveryCanceller`: unchanged. Its `forceFill` only writes the
  cancellation columns, so `distance_km` and `fee_rupiah` are
  preserved by construction. Covered by regression assertion.

### Persistence

- Migration
  `2026_07_30_141932_add_distance_and_fee_to_deliveries_table.php`
  adding `distance_km decimal(8,3) NULL` after `customer_longitude`
  and `fee_rupiah unsigned int NULL` after `distance_km`.
- `App\Models\Delivery`: both columns added to `$fillable`; casts
  `distance_km => decimal:3` and `fee_rupiah => integer`.
- `database/factories/DeliveryFactory.php` `scheduled` state
  (inherited by `cancelledFromScheduled`) now populates both columns
  using container-resolved calculators so the formula lives in one
  place.

### Presentation

- `resources/views/deliveries/index.blade.php` gains a Fee column
  formatted `Rp <indonesian-thousands>`; drafts show a placeholder.
- `resources/views/deliveries/show.blade.php` gains a Pricing card
  showing distance (3 dp), fee (rupiah), and a short note that both
  values were frozen at scheduling and are not recalculated.

## Formula

```
distance_km  = Haversine(kLat, kLng, cLat, cLng, R = 6371.0088)
                rounded half-up to 3 decimals
raw_fee      = distance_km * pricing.rate_per_km_rupiah
rounded      = round(raw_fee / pricing.fee_rounding_step_rupiah, 0, HALF_UP)
               * pricing.fee_rounding_step_rupiah
fee_rupiah   = max(pricing.minimum_fee_rupiah, rounded)
```

Defaults: `minimum_fee_rupiah = 5000`, `rate_per_km_rupiah = 2000`,
`fee_rounding_step_rupiah = 100`.

## Integration point

Inside `DeliveryScheduler::schedule()`, in a single DB transaction:

1. Lock the delivery, kitchen, and customer rows (`lockForUpdate`).
2. Verify draft status, `scheduled_at` present and in the future,
   kitchen and customer active, concurrency cap not exceeded.
3. Capture the immutable ten-column snapshot from the locked rows.
4. Compute `distance_km` from the snapshot coordinates and round it.
5. Compute `fee_rupiah` from the rounded `distance_km`.
6. Generate the receipt number (retry loop on collision).
7. `forceFill` all of the above onto the delivery and `save`.

Because distance and fee are computed from the snapshot values, not
from the live source rows, later edits to the kitchen or customer
cannot cause the priced value to disagree with the receipted address.

## Tests

New:

- `tests/Unit/Domain/Delivery/DistanceCalculatorTest.php`
  (12 tests): identity, 1 deg longitude at equator, 1 deg latitude,
  symmetry, Jakarta-to-Bogor bounded range, antipodal half-circumference,
  finiteness, four out-of-range failures, Earth radius constant.
- `tests/Unit/Domain/Delivery/PricingCalculatorTest.php`
  (15 tests): nine-row truth table via data provider, negative
  distance rejection, three per-key config overrides, non-positive
  step rejection, return type, zero-distance floor.
- `tests/Feature/Delivery/DeliveryPricingTest.php` (8 tests):
  null-on-draft, freeze at schedule, cancel-preserves,
  source-move-does-not-drift, show-card rendering, draft-placeholder
  rendering, index Fee column, config-override-honored-at-schedule.

Extended:

- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php` scheduler
  helper now injects `DistanceCalculator` and `PricingCalculator`;
  the happy-path test asserts frozen distance and fee.
- `tests/Unit/Domain/Delivery/DeliveryCancellerTest.php`
  scheduled-cancel test asserts distance and fee are preserved
  byte-for-byte.

## Verification

- `php artisan test`: **271 passed / 720 assertions** (was 235 / 653;
  +36 tests, +67 assertions).
- `npm run build`: successful (Vite 8.1.5, 397 ms).
- `composer audit`: 0 vulnerabilities.
- `npm audit`: 0 vulnerabilities.
- `git diff --check`: clean.
- MySQL migration applied at runtime
  (`2026_07_30_141932_add_distance_and_fee_to_deliveries_table`,
  294.68 ms).
- 127.0.0.1 smoke test: `/login` returns 200; `/deliveries` returns
  302 to `/login` for guests; port 8000 released after teardown.
- Runtime schema verified: `distance_km` at column position 17,
  `fee_rupiah` at column position 18 (immediately after
  `customer_longitude`, matching migration intent).

## Deliberately not implemented

- No courier assignment or driver surface.
- No `in_transit` or `delivered` transitions.
- No customer-facing pricing, fee, or receipt page.
- No fee recalculation, discount, tax, surcharge, or refund.
- No routing distance (Haversine geodesic only).
- No new HTTP route, controller action, or FormRequest. The delivery
  state machine and route list are unchanged from Packet 07.
- No SMS, GPS, telemetry, or firmware work.
- Leaflet 1.9.4 is unchanged and unused by this packet.
- `.env` and `/home/ubuntu/GPS-server` untouched.

## Traceability

| Requirement    | Approved row | Implementing artifact                          |
| -------------- | ------------ | ---------------------------------------------- |
| DEL-FR-031     | AR-32        | `DistanceCalculator::between`                  |
| DEL-FR-032     | AR-29 rev.   | `PricingCalculator::feeForDistanceKm`          |
| DEL-FR-033     | AR-30        | `config/pricing.php`, `.env.example`           |
| DEL-FR-034     | AR-31        | `DeliveryScheduler`, `DeliveryCanceller`       |
| DEL-FR-035     | AR-33        | `deliveries/index.blade.php`, `show.blade.php` |
| DEL-FR-036     | AR-31        | Nullable columns + scheduling transaction      |

