# ADR-003 — Free Mapping Stack

## Status

Accepted

## Context

The system will later display maps for staff endpoint selection and customer
tracking. Map provider choice affects cost, licensing, and attribution
requirements.

## Decision

- Leaflet will be used as the map interface library.
- OpenStreetMap-compatible tiles will be used.
- Visible attribution is required wherever tiles are displayed.
- The tile URL must later be configurable (not hard-coded).
- No paid map API is approved.

## Consequences

- No map package is installed in this packet; Leaflet is deferred to a later
  packet.
- Attribution and configurable tile URLs must be honored when the map is
  implemented.
- Public OpenStreetMap tile usage carries availability and fair-use limitations
  (see the risk register).
