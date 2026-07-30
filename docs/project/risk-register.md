# Risk Register

This register tracks known project risks. It will be updated as the project
progresses.

| ID | Risk | Impact | Mitigation / Note |
|----|------|--------|-------------------|
| R-01 | Existing `/home/ubuntu/GPS-server` application must not be modified. | High | Strictly out of scope; never inspected, copied, or changed. |
| R-02 | No production web server (Nginx/Apache) exists. | Medium | Dev uses `php artisan serve`; production server deferred to a later packet. |
| R-03 | No domain exists. | Low | IP-based access only for now; domain + TLS later. |
| R-04 | HTTP telemetry is not confidential. | Medium | Accepted prototype limitation; no secrets in telemetry payloads. |
| R-05 | MySQL project database is not yet created. | Medium | SQLite used for now; MySQL provisioning in a later packet. |
| R-06 | Power architecture remains physically unverified. | High | Hardware must be physically verified before energizing or charging. |
| R-07 | SIM800L 2G coverage must be tested with Telkomsel. | Medium | Field test required before relying on GPRS telemetry. |
| R-08 | Public OpenStreetMap tile usage has availability/fair-use limits. | Medium | Configurable tile URL; respect attribution and usage policy. |
| R-09 | One-month retention cleanup is not yet implemented. | Medium | Retention/cleanup job to be added in a later packet. |

## Notes

These risks are informational for the current phase. No mitigation code has been
implemented in this packet.
