# ShiftSwap Data Models

> Status: Draft | Version: 1.0 | Date: September 18, 2026
>
> This is the canonical data-model reference for ShiftSwap. Product behavior is defined by `docs/ShiftSwap-PRD.md`; implementation behavior is defined by `docs/SPEC.md`. Keep this file synchronized when schema decisions change.

## 1. Modeling principles

- A `business` is a tenant. Every business-owned record must be scoped to `business_id`, directly or through a required parent relationship.
- A business may have multiple locations. Shift conflicts, staff hours, availability warnings, and staffing warnings are evaluated across all locations of the same business.
- MVP users belong to one business. The schema remains compatible with future multi-business membership.
- Shift datetimes are stored in UTC. `businesses.timezone` is used for local date filters, overnight shifts, time-off, availability, and staffing windows.
- Historical assignments and decisions are preserved. Do not hard-delete operational history.
- All foreign keys and indexes below are required unless the implementation uses an equivalent constraint.

## 2. Tenant and identity models

### 2.1 `businesses`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `name` | `varchar(255)` not null |
| `slug` | `varchar(255)` unique, URL-safe |
| `timezone` | `varchar(64)` not null, default `Asia/Manila` (IANA timezone) |
| `created_at`, `updated_at` | timestamps |

### 2.2 `users`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `name` | `varchar(255)` not null |
| `email` | `varchar(255)` unique, not null |
| `password` | `varchar(255)` not null, hashed |
| `remember_token` | `varchar(100)` nullable |
| `created_at`, `updated_at` | timestamps |

### 2.3 `business_user`

Membership and role are business-specific.

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `user_id` | FK to `users.id`, cascade delete |
| `role` | enum: `owner`, `manager`, `staff` |
| `created_at`, `updated_at` | timestamps |

Constraint: unique `(business_id, user_id)`. MVP rules allow one business membership per user; future multi-business support may add memberships without changing this table.

Application invariants: owners alone may promote or demote managers; ownership transfer must be transactional; a business must never be left without an owner.

### 2.4 `invitations`

Invitation tokens must be single-use and expire after 48 hours. Store a hash of the token rather than a recoverable raw token.

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `email` | `varchar(255)` not null |
| `token_hash` | `varchar(255)` unique, not null |
| `status` | enum: `pending`, `accepted`, `expired` |
| `expires_at` | timestamp not null |
| `invited_by` | FK to `users.id`, not null |
| `created_at`, `updated_at` | timestamps |

Constraint: one pending invitation per `(business_id, email)`. Resending invalidates the old token and issues a new one. During the MVP, reject an invitation when the user already belongs to another business.

### 2.5 `ownership_transfers`

Ownership transfer is initiated by the current owner and accepted by an active member of the same business.

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `current_owner_id` | FK to `users.id`, not null |
| `successor_id` | FK to `users.id`, not null |
| `token_hash` | `varchar(255)` unique, not null |
| `status` | enum: `pending`, `accepted`, `expired`, `cancelled` |
| `expires_at` | timestamp not null |
| `accepted_at` | timestamp nullable |
| `created_at`, `updated_at` | timestamps |

Acceptance must atomically promote the successor to `owner` and change the former owner to `manager` or remove them. The business must not temporarily have zero owners.

## 3. Scheduling models

### 3.1 `locations`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `name` | `varchar(255)` not null |
| `address` | `text` nullable |
| `created_at`, `updated_at` | timestamps |

### 3.2 `shifts`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `location_id` | FK to `locations.id`, set null on delete |
| `role_label` | `varchar(100)` nullable |
| `start_at`, `end_at` | UTC datetimes, not null |
| `created_by` | FK to `users.id`, not null |
| `deleted_at` | timestamp nullable for soft deletion |
| `created_at`, `updated_at` | timestamps |

Constraint: `end_at` must be after `start_at`. Index `(business_id, start_at, end_at)`.

### 3.3 `shift_staff`

An assignment history record. Do not hard-delete after a swap.

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `shift_id` | FK to `shifts.id`, cascade delete |
| `user_id` | FK to `users.id`, cascade delete |
| `status` | enum: `assigned`, `removed` |
| `assigned_at` | timestamp not null |
| `removed_at` | timestamp nullable |
| `removed_reason` | `varchar(100)` nullable |
| `created_at`, `updated_at` | timestamps |

Use an index on `(shift_id, user_id, status)`. Enforce only one active `assigned` record for a user and shift in the service/database strategy; retain multiple historical records when an assignment is removed and later restored.

### 3.4 `shift_swaps`

A swap belongs to a specific assignment, not merely to a shift. This allows multiple workers on one shift to offer different assignments.

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `shift_id` | FK to `shifts.id`, cascade delete |
| `shift_staff_id` | FK to `shift_staff.id`, cascade delete |
| `offered_by` | FK to `users.id`, not null |
| `requested_by` | FK to `users.id`, nullable |
| `status` | enum: `offered`, `pending_approval`, `approved`, `cancelled` |
| `cancellation_reason` | `varchar(100)` nullable |
| `notes` | `text` nullable |
| `manager_notes` | `text` nullable |
| `created_at`, `updated_at` | timestamps |

One assignment may have one active offer at a time. Multiple assignments on the same shift may each have an offer. Only one request may be pending for a given offered assignment; competing requests are cancelled with a reason and the affected staff member is notified. Index `(business_id, status)` and `(shift_staff_id, status)`.

Swap transitions:

```text
offered -> pending_approval -> approved
offered -> cancelled
pending_approval -> offered       (manager rejects)
pending_approval -> cancelled     (competing request accepted)
```

## 4. Availability and workload models

### 4.1 `availability_templates`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `name` | `varchar(255)` not null |
| `created_by` | FK to `users.id`, not null |
| `created_at`, `updated_at` | timestamps |

Templates are reusable within one business only.

### 4.2 `availability_template_rules`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `template_id` | FK to `availability_templates.id`, cascade delete |
| `day_of_week` | tiny integer, 0–6 |
| `available_from`, `available_until` | local business time, nullable for unavailable days |
| `created_at`, `updated_at` | timestamps |

Constraint: rules must use the business timezone and must represent valid windows. Overnight availability may be represented by a rule spanning the local date boundary or by two rules, consistently across the application.

### 4.3 `staff_availability`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `user_id` | FK to `users.id`, cascade delete |
| `template_id` | FK to `availability_templates.id`, nullable |
| `effective_from` | date not null |
| `effective_until` | date nullable |
| `created_at`, `updated_at` | timestamps |

This associates a worker with a business-specific availability template for a period.

### 4.4 `staff_availability_exceptions`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `user_id` | FK to `users.id`, cascade delete |
| `date` | local business date, not null |
| `is_available` | boolean not null |
| `available_from`, `available_until` | local business time, nullable |
| `reason` | `text` nullable |
| `created_at`, `updated_at` | timestamps |

Exceptions override the applicable recurring template for that date.

### 4.5 `staff_scheduling_preferences`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `user_id` | FK to `users.id`, cascade delete |
| `period_type` | enum: `weekly`, `biweekly`, `monthly` |
| `minimum_hours` | decimal(6,2) nullable |
| `maximum_hours` | decimal(6,2) nullable |
| `effective_from` | date not null |
| `effective_until` | date nullable |
| `created_at`, `updated_at` | timestamps |

Minimum and maximum hours are soft targets. Violations warn but do not block an owner or manager. Hours include assignments across all locations of the business.

## 5. Staffing and audit models

### 5.1 `staffing_requirements`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `location_id` | FK to `locations.id`, cascade delete |
| `role_label` | `varchar(100)` nullable |
| `day_of_week` | tiny integer, nullable for date-specific rules |
| `effective_date` | date nullable |
| `start_time`, `end_time` | local business times, not null |
| `minimum_personnel` | unsigned integer not null |
| `created_by` | FK to `users.id`, not null |
| `created_at`, `updated_at` | timestamps |

A staffing requirement identifies the minimum number of assigned active workers for a location, role, and time window. Violations warn but do not block scheduling.

### 5.2 `schedule_overrides`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `shift_id` | FK to `shifts.id`, nullable |
| `user_id` | FK to `users.id`, nullable |
| `type` | enum: `availability`, `hours`, `staffing` |
| `reason` | `text` nullable |
| `overridden_by` | FK to `users.id`, not null |
| `created_at`, `updated_at` | timestamps |

Use this table when an owner or manager proceeds despite a warning and an audit trail is desired.

## 6. Time off and notifications

### 6.1 `time_off_requests`

| Column | Definition |
|---|---|
| `id` | `bigint unsigned` primary key |
| `business_id` | FK to `businesses.id`, cascade delete |
| `user_id` | FK to `users.id`, cascade delete |
| `start_date`, `end_date` | local business dates, not null |
| `reason` | `text` nullable |
| `status` | enum: `pending`, `approved`, `rejected` |
| `manager_notes` | `text` nullable |
| `created_at`, `updated_at` | timestamps |

Dates are inclusive. Conflict checks convert shift UTC datetimes into the business timezone before comparing dates.

### 6.2 `notifications`

Use Laravel's generated notification migration (`php artisan notifications:table`). Do not hand-roll it.

| Column | Definition |
|---|---|
| `id` | UUID primary key |
| `type` | notification class name |
| `notifiable_type`, `notifiable_id` | polymorphic recipient |
| `data` | JSON not null |
| `read_at` | timestamp nullable |
| `created_at`, `updated_at` | timestamps |

Notification jobs must be retry-safe. A retry must not create duplicate notifications for the same domain event.

## 7. Cross-model invariants

1. Every business-owned record resolves to one tenant; cross-tenant access returns 403.
2. A shift assignment is conflicting when `new_start < existing_end AND new_end > existing_start`; equality at the boundary is allowed.
3. Swap approval re-checks membership, assignment state, shift existence, conflicts, time off, and staffing warnings inside one transaction.
4. Staffing, availability, and hours violations are warnings only in the MVP.
5. A business cannot be left without an owner during ownership transfer.
6. Historical assignments, swaps, time-off decisions, and override records are preserved.
