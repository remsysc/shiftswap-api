# Product Decisions

## Multi-tenancy and membership

- ShiftSwap is a multi-tenant product supporting multiple independent businesses.
- The MVP supports one business membership per user. This applies to owners, managers, and staff; multi-business membership and switching are future features.
- A business can have multiple locations. Same-business conflicts are checked across all locations because they share a tenant.

## Scheduling and swaps

- Owners/managers create and assign the official schedule.
- Staff cannot self-assign normal shifts.
- Staff can offer an assigned shift for swap or request an offered shift, but manager approval is required before assignments change.
- The MVP does not enforce minimum swap lead time. A shift may be offered before it starts, subject to past-shift and conflict rules.
- Configurable minimum swap lead time is a post-MVP backlog item, not an MVP requirement.

## Cross-business privacy and conflicts

- Separate businesses must not see each other's schedules, locations, managers, or assignments.
- In a future multi-business scenario, each business sees only its own tenant data and may schedule based on that data.
- If schedules conflict across businesses, only the staff member receives a generic conflict notice. The system must not reveal the other business, block the assignment, or resolve the conflict automatically.
- The staff member is responsible for resolving cross-business conflicts through a swap, schedule discussion, or another arrangement.

## Ownership

- An owner must transfer ownership to another business member before leaving. Ownership transfer must be atomic, and a business must never be left without an owner.

## Source

These decisions are reflected in `docs/SPEC.md` version 1.5 and `docs/ShiftSwap-PRD.md` version 1.3.

## Finalized MVP decisions

- Multiple offers are allowed for different assignments on the same shift, but only one request may be pending per offered assignment. Competing requests are set to `cancelled` with a reason and the affected staff are notified.
- Owners and managers may invite staff; only owners may invite or promote managers. MVP invitations are rejected when the user already belongs to another business. Resending replaces the old pending token.
- Ownership transfer requires an active member of the same business, successor confirmation, and an atomic transfer; the former owner becomes a manager or leaves after transfer.
- Owners/managers may swap assigned shifts. Availability templates, date exceptions, and soft minimum/maximum hours are customizable; violations warn but do not block scheduling. Minimum staffing requirements are MVP warnings only, with the manager retaining final responsibility.
- Business timezones default to `Asia/Manila`; UTC storage is interpreted using the business timezone for date and coverage rules.

These decisions are reflected in `docs/SPEC.md` v1.5 and `docs/ShiftSwap-PRD.md` v1.3.

## Documentation structure

- `docs/DATA-MODELS.md` is the canonical modular data-model reference. `docs/SPEC.md` links to it rather than duplicating the schema.

## Local development and validation

- Use Podman Compose for the local PostgreSQL database. The canonical image is `docker.io/library/postgres:16.4-alpine`; local credentials and database settings are in `.env`/`.env.example`.
- Keep PHPUnit on SQLite `:memory:` for isolated tests; local application migrations and manual connectivity use PostgreSQL.
- A reusable global Kiro stop hook lives at `~/.kiro/agents/global-project-validation.json` and invokes `~/.kiro/hooks/validate-project.sh`. It detects common project types and runs targeted/full tests plus configured checks, including Java/Spring Maven or Gradle support.
