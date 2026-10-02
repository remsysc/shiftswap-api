# SPEC — ShiftSwap

> Status: Draft | Version: 1.6 | Date: September 22, 2026
> Source of truth for implementation. Any behavior not covered here is an open
> question, not a green light to assume. When code and spec disagree, that is a
> bug — determine which side is wrong and fix it, then update this file.

---

## 1. Scope

This spec covers the full MVP implementation of ShiftSwap: a multi-tenant SaaS
for shift scheduling and swap management. It is derived from `ShiftSwap-PRD.md`
v1.2 and defines the exact technical contract for the Laravel API backend and
Vue 3 SPA frontend.

Stack:

- **Backend:** PHP 8.3 + Laravel 13, MySQL 8, stateless Bearer token auth
- **Frontend:** Vue 3 + Vite + Pinia + Tailwind CSS (separate repository)
- **Queue:** Laravel Jobs (SQS in production, database driver locally)
- **Testing:** Pest (feature + unit)
- **Deployment:** AWS EC2/ECS + RDS + S3 + SQS

---

## 2. Non-Goals

The following are explicitly out of scope for this spec and must not be
implemented or assumed:

- Payroll, POS, or HR integrations
- GPS or biometric time clock
- DOLE compliance rules (OT, rest day enforcement)
- Billing or subscription management
- Mobile native apps
- Auto-scaling (must be explainable, not implemented)
- Real-time push (WebSockets / Pusher) — polling or page reload is acceptable

---

## 3. Requirements (EARS)

### 3.1 Authentication & Tenancy

| ID    | Requirement                                                                                                                                                                    |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| FR-1  | WHEN a user submits a valid registration form THE SYSTEM SHALL create a `User` record, a `Business` record, and a `business_user` pivot record with `role = owner`.            |
| FR-2  | WHEN a user submits a registration form with an email that already exists THE SYSTEM SHALL return 422 with error `email: ["The email has already been taken."]`.               |
| FR-3  | WHEN an Owner/Manager submits a staff invite with a registered email THE SYSTEM SHALL create a `business_user` record with `role = staff` and send an invitation notification. |
| FR-4  | WHEN an Owner/Manager submits a staff invite with an unregistered email THE SYSTEM SHALL create an `invitations` record with a signed token and send an invite email.          |
| FR-5  | WHEN an invited user registers using the invite token THE SYSTEM SHALL link their new `User` to the business and mark the invitation as `accepted`.                            |
| FR-6  | WHEN an invite token is expired or invalid THE SYSTEM SHALL return 422 with `token: ["Invalid or expired invitation."]`.                                                       |
| FR-7  | THE SYSTEM SHALL scope all business data queries to `business_id` matching the authenticated user's active business context.                                                   |
| FR-8  | IF a user attempts to access a resource belonging to a different business THEN THE SYSTEM SHALL return 403.                                                                    |
| FR-9  | WHEN a user logs in with valid credentials THE SYSTEM SHALL issue a Bearer token and return the authenticated user object.                                                     |
| FR-10 | WHEN a user logs in with invalid credentials THE SYSTEM SHALL return 401 with `message: "Invalid credentials."`.                                                               |
| FR-11 | WHILE a login IP has exceeded 5 failed attempts in 60 seconds THE SYSTEM SHALL return 429 with `message: "Too many login attempts."`.                                          |
| FR-12 | WHEN a user logs out THE SYSTEM SHALL invalidate the current Bearer token and return 204.                                                                                      |

### 3.2 Roles & Permissions

| ID    | Requirement                                                                                                                                                                                                                        |
| ----- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-13 | THE SYSTEM SHALL enforce three roles per business: `owner`, `manager`, `staff`.                                                                                                                                                    |
| FR-14 | WHEN a `staff` user attempts an action restricted to `manager` or `owner` THE SYSTEM SHALL return 403.                                                                                                                             |
| FR-15 | WHEN an `owner` updates a `business_user` role THE SYSTEM SHALL accept `manager` or `staff` as the target role; ownership transfer is handled separately and owners cannot demote themselves without first transferring ownership. |
| FR-16 | IF an `owner` attempts to change their own role THEN THE SYSTEM SHALL return 422 with `role: ["Cannot change your own role."]`.                                                                                                    |

### 3.3 Shift Management

| ID    | Requirement                                                                                                                                                                                      |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| FR-17 | WHEN a manager creates a shift THE SYSTEM SHALL require: `start_at` (datetime), `end_at` (datetime). Optional: `location_id`, `role_label`.                                                      |
| FR-18 | IF `end_at` is not after `start_at` THEN THE SYSTEM SHALL return 422 with `end_at: ["End time must be after start time."]`.                                                                      |
| FR-19 | WHEN a manager assigns a staff member to a shift THE SYSTEM SHALL check for overlapping shifts for that user.                                                                                    |
| FR-20 | IF the assigned staff member has an overlapping shift or approved time-off THEN THE SYSTEM SHALL return 422 with `user_id: ["This staff member has a conflicting shift or approved time-off."]`. |
| FR-21 | **Overlap rule:** two shifts overlap when `new_start < existing_end AND new_end > existing_start` for the same `user_id`. Boundary equality (end == start) is NOT an overlap.                    |
| FR-22 | WHEN a manager deletes a shift THE SYSTEM SHALL soft-delete it and cancel any `offered` or `pending_approval` swap records associated with it.                                                   |
| FR-23 | WHEN a manager requests shifts by date range THE SYSTEM SHALL return all shifts for the business within that range, with assigned staff eager-loaded.                                            |

### 3.4 Shift Swap Marketplace

| ID     | Requirement                                                                                                                                                                                                                                                                                                                                                                                    |
| ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-24  | WHEN a staff member offers a shift for swap THE SYSTEM SHALL create a `shift_swaps` record with `status = offered`, `offered_by = auth user`, `requested_by = null`.                                                                                                                                                                                                                           |
| FR-25  | IF the staff member does not own the shift (is not in `shift_staff` with `status = assigned`) THEN THE SYSTEM SHALL return 403.                                                                                                                                                                                                                                                                |
| FR-26  | IF the shift has already passed (`start_at < now()`) THEN THE SYSTEM SHALL return 422 with `shift_id: ["Cannot offer a past shift for swap."]`.                                                                                                                                                                                                                                                |
| FR-27  | WHEN a staff member requests to take an offered shift THE SYSTEM SHALL update `shift_swaps.status` to `pending_approval` and set `requested_by = auth user`.                                                                                                                                                                                                                                   |
| FR-28  | IF the requesting staff member has an overlapping shift or approved time-off THE SYSTEM SHALL return 422 with `user_id: ["You have a conflicting shift or approved time-off."]`.                                                                                                                                                                                                               |
| FR-29  | IF the `shift_swap.status` is not `offered` THEN THE SYSTEM SHALL return 422 with `status: ["This shift is no longer available."]`.                                                                                                                                                                                                                                                            |
| FR-29A | THE SYSTEM SHALL allow multiple offers for different assignments on the same future shift, but only one request may be pending for a given offered assignment at a time. When one request is accepted for processing, competing requests for that same assignment SHALL be set to `cancelled` with `cancellation_reason = another_request_accepted`, and the affected staff SHALL be notified. |
| FR-30  | WHEN a manager approves a swap THE SYSTEM SHALL: set `offered_by`'s `shift_staff.status = removed`; create a new `shift_staff` record for `requested_by` with `status = assigned`; set `shift_swaps.status = approved`; dispatch `SendSwapApprovedNotification` for both users.                                                                                                                |
| FR-31  | WHEN a manager rejects a swap THE SYSTEM SHALL: set `shift_swaps.status = offered` (revert, not rejected); set `requested_by = null`; dispatch `SendSwapRejectedNotification` to the requesting staff.                                                                                                                                                                                         |
| FR-32  | WHEN a staff member cancels their own offer WHILE `status = offered` THE SYSTEM SHALL set `shift_swaps.status = cancelled`.                                                                                                                                                                                                                                                                    |
| FR-33  | IF a staff member attempts to cancel a swap that is not in `offered` status THEN THE SYSTEM SHALL return 422 with `status: ["Cannot cancel a swap that is already being processed."]`.                                                                                                                                                                                                         |

**Valid status transitions:**

```
offered           → pending_approval  (staff requests it)
offered           → cancelled         (offerer cancels)
pending_approval  → approved          (manager approves)
pending_approval  → offered           (manager rejects — reverts, not terminal)
```

`rejected` is NOT a terminal status. Rejection reverts to `offered` so other
staff can still request the shift.

### 3.5 Time-Off Requests

| ID    | Requirement                                                                                                                                                                                                  |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| FR-34 | WHEN a staff member submits a time-off request THE SYSTEM SHALL require `start_date` and `end_date`. `reason` is optional. Initial status: `pending`.                                                        |
| FR-35 | IF `end_date` is before `start_date` THEN THE SYSTEM SHALL return 422 with `end_date: ["End date must be on or after start date."]`.                                                                         |
| FR-36 | WHEN a manager views a time-off request THE SYSTEM SHALL include a `conflicts` array listing any assigned shifts that overlap the requested dates.                                                           |
| FR-37 | WHEN a manager approves a time-off request THE SYSTEM SHALL set `status = approved` and dispatch `SendTimeOffStatusNotification` to the staff member.                                                        |
| FR-38 | WHEN a manager rejects a time-off request THE SYSTEM SHALL set `status = rejected`, optionally store `manager_notes`, and dispatch `SendTimeOffStatusNotification`.                                          |
| FR-39 | IF a staff member already has a `pending` or `approved` time-off request for overlapping dates THEN THE SYSTEM SHALL return 422 with `start_date: ["You already have a time-off request for this period."]`. |

### 3.6 Notifications

| ID     | Requirement                                                                                                                                                                              |
| ------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-40  | THE SYSTEM SHALL dispatch all notification emails as queued Laravel Jobs implementing `ShouldQueue`. No inline `Mail::send()` or synchronous notification dispatch in controllers.       |
| FR-41  | WHEN a shift is assigned to a staff member THE SYSTEM SHALL dispatch `SendShiftAssignedNotification`.                                                                                    |
| FR-42  | WHEN a swap offer is created THE SYSTEM SHALL notify only eligible staff members who can take the vacant shift; it SHALL NOT broadcast to all business staff or notify the offerer.      |
| FR-43  | WHEN a swap is requested THE SYSTEM SHALL dispatch `SendSwapRequestedNotification` to the manager(s) of the business.                                                                    |
| FR-44  | WHEN a swap is approved THE SYSTEM SHALL dispatch `SendSwapApprovedNotification` to both `offered_by` and `requested_by`.                                                                |
| FR-45  | WHEN a swap is rejected THE SYSTEM SHALL dispatch `SendSwapRejectedNotification` to `requested_by` only.                                                                                 |
| FR-45A | WHEN a competing swap request is automatically cancelled because another request was accepted, THE SYSTEM SHALL dispatch `SendSwapUnavailableNotification` to the affected staff member. |
| FR-46  | WHEN a time-off request is submitted THE SYSTEM SHALL dispatch `SendTimeOffSubmittedNotification` to manager(s).                                                                         |
| FR-47  | WHEN a time-off request is approved or rejected THE SYSTEM SHALL dispatch `SendTimeOffStatusNotification` to the requesting staff member.                                                |
| FR-48  | WHERE in-app notifications are enabled THE SYSTEM SHALL create a `notifications` record (Laravel's built-in) for each event above.                                                       |

### 3.7 Dashboard

| ID    | Requirement                                                                                                                                                                                                                                             |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-49 | WHEN a manager/owner requests the dashboard THE SYSTEM SHALL return: upcoming shifts for the next 7 days (with staff), count of `pending_approval` swaps, count of `pending` time-off requests. All counts via DB aggregation, not PHP-level iteration. |
| FR-50 | WHEN a staff member requests the dashboard THE SYSTEM SHALL return: their assigned shifts for the next 14 days, their pending time-off requests, swap requests they are involved in (as `offered_by` or `requested_by`).                                |

### 3.8 Availability, Hours, and Staffing Coverage

| ID    | Requirement                                                                                                                                                                                                                                                              |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| FR-51 | THE SYSTEM SHALL allow an owner or manager to configure business-specific staff availability, reusable availability templates, and date-specific exceptions.                                                                                                             |
| FR-52 | THE SYSTEM SHALL allow an owner or manager to configure target minimum and maximum hours for a staff member for a configurable scheduling period, such as a week. These targets are guidance, not guarantees.                                                            |
| FR-53 | WHEN a proposed assignment falls outside a staff member's declared availability or places their hours below the minimum or above the maximum target, THE SYSTEM SHALL show a warning but SHALL allow the owner or manager to continue. An override may record a reason.  |
| FR-54 | THE SYSTEM SHALL allow an owner or manager to configure a minimum required personnel count for a location, role, and time window.                                                                                                                                        |
| FR-55 | WHEN an assignment, removal, or approved swap would leave a location below its required personnel count, THE SYSTEM SHALL show a staffing warning but SHALL allow the owner or manager to continue. The manager remains responsible for the resulting coverage decision. |
| FR-56 | Hours and staffing coverage SHALL be calculated across all locations belonging to the same business.                                                                                                                                                                     |

---

## 4. Data Models

The canonical data-model definitions are maintained separately in [`docs/DATA-MODELS.md`](DATA-MODELS.md).

That document covers the MVP schema and the approved model extensions for availability, scheduling preferences, staffing requirements, ownership transfers, historical assignments, swap-to-assignment linkage, cancellation reasons, and business timezones.

Implementation must keep this specification, the PRD, and `docs/DATA-MODELS.md` synchronized when a model or invariant changes.

---

## 5. API Contracts

All endpoints are prefixed `/api`. All requests and responses use `Content-Type: application/json`. All protected endpoints require a valid Bearer token in the `Authorization: Bearer <token>` header.

### 5.1 Auth

All protected endpoints require a valid Bearer token in the `Authorization: Bearer <token>` header.

```
POST /api/auth/register
  Auth: none
  Request:  { name: string, email: string, password: string,
              password_confirmation: string, business_name: string }
  Response 201: { data: User, token: string }
  Response 422: { message: string, errors: { field: [string] } }

POST /api/auth/login
  Auth: none
  Request:  { email: string, password: string }
  Response 200: { data: User, token: string }
  Response 401: { message: "Invalid credentials." }
  Response 429: { message: "Too many login attempts." }

POST /api/auth/logout
  Auth: required (Bearer token)
  Response 204: (no body)

GET /api/auth/user
  Auth: required (Bearer token)
  Response 200: { data: User }
```

### 5.2 Business & Staff Management

```
GET /api/businesses/{business}/staff
  Auth: Bearer token, manager, owner
  Response 200: { data: [BusinessUser] }

POST /api/businesses/{business}/staff
  Auth: Bearer token, owner, manager
  Request:  { email: string, role: "manager"|"staff" }
  Response 201: { data: BusinessUser }   -- if user exists and was linked
           202: { data: Invitation }      -- if invite was sent
  Response 422: { message, errors }

PATCH /api/businesses/{business}/staff/{user}
  Auth: Bearer token, owner
  Request:  { role: "manager"|"staff" }
  Response 200: { data: BusinessUser }
  Response 422: { message, errors }      -- e.g. cannot change own role

DELETE /api/businesses/{business}/staff/{user}
  Auth: Bearer token, owner
  Response 204

POST /api/businesses/{business}/ownership-transfer
  Auth: Bearer token, owner
  Request:  { successor_email: string }
  Response 202: { data: OwnershipTransfer }
  Response 422: { message, errors } -- successor must be an active member of this business

POST /api/ownership-transfers/{token}/accept
  Auth: Bearer token (as successor)
  Response 204
  Response 422: { message, errors }

GET /api/businesses/{business}/invitations
  Auth: Bearer token, owner, manager
  Response 200: { data: [Invitation] }

POST /api/invitations/{token}/accept
  Auth: none
  Request:  { name: string, password: string, password_confirmation: string }
  Response 201: { data: User, token: string }
  Response 422: { message, errors }
```

### 5.3 Locations

```
GET /api/businesses/{business}/locations
  Auth: Bearer token, any member
  Response 200: { data: [Location] }

POST /api/businesses/{business}/locations
  Auth: Bearer token, owner, manager
  Request:  { name: string, address?: string }
  Response 201: { data: Location }

PATCH /api/businesses/{business}/locations/{location}
  Auth: Bearer token, owner, manager
  Request:  { name?: string, address?: string }
  Response 200: { data: Location }

DELETE /api/businesses/{business}/locations/{location}
  Auth: Bearer token, owner, manager
  Response 204
```

### 5.4 Shifts

```
GET /api/businesses/{business}/shifts
  Auth: Bearer token, any member
  Query:  start_date (required, date), end_date (required, date)
  Response 200: { data: [Shift with staff[]] }

POST /api/businesses/{business}/shifts
  Auth: Bearer token, owner, manager
  Request:  { start_at: datetime, end_at: datetime,
              location_id?: int, role_label?: string }
  Response 201: { data: Shift }
  Response 422: { message, errors }

PATCH /api/businesses/{business}/shifts/{shift}
  Auth: Bearer token, owner, manager
  Request:  { start_at?: datetime, end_at?: datetime,
              location_id?: int, role_label?: string }
  Response 200: { data: Shift }
  Response 422: { message, errors }

DELETE /api/businesses/{business}/shifts/{shift}
  Auth: Bearer token, owner, manager
  Response 204

POST /api/businesses/{business}/shifts/{shift}/staff
  Auth: Bearer token, owner, manager
  Request:  { user_id: int }
  Response 201: { data: ShiftStaff }
  Response 422: { message, errors }    -- overlap or time-off conflict

DELETE /api/businesses/{business}/shifts/{shift}/staff/{user}
  Auth: Bearer token, owner, manager
  Response 204
```

### 5.5 Shift Swaps

```
GET /api/businesses/{business}/shift-swaps
  Auth: Bearer token, any member
  Query:  status? (offered|pending_approval|approved|cancelled)
  Response 200: { data: [ShiftSwap] }

POST /api/businesses/{business}/shift-swaps
  Auth: Bearer token, staff, manager, owner
  Request:  { shift_id: int, notes?: string }
  Response 201: { data: ShiftSwap }
  Response 403: (not assigned to shift)
  Response 422: { message, errors }

POST /api/businesses/{business}/shift-swaps/{swap}/request
  Auth: Bearer token, staff, manager, owner
  Response 200: { data: ShiftSwap }
  Response 422: { message, errors }    -- overlap, time-off, or wrong status

POST /api/businesses/{business}/shift-swaps/{swap}/approve
  Auth: Bearer token, owner, manager
  Request:  { manager_notes?: string }
  Response 200: { data: ShiftSwap }
  Response 422: { message, errors }

POST /api/businesses/{business}/shift-swaps/{swap}/reject
  Auth: Bearer token, owner, manager
  Request:  { manager_notes?: string }
  Response 200: { data: ShiftSwap }

POST /api/businesses/{business}/shift-swaps/{swap}/cancel
  Auth: Bearer token, offered_by only
  Response 200: { data: ShiftSwap }
  Response 422: { message, errors }    -- wrong status
```

### 5.6 Time-Off Requests

```
GET /api/businesses/{business}/time-off-requests
  Auth: manager/owner sees all; staff sees own only
  Query:  status? (pending|approved|rejected), user_id? (manager/owner only)
  Response 200: { data: [TimeOffRequest] }

POST /api/businesses/{business}/time-off-requests
  Auth: any member (for themselves)
  Request:  { start_date: date, end_date: date, reason?: string }
  Response 201: { data: TimeOffRequest }
  Response 422: { message, errors }

GET /api/businesses/{business}/time-off-requests/{request}
  Auth: owner of request, or manager/owner
  Response 200: { data: TimeOffRequest, conflicts: [Shift] }

POST /api/businesses/{business}/time-off-requests/{request}/approve
  Auth: owner, manager
  Request:  { manager_notes?: string }
  Response 200: { data: TimeOffRequest }

POST /api/businesses/{business}/time-off-requests/{request}/reject
  Auth: owner, manager
  Request:  { manager_notes?: string }
  Response 200: { data: TimeOffRequest }
```

### 5.7 Dashboard

```
GET /api/businesses/{business}/dashboard
  Auth: any member
  Response 200 (manager/owner):
    {
      data: {
        upcoming_shifts: [Shift],         -- next 7 days, with staff[]
        pending_swaps_count: int,
        pending_time_off_count: int
      }
    }
  Response 200 (staff):
    {
      data: {
        upcoming_shifts: [Shift],         -- next 14 days, assigned to auth user
        pending_time_off_requests: [TimeOffRequest],
        active_swaps: [ShiftSwap]         -- offered_by or requested_by = auth user
      }
    }
```

### 5.8 Notifications

```
GET /api/businesses/{business}/notifications
  Auth: any member (own only)
  Response 200: { data: [Notification] }

POST /api/businesses/{business}/notifications/{notification}/read
  Auth: owner of notification
  Response 204

POST /api/businesses/{business}/notifications/read-all
  Auth: any member
  Response 204
```

---

## 6. API Resource Shapes

All responses wrap data in `{ data: ... }`. Use Laravel API Resources for every response — never return raw Eloquent models.

```
User:
  id, name, email, created_at

BusinessUser:
  id, user: User, role, created_at

Shift:
  id, business_id, location: Location|null, role_label,
  start_at, end_at, created_by: User,
  staff: [ShiftStaff]                  -- eager-loaded, status = assigned only

ShiftStaff:
  id, user: User, status

ShiftSwap:
  id, shift: Shift, offered_by: User,
  requested_by: User|null, status, cancellation_reason: string|null,
  notes, manager_notes,
  created_at, updated_at

TimeOffRequest:
  id, user: User, start_date, end_date, reason,
  status, manager_notes, created_at, updated_at

Location:
  id, business_id, name, address

Invitation:
  id, email, status, created_at
```

---

## 7. Authorization Matrix

Enforced via Laravel Policy classes. No business logic in middleware.

| Action                       | Owner     | Manager   | Staff               |
| ---------------------------- | --------- | --------- | ------------------- |
| View shifts                  | ✅        | ✅        | ✅ (own + open)     |
| Create / edit / delete shift | ✅        | ✅        | ❌                  |
| Assign staff to shift        | ✅        | ✅        | ❌                  |
| Offer shift for swap         | ✅        | ✅        | ✅ (own shift only) |
| Request a swap               | ✅        | ✅        | ✅                  |
| Cancel own swap offer        | ✅        | ✅        | ✅ (own offer only) |
| Approve / reject swap        | ✅        | ✅        | ❌                  |
| Submit time-off request      | ✅        | ✅        | ✅ (own only)       |
| Approve / reject time-off    | ✅        | ✅        | ❌                  |
| Manage staff roles           | ✅        | ❌        | ❌                  |
| Invite staff                 | ✅        | ✅        | ❌                  |
| View dashboard               | ✅ (full) | ✅ (full) | ✅ (own)            |

**Policy classes to implement:**

```
ShiftPolicy         — viewAny, view, create, update, delete, assignStaff
ShiftSwapPolicy     — viewAny, create (offer), request, approve, reject, cancel
TimeOffPolicy       — viewAny, view, create, approve, reject
BusinessUserPolicy  — viewAny, update (role), delete
```

---

## 8. Queue Jobs

All jobs implement `ShouldQueue`. All are dispatched asynchronously — never inline in a controller or service.

| Job Class                          | Trigger                     | Recipients                                   |
| ---------------------------------- | --------------------------- | -------------------------------------------- |
| `SendShiftAssignedNotification`    | Staff assigned to shift     | Assigned staff                               |
| `SendSwapOfferedNotification`      | Swap offer created          | Eligible staff who can take the vacant shift |
| `SendSwapRequestedNotification`    | Swap request submitted      | All managers in business                     |
| `SendSwapApprovedNotification`     | Swap approved               | `offered_by` + `requested_by`                |
| `SendSwapRejectedNotification`     | Swap rejected               | `requested_by` only                          |
| `SendSwapUnavailableNotification`  | Competing request cancelled | Affected staff member                        |
| `SendTimeOffSubmittedNotification` | Time-off request created    | All managers in business                     |
| `SendTimeOffStatusNotification`    | Time-off approved/rejected  | Requesting staff                             |

Job location: `app/Jobs/Notifications/`

Each job receives the relevant model ID (not the model instance) and resolves it
inside `handle()` to avoid serialization issues with stale data.

---

## 9. Conflict Detection

Centralize this logic in a single service class — do not duplicate across controllers.

```php
// app/Services/ShiftConflictService.php

public function hasShiftOverlap(int $userId, Carbon $start, Carbon $end, ?int $excludeShiftId = null): bool
// Checks shift_staff (status = assigned) for overlap: start < existing_end AND end > existing_start

public function hasTimeOffConflict(int $userId, Carbon $start, Carbon $end): bool
// Checks time_off_requests (status = approved) for overlap

public function hasConflict(int $userId, Carbon $start, Carbon $end, ?int $excludeShiftId = null): bool
// Calls both above; returns true if either is true
```

Called from:

- `ShiftStaffController::store()` (assigning staff)
- `ShiftSwapController::request()` (requesting a swap)
- `TimeOffRequestController::store()` (submitting time-off, prevents duplicate)

---

## 10. Edge Cases & Error Handling

| Scenario                                                         | Behavior                                                                                  |
| ---------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Shift `end_at == existing`start_at` for same user                | NOT an overlap (boundary equality is allowed)                                             |
| Manager approves swap but requester now has a conflict           | Re-run conflict detection during approval; reject approval with 422 if a conflict exists. |
| Staff submits time-off that overlaps an existing pending request | Return 422 (FR-39)                                                                        |
| Manager deletes a shift with a `pending_approval` swap           | Cascade-cancel the swap; notify both parties                                              |
| Staff removed from business while having an active swap          | Cancel their `offered` and `pending_approval` swaps; preserve historical records.         |
| Invitation token used twice                                      | Return 422 — `status = accepted`, token is consumed                                       |
| Invitation expires (48hr)                                        | Return 422 — system marks `status = expired` on next use                                  |
| Existing member is invited to the same business                  | Return 422 — user is already a member                                                     |
| User already belongs to another business during MVP              | Return 422 — one business membership is supported at launch                               |
| Resending a pending invitation                                   | Invalidate the old token and issue a new 48-hour token for the same email                 |
| Owner tries to leave the business                                | Require confirmed ownership transfer to an active member first.                           |
| `start_date == end_date` for time-off                            | Valid — single-day off                                                                    |
| Swap requested for a shift that starts in < 1 hour               | No restriction defined in PRD — allowed by default; see Open Questions                    |

---

## 11. Testing Requirements

Framework: **Pest**. All tests use `RefreshDatabase`. Factories required for all models.

### 11.1 Feature Tests (HTTP layer)

Cover the full request → response cycle including auth and policy enforcement.

**Auth**

- Register creates user + business + owner pivot ✓
- Duplicate email returns 422 ✓
- Login with valid credentials returns user ✓
- Login with invalid credentials returns 401 ✓
- Rate limit returns 429 after 5 failures ✓
- Logout returns 204 and invalidates the token ✓

**Shifts**

- Manager can create shift ✓
- Staff cannot create shift → 403 ✓
- Creating shift with `end_at < start_at` → 422 ✓
- Assigning staff with overlap → 422 ✓
- Assigning staff with approved time-off → 422 ✓
- Deleting shift cancels pending swaps ✓

**Shift Swaps — every state transition**

- Staff can offer own shift → status = `offered` ✓
- Staff cannot offer shift they are not assigned to → 403 ✓
- Staff cannot offer a past shift → 422 ✓
- Staff can request an offered shift → status = `pending_approval` ✓
- Requesting staff with overlap → 422 ✓
- Requesting a non-offered swap → 422 ✓
- Manager approves swap → correct `shift_staff` updates + jobs dispatched ✓
- Manager rejects swap → status reverts to `offered`, `requested_by = null` ✓
- Offerer can cancel own offer → status = `cancelled` ✓
- Cannot cancel a `pending_approval` swap → 422 ✓

**Time-Off**

- Staff can submit time-off request ✓
- Duplicate overlapping request → 422 ✓
- Manager approves → status = `approved`, job dispatched ✓
- Manager rejects → status = `rejected`, job dispatched ✓
- Conflict list returned on GET single request ✓

**Authorization**

- Staff cannot access another business's shifts → 403 ✓
- Staff cannot approve swaps → 403 ✓
- Owner cannot change their own role → 422 ✓

### 11.2 Unit Tests

- `ShiftConflictService::hasShiftOverlap()` — boundary cases (exact match, +1 min, -1 min, no overlap)
- `ShiftConflictService::hasTimeOffConflict()` — same boundary cases
- Swap status machine — invalid transitions should not be reachable via public methods

### 11.3 Test Utilities

```php
// Use Queue::fake() for all notification tests
// Assert jobs dispatched, not email sent:
Queue::assertPushed(SendSwapApprovedNotification::class, fn($job) => $job->swapId === $swap->id);

// Factory baseline:
BusinessFactory with Owner + 2 Staff users seeded by default
ShiftFactory defaults to tomorrow 09:00–17:00
```

---

## 12. Assumptions

- Tenant resolution is by URL parameter (`/api/businesses/{business}/...`), not subdomain. Simpler for MVP and interview explainability.
- Every user, including owners, managers, and staff, can belong to only one business at launch, even though the schema supports many memberships. Staff can see and work shifts only for that business; multi-business switching is a future feature.
- The PRD remains the product baseline. This technical specification adds implementation detail and applies only the explicit MVP decisions recorded here when the PRD leaves behavior open.
- Shift datetimes are stored in UTC. Each business has an IANA timezone, defaulting to `Asia/Manila`, for date filters, overnight shifts, time-off conflict evaluation, availability, and staffing windows. The frontend displays times in the business timezone.
- Email is the only notification channel for MVP (no SMS, no push).
- Availability and minimum/maximum hours are business-specific scheduling guidance. Owners and managers may use reusable templates and override warnings; the values are not guarantees.
- Staffing requirements are business-specific warnings for location, role, and time window. Owners and managers may override them, and the manager is responsible for the resulting coverage decision.
- SQS is the target queue driver for production; `database` driver is acceptable for local dev and CI.
- "Managers" in a business for notification purposes means all `business_user` records with `role = manager` or `role = owner`.
- Business tenants must not see or infer another business's schedules, locations, managers, or assignments. If future multi-business membership is enabled and a user's schedules conflict across businesses, only the staff member may receive a generic conflict notice; the affected businesses receive no cross-tenant details and the system does not block or resolve the conflict. The staff member is responsible for resolving it through a swap, availability discussion, or another arrangement.

---

## 13. Open Questions

- ✅ **Resolved OQ-1:** Notify only eligible staff about available/vacant swap shifts. Do not broadcast the offer to every staff member.
- ✅ **Resolved OQ-2:** Re-run conflict detection when a manager approves a swap. If the requester now conflicts with another shift or approved time-off, do not approve the swap.
- ✅ **Resolved OQ-3:** When a staff member is removed, cancel their `offered` and `pending_approval` swaps while preserving historical records.
- ✅ **Resolved OQ-4:** The MVP has no minimum swap lead-time rule. Lead time is only the interval between when an offer is made and the offered shift's `start_at`; it is not the same as staffing coverage. For example, a waiter shift from 08:00–13:00 followed by another from 13:00–17:00 provides continuous time coverage because the boundary times meet, but if the first waiter offers the 08:00 shift at 07:00, the swap lead time is one hour. The MVP allows an offer any time before the shift starts, subject to FR-26. Minimum swap lead time is recorded in the post-MVP backlog below.
- ✅ **Resolved OQ-5:** An owner must transfer ownership to another business member before leaving. Ownership transfer must be atomic: promote the successor to `owner`, then allow the former owner to leave or continue as a non-owner member. The business must never be left without an owner.

---

## 14. Post-MVP Backlog

### BL-4: Automated staffing and hours optimization

The MVP shows availability, hours, and staffing warnings but leaves the final decision to the owner or manager. Future versions may recommend schedules, balance hours automatically, or optimize coverage without taking control away from management.

### BL-2: Privacy-preserving cross-business conflict notice

If multi-business membership is enabled after the MVP, a staff member may have schedules assigned by separate companies. Each company must continue to see only its own tenant data and may assume availability based on the information within its tenant. When schedules conflict across businesses, notify only the staff member with a generic message such as `You have a schedule conflict with another business.` Do not reveal the other business or schedule, do not block the assignment, and do not automatically resolve the conflict. The staff member remains responsible for requesting a swap or making another arrangement.

This behavior does not change conflict detection between locations of the same business: those conflicts remain system-enforced because all locations belong to the same tenant.

### BL-3: Availability and weekly hours targets

**Moved into MVP requirements FR-51–FR-53.** The MVP supports customizable business-specific availability, reusable templates, date-specific exceptions, and soft minimum/maximum hours targets. Violations warn but do not block owner or manager scheduling decisions.

### BL-1: Configurable minimum swap lead time

Allow each business to configure a minimum amount of notice required before a shift can be offered or requested for swap.

Example setting:

```text
minimum_swap_lead_time_minutes = 60
```

For a shift starting at 08:00, an offer or request made at 07:00 or earlier is allowed; one made at 07:01 is rejected. The feature should define whether the rule applies when the offer is created, when a staff member requests it, or both. It should also provide a clear validation error and be covered by feature tests around the exact boundary.

This is not required for the MVP. Until implemented, shifts may be offered or requested any time before `start_at`, subject to the existing past-shift and conflict rules.

---

## 15. Changelog

| Date       | Version | Change                                                                                                                                                                     |
| ---------- | ------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 2026-09-18 | 1.0     | Initial spec derived from ShiftSwap PRD v1.2                                                                                                                               |
| 2026-09-18 | 1.1     | Aligned stack versions with the app; resolved MVP tenancy, notification, approval re-check, removal, and ownership-transfer decisions; documented swap lead-time trade-off |
| 2026-09-18 | 1.2     | Added configurable minimum swap lead time to the post-MVP backlog; kept it out of MVP scope                                                                                |
| 2026-09-18 | 1.3     | Added privacy-preserving cross-business conflict behavior; staff remain responsible for resolving cross-tenant schedule conflicts                                          |
| 2026-09-18 | 1.4     | Added availability and soft minimum/maximum weekly hours targets to the post-MVP backlog; owner overrides remain allowed                                                   |
| 2026-09-18 | 1.5     | Moved availability, hours targets, and staffing warnings into MVP; defined ownership transfer endpoints, invitation rules, and business timezone behavior                  |
| 2026-09-22 | 1.6     | Switched authentication from Sanctum SPA cookie auth to stateless Bearer token auth; frontend deployed as a separate repository; API reusable by a future mobile app       |
