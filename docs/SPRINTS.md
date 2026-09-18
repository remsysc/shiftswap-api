# ShiftSwap Sprint Plan

> Sprint 1 derived from `docs/SPEC.md` v1.5, `docs/ShiftSwap-PRD.md` v1.3, and `docs/DATA-MODELS.md` v1.0.
>
> This is a living plan. Update it when the specification changes rather than silently allowing the plan to drift.

## Overview

| Sprint | Goal                                                                   | Requirements                  | Est. size |
| ------ | ---------------------------------------------------------------------- | ----------------------------- | --------- |
| 1      | Foundation + authentication, tenancy, and authorization vertical slice | SETUP, FR-1, FR-2, FR-7–FR-16 | L         |

Sprint 1 assumes a solo developer or small team and has no fixed calendar deadline recorded yet. Estimates are relative, not delivery promises.

## Sprint 1 — Foundation + Auth/Tenancy/Authorization

**Goal:** Establish the backend foundation and deliver a demonstrable secure tenant boundary: a user can register a business, authenticate with Sanctum, retrieve their authenticated identity, log out, and receive correct role and cross-business authorization responses.

**Demoable outcome:** Using the API and feature tests, demonstrate registration creating an owner and business, login issuing the SPA session, authenticated-user retrieval, protected business access, staff restrictions, owner role management, and logout invalidating the session.

**Requirements covered:** SETUP, FR-1, FR-2, FR-7, FR-8, FR-9, FR-10, FR-11, FR-12, FR-13, FR-14, FR-15, FR-16.

### Dependencies

- No earlier sprint.
- Use the canonical schemas and invariants in `docs/DATA-MODELS.md`.
- Staff invitation creation (`FR-3` and `FR-4`) is intentionally deferred until the membership/auth foundation and queued notification flow are stable.
- Shift, swap, time-off, availability, and staffing workflows are deferred because they depend on the tenant and authorization boundary.

### Tasks

- [ ] **SETUP-1** — Confirm the app runtime, database/test configuration, Sanctum SPA cookie configuration, API route conventions, and JSON response envelope (S) — supports FR-1 and FR-9.
- [ ] **SETUP-2** — Create migrations, models, relationships, factories, and tenant-aware query conventions for `businesses`, `users`, and `business_user` (M) — supports FR-1, FR-7, and FR-13.
- [ ] **SETUP-3** — Add API Resources and consistent validation/authentication/authorization error responses; do not return raw Eloquent models (S) — supports the API contract and FR-2, FR-8, and FR-10.
- [ ] **SETUP-4** — Establish feature-test helpers for an owner, manager, staff member, and separate business using `RefreshDatabase` (S) — supports FR-7, FR-8, and FR-13–FR-16.
- [ ] **FR-1.1** — Implement registration as one transaction that creates the `User`, `Business`, and `business_user` owner record (M) — traces to FR-1.
- [ ] **FR-1.2** — Return the registered user through the `{ data: ... }` API Resource envelope with HTTP 201 (S) — traces to FR-1.
- [ ] **FR-2.1** — Validate duplicate email registration and return HTTP 422 with `email: ["The email has already been taken."]` (S) — traces to FR-2.
- [ ] **FR-7.1** — Resolve the authenticated user’s MVP business context and scope every protected business query by `business_id` (M) — traces to FR-7.
- [ ] **FR-8.1** — Add tenant-aware route/resource authorization so a user accessing another business’s resource receives HTTP 403 (M) — traces to FR-8.
- [ ] **FR-9.1** — Implement login with valid credentials, Sanctum session cookie issuance, and authenticated user response (M) — traces to FR-9.
- [ ] **FR-10.1** — Return HTTP 401 with `message: "Invalid credentials."` for invalid login credentials (S) — traces to FR-10.
- [ ] **FR-11.1** — Rate-limit failed login attempts to five failures per IP within 60 seconds and return HTTP 429 with `message: "Too many login attempts."` (S) — traces to FR-11.
- [ ] **FR-12.1** — Implement logout to invalidate the current Sanctum session token and return HTTP 204 with no body (S) — traces to FR-12.
- [ ] **FR-13.1** — Define and validate the `owner`, `manager`, and `staff` role values per business (S) — traces to FR-13.
- [ ] **FR-14.1** — Add policy coverage proving staff cannot perform manager/owner-only actions and receive HTTP 403 (M) — traces to FR-14.
- [ ] **FR-15.1** — Implement owner-only business membership role updates accepting only `manager` or `staff` as target roles (M) — traces to FR-15.
- [ ] **FR-16.1** — Prevent an owner from changing their own role and return HTTP 422 with `role: ["Cannot change your own role."]` (S) — traces to FR-16.
- [ ] **SETUP-5** — Wire the global project validation hook into the implementation workflow and ensure changed tests run before the full application checks (S) — supports all Sprint 1 tasks.

### Definition of Done

- [ ] Registration creates exactly one `User`, one `Business`, and one owner `business_user` record in a transaction; the endpoint returns HTTP 201.
- [ ] Duplicate email registration returns HTTP 422 with the exact `email` validation message required by FR-2.
- [ ] Valid login issues a Sanctum session cookie and returns the authenticated user.
- [ ] Invalid login returns HTTP 401 with the exact `Invalid credentials.` message.
- [ ] The login limiter returns HTTP 429 with `Too many login attempts.` after five failed attempts from one IP within 60 seconds.
- [ ] Logout invalidates the active Sanctum session and returns HTTP 204 with no response body.
- [ ] Protected business data queries are scoped to the authenticated user’s business, and cross-business access returns HTTP 403.
- [ ] The system enforces exactly the `owner`, `manager`, and `staff` role values used by the specification.
- [ ] Staff receives HTTP 403 for manager/owner-only actions.
- [ ] Owner role updates accept only `manager` or `staff`, and an owner changing their own role returns HTTP 422 with the exact required validation message.
- [ ] Feature tests cover the complete request-to-response paths and use `RefreshDatabase`; authorization tests cover a separate business.
- [ ] The project validation hook passes the isolated/full tests and application checks after Sprint 1 changes.

### Validation commands

```bash
./scripts/validate-feature.sh
composer ci:check
```

The global Kiro validator should be active through the `global-project-validation` agent. The project-local validator remains available at `scripts/validate-feature.sh`.

### Explicitly deferred

- `FR-3` and `FR-4`: staff invitations and queued invitation notifications.
- `FR-5` and `FR-6`: invitation acceptance and invalid/expired token behavior.
- `FR-17+`: locations, shifts, assignments, swaps, time-off, notifications, dashboards, availability, hours targets, and staffing warnings.

## Next sequencing checkpoint

Before Sprint 2 begins, confirm that the authentication and tenant boundary are stable. Sprint 2 can then add staff invitations (`FR-3`–`FR-6`) and the first location/shift-management vertical slice without duplicating authentication infrastructure.
