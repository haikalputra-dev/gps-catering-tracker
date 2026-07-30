# Customer Management

Operator-facing guide for the customer records feature introduced by
Task Packet 06.

## Who can use it

- **Owner** and **staff** roles: full access to list, create, edit,
  and toggle active/inactive.
- **Courier** role: no access. Any customer route returns HTTP 403.
- **Guest** (not logged in): redirected to `/login`.
- **Inactive user session**: logged out by the `active` middleware.

## What a customer record contains

| Field       | Type          | Notes                                             |
|-------------|---------------|---------------------------------------------------|
| `name`      | string(150)   | Required. Displayed in the list and edit views.   |
| `phone`     | string(25)    | Required. Unique after normalization. See below.  |
| `address`   | text          | Required. Free-text delivery address.             |
| `latitude`  | decimal(10,7) | Required. `-90` to `90`.                          |
| `longitude` | decimal(10,7) | Required. `-180` to `180`.                        |
| `notes`     | text nullable | Optional. Free-text delivery hints.               |
| `is_active` | boolean       | Required. Defaults to `true` on create.           |

## Phone number handling

Phone input is normalized before being stored:

- Leading and trailing whitespace is trimmed.
- Whitespace inside the string, hyphens, and parentheses are removed.
- A single leading `+` is preserved. Any additional `+` characters
  are stripped.
- Any alphabetic character in raw input causes validation to fail.
- The stored value must contain 9 to 15 digits.

Examples of accepted inputs and how they are stored:

| Input                    | Stored form         |
|--------------------------|---------------------|
| `+62 812-3456-7890`      | `+6281234567890`    |
| `+62(812)34567890`       | `+6281234567890`    |
| `0812-3456-7890`         | `081234567890`      |
| `   +62 813 9999 0000  ` | `+6281399990000`    |

Two operators entering the same customer's number in different
formats will produce the same stored value, and the second submission
will fail with a uniqueness error.

## Listing customers

- Route: `GET /customers`.
- Pagination: 15 records per page.
- Ordering: active first, then by `name` ascending, then by `id`.
- Phone masking: the list shows the first five characters and the
  last four digits, with the middle portion replaced by bullets, for
  example `+6281••••7890`. The full number is available on the edit
  page.

## Creating a customer

- Route: `GET /customers/create`, submitting to `POST /customers`.
- The form validates on the server; the map picker is an input aid
  only. Latitude and longitude are hidden fields updated by
  `resources/js/customer-map.js`.
- The form will not submit until a coordinate is selected on the map.
- A newly created customer defaults to active. Uncheck the toggle to
  create an inactive record (rare).

## Editing a customer

- Route: `GET /customers/{customer}/edit`, submitting to
  `PUT /customers/{customer}`.
- Any field can be updated, including the phone number. The
  uniqueness check ignores the record itself when validating.
- The active toggle deactivates or reactivates the customer without
  altering other fields.

## Removing a customer

Customers cannot be deleted. There is no delete route, no destroy
controller action, no soft-delete column. If a customer must be
retired, mark them inactive from the edit form. `DELETE /customers/{id}`
returns HTTP 405 because the route is not registered.

## Map picker

- Uses Leaflet 1.9.4, bundled through Vite. No CDN, no keyed
  provider, no browser geolocation.
- Reads its configuration from `config/map.php`
  (`default_latitude`, `default_longitude`, `default_zoom`,
  `selection_zoom`, `tile_url`, `tile_attribution`, `tile_max_zoom`).
- Click the map or drag the marker to change the coordinate. The
  hidden latitude and longitude inputs update in real time.
- Attribution is rendered by Leaflet from the configured value; the
  default attributes OpenStreetMap.

## Validation errors

Common validation errors and how to resolve them:

| Error message                                        | Cause / fix                                                  |
|------------------------------------------------------|--------------------------------------------------------------|
| "The name field is required."                        | Enter a non-empty name up to 150 characters.                 |
| "The phone number must contain 9 to 15 digits..."    | Remove letters or extend the number to at least 9 digits.    |
| "The phone has already been taken."                  | A customer with that normalized number already exists.       |
| "The address field is required."                     | Enter a delivery address up to 1000 characters.              |
| "The latitude must be between -90 and 90."           | Reselect the coordinate on the map.                          |
| "The longitude must be between -180 and 180."        | Reselect the coordinate on the map.                          |
| "Please select a coordinate on the map before ..."   | Client-side hint; click the map before submitting.           |

## Related documents

- `docs/decisions/ADR-009-customer-entity-and-lifecycle.md`
- `docs/customers/customer-phone-and-privacy.md`
- `docs/requirements/customer-requirements.md`
- `docs/kitchens/kitchen-management.md` (parallel entity, same
  lifecycle pattern)
