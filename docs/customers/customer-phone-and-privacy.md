# Customer Phone Number and Privacy

Reference for the phone-number handling rules introduced in Task
Packet 06.

## Why phone is the natural identifier

Customers do not authenticate to this system. The operator-facing
records identify a customer by the phone number the operator dials
when there is a delivery question. Because the same number can be
typed in many formats, we normalize on the way in.

The full rationale sits in
`docs/decisions/ADR-009-customer-entity-and-lifecycle.md`.

## Normalization rules

Implemented by `app/Domain/Customer/CustomerPhone.php`.

- Leading and trailing whitespace is trimmed.
- Whitespace inside the string, hyphens, and parentheses are
  removed.
- A single leading `+` is preserved. Additional `+` characters are
  stripped.
- Any alphabetic character in raw input causes validation to fail.
- The result must contain 9 to 15 digits.

The rule is deliberately permissive on input (spaces, hyphens,
parentheses are common in Indonesian formatting) and strict on
output (single stored form).

## Comparison with the user account phone rule

User accounts (see `app/Http/Requests/Owner/StoreUserRequest.php`)
allow the pattern `[0-9+\-() ]+` with a `max:25` length and no
length floor. Customer phone rules are stricter:

| Aspect                | User accounts               | Customers                                   |
|-----------------------|-----------------------------|---------------------------------------------|
| Alphabetic characters | Rejected (regex forbids)    | Rejected (regex forbids)                    |
| Separators allowed    | Yes, kept raw               | Yes, stripped before storage                |
| Length floor          | None                        | 9 digits after normalization                |
| Length ceiling        | 25 characters raw           | 15 digits after normalization               |
| Uniqueness            | On raw stored form          | On normalized stored form                   |
| Purpose               | Contact detail for a login  | Natural identifier for delivery record      |

If the user-account rule is tightened in a future packet, this
document should be revisited to keep the two policies aligned where
that makes sense.

## Uniqueness

The migration adds a unique index on `customers.phone`. Because the
value is normalized before being written, two records cannot exist
for the same phone number even if they were typed in different
formats. Inactive customers still occupy their phone number; a
retired customer who returns is expected to be reactivated rather
than recreated.

## Display and masking

The customer index view (`resources/views/customers/index.blade.php`)
masks each phone number so that only the first five characters and
last four digits are visible, with the middle portion rendered as
bullets. For example, `+6281234567890` renders as `+6281•••••7890`.

The masking is applied only in the index list; the edit form and
create form display the full value to authorized operators. This is
defense in depth for a back-office screen; it is not a substitute
for controlling who has an operator account.

## Data at rest

- `customers.phone` is `VARCHAR(25)`. No encryption at rest is
  configured for this column in Packet 06; the surrounding database
  security controls (DB user permissions, network isolation) are the
  boundary.
- Backups follow the general database backup policy of the
  deployment environment. No customer-specific backup rule is
  defined in this packet.

## What we do not do

- No SMS is sent to the phone number in this packet. There is no
  SMS gateway wired up to customer records.
- No verification of the phone number (call, SMS, or otherwise) is
  performed. Correctness is the operator's responsibility.
- No third-party service receives customer phone data. The phone
  number never leaves the application boundary during Packet 06
  work.
- No customer phone lookup, export, or reporting endpoint is
  exposed.

## Fixtures and tests

`CustomerFactory` uses Faker to synthesize phone numbers of the
form `+62` followed by ten random digits, which normalize cleanly
to the stored format. No real-world numbers appear in factories,
seeders, tests, or documentation.

## Related documents

- `docs/decisions/ADR-009-customer-entity-and-lifecycle.md`
- `docs/customers/customer-management.md`
- `docs/requirements/customer-requirements.md`
- `docs/requirements/identity-access-requirements.md` (user phone
  rule)
