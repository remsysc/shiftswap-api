# ShiftSwap Sprint Plan

> Sprint 1 derived from `docs/SPEC.md` v1.6, `docs/ShiftSwap-PRD.md` v1.4, and `docs/DATA-MODELS.md` v1.0.
>
> This is a living plan. Update it when the specification changes rather than silently allowing the plan to drift.

## Overview

| Sprint | Goal                                                                   | Requirements                  | Est. size |
| ------ | ---------------------------------------------------------------------- | ----------------------------- | --------- |
| 1      | Foundation + authentication, tenancy, and authorization vertical slice | SETUP, FR-1, FR-2, FR-7–FR-16 | L         |

Sprint 1 assumes a solo developer or small team and has no fixed calendar deadline recorded yet. Estimates are relative, not delivery promises.

## Sprint 1 — Foundation + Auth/Tenancy/Authorization

**Goal:** Establish the backend foundation and deliver a demonstrable secure tenant boundary: a user can register a business, authenticate and receive a Bearer token, retrieve their authenticated identity, log out, and receive correct role and cross-business authorization responses.

**Demoable outcome:** Using the API and feature tests, demonstrate registration creating an owner and business, login issuing a Bearer token, authenticated-user retrieval, protected business access, staff restrictions, owner role management, and logout invalidating the token.

**Requirements covered:** SETUP, FR-1, FR-2, FR-7, FR-8, FR-9, FR-10, FR-11, FR-12, FR-13, FR-14, FR-15, FR-16.

### Dependencies

- No earlier sprint.
- Use the canonical schemas and invariants in `docs/DATA-MODELS.md`.
- Staff invitation creation (`FR-3` and `FR-4`) is intentionally deferred until the membership/auth foundation and queued notification flow are stable.
- Shift, swap, time-off, availability, and staffing workflows are deferred because they depend on the tenant and authorization boundary.

### Tasks

- [x] **SETUP-1** — Confirm the app runtime, database/test configuration, Bearer token authentication setup, API route conventions, and JSON response envelope (S) — supports FR-1 and FR-9.
- [x] **SETUP-2** — Create migrations, models, relationships, factories, and tenant-aware query conventions for `businesses`, `users`, and `business_user` (M) — supports FR-1, FR-7, and FR-13.
- [x] **SETUP-3** — Add API Resources and consistent validation/authentication/authorization error responses; do not return raw Eloquent models (S) — supports the API contract and FR-2, FR-8, and FR-10.
- [x] **SETUP-4** — Establish feature-test helpers for an owner, manager, staff member, and separate business using `RefreshDatabase` (S) — supports FR-7, FR-8, and FR-13–FR-16.
- [x] **FR-1.1** — Implement registration as one transaction that creates the `User`, `Business`, and `business_user` owner record (M) — traces to FR-1.
- [x] **FR-1.2** — Return the registered user through the `{ data: ... }` API Resource envelope with HTTP 201 (S) — traces to FR-1.
- [x] **FR-2.1** — Validate duplicate email registration and return HTTP 422 with `email: ["The email has already been taken."]` (S) — traces to FR-2.
- [x] **FR-7.1** — Resolve the authenticated user’s MVP business context and scope every protected business query by `business_id` (M) — traces to FR-7.
- [x] **FR-8.1** — Add tenant-aware route/resource authorization so a user accessing another business’s resource receives HTTP 403 (M) — traces to FR-8.
- [x] **FR-9.1** — Implement login with valid credentials, Bearer token issuance, and authenticated user response (M) — traces to FR-9.
- [x] **FR-10.1** — Return HTTP 401 with `message: "Invalid credentials."` for invalid login credentials (S) — traces to FR-10.
- [x] **FR-11.1** — Rate-limit failed login attempts to five failures per IP within 60 seconds and return HTTP 429 with `message: "Too many login attempts."` (S) — traces to FR-11.
- [x] **FR-12.1** — Implement logout to invalidate the current Bearer token and return HTTP 204 with no body (S) — traces to FR-12.
- [x] **FR-13.1** — Define and validate the `owner`, `manager`, and `staff` role values per business (S) — traces to FR-13.
- [x] **FR-14.1** — Add policy coverage proving staff cannot perform manager/owner-only actions and receive HTTP 403 (M) — traces to FR-14.
- [x] **FR-15.1** — Implement owner-only business membership role updates accepting only `manager` or `staff` as target roles (M) — traces to FR-15.
- [x] **FR-16.1** — Prevent an owner from changing their own role and return HTTP 422 with `role: ["Cannot change your own role."]` (S) — traces to FR-16.
- [x] **SETUP-5** — Wire the global project validation hook into the implementation workflow and ensure changed tests run before the full application checks (S) — supports all Sprint 1 tasks.

### Definition of Done

- [x] Registration creates exactly one `User`, one `Business`, and one owner `business_user` record in a transaction; the endpoint returns HTTP 201.
- [x] Duplicate email registration returns HTTP 422 with the exact `email` validation message required by FR-2.
- [x] Valid login issues a Bearer token and returns the authenticated user.
- [x] Invalid login returns HTTP 401 with the exact `Invalid credentials.` message.
- [x] The login limiter returns HTTP 429 with `Too many login attempts.` after five failed attempts from one IP within 60 seconds.
- [x] Logout invalidates the active Bearer token and returns HTTP 204 with no response body.
- [x] Protected business data queries are scoped to the authenticated user’s business, and cross-business access returns HTTP 403.
- [x] The system enforces exactly the `owner`, `manager`, and `staff` role values used by the specification.
- [x] Staff receives HTTP 403 for manager/owner-only actions.
- [x] Owner role updates accept only `manager` or `staff`, and an owner changing their own role returns HTTP 422 with the exact required validation message.
- [x] Feature tests cover the complete request-to-response paths and use `RefreshDatabase`; authorization tests cover a separate business.
- [x] The project validation hook passes the isolated/full tests and application checks after Sprint 1 changes.

### Validation commands

```bash
./scripts/validate-feature.sh
composer ci:check
```

The global Kiro validator should be active through the `global-project-validation` agent. The project-local validator remains available at `scripts/validate-feature.sh`.

## Sprint 2 Plan — Invitations, Locations, and Shift Management

### Goal
Implement staff invitations with single-use expiring signed tokens, business location management, the shift management core slice with assignment history, and centralized overlap conflict detection.

### Tasks

- [x] **SETUP-6** — Create migrations, models, factories, and relationships for `invitations` and `locations` (S) — supports FR-3–FR-6, and FR-16A.
- [x] **FR-3.1** — Implement staff invitation dispatch via `POST /api/businesses/{business}/staff` generating a hashed 48-hour token and returning HTTP 202 (M) — traces to FR-3 and FR-4.
- [x] **FR-4.1** — Validate that invitations to an email already associated with any business return HTTP 422 with `email: ["This user already belongs to a business."]` (S) — traces to FR-4.
- [x] **FR-5.1** — Implement `POST /api/invitations/{token}/accept` to register an invited user, link them to the business as `staff`, and consume the token (M) — traces to FR-5.
- [x] **FR-6.1** — Validate that expired (past 48h) or already consumed invitation tokens return HTTP 422 with `token: ["Invalid or expired invitation."]` (S) — traces to FR-6.
- [x] **FR-16A.1** — Implement `LocationPolicy` and CRUD endpoints for `/api/businesses/{business}/locations` restricting modifications to owners/managers while allowing staff to view (M) — traces to FR-16A.
- [x] **SETUP-7** — Create migrations, models, factories, and relationships for `shifts` and `shift_staff` supporting assignment status history and soft deletion (M) — supports FR-17–FR-23.
- [x] **FR-17.1** — Implement shift creation via `POST /api/businesses/{business}/shifts` requiring `start_at` and `end_at` with optional `location_id` and `role_label` (M) — traces to FR-17.
- [x] **FR-18.1** — Validate that creating or updating a shift with `end_at <= start_at` returns HTTP 422 with `end_at: ["End time must be after start time."]` (S) — traces to FR-18.
- [x] **FR-19.1** — Build `ShiftConflictService::hasShiftOverlap` implementing the overlap rule `new_start < existing_end AND new_end > existing_start` where boundary equality is allowed (M) — traces to FR-19 and FR-21.
- [x] **FR-20.1** — Implement staff assignment via `POST /api/businesses/{business}/shifts/{shift}/staff` checking for shift overlap and returning HTTP 422 on conflict (M) — traces to FR-20.
- [x] **FR-22.1** — Implement shift deletion with soft deletion and cascade cancellation of associated open swaps (S) — traces to FR-22.
- [x] **FR-23.1** — Implement shift listing via `GET /api/businesses/{business}/shifts` with required `start_date` and `end_date` query filters and eager-loaded staff (M) — traces to FR-23.

### Definition of Done

- [x] Staff invitation generates a hashed single-use token expiring in 48 hours; resending replaces the previous pending token.
- [x] Inviting a user already belonging to any business returns HTTP 422.
- [x] Accepting an invitation creates the user, links them as `staff`, consumes the token, and returns HTTP 201 with Bearer token.
- [x] Expired or invalid invitation tokens return HTTP 422 with `token: ["Invalid or expired invitation."]`.
- [x] Locations support CRUD operations scoped to the business; managers/owners can manage, staff can only view.
- [x] Shifts require `start_at` and `end_at` in UTC; `end_at <= start_at` returns HTTP 422 with the exact required message.
- [x] Shift assignments enforce the overlap rule; boundary equality (`end_at == existing_start_at`) is permitted.
- [x] Shift deletion soft-deletes the shift record.
- [x] Shift queries require date range filters and eager-load assigned staff.
- [x] All features are covered by Pest tests using `RefreshDatabase`; static analysis and Pint checks pass.

## Sprint 3 Plan — Shift Swaps, Time-Off, Notifications, Dashboards, and Ownership Transfer

### Goal
Deliver the complete core marketplace functionality: shift swap workflow with single-requester lock and manager approval/rejection, time-off requests with conflict detection, asynchronous queued notifications and in-app feeds, role-specific aggregated dashboards, and business ownership transfer.

### Tasks

#### Slice 3A: Shift Swap Marketplace (FR-24–FR-33)
- [x] **FR-24.1** — Implement shift swap offer dispatch via `POST /api/businesses/{business}/shift-swaps` creating `shift_swaps` record with `status = offered`, `offered_by = auth user` (M) — traces to FR-24.
- [x] **FR-25.1** — Enforce that only users actively assigned to the shift can offer it for swap; otherwise return HTTP 403 (S) — traces to FR-25.
- [x] **FR-26.1** — Validate that shifts already in the past (`start_at < now()`) cannot be offered for swap, returning HTTP 422 with `shift_id: ["Cannot offer a past shift for swap."]` (S) — traces to FR-26.
- [x] **FR-27.1** — Implement swap request via `POST /api/businesses/{business}/shift-swaps/{swap}/request` transitioning offer to `pending_approval` and setting `requested_by = auth user` (M) — traces to FR-27.
- [x] **FR-28.1** — Enforce that requesting staff members with conflicting shifts or approved time-off receive HTTP 422 with `user_id: ["You have a conflicting shift or approved time-off."]` (S) — traces to FR-28.
- [x] **FR-29.1** — Enforce single-requester lock: non-`offered` swaps reject requests with HTTP 422 `status: ["This shift is no longer available."]` (S) — traces to FR-29, FR-29A.
- [x] **FR-30.1** — Implement manager approval via `POST /api/businesses/{business}/shift-swaps/{swap}/approve` updating assignment history (`offered_by` -> `removed`, `requested_by` -> `assigned`), setting `status = approved`, and dispatching notifications (M) — traces to FR-30.
- [x] **FR-31.1** — Implement manager rejection via `POST /api/businesses/{business}/shift-swaps/{swap}/reject` reverting status to `offered`, clearing `requested_by` and `manager_notes`, and dispatching rejection notification (M) — traces to FR-31.
- [x] **FR-32.1** — Implement swap offer cancellation via `POST /api/businesses/{business}/shift-swaps/{swap}/cancel` by `offered_by` while `status = offered` (S) — traces to FR-32.
- [x] **FR-33.1** — Validate that cancelling a swap in non-`offered` status returns HTTP 422 with `status: ["Cannot cancel a swap that is already being processed."]` (S) — traces to FR-33.
- [x] **FR-33A.1** — Implement shift swap listing via `GET /api/businesses/{business}/shift-swaps` with optional `status` filter (S).

#### Slice 3B: Time-Off Requests (FR-34–FR-39)
- [x] **SETUP-8** — Create migration, model, factory, and relationships for `time_off_requests` (S) — supports FR-34–FR-39.
- [x] **FR-34.1** — Implement time-off request creation via `POST /api/businesses/{business}/time-off-requests` requiring `start_date` and `end_date` with initial `status = pending` (M) — traces to FR-34.
- [x] **FR-35.1** — Validate that `end_date < start_date` returns HTTP 422 with `end_date: ["End date must be on or after start date."]` (S) — traces to FR-35.
- [x] **FR-36.1** — Implement time-off request retrieval with `conflicts` array listing any assigned shifts overlapping the requested dates (M) — traces to FR-36.
- [x] **FR-37.1** — Implement manager approval via `POST /api/businesses/{business}/time-off-requests/{request}/approve` setting `status = approved` and dispatching notification (M) — traces to FR-37.
- [x] **FR-38.1** — Implement manager rejection via `POST /api/businesses/{business}/time-off-requests/{request}/reject` setting `status = rejected`, optionally storing `manager_notes`, and dispatching notification (M) — traces to FR-38.
- [x] **FR-39.1** — Validate that overlapping pending or approved time-off requests return HTTP 422 with `start_date: ["You already have a time-off request for this period."]` (S) — traces to FR-39.
- [x] **FR-39A.1** — Connect `time_off_requests` with `ShiftConflictService::hasTimeOffConflict` to ensure approved time-offs block shift assignments and swap requests (M).

#### Slice 3C: Queued Notifications & In-App Feed (FR-40–FR-48)
- [x] **SETUP-9** — Create Laravel standard notifications table migration (`notifications`) (S) — supports FR-48.
- [x] **FR-40.1** — Implement queued notification jobs (`ShouldQueue`) for all domain events without inline mail dispatch (M) — traces to FR-40.
- [x] **FR-41.1**–**FR-47.1** — Implement notifications for shift assignment, swap offers (eligible staff only per FR-42), swap requested, swap approved, swap rejected, time-off submitted, and time-off status (M).
- [x] **FR-48.1** — Implement in-app notification endpoints: `GET /api/businesses/{business}/notifications`, `POST .../read`, and `POST .../read-all` (M) — traces to FR-48.

#### Slice 3D: Dashboards & Ownership Transfer (FR-49–FR-50, SPEC 5.2)
- [x] **FR-49.1** — Implement manager/owner dashboard aggregation via DB-level counts (upcoming 7-day shifts, pending swaps, pending time-off) (M) — traces to FR-49, NFR-3.
- [x] **FR-50.1** — Implement staff dashboard (assigned 14-day shifts, pending time-off, active swap participation) (M) — traces to FR-50.
- [x] **SETUP-10** — Create migration and model for `ownership_transfers` (S).
- [x] **FR-OT.1** — Implement ownership transfer initiation (`POST /api/businesses/{business}/ownership-transfer`) and atomic acceptance (`POST /api/ownership-transfers/{token}/accept`) ensuring zero window with 0 owners (M).

### Definition of Done
- [x] Staff can offer assigned future shifts for swap; past shifts return 422; non-owners return 403.
- [x] First valid taker locks offer into `pending_approval`; second taker receives 422; conflicting taker receives 422.
- [x] Manager approval atomically reassigns shift, updates history, and notifies both staff.
- [x] Manager rejection reverts offer to `offered` and notifies requester.
- [x] Staff can cancel own offer while `offered`; cancelling `pending_approval` returns 422.
- [x] Time-off requests enforce date order, check overlapping shifts, and block conflicting requests.
- [x] Notifications run via queued jobs and populate database in-app notifications.
- [x] Dashboard aggregates data via SQL without PHP-level loops.
- [x] Ownership transfer operates transactionally and changes owner/manager roles atomically.
- [x] All features are covered by Pest tests using `RefreshDatabase`; static analysis and Pint checks pass.
