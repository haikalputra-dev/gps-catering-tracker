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

## Notes

These risks are informational for the current phase. No mitigation code has been
implemented in this packet.
