# ShiftSwap Project Memory

## Project

ShiftSwap is a portfolio-grade multi-tenant SaaS for small shift-based businesses. The current implementation contract is `docs/SPEC.md`, derived from `docs/ShiftSwap-PRD.md`.

## Current MVP scope

- Laravel API with Vue frontend, role-based business scheduling, shift swaps, time-off requests, queued notifications, and AWS-oriented deployment.
- Sprint 1 plan: `docs/SPRINTS.md` — foundation plus authentication, tenancy, and authorization vertical slice.
- Completed:
  - Sprint 1 is 100% complete! Foundation, registration (`FR-1.1`, `FR-1.2`, `FR-2.1`), tenant context & authorization (`FR-7.1`, `FR-8.1`), authentication & token management (`FR-9.1`–`FR-12.1`), roles & permissions policy slice (`FR-13.1`–`FR-16.1`), and project validation hook (`SETUP-5`) are fully implemented and verified via TDD.
  - Sprint 2 is 100% complete! Staff invitations (`FR-3.1`–`FR-6.1`), location management (`FR-16A.1`), shift schema & models (`SETUP-7`), shift CRUD (`FR-17.1`, `FR-18.1`, `FR-22.1`, `FR-23.1`), and shift overlap conflict detection & staff assignment (`FR-19.1`, `FR-20.1`, `FR-21`) are fully implemented and verified.
  - Sprint 3 is 100% complete! Shift Swap Marketplace (`FR-24`–`FR-33`), Time-Off Requests with conflict detection (`FR-34`–`FR-39`), Queued Notifications & In-App Feed (`FR-40`–`FR-48`), Dashboards (`FR-49`–`FR-50`), and Business Ownership Transfer (`SPEC 5.2`) are fully implemented and verified.
  - Fullstack Integration & Hardening:
    - Added fullstack `compose.yaml` orchestrating PostgreSQL 17, Laravel 11 API, and Vue 3 frontend with hot-reload volume mapping.
    - Added Shift Swap Claimant Withdrawal (`POST /shift-swaps/{swap}/withdraw`) enabling staff to cancel claims prior to manager review, safely returning the shift to open marketplace.
    - Hardened concurrency using `lockForUpdate()` in swap approvals and claim processing, preventing race conditions and double bookings.
    - Added rate limiting for public token acceptance endpoints (`invitation-accept`, `ownership-accept`).
    - All 127 Pest feature/unit tests pass (654 assertions) with clean Pint code style.
- Reconciled: PRD v1.5, SPEC v1.7, and DATA-MODELS v1.1 aligned. Standardized on PostgreSQL 17, single-requester swap lock, and shifted availability/staffing configuration to Post-MVP (BL-3 & BL-4).
- Active work / Next steps: Sprints 1, 2, and 3 backend slices are 100% complete. Ready for next phases (e.g. frontend integration, deployment pipeline, or Post-MVP backlog).
- The product supports multiple independent business tenants.
- MVP users—including owners, managers, and staff—belong to one business only. Multi-business membership and switching are future capabilities.
- A business may have multiple locations. Assignments, hours, and conflict checks apply across all locations within the same business.
- Business timezone defaults to `Asia/Manila` and governs date filters, overnight shifts, time-off, and staffing windows while shift datetimes are stored in UTC.

## Key product rule

The business owns the official schedule. Staff may offer or request swaps, but a swap changes the assignment only after manager approval. Multiple offers are allowed for different assignments on the same shift; only one request is pending per offered assignment (first request transitions to `pending_approval`; subsequent requests return 422).

## Scheduling rules

- Conflict detection checks shift assignment overlap (`new_start < existing_end AND new_end > existing_start`) and approved time-off.
- Business timezone defaults to `Asia/Manila`; UTC shift storage is interpreted using the business timezone for date filters, overnight shifts, and time-off boundaries.
- Reusable availability templates, date exceptions, soft hours targets, and minimum staffing requirements are Post-MVP backlog items (BL-3 & BL-4).

## Tenant privacy

Separate businesses must not see or infer each other's schedules, locations, managers, or assignments. If future multi-business membership creates a cross-business conflict, notify only the staff member generically; do not block or resolve it automatically. The staff member is responsible for resolving the conflict.

## References

- Product requirements: `docs/ShiftSwap-PRD.md`
- Technical specification: `docs/SPEC.md`
- Canonical data models: `docs/DATA-MODELS.md`
- Detailed decisions: `docs/ai/decisions.md`

## Development workflow

- Local development database uses PostgreSQL through `compose.yaml` and `podman-compose`.
- Compose uses the fully qualified image `docker.io/library/postgres:17-alpine`, binds `127.0.0.1:5432`, and persists data in the `shiftswap_postgres_data` volume.
- Laravel local `.env` uses `DB_CONNECTION=pgsql`; PHPUnit remains isolated on SQLite `:memory:`.
- The reusable global Kiro validator is `~/.kiro/hooks/validate-project.sh`, configured by `~/.kiro/agents/global-project-validation.json` for Laravel, Node, Python, Rust, Go, and Java/Spring projects.
