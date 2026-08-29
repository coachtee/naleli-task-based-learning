# KCS Education Backend

The central learner and education backend behind **kcs.edu.za** and the
**Naleli Workspace** Android app. One learner, one permanent reference, one
backend, many programmes over time.

## Where this sits

```
kcs.edu.za  ──(Fluent Forms webhook)──▶  THIS  ◀──(REST, Phase 3)──  Naleli Workspace
   applications                       backend                        Android app
```

The backend is the source of truth for learners, applications, enrolments,
payments, programmes, competence and certificates. The website keeps its
existing application form; the app keeps working offline.

## Requirements

- PHP 8.3+ (the KCS host runs 8.3.33; developed against 8.4)
- MySQL 8 or MariaDB 10.6+ (the KCS host runs MariaDB 11.4)
- Composer

No Docker, no Redis, no queue worker infrastructure. Queues use the database
driver; cache and sessions use files. A normal VPS or cPanel account with
shell access and one cron entry is the whole deployment target.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan test
```

Local development defaults to SQLite so the suite runs without a database
server. Production is MySQL/MariaDB — see `.env.example`.

## Phase 1 scope

Built: learners and permanent references, programmes and intakes,
applications, enrolments, invoices and payments with manual confirmation,
access tokens, entitlements, the Fluent Forms webhook, and a Filament
dashboard.

Not built yet, deliberately: live payment gateways (Phase 2), any change to
the Android app (Phase 3), assessment and moderation (Phase 4), certificates
(Phase 5). See `docs/API-CONTRACT.md` for the contract the app will use.

## Conventions

- **Money is integer cents.** No floats, formatting only at the edges.
- **Statuses are PHP backed enums** cast on the model. Columns are `varchar`
  rather than SQL `ENUM` so adding a state is a code change, not a table
  rebuild — and so the schema is identical on MySQL and SQLite.
- **Learner references are allocated only by `LearnerRegistry`.** Nothing else
  writes `learner_ref`.
- **The app can never write competence, entitlement or a certificate.**
