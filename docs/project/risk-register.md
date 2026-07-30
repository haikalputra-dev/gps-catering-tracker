# Risk Register

This register tracks known project risks. It will be updated as the project
progresses.

| ID | Risk | Impact | Mitigation / Note |
|----|------|--------|-------------------|
| R-01 | Existing `/home/ubuntu/GPS-server` application must not be modified. | High | Strictly out of scope; never inspected, copied, or changed. |
| R-02 | No production web server (Nginx/Apache) exists. | Medium | Dev uses `php artisan serve`; production server deferred to a later packet. |
| R-03 | No domain exists. | Low | IP-based access only for now; domain + TLS later. |
| R-04 | HTTP telemetry is not confidential. | Medium | Accepted prototype limitation; no secrets in telemetry payloads. |
| R-05 | MySQL project database is not yet created. | Medium | Resolved in Packet 03: `gps_catering_tracker` provisioned with a least-privilege user; runtime now uses MySQL. |
| R-10 | Test suite runs on SQLite but runtime is MySQL, so DB-specific behaviour may diverge. | Medium | Tracked in ADR-006; revisit test strategy before any migration that uses MySQL-specific features. |
| R-11 | No backup/rotation automation for the MySQL runtime database. | Low | Manual `mysqldump` procedure documented in `docs/database/mysql-integration.md`; automation deferred until real data exists. |
| R-06 | Power architecture remains physically unverified. | High | Hardware must be physically verified before energizing or charging. |
| R-07 | SIM800L 2G coverage must be tested with Telkomsel. | Medium | Field test required before relying on GPRS telemetry. |
| R-08 | Public OpenStreetMap tile usage has availability/fair-use limits. | Medium | Configurable tile URL; respect attribution and usage policy. |
| R-09 | One-month retention cleanup is not yet implemented. | Medium | Retention/cleanup job to be added in a later packet. |
| R-12 | Initial owner account exists only after `app:create-owner` is run; skipping it leaves the site un-loginable. | Medium | Documented in `docs/authentication/initial-owner-command.md`; add to deployment checklist. Command creates an active owner in one step. |
| R-13 | No self-service password reset. An owner who forgets their password cannot recover it through the UI. | Medium | Mitigated by running `app:create-owner` again on the host to create/replace credentials; enterprise reset flow deferred to a later packet. |
| R-14 | Session fixation and CSRF hardening rely on framework defaults plus explicit regeneration on login/logout. | Medium | Verified by `LoginTest::test_successful_login_regenerates_session_id` and logout tests; revisit if `SESSION_*` settings change. |
| R-15 | Rate-limit key is `email + IP`; a shared NAT can spread attempts across users and slightly weaken throttling. | Low | Accepted for LAN deployment; move to `email` or `email + user-agent` key if abuse is observed. |
| R-16 | Owner role is only prevented from being set through code paths (enum, form request, controller guard). A future contributor could bypass by adding a raw update. | Medium | Enforced in three layers; `UserManagementUpdateTest::test_owner_cannot_be_edited_through_crafted_request` guards against regression. |
| R-17 | Kitchens cannot be deleted through HTTP. Cleaning up test/demo records requires a manual database operation. | Low | Deliberate design (ADR-008, AR-21). Deactivation (`is_active = false`) preserves the record and is reversible. |
| R-18 | Default map tile provider is the public OpenStreetMap tile service, which has no SLA and community fair-use limits. | Medium | Tile URL and attribution are configurable via `MAP_TILE_URL` / `MAP_TILE_ATTRIBUTION`; switch to a hosted provider before production traffic. See `docs/kitchens/map-coordinate-selection.md`. |
| R-19 | Coordinate authority sits entirely on the server; a browser without JS still sees the raw numeric fields but cannot use the map picker. | Low | Latitude/longitude inputs are validated server-side. Progressive enhancement is acceptable for the current internal user base. |
| R-20 | Customers cannot be deleted through HTTP. Cleaning up test/demo records requires a manual database operation. | Low | Deliberate design (ADR-009, AR-22). Deactivation (`is_active = false`) preserves the record and is reversible. |
| R-21 | Customer phone numbers are stored in plain text; operator screens display them (masked on list, full on edit). | Medium | Access is limited to owner and staff by role middleware. Phone is masked in the index list. Encryption at rest is out of scope for Packet 06; revisit if the deployment threat model changes. See `docs/customers/customer-phone-and-privacy.md`. |
| R-22 | Delivery concurrency cap enforcement relies on row-level locks on the target delivery but does not fully serialise the count query. Two concurrent schedulers could theoretically both pass the cap check. | Low | Row lock on the target delivery plus single-operator workflow makes this a theoretical concern only. ADR-012 flags an advisory-lock tightening path if a real race is observed. |
| R-23 | Receipt-number generation depends on a bounded retry loop; sustained collisions could throw `RuntimeException`. | Low | 4-char suffix over a 30-char alphabet is 810,000 daily values; the 10-attempt loop covers realistic operational volumes. Unit tests cover the retry and collision paths. |
| R-24 | Deliveries cannot be deleted through HTTP; cancelled and delivered rows accumulate. | Low | Deliberate design (ADR-010). Cancellation is the terminal disposal path. Retention policy is deferred to a later packet. |
| R-25 | Snapshot columns duplicate kitchen and customer data at scheduling time; storage grows with delivery volume. | Low | Ten columns per delivery is manageable at expected volume. Alternative history-table designs were considered and rejected in ADR-011. |
| R-26 | Application timezone is Asia/Jakarta while storage is UTC; validation rules like `after:now` compare in Jakarta, requiring test authors to construct future timestamps in the app timezone. | Low | Documented in `docs/deliveries/*` and task-packet-07-report.md; delivery tests use `now()` rather than `Carbon::now('UTC')` for future timestamps. |
| R-27 | Geodesic Haversine distance underestimates real driving distance, especially in urban Jakarta where straight lines ignore rivers and one-way streets. | Medium | Accepted for prototype (ADR-013). Operators can raise `PRICING_RATE_PER_KM_RUPIAH` or `PRICING_MINIMUM_FEE_RUPIAH` without a code change. Routing-service integration is a future packet. |
| R-28 | Delivery fee is frozen at scheduling; if the price sheet changes after a delivery is scheduled but before it is delivered, the receipted amount uses the old rate. | Low | Deliberate design (ADR-013, DEL-FR-034). Cancel and re-create is the recalculation path. |

## Notes

These risks are informational for the current phase. No mitigation code has been
implemented in this packet.
