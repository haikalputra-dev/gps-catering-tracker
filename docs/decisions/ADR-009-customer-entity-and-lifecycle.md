# ADR-009: Customer Entity and Lifecycle

- Status: Accepted
- Date: 2026-07-30
- Deciders: Product Manager, Tech Lead
- Related: ADR-003 (free mapping stack), ADR-007 (role-based session
  auth), ADR-008 (kitchen lifecycle and coordinate selection),
  AR-22 (customer entity and lifecycle)

## Context

Task Packet 06 introduces the second business entity of the system:
the customer. Customers are the delivery endpoints that later packets
(delivery scheduling, tracking, pricing) will point at.

Two decisions had to be settled in this packet, mirroring the shape
that Packet 05 established for kitchens:

1. What is the natural identifier for a customer and how is the
   customer removed?
2. How does an operator record a customer's delivery coordinate?

Prior decisions constrain the answers:

- ADR-003 forbids paid mapping providers.
- ADR-007 established that only owner and staff roles administer data;
  couriers do not.
- ADR-008 set the pattern of an active/inactive lifecycle with no
  delete route for first-class business entities.
- The system explicitly does not implement customer login. Customers
  never authenticate; they are records managed by owners and staff.

## Decision

### Identifier: phone number, unique after normalization

Customers are uniquely identified by their phone number stored in a
canonical form. Input is normalized by trimming whitespace, stripping
hyphens, parentheses and internal separators, and preserving at most
one leading `+`. The stored form must contain 9 to 15 digits. Any
alphabetic character in raw input is rejected. Uniqueness is enforced
on the normalized column value.

The rationale:

- Phone number is the most reliable natural identifier for a delivery
  customer in this business context. Names collide, addresses change,
  emails are not always collected.
- Normalizing before storage removes the ambiguity between
  `+62 812-3456-7890`, `+6281234567890`, and `+62(812)34567890` — all
  represent the same customer.
- A separate synthetic code, like the kitchen code, would add manual
  operator work without adding value: the phone number already exists
  in the operator's records.

### Lifecycle: active/inactive, no delete

Customers carry an `is_active` boolean. Owners and staff can flip this
value through the edit form. There is no delete route, no destroy
controller action, no soft-delete column, no restore path, and no
delete button in the UI. `DELETE /customers/{id}` returns HTTP 405
because the route is not registered.

The rationale mirrors ADR-008:

- Future delivery, tracking, and pricing records will reference the
  customer. Deletion would leave those references dangling or force
  cascade behavior we are not yet ready to specify.
- Historical reporting requires the record to remain even after a
  customer stops ordering.
- `is_active` keeps uniqueness of the phone column simple: an inactive
  customer still occupies the phone number, preventing accidental
  duplicate creation if the same customer resumes ordering later.

### Coordinate selection: reuse the kitchen map contract

Customer coordinates are chosen with the same Leaflet 1.9.4 stack the
kitchen form uses, driven by the same `config/map.php` values. The
customer map is a separate ES module (`customer-map.js`) with
independent DOM identifiers, but the contract is identical: the
operator clicks the map or drags a marker, the module writes the
coordinate into hidden inputs, and the server re-validates the values
on submit.

The rationale:

- Reuse of config and behavior keeps the two entity forms consistent
  for operators and reviewers.
- Duplicating the JavaScript module (rather than sharing one) keeps
  the two entities from becoming accidentally coupled through their
  input aids. If either entity's map needs to diverge later, the
  change stays local.

### Privacy: phone masking in list views

The customer index masks phone numbers in the table (first five
characters plus last four digits). The full phone number remains in
the edit form and in the database. This is a defense-in-depth measure
appropriate for a delivery back-office screen visible to multiple
staff.

### Explicitly excluded

- No customer login, customer portal, or customer API.
- No customer-facing SMS, email, or notification of any kind in this
  packet.
- No geocoding, address search, or routing.
- No relationships to deliveries, prices, tracking, devices, or SMS
  templates. Those wire up in later packets.
- No delete route. Owner, staff, and courier all fail to reach a
  delete endpoint because none exists.

## Consequences

### Positive

- The customer surface stays small: 5 routes, no delete path, no
  third-party integrations, and no auth flow beyond the existing
  session mechanism.
- Deactivation is reversible with no data loss.
- Coordinate authority is the server, matching the kitchen contract.
- Phone normalization prevents duplicate customers created from
  different formattings of the same number.

### Negative

- Operators cannot fully remove a customer record. Cleanup is a
  manual database task if it ever becomes necessary.
- Enforcing a specific normalization means operators cannot store a
  customer's phone number in unusual formats (extensions, letter
  vanity numbers). This is intentional.
- Phone masking on the index list adds a small friction when scanning
  for a specific customer by number; edit view exposes the full value.

### Neutral

- Later packets referencing customers must check `is_active` before
  scheduling deliveries or displaying customers in courier-facing UI.
- The `customer_map.js` module is a near-duplicate of `kitchen-map.js`
  but with distinct DOM IDs; a future refactor may extract a shared
  helper if a third map surface appears.

## Alternatives considered

- **Synthetic customer code (like `KIT-####`).** Rejected: phone
  number is the natural identifier and adding another code doubles
  data entry without benefit.
- **Soft-delete via `deleted_at`.** Rejected for the same reasons as
  ADR-008: it complicates uniqueness and adds nothing over
  `is_active`.
- **Free-text phone storage without normalization.** Rejected:
  duplicates would appear whenever two operators formatted the same
  number differently.
- **Customer login and self-service portal.** Rejected: out of scope
  for this system. Customers are records, not authenticated users.

## Related decisions

- ADR-003: free mapping stack, which forbids paid providers.
- ADR-007: session auth and role gating that this decision inherits.
- ADR-008: kitchen lifecycle pattern that this ADR mirrors.
- AR-22: customer entity and lifecycle summary in
  `docs/project/decision-log.md`.
