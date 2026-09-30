# ShiftSwap Project Memory

## Project

ShiftSwap is a portfolio-grade multi-tenant SaaS for small shift-based businesses. The current implementation contract is `docs/SPEC.md`, derived from `docs/ShiftSwap-PRD.md`.

## Current MVP scope

- Laravel API with Vue frontend, role-based business scheduling, shift swaps, time-off requests, queued notifications, and AWS-oriented deployment.
- Sprint 1 plan: `docs/SPRINTS.md` — foundation plus authentication, tenancy, and authorization vertical slice.
- Active work: Building the owner registration slice (`FR-1.1`, `FR-1.2`, `FR-2.1`) outside-in via TDD. Initial Pest feature test created in `tests/Feature/Auth/RegistrationTest.php`. Next step is implementing `routes/api.php` route and `RegisterController`.
- The product supports multiple independent business tenants.
- MVP users—including owners, managers, and staff—belong to one business only. Multi-business membership and switching are future capabilities.
- A business may have multiple locations. Assignments, hours, availability, staffing warnings, and conflict checks apply across all locations within the same business.
- Availability and minimum/maximum hours are customizable soft targets. Violations warn but do not block owner/manager decisions.
- Minimum personnel requirements per location, role, and time window are part of the MVP and produce warnings only.
- Business timezone defaults to `Asia/Manila` and governs date filters, overnight shifts, time-off, availability, and staffing windows while shift datetimes are stored in UTC.

## Key product rule

The business owns the official schedule. Staff may offer or request swaps, but a swap changes the assignment only after manager approval. Multiple offers are allowed for different assignments, but competing requests for the same offered assignment are cancelled and notified when one is accepted.

## Scheduling rules

- Availability templates, date exceptions, and soft minimum/maximum hours are customizable per business. Violations warn but do not block owner/manager decisions.
- Minimum personnel requirements per location, role, and time window are part of the MVP and produce warnings only; the manager has final responsibility.
- Business timezone defaults to `Asia/Manila`; UTC shift storage is interpreted using the business timezone for date filters, overnight shifts, time-off, availability, and staffing windows.

## Tenant privacy

Separate businesses must not see or infer each other's schedules, locations, managers, or assignments. If future multi-business membership creates a cross-business conflict, notify only the staff member generically; do not block or resolve it automatically. The staff member is responsible for resolving the conflict.

## References

- Product requirements: `docs/ShiftSwap-PRD.md`
- Technical specification: `docs/SPEC.md`
- Canonical data models: `docs/DATA-MODELS.md`
- Detailed decisions: `docs/ai/decisions.md`

## Development workflow

- Local development database uses PostgreSQL through `compose.yaml` and `podman-compose`.
- Compose uses the fully qualified image `docker.io/library/postgres:16.4-alpine`, binds `127.0.0.1:5432`, and persists data in the `shiftswap_postgres_data` volume.
- Laravel local `.env` uses `DB_CONNECTION=pgsql`; PHPUnit remains isolated on SQLite `:memory:`.
- The reusable global Kiro validator is `~/.kiro/hooks/validate-project.sh`, configured by `~/.kiro/agents/global-project-validation.json` for Laravel, Node, Python, Rust, Go, and Java/Spring projects.
