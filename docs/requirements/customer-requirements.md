# Customer Requirements

Scope introduced by Task Packet 06.

## Functional requirements

### FR-CUS-01 - Persistent customer records
The system SHALL persist a customer record with the following required
fields: name, phone, address, latitude, longitude, is_active. Notes are
optional.

### FR-CUS-02 - Customer phone format
Customer phone numbers SHALL be trimmed and normalized before storage:
whitespace, hyphens and parentheses SHALL be stripped; at most one
leading `+` SHALL be preserved. The stored value MUST contain 9 to 15
digits. Any alphabetic character in raw input SHALL cause validation to
fail.

### FR-CUS-03 - Phone uniqueness
The normalized phone number SHALL be unique across all customer records,
active or inactive.

### FR-CUS-04 - Coordinate precision
Latitude and longitude SHALL be stored with a precision of seven decimal
places (`decimal(10, 7)`). Latitude MUST be between -90 and 90;
longitude MUST be between -180 and 180.

### FR-CUS-05 - Coordinate authority
The server SHALL validate coordinates on every write. The map picker is
an input aid only.

### FR-CUS-06 - Role-based access
Only users with role `owner` or `staff` SHALL access any customer route.
Couriers SHALL receive HTTP 403. Guests SHALL be redirected to `/login`.
Inactive users SHALL be logged out by the existing `active` middleware.

### FR-CUS-07 - Route surface
The application SHALL expose exactly five customer routes:

- `GET /customers` (customers.index)
- `GET /customers/create` (customers.create)
- `POST /customers` (customers.store)
- `GET /customers/{customer}/edit` (customers.edit)
- `PUT/PATCH /customers/{customer}` (customers.update)

No delete, destroy, or restore route SHALL exist.

### FR-CUS-08 - Active/inactive lifecycle
A customer SHALL be either active or inactive. Owners and staff SHALL be
able to deactivate and reactivate through the same edit form.
Deactivation SHALL NOT alter any other field.

### FR-CUS-09 - Index ordering
The index page SHALL list active customers before inactive ones. Within
a status group, ordering SHALL be by name ascending, then by id
ascending.

### FR-CUS-10 - Pagination
The index page SHALL paginate at 15 records per page.

### FR-CUS-11 - Phone masking in listing
The customer index SHALL mask stored phone numbers so that the middle
portion is hidden while the last four digits remain visible. The edit
form SHALL display the full phone number to authorized operators.

### FR-CUS-12 - Map picker
The create and edit forms SHALL provide a Leaflet-based map to place or
move a single marker. Clicking the map SHALL set or move the marker.
Dragging the marker SHALL update the stored coordinate. The current
coordinate SHALL be displayed to the user.

### FR-CUS-13 - Local Leaflet assets
Leaflet SHALL be bundled through Vite. The application SHALL NOT load
Leaflet, its CSS, or its marker images from a CDN. The already-installed
`leaflet@1.9.4` package SHALL be reused; no re-installation or upgrade
is required.

### FR-CUS-14 - Shared map configuration
The customer map SHALL reuse the existing `config/map.php` values
(default center, zoom levels, tile URL, tile attribution, maximum tile
zoom). No new configuration keys SHALL be introduced for the customer
map.

### FR-CUS-15 - Attribution
The map SHALL display an attribution accurate to the configured tile
provider. Default configuration SHALL attribute OpenStreetMap.

## Non-functional requirements

### NFR-CUS-01 - No delete pathway
No HTTP method SHALL remove a customer record. Data loss is prevented by
the absence of a route, controller action, and UI button.

### NFR-CUS-02 - No customer login or portal
Customers SHALL NOT authenticate. There SHALL be no customer login page,
customer session, or customer self-service API.

### NFR-CUS-03 - No geolocation
The application SHALL NOT call browser geolocation APIs.

### NFR-CUS-04 - No geocoding
The application SHALL NOT call any geocoding or reverse-geocoding
service.

### NFR-CUS-05 - No paid map providers
The application SHALL NOT integrate any paid map provider or provider
requiring an API key. Default configuration SHALL rely on
OpenStreetMap's public tile service.

### NFR-CUS-06 - No public endpoints
Customer endpoints SHALL require authentication, active status, and the
owner or staff role. No public or API endpoint SHALL be exposed for
customers in this packet.

### NFR-CUS-07 - No cross-entity ties in this packet
Customers in this packet SHALL NOT reference any kitchen, delivery,
route, pricing, tracking, device, or SMS entity. Foreign keys SHALL NOT
be defined on the `customers` table.

### NFR-CUS-08 - No real PII in fixtures
Factories, seeders, tests, and documentation SHALL NOT contain real
personal data. Faker-generated values only.

## Out of scope for Packet 06

- Customer login, customer portal, customer API.
- Delivery scheduling and delivery history.
- Customer preferences, order history, invoicing.
- Bulk import or CSV upload of customers.
- SMS or email notifications to customers.
- Data export, reporting, or analytics.
- Customer soft-delete or archival distinct from `is_active`.
- Public API for third-party consumers.

## Traceability

| Requirement | Enforced by                                                                                    |
|-------------|-------------------------------------------------------------------------------------------------|
| FR-CUS-01   | `2026_07_30_060000_create_customers_table.php`, `app/Models/Customer.php`                       |
| FR-CUS-02   | `app/Domain/Customer/CustomerPhone.php`, form requests, `CustomerPhoneTest`, `CustomerValidationTest` |
| FR-CUS-03   | `StoreCustomerRequest`, `UpdateCustomerRequest`, migration unique index, `CustomerValidationTest` |
| FR-CUS-04   | migration, `Customer::$casts`, `CustomerValidationTest`                                         |
| FR-CUS-05   | `StoreCustomerRequest`, `UpdateCustomerRequest`                                                 |
| FR-CUS-06   | `routes/web.php`, `RequireRole` middleware, `CustomerAuthorizationTest`                         |
| FR-CUS-07   | `routes/web.php`, `CustomerRouteTest`                                                           |
| FR-CUS-08   | `CustomerController@update`, `CustomerManagementTest`                                           |
| FR-CUS-09   | `CustomerController@index`, `CustomerManagementTest::test_index_orders_active_before_inactive` |
| FR-CUS-10   | `CustomerController::PER_PAGE`                                                                  |
| FR-CUS-11   | `resources/views/customers/index.blade.php`, `CustomerManagementTest::test_index_masks_phone_in_listing` |
| FR-CUS-12   | `resources/js/customer-map.js`, `resources/views/customers/_form.blade.php`                     |
| FR-CUS-13   | `package.json` (leaflet exact), Vite build output includes marker assets                        |
| FR-CUS-14   | `CustomerController::mapConfig`, `config/map.php` unchanged                                     |
| FR-CUS-15   | `config/map.php`, `resources/js/customer-map.js`                                                |
| NFR-CUS-01  | routes, controller (no destroy), `CustomerManagementTest::test_delete_route_does_not_exist`     |
| NFR-CUS-02  | routes contain no auth endpoints for customers                                                  |
| NFR-CUS-03  | `resources/js/customer-map.js` (no `navigator.geolocation` reference)                           |
| NFR-CUS-04  | no HTTP client to geocoding services in code                                                    |
| NFR-CUS-05  | `config/map.php` defaults, no keyed providers configured                                        |
| NFR-CUS-06  | route group middleware `auth`, `active`, `role:owner,staff`                                     |
| NFR-CUS-07  | code review; no foreign keys defined on `customers`                                             |
| NFR-CUS-08  | `CustomerFactory` uses Faker only; DatabaseSeeder unchanged                                     |
