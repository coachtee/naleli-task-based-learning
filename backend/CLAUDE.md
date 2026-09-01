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
  On `learner_submissions` that is held structurally: `result`, `assessed_at`,
  `assessed_by` and `feedback` are left out of `ProgressSynchroniser`'s upsert
  column list, so no request shape can move them.
- **The learner account owns the learning record.** A phone and a lab PC are
  working copies of it, never the original. Sync merges — completion is a
  ratchet, evidence is idempotent on `client_evidence_id`, nothing is
  last-write-wins. `docs/API-CONTRACT.md` has the rules and why each one is
  the way it is; changing one of them changes whether a learner can lose an
  afternoon's work.

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

Phase 3 (the learning record) is now built server-side: `/api/v1/me/progress`
and `/api/v1/me/evidence`. No client calls it yet. It was brought forward
deliberately — both the Android app and the planned desktop PWA need exactly
these endpoints, so building them first means the client decision cannot be
made wrong.

Not yet: any Android app change, assessment, moderation, certificates. Do not
build ahead of the phase.

Known gap: no programme in the catalogue sets `content_code`, so the field the
client uses to pick a content pack comes back null. `CatalogueManifest` is
where that would be fixed.
