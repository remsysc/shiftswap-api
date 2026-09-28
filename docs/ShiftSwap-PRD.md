# ShiftSwap – Product Requirements Document (PRD)

**Version:** 1.4
**Owner:** Rem
**Date:** September 22, 2026
**Status:** Draft (portfolio project)

---

## 1. Overview

### 1.1 Product Vision

ShiftSwap is a multi-tenant SaaS that helps small teams (cafes, clinics, retail stores, salons, etc.) manage staff schedules and enables employees to offer, request, and swap shifts with manager approval.

Inspired by tools like Deputy, When I Work, and Homebase — but intentionally scoped as a portfolio-grade MVP to demonstrate:

- Backend design (Laravel + RESTful API)
- Frontend UX (Vue 3 SPA)
- Cloud deployment (AWS)
- Real-world business logic (scheduling, approvals, notifications)

### 1.2 Target Users

**Primary:**

Small businesses (SMEs) in the Philippines and similar markets:

- 5–30 staff
- 1–3 locations
- Hourly or shift-based workers (F&B, retail, clinics, salons)

**Secondary:**

- Managers/owners who create schedules and approve changes
- Staff who need to view shifts, request time off, and swap shifts

### 1.3 Goals

Build a production-like SaaS with:

- Multi-tenancy
- Role-based access
- Shift scheduling + swap marketplace
- Time-off requests
- Notifications
- AWS deployment (EC2/ECS, RDS, S3, queues)

Create a strong portfolio piece that stands out vs. typical CRUD apps and demonstrates the ability to build real business software.

### 1.4 API Design

The backend exposes a **RESTful JSON API** built with Laravel. The Vue 3 frontend communicates exclusively via this API (no Blade views for the SPA). The frontend is deployed as a separate repository.

Authentication is **stateless, token-based**: users receive a Bearer token on login, store it locally (localStorage/sessionStorage), and send it in the `Authorization: Bearer <token>` header with every request. This approach:

- Demonstrates API-first architecture
- Allows the API to be reused by a future mobile app (iOS/Android)
- Works seamlessly with separate repo deployments
- Is a natural talking point in technical interviews

---

## 2. Problem Statement

Small shift-based businesses struggle with:

- Managing schedules in spreadsheets or chat groups
- Handling last-minute shift changes (sickness, emergencies)
- Coordinating shift swaps without confusion or conflicts
- Ensuring managers approve changes before they take effect

Existing tools (Deputy, Homebase, 7shifts) are powerful but:

- Can be overkill for very small teams
- Have complex pricing and feature sets
- Are not always adopted by PH SMEs due to cost or complexity

ShiftSwap demonstrates the ability to build a focused, modern alternative with core workflows done well.

---

## 3. Scope

### 3.1 In Scope (MVP)

**Multi-tenant architecture**

- Each business (tenant) has isolated data
- Users belong to one business during the MVP; the schema supports multiple memberships for future expansion.
- All data queries are scoped by `business_id`

**Authentication & roles**

- Email/password login & registration with stateless Bearer token auth
- Roles per business: Owner, Manager, Staff
- Policies/gates enforce access control

**Shift management**

- Managers: create, edit, delete, and assign shifts
- Managers: view shifts by day/week/month
- Staff: view assigned shifts and optional open shifts list

**Shift swap marketplace**

- Staff: offer a shift, browse offers, request to take a shift
- Managers: approve or reject swap requests
- System: conflict checks (overlap, time-off)

**Time-off requests**

- Staff: submit requests with date range and optional reason
- Managers: approve or reject with optional comment
- System: flag conflicts with existing shifts

**Notifications**

- Queued email notifications for all key events
- Optional in-app notification list (mark as read)

**Dashboard & basic reporting**

- Manager/Owner: upcoming shifts, pending swap count, pending time-off count
- Staff: upcoming shifts, pending request statuses

**AWS deployment**

- EC2 or ECS (Laravel + PHP + Nginx)
- RDS (MySQL)
- S3 (exports or attachments)
- SQS or database-driven queues
- Basic CI/CD (GitHub Actions or deploy script)

### 3.2 Out of Scope (MVP)

The following are explicitly excluded from the portfolio version:

- Payroll integrations
- Time clock / GPS clock-in
- Advanced labor cost tracking or forecasting
- POS integrations
- Mobile apps (responsive web only)
- DOLE compliance rules (OT calculations, rest days) — mentioned as future extension
- Billing/subscription system

---

## 4. User Personas

### 4.1 Owner (Business Owner)

**Goals:** Full visibility into schedules; ensure the business is properly staffed.

**Pain points:** Last-minute no-shows; confusion around shift swaps.

**Key tasks:**

- View overall schedule
- See pending swaps and time-off requests
- Manage manager/staff roles

### 4.2 Manager

**Goals:** Create and maintain rosters; approve/deny shift changes quickly.

**Pain points:** Manual coordination via chat; double-bookings.

**Key tasks:**

- Create/edit/delete shifts and assign staff
- Review and approve swap requests and time-off requests
- View dashboard of upcoming shifts and pending items

### 4.3 Staff

**Goals:** Know their schedule in advance; easily request time off or swap shifts.

**Pain points:** Unclear schedules; difficulty finding cover.

**Key tasks:**

- View their shifts (calendar + list)
- Request time off
- Offer a shift for swap; browse and request available shifts

---

## 5. Functional Requirements

### 5.1 Authentication & Tenancy

| ID   | Requirement                                                                                                                                                                                                               |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-1 | Users can register and create a new business (tenant).                                                                                                                                                                    |
| FR-2 | Owners/Managers can add staff by email. If the email is already registered, the user is linked to the business with the Staff role. If not, a pending invitation record is created and fulfilled when the user registers. |
| FR-3 | Each user can have different roles in different businesses.                                                                                                                                                               |
| FR-4 | All data queries are scoped by `business_id` to ensure tenant isolation.                                                                                                                                                  |
| FR-5 | Middleware/policies prevent users from accessing other businesses' data (no IDOR).                                                                                                                                        |

### 5.2 Roles & Permissions

| ID   | Requirement                                                                                                         |
| ---- | ------------------------------------------------------------------------------------------------------------------- |
| FR-6 | Roles per business: Owner, Manager, Staff.                                                                          |
| FR-7 | Owners and Managers can create/edit/delete shifts and approve/reject swaps and time-off.                            |
| FR-8 | Staff can view their own shifts, request time off, and offer/request swaps. Staff cannot approve swaps or time-off. |
| FR-9 | Owners can manage roles (promote/demote) within their business.                                                     |

### 5.3 Shift Management

| ID    | Requirement                                                                                                                                                                                                                                                               |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-10 | Managers can create shifts with: date, start time, end time, optional location, optional role/type (e.g., cashier, barista).                                                                                                                                              |
| FR-11 | Managers can assign one or more staff to a shift.                                                                                                                                                                                                                         |
| FR-12 | System validates: no overlapping shifts for the same staff member; shifts do not conflict with approved time-off. **Overlap rule:** two shifts overlap when `new_start < existing_end AND new_end > existing_start` for the same `user_id` within the same `business_id`. |
| FR-13 | Managers can view shifts by day, week, and month (calendar view).                                                                                                                                                                                                         |
| FR-14 | Staff can view their upcoming shifts and an optional list of open (unassigned) shifts.                                                                                                                                                                                    |

### 5.4 Shift Swap Marketplace

| ID    | Requirement                                                                                                                                                                                                                                          |
| ----- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-15 | Staff can offer one of their shifts for swap (select shift + optional note). Swap status set to `offered`.                                                                                                                                           |
| FR-16 | Other staff can browse offered shifts and request to take one. Multiple workers may offer different assignments on the same shift.                                                                                                                   |
| FR-17 | When a staff member requests an offered shift, a `ShiftSwap` record is created with status `pending_approval`. Only one request may be pending for a given offered assignment; competing requests are cancelled and the affected staff are notified. |
| FR-18 | Manager can view pending swap requests and approve or reject each.                                                                                                                                                                                   |
| FR-19 | On approval: original staff `shift_staff.status` set to `removed`; new staff assigned; both users notified.                                                                                                                                          |
| FR-20 | On rejection: swap reverts to `offered` state; requesting staff notified.                                                                                                                                                                            |
| FR-21 | Staff can cancel their own offer while status is `offered`. Swap status set to `cancelled`.                                                                                                                                                          |
| FR-22 | System prevents swaps that create overlapping shifts or conflict with approved time-off (same rule as FR-12).                                                                                                                                        |

**Swap status machine:**

```
offered → pending_approval   (another staff requests it)
offered → cancelled          (original staff withdraws the offer)
pending_approval → approved  (manager approves)
pending_approval → offered   (manager rejects; swap becomes available again)
pending_approval → cancelled (another request for the same assignment was accepted)
```

A manager rejection reverts the offer to `offered`; automatic cancellation of a competing request is terminal for that request and notifies the affected staff member.

### 5.5 Time-Off Requests

| ID    | Requirement                                                                                                                  |
| ----- | ---------------------------------------------------------------------------------------------------------------------------- |
| FR-23 | Staff can submit time-off requests: start date, end date, optional reason. Initial status: `pending`.                        |
| FR-24 | Manager can view pending requests and approve or reject with optional comment.                                               |
| FR-25 | System flags conflicts: if time-off overlaps with existing assigned shifts, a warning is shown to the manager during review. |
| FR-26 | Notifications sent on: time-off submitted (to manager), time-off approved/rejected (to staff).                               |

### 5.6 Notifications

| ID    | Requirement                                                                                                                                               |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-27 | Queued email notifications for: new shift assigned; shift offered for swap; swap requested; swap approved/rejected; time-off submitted/approved/rejected. |
| FR-28 | Optional in-app notifications: simple list of recent events; mark as read.                                                                                |

### 5.7 Dashboard & Basic Reporting

| ID    | Requirement                                                                                                                                 |
| ----- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| FR-29 | Manager/Owner dashboard: upcoming shifts (next 7 days), count of pending swap requests, count of pending time-off requests.                 |
| FR-30 | Staff dashboard: their upcoming shifts (next 7–14 days), status of pending time-off requests, status of swap requests they are involved in. |

---

## 6. Non-Functional Requirements

### 6.1 Performance

| ID    | Requirement                                                                                                                             |
| ----- | --------------------------------------------------------------------------------------------------------------------------------------- |
| NFR-1 | Typical pages load in < 2 seconds on standard broadband.                                                                                |
| NFR-2 | Calendar views for up to 30 staff and 31 days remain responsive.                                                                        |
| NFR-3 | Dashboard summary queries use DB-level aggregation (not PHP-level counting). Eager loading enforced — no N+1 queries on shift listings. |

### 6.2 Security

| ID    | Requirement                                                                                                              |
| ----- | ------------------------------------------------------------------------------------------------------------------------ |
| NFR-4 | Passwords hashed using Laravel's default (bcrypt/argon2).                                                                |
| NFR-5 | Bearer tokens sent via the `Authorization` header (not cookies), which avoids CSRF exposure for state-changing requests. |
| NFR-6 | Authorization enforced via Laravel policies/gates; no IDOR vulnerabilities.                                              |
| NFR-7 | HTTPS enforced in production (via ALB or reverse proxy with cert).                                                       |
| NFR-8 | Login endpoint rate-limited using Laravel's `ThrottleRequests` middleware (e.g., 5 attempts/minute per IP).              |

### 6.3 Testing

| ID     | Requirement                                                                                                                                               |
| ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| NFR-9  | Feature tests written with **Pest** covering all critical workflows: auth, shift CRUD, swap state transitions, time-off approval, and conflict detection. |
| NFR-10 | Each swap state transition (FR-15 → FR-21) must have a corresponding test asserting the correct status, DB state, and notification dispatch.              |
| NFR-11 | Conflict detection logic (FR-12, FR-22) must be unit-tested with edge cases: exact boundary times, same-day overlap, time-off boundary.                   |
| NFR-12 | Tests run in CI (GitHub Actions) on every push. Pipeline fails on test failure — no merging broken code.                                                  |
| NFR-13 | Use `RefreshDatabase` + factories for test isolation. No shared mutable state between tests.                                                              |

> No tests = junior signal to overseas employers. Pest is preferred over PHPUnit for readability; the syntax reads closer to plain English and is easier to walk through in a code review.

### 6.4 Reliability

| ID     | Requirement                                                                   |
| ------ | ----------------------------------------------------------------------------- |
| NFR-14 | Automated daily database backups configured on RDS.                           |
| NFR-15 | Application logs stored and accessible (CloudWatch Logs or log files on EC2). |

### 6.5 Maintainability

| ID     | Requirement                                                                                                                                                        |
| ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| NFR-16 | Code follows Laravel conventions: Controllers, Form Requests, Models, Policies, Service classes where appropriate. Minimal logic in controllers.                   |
| NFR-17 | Vue 3 frontend uses Composables and/or Pinia for shared state; reusable components for forms, tables, and modals.                                                  |
| NFR-18 | README includes: setup instructions, architecture overview, AWS services used and rationale, link to Postman collection or OpenAPI spec, and architecture diagram. |

**API Design Standards (NFR-19)**

All API endpoints must follow these conventions:

- **Resource naming:** plural nouns, nested where ownership is implied.
    ```
    GET    /api/businesses/{business}/shifts
    POST   /api/businesses/{business}/shifts
    PATCH  /api/businesses/{business}/shifts/{shift}
    DELETE /api/businesses/{business}/shifts/{shift}

    POST   /api/businesses/{business}/shift-swaps/{swap}/approve
    POST   /api/businesses/{business}/shift-swaps/{swap}/reject
    ```
- **HTTP status codes used correctly:**
    - `200` — successful read/update
    - `201` — resource created (return created resource)
    - `204` — successful delete (no body)
    - `422` — validation error (Laravel default)
    - `403` — unauthorized action (Policy denial)
    - `404` — resource not found
- **Consistent error envelope:**
    ```json
    {
        "message": "Human-readable error",
        "errors": {
            "field": ["Validation message"]
        }
    }
    ```
- **Consistent success envelope** (optional but recommended):
    ```json
    {
        "data": {}
    }
    ```
    Use Laravel API Resources (`php artisan make:resource`) for all responses — never return raw Eloquent models.

**Authorization: Laravel Policies (NFR-20)**

- Every model action (view, create, update, delete, approve) must be gated via a **Policy class**, not inline `if` checks or raw middleware.
- Controllers stay thin — call `$this->authorize()` and delegate to the Policy.
- Example: `ShiftSwapPolicy::approve()` checks that the authenticated user is a manager/owner of the same business as the swap.
- Policies are unit-testable in isolation — test the policy directly, not just the endpoint.

**Queue Usage: Jobs not inline Mail (NFR-21)**

- All notifications must be dispatched as **queued Jobs** or **queued Notifiables** — never `Mail::send()` or `Notification::send()` inline in a controller.
- Each notification event maps to a dedicated Job class:
    ```
    App\Jobs\SendShiftAssignedNotification
    App\Jobs\SendSwapApprovedNotification
    App\Jobs\SendTimeOffStatusNotification
    ```
- Jobs must implement `ShouldQueue` and be dispatchable independently for testing.
- Use `Queue::fake()` in tests to assert jobs are dispatched without actually sending email.

### 6.6 Scalability (Conceptual)

| ID     | Requirement                                                                                                                                                                                                                         |
| ------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| NFR-22 | Architecture is describable as scalable: stateless app servers (EC2/ECS), managed DB (RDS), static assets on S3 (+ CloudFront optional), queues for async work. Auto-scaling is not required to implement, but must be explainable. |

---

## 7. Data Model

### businesses

| Column     | Type          | Notes               |
| ---------- | ------------- | ------------------- |
| id         | bigint PK     |                     |
| name       | string        |                     |
| slug       | string unique | URL-safe identifier |
| created_at | timestamp     |                     |
| updated_at | timestamp     |                     |

### users

| Column     | Type          | Notes         |
| ---------- | ------------- | ------------- |
| id         | bigint PK     |               |
| name       | string        |               |
| email      | string unique |               |
| password   | string        | bcrypt/argon2 |
| created_at | timestamp     |               |
| updated_at | timestamp     |               |

### business_user (pivot)

| Column      | Type            | Notes                   |
| ----------- | --------------- | ----------------------- |
| id          | bigint PK       |                         |
| business_id | FK → businesses |                         |
| user_id     | FK → users      |                         |
| role        | enum            | owner / manager / staff |

### locations (optional)

| Column      | Type            | Notes |
| ----------- | --------------- | ----- |
| id          | bigint PK       |       |
| business_id | FK → businesses |       |
| name        | string          |       |
| address     | string nullable |       |

### shifts

| Column      | Type                    | Notes                  |
| ----------- | ----------------------- | ---------------------- |
| id          | bigint PK               |                        |
| business_id | FK → businesses         |                        |
| location_id | FK → locations nullable |                        |
| start_at    | datetime                |                        |
| end_at      | datetime                |                        |
| role_label  | string nullable         | e.g., cashier, barista |
| created_by  | FK → users              |                        |
| created_at  | timestamp               |                        |
| updated_at  | timestamp               |                        |

### shift_staff

| Column     | Type        | Notes              |
| ---------- | ----------- | ------------------ |
| id         | bigint PK   |                    |
| shift_id   | FK → shifts |                    |
| user_id    | FK → users  |                    |
| status     | enum        | assigned / removed |
| created_at | timestamp   |                    |
| updated_at | timestamp   |                    |

> `status` is required to preserve assignment history after a swap approval (FR-19). Use soft removal, not hard delete.

### shift_swaps

| Column        | Type                | Notes                                                        |
| ------------- | ------------------- | ------------------------------------------------------------ |
| id            | bigint PK           |                                                              |
| business_id   | FK → businesses     |                                                              |
| shift_id      | FK → shifts         |                                                              |
| offered_by    | FK → users          |                                                              |
| requested_by  | FK → users nullable | null until someone requests                                  |
| status        | enum                | offered / pending_approval / approved / rejected / cancelled |
| manager_notes | text nullable       |                                                              |
| created_at    | timestamp           |                                                              |
| updated_at    | timestamp           |                                                              |

### time_off_requests

| Column        | Type            | Notes                         |
| ------------- | --------------- | ----------------------------- |
| id            | bigint PK       |                               |
| business_id   | FK → businesses |                               |
| user_id       | FK → users      |                               |
| start_date    | date            |                               |
| end_date      | date            |                               |
| reason        | text nullable   |                               |
| status        | enum            | pending / approved / rejected |
| manager_notes | text nullable   |                               |
| created_at    | timestamp       |                               |
| updated_at    | timestamp       |                               |

### notifications (optional, in-app)

> Use Laravel's built-in `php artisan notifications:table` — this generates the standard `notifications` table with `id`, `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), `read_at`, `created_at`. Do not hand-roll this table.

---

## 8. UX & UI Guidelines

### 8.1 General

- Clean, simple UI; no need for pixel-perfect design
- Responsive layout (mobile-friendly)
- Consistent component library (Tailwind CSS + Headless UI or similar)

### 8.2 Key Screens

**Auth**

- Login
- Register (creates account + new business)
- Invitation acceptance page

**Manager / Owner**

- Dashboard (upcoming shifts, pending swap count, pending time-off count)
- Calendar view (day / week / month)
- Shift form (create / edit)
- Swap requests list + detail + approve/reject action
- Time-off requests list + detail + approve/reject action
- Staff management (list, invite, edit role)

**Staff**

- Dashboard (my shifts, pending request statuses)
- My shifts (calendar + list)
- Offer shift for swap (form/modal)
- Browse offered shifts + request to take
- Time-off request form + history list

---

## 9. AWS Architecture

### Compute

- **EC2** (Amazon Linux 2) or **ECS Fargate** running:
    - PHP 8.x + Laravel
    - Nginx
    - Queue worker (`php artisan queue:work`)

### Database

- **RDS** (MySQL 8 or Aurora MySQL)
- Security group: accessible only from app server security group

### Storage

- **S3** bucket for:
    - Schedule exports (CSV/PDF)
    - Optional: staff documents

### Queues

- **SQS** (AWS-native, preferred) or database driver (simpler for initial deploy)
- Laravel queue worker processes jobs asynchronously

### Networking & Security

- **VPC** with public subnet (ALB, NAT) and private subnet (app servers, RDS)
- **Security groups:**
    - ALB: allows 80/443 from internet
    - App servers: allows traffic from ALB only
    - RDS: allows MySQL port from app servers only
- **IAM roles:** EC2/ECS role with least-privilege permissions to S3, SQS, CloudWatch Logs

### CI/CD

- GitHub Actions pipeline:
    1. Run tests
    2. SSH to EC2 / trigger ECS deploy
    3. Pull latest code
    4. Run `composer install --no-dev`
    5. Run `php artisan migrate --force`
    6. Restart queue workers

> You should be able to draw and explain this architecture in an interview, including how you would add auto-scaling (ECS Fargate + ALB target tracking) and a CDN (CloudFront in front of S3 and ALB).

---

## 10. Success Metrics

Since this is a portfolio project, success is defined by:

**Completeness**

- All MVP features implemented and working
- Deployed and accessible via a public URL

**1. Tests (Pest)**

- Feature tests cover: auth, shift CRUD, all swap state transitions, time-off approval, conflict detection edge cases
- Tests run in CI and pass on every push
- `Queue::fake()` used to assert notification jobs are dispatched
- _Signal to employer: you write testable code, not just working code_

**2. API Design**

- All endpoints follow RESTful resource naming conventions
- HTTP status codes used correctly and consistently
- All responses go through Laravel API Resources — no raw model serialization
- Consistent error envelope on validation and authorization failures
- _Signal to employer: you understand API contracts, not just routing_

**3. Authorization via Policies**

- Every guarded action uses a Laravel Policy class
- Controllers call `$this->authorize()` — no business logic in middleware
- Policies are tested in isolation
- _Signal to employer: you understand authorization architecture, not just authentication_

**4. Queue Jobs**

- All email/notification logic is dispatched as a queued Job implementing `ShouldQueue`
- No inline `Mail::send()` in controllers
- Jobs are named, single-responsibility, and independently testable
- _Signal to employer: you understand async systems and don't block the request cycle_

**5. README + Architecture Diagram**

- README covers: local setup, env vars, architecture overview, AWS services and why each was chosen
- Architecture diagram (draw.io or Excalidraw export) committed to repo
- Postman collection or OpenAPI spec linked
- _Signal to employer: you can communicate systems, not just build them_

**Demonstrable skills**

- Can explain:
    - Multi-tenancy design and tenant isolation
    - Shift swap state machine and conflict detection logic
    - AWS architecture and service choices
    - Why queues, why policies, why API Resources
- Can walk through:
    - Key API endpoints and their auth/authorization flow
    - Key Vue components and state management
    - Deployment process end-to-end

**Portfolio impact**

- Stands out vs. generic CRUD apps
- Clearly communicates: _"I can design and build real SaaS-like systems"_

---

## 12. Clarified Product Decisions

These decisions refine the MVP behavior described above:

- ShiftSwap supports multiple independent business tenants. During the MVP, a user may belong to only one business; multi-business membership and switching remain future capabilities.
- A business may operate multiple locations. Shift conflicts, staff hours, availability, and staffing warnings are evaluated across all locations belonging to that business.
- Owners and managers control the official schedule. Staff may offer or request swaps, but manager approval is required before assignments change. Owners and managers may also swap shifts when they are assigned workers.
- A shift may have multiple offers for different assignments, but only one request may be pending for a given offered assignment. When one request is accepted for processing, competing requests are cancelled and the affected staff are notified.
- Staff availability and minimum/maximum hours are customizable guidance, including reusable templates and exceptions. Violations produce warnings, not blocks; owners and managers have the final decision and may override them.
- Owners and managers may configure minimum personnel requirements by location, role, and time window. Coverage violations produce warnings only; the manager remains responsible for the decision.
- In the MVP, an invitation to a user who already belongs to another business is rejected. Only owners may invite or promote managers; owners and managers may invite staff.
- Ownership transfer requires confirmation by an active member of the same business and is atomic. The successor becomes owner before the former owner leaves or becomes a manager.
- Separate businesses never see each other's schedules. Future cross-business conflicts notify only the staff member generically; the system does not block or resolve them.
- Shift datetimes are stored in UTC and interpreted using each business's timezone, defaulting to `Asia/Manila`, for date ranges, overnight shifts, time-off, availability, and staffing warnings.

## 11. Future Enhancements (Post-MVP)

Not required for the portfolio version, but worth mentioning as next steps:

- **Billing:** Stripe integration with per-business subscription plans
- **DOLE compliance:** rest day rules, overtime calculations
- **Time clock:** GPS-validated clock-in/out
- **Exports:** Auto-generate schedule PDF/CSV to S3 on publish
- **Analytics:** Hours per staff, overtime trends, coverage gaps
- **Mobile app:** React Native or Flutter reusing the same Laravel API
- **Advanced scheduling:** Recurring shifts, shift templates, auto-fill open shifts
