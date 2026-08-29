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

## Run it locally

`.env.example` targets MySQL, because that is what production is. To just
look at the dashboard you do not need a database server — point it at SQLite:

```bash
composer install
cp .env.example .env
php artisan key:generate

# Two lines make it run with no database server at all.
sed -i 's/^DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
touch database/database.sqlite

php artisan migrate --seed
php artisan db:seed --class=DemoDataSeeder   # optional: a sample intake
php artisan serve
```

Then open **http://127.0.0.1:8000/admin** and sign in with
`admin@kcs.edu.za` / `password`.

**Change that password before this is reachable by anyone else.** The seeded
account exists so a fresh clone is openable; it is not a production
credential, and `AdminUserSeeder` is not run in production.

For MySQL, leave `.env` as it comes, create the database and user named in
it, and skip the two SQLite lines. The test suite always runs on SQLite in
memory regardless — see `phpunit.xml`.

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
