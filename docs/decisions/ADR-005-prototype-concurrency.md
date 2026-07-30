# ADR-005 — Prototype Concurrency

## Status

Accepted

## Context

The prototype hardware and operational model support only a single delivery
being actively tracked at once. Device assignment must remain unambiguous.

## Decision

- The prototype supports a maximum of one active delivery at a time.
- Devices can be administratively reassigned only when not in an active tracking
  session.
- Enforcement of these constraints will be implemented later.

## Consequences

- No concurrency enforcement exists yet; this ADR records the agreed constraint.
- Future delivery and device-assignment logic must enforce the single-active
  delivery rule and block reassignment during active sessions.
