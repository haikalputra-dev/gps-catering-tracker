# Kitchen Management

Introduced in Task Packet 05.

## Fields

| Field       | Type          | Notes                                          |
|-------------|---------------|------------------------------------------------|
| `id`        | bigint        | Primary key                                    |
| `code`      | string(30)    | Unique, normalized uppercase, `[A-Z0-9-]+`     |
| `name`      | string(150)   | Required                                        |
| `address`   | text          | Required                                        |
| `phone`     | string(25)    | Optional, digits and `+ - ( ) space`            |
| `latitude`  | decimal(10,7) | Required, between -90 and 90                    |
| `longitude` | decimal(10,7) | Required, between -180 and 180                  |
| `is_active` | boolean       | Default true, indexed                           |
| `created_at`| timestamp     | Managed by Eloquent                             |
| `updated_at`| timestamp     | Managed by Eloquent                             |

Coordinates persist to seven decimal places (roughly 1 cm resolution).

## Access

| Role     | List | Create | Edit | Deactivate | Delete |
|----------|------|--------|------|------------|--------|
| Owner    | Yes  | Yes    | Yes  | Yes        | No     |
| Staff    | Yes  | Yes    | Yes  | Yes        | No     |
| Courier  | No (HTTP 403) | No | No | No       | No     |
| Guest    | Redirected to /login | | | |     |

Enforcement is layered:

- Route middleware `role:owner,staff` on every kitchen route.
- Existing `auth` and `active` middleware handle authentication and
  inactive-user shutdown.
- No delete route exists. `DELETE /kitchens/{kitchen}` returns HTTP 405.

## Code normalization

Kitchen codes are normalized before validation:

1. Trim surrounding whitespace.
2. Uppercase.
3. Validate against `/^[A-Z0-9-]+$/` (max 30 characters).

Examples:

```
kitchen-001   -> KITCHEN-001    (valid)
 sukabumi-1   -> SUKABUMI-1     (valid)
KITCHEN 01    -> rejected       (space)
KITCHEN_01    -> rejected       (underscore)
kitchen.01    -> rejected       (dot)
```

Uniqueness is checked against the stored uppercase form. Update requests
may keep the same code they already have.

Implementation: `app/Domain/Kitchen/KitchenCode.php`, exercised by
`tests/Unit/Domain/Kitchen/KitchenCodeTest.php` and by the request
classes in `app/Http/Requests/Kitchen/`.

## Lifecycle

- Kitchens can be created active or inactive.
- Active kitchens sort before inactive on the index page; within a
  status group, kitchens are ordered by name and then by ID.
- Deactivation preserves the record and all fields. It does NOT delete.
- Reactivation is done through the same edit form.
- Inactive kitchens will later be excluded from delivery scheduling
  (deferred to a future packet).

There is no soft-delete column, no restore path, and no hard-delete
route. The application intentionally has no way to remove kitchen
records through HTTP.

## Workflow

### Create

1. Owner or staff opens `/kitchens/create`.
2. Fills out code, name, address, and optional phone.
3. Clicks the map or drags the marker to choose coordinates.
4. Confirms active/inactive state.
5. Submits the form; on success, redirected to the index with a status
   message.

### Edit

1. Owner or staff opens `/kitchens/{id}/edit`.
2. Saved coordinate is loaded onto the map.
3. Any field, including code, may be updated.
4. Coordinate can be moved by clicking the map or dragging the marker.
5. Active/inactive state may be toggled from the same form.

## Non-goals

- No delivery relationship.
- No pricing configuration.
- No service-area polygon.
- No image uploads.
- No public/API endpoints.
- No delete or restore operations.

Related documents:

- `docs/kitchens/map-coordinate-selection.md`
- `docs/decisions/ADR-008-kitchen-lifecycle-and-coordinate-selection.md`
- `docs/requirements/kitchen-requirements.md`
