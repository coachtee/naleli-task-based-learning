# The learner workspace (`/workspace`)

The same work as the Android app, in a browser, installable on a Windows PC.

## Why a web app and not an `.exe`

A lab PC cannot install an APK, and a Windows installer needs a code-signing
certificate at roughly R4,000–8,000 a year or every learner meets a SmartScreen
warning on first run. An installable web app avoids that entirely: Edge is
already on every machine, **Install this site as an app** gives it a Start-menu
entry and its own window, and shipping an update is a deploy rather than a
visit to thirty computers.

If the lab is ever domain-joined, Edge's `WebAppInstallForceList` group policy
installs it on every PC silently, at no cost.

## The rotation it is built for

Three classes a day on one machine, every day:

```
08:00  Naledi signs in  → her work        → logs out
10:00  Sipho signs in   → his work        → logs out
12:00  Lerato signs in  → her work        → logs out
```

The account owns the work; the PC is a desk. So the design rules are:

- **Signing in pulls the learner's record** from the server and shows it.
- **Every tick is sent immediately**, not at logout. The most a machine ever
  holds on its own is a few seconds of work.
- **The screen always says where the work is** — `Saved`, `Saving…`, or
  `3 changes waiting · offline`.
- **Logout will not lose anything.** Empty queue: go. Queue and a connection:
  send it first, then go. Queue and no connection: say so, and offer to stay
  signed in until the line returns, or to save a copy to a file. There is no
  quiet third option.
- **Idle for 20 minutes signs the learner out** after draining, so a learner
  who walks off does not leave the seat warm for the next one.

## Where a learner's data lives, and does not

| | Where | Cleared |
|---|---|---|
| Session token | `sessionStorage` | On logout, and when the window closes |
| Pending changes | IndexedDB, keyed `learner_ref:programme` | When the server confirms them |
| Last known record | IndexedDB, same key | Overwritten on each sync |
| Course content | IndexedDB + service worker cache | Shared, not personal |

The service worker **never** caches anything under `/api/v1/me/`. That is one
learner's work on a machine three learners a day use, and a cache the next page
could read is exactly the leak the login exists to prevent.

`navigator.storage.persist()` is requested on load. On an installed app
Chromium grants it without prompting; without it the browser may drop a
learner's queued work when the disk fills, which is the one failure that would
end all trust in the thing.

## Deploying it

The application's front controller lives in `public_html/admin`, so the
workspace answers at `kcs.edu.za/admin/workspace/` — which reads like a staff
area to a learner. The website rewrites it, exactly as it already rewrites
`/my/...` for profile links:

```apache
RewriteRule ^workspace(/.*)?$ /admin/workspace$1 [L]
```

Everything the app needs is under that one path, and a service worker only
controls pages inside its own scope, so `/workspace/sw.js` controls the app and
nothing else on the domain.

Content packs are read from `SYNC_CONTENT_PATH` (see `config/sync.php`). The
repository keeps them in `content/`; a deployment copies that directory beside
the application.

## Trying it

```bash
php artisan migrate --seed
php artisan lab:demo-learners     # prints three student numbers and PINs
php artisan serve
```

Then open `/workspace` and sign in as one of them. `tests/Browser/rotation.mjs`
drives the whole rotation — three learners, a dropped line, a blocked logout,
and the same learner on a second machine — in a real browser.

## Not built yet

Evidence upload and written answers from the browser (the endpoints exist —
`POST /api/v1/me/evidence` — the screens do not), and reading lesson text. The
shell proves the part that could lose a learner's work; the rest is screens on
top of a record that already syncs.
