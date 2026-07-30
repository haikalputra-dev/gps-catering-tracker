# ADR-004 — Customer Tracking Authentication

## Status

Accepted

## Context

Customers must be able to track a delivery without a full user account, while
preventing enumeration and protecting personal data.

## Decision

- Customers authenticate tracking access using a receipt number plus the last
  four digits of the assigned customer phone number.
- The full phone number must never be displayed publicly.
- A generic error response must be used for failed attempts (no distinction
  between "unknown receipt" and "wrong digits").
- Rate limiting will be implemented later to reduce brute-force risk.

## Consequences

- No authentication feature exists yet; this ADR records the agreed approach.
- Implementation must avoid leaking whether a receipt exists.
- Rate limiting is a required follow-up before public exposure.
