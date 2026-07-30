# Kitchen Requirements

Scope introduced by Task Packet 05.

## Functional requirements

### FR-KIT-01 - Persistent kitchen records
The system SHALL persist a kitchen record with the following required
fields: code, name, address, latitude, longitude, is_active. Phone is
optional.

### FR-KIT-02 - Kitchen code format
Kitchen codes SHALL be unique, uppercase, and match the pattern
`[A-Z0-9-]+` with a maximum length of 30 characters. Input SHALL be
trimmed and uppercased before validation.

### FR-KIT-03 - Coordinate precision
Latitude and longitude SHALL be stored with a precision of seven
decimal places (`decimal(10, 7)`). Latitude MUST be between -90 and 90,
longitude MUST be between -180 and 180.

### FR-KIT-04 - Coordinate authority
The server SHALL validate coordinates on every write. The map picker is
an input aid only.

### FR-KIT-05 - Role-based access
Only users with role `owner` or `staff` SHALL access any kitchen route.
Couriers SHALL receive HTTP 403. Guests SHALL be redirected to `/login`.
Inactive users SHALL be logged out by the existing `active` middleware.

### FR-KIT-06 - Route surface
The application SHALL expose exactly five kitchen routes:

- `GET /kitchens` (kitchens.index)
- `GET /kitchens/create` (kitchens.create)
- `POST /kitchens` (kitchens.store)
- `GET /kitchens/{kitchen}/edit` (kitchens.edit)
- `PUT/PATCH /kitchens/{kitchen}` (kitchens.update)

No delete, destroy, or restore route SHALL exist.

### FR-KIT-07 - Active/inactive lifecycle
A kitchen SHALL be either active or inactive. Owners and staff SHALL be
able to deactivate and reactivate through the same edit form.
Deactivation SHALL NOT alter any other field.

### FR-KIT-08 - Index ordering
The index page SHALL list active kitchens before inactive ones. Within a
status group, ordering SHALL be by name ascending, then by id ascending.

### FR-KIT-09 - Pagination
The index page SHALL paginate at 15 records per page.

### FR-KIT-10 - Map picker
The create and edit forms SHALL provide a Leaflet-based map to place or
move a single marker. Clicking the map SHALL set or move the marker.
Dragging the marker SHALL update the stored coordinate. The current
coordinate SHALL be displayed to the user.

### FR-KIT-11 - Local Leaflet assets
Leaflet SHALL be bundled through Vite. The application SHALL NOT load
Leaflet, its CSS, or its marker images from a CDN.

### FR-KIT-12 - Configurable tile provider
The tile URL, attribution, default center, default zoom, selection zoom,
and maximum tile zoom SHALL be configurable via `config/map.php`, backed
by `MAP_*` environment variables.

### FR-KIT-13 - Attribution
The map SHALL display an attribution accurate to the configured tile
provider. Default configuration SHALL attribute OpenStreetMap.

## Non-functional requirements

### NFR-KIT-01 - No delete pathway
No HTTP method SHALL remove a kitchen record. Data loss is prevented by
the absence of a route, controller action, and UI button.

### NFR-KIT-02 - No geolocation
The application SHALL NOT call browser geolocation APIs.

### NFR-KIT-03 - No geocoding
The application SHALL NOT call any geocoding or reverse-geocoding
service.

### NFR-KIT-04 - No paid map providers
The application SHALL NOT integrate any paid map provider or provider
requiring an API key. Default configuration SHALL rely on
OpenStreetMap's public tile service.

### NFR-KIT-05 - No public endpoints
Kitchen endpoints SHALL require authentication, active status, and the
owner or staff role. No public or API endpoint SHALL be exposed for
kitchens in this packet.

### NFR-KIT-06 - No customer, delivery, or pricing tie-in
Kitchens in this packet SHALL NOT reference any customer, delivery,
route, pricing, tracking, device, or SMS entity.

## Out of scope for Packet 05

- Delivery scheduling.
- Kitchen imagery.
- Kitchen contact list beyond a single phone number.
- Kitchen service radius or delivery polygon.
- Multi-tenant isolation.
- Data export or reporting.
- Kitchen soft-delete or archival distinct from `is_active`.
- Bulk import.
- Public API for third-party consumers.

## Traceability

| Requirement | Enforced by                                                                                                             |
|-------------|--------------------------------------------------------------------------------------------------------------------------|
| FR-KIT-01   | `2026_07_30_042022_create_kitchens_table.php`, `app/Models/Kitchen.php`                                                  |
| FR-KIT-02   | `app/Domain/Kitchen/KitchenCode.php`, `app/Http/Requests/Kitchen/*`, unit + validation tests                             |
| FR-KIT-03   | migration, `Kitchen::$casts`, validation tests                                                                           |
| FR-KIT-04   | `StoreKitchenRequest`, `UpdateKitchenRequest`                                                                            |
| FR-KIT-05   | `routes/web.php`, `RequireRole` middleware, `KitchenAuthorizationTest`                                                   |
| FR-KIT-06   | `routes/web.php`, `KitchenRouteTest`, `KitchenLifecycleTest::test_no_delete_route`                                       |
| FR-KIT-07   | `KitchenController@update`, `KitchenManagementTest`, `KitchenLifecycleTest`                                              |
| FR-KIT-08   | `KitchenController@index`, `KitchenLifecycleTest`                                                                        |
| FR-KIT-09   | `KitchenController@index`, `KitchenLifecycleTest`                                                                        |
| FR-KIT-10   | `resources/js/kitchen-map.js`, `resources/views/kitchens/_form.blade.php`                                                |
| FR-KIT-11   | `package.json` (leaflet exact), Vite build output includes marker assets                                                  |
| FR-KIT-12   | `config/map.php`, `.env.example`                                                                                         |
| FR-KIT-13   | `config/map.php`, `resources/js/kitchen-map.js`                                                                          |
| NFR-KIT-01  | routes, controller (no destroy), `KitchenLifecycleTest`                                                                  |
| NFR-KIT-02  | `resources/js/kitchen-map.js` (no `navigator.geolocation` reference)                                                     |
| NFR-KIT-03  | no HTTP client to geocoding services in code                                                                             |
| NFR-KIT-04  | `config/map.php` defaults, no keyed providers configured                                                                 |
| NFR-KIT-05  | route group middleware `auth`, `active`, `role:owner,staff`                                                              |
| NFR-KIT-06  | code review; no foreign keys defined on `kitchens`                                                                       |
