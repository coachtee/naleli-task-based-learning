# KCS Education Backend — working notes

Laravel 13 · PHP 8.3+ · MySQL/MariaDB · Filament · deployed to a plain VPS.

## Non-negotiables

- **No new infrastructure.** No Docker, Redis, microservices or queue daemons
  beyond the database driver. If a change needs new infrastructure, raise it
  rather than adding it.
- **Money is integer cents.** No floats. Format only at the edges.
- **Statuses are PHP backed enums** in `app/Enums`, cast on the model. Columns
  are `varchar`, not SQL `ENUM`, so adding a state is a code change rather than
  a table rebuild — and the schema is identical on MySQL and SQLite.
- **Only `LearnerRegistry` allocates `learner_ref`.** Nothing else writes it.
  "One learner, one ID for life" is enforced there or not at all.
- **`EnrolmentActivator::settle()` is the only path to activation.** It is
  idempotent and atomic; keep it that way. Every payment route ends there.
- **The Android app can never write competence, entitlement or a certificate.**
  It reports what the learner did; the backend decides what that counts for.

## Testing

`php artisan test`. Local and CI run SQLite; production is MariaDB. Anything
relying on row-level locking (`lockForUpdate`) is a no-op on SQLite and needs
verifying on MariaDB in staging — see `LearnerRegistry::allocateReference()`.

## Phase

Phase 1: applications in from the website, learner records, payments by manual
confirmation, enrolments, tokens, entitlements, Filament dashboard. Pay@ Go is
live on top of that — see `docs/PAYMENTS-PAYAT.md`; its callback is unsigned,
so settlement is always decided by reading the reference back from Pay@ and
never from the callback body.
Not yet: any Android app change, assessment, moderation, certificates. Do not
build ahead of the phase.
