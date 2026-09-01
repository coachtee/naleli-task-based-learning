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

Then open `/workspace` and sign in as one of them. Add `--reset` to
`lab:demo-learners` to clear the work those three have already done, so a UAT
run starts from a first morning.

`tests/Browser/uat.mjs` drives the whole product in a real browser: reading the
lesson, a blocked hand-in, ticking the steps, typing an answer, attaching a
file while the line is down, watching it upload itself when the line returns,
handing in, the next class finding a clean seat, and the same learner picking
up on a different computer.

## What a learner can do

Four tabs on every task:

| Tab | What it holds |
|---|---|
| **Learn** | The lesson: what they are doing, why it matters, understand / practise / do it for real, what must be handed in, and self-check questions |
| **Your steps** | The sub-step checklist. Locked once the task is handed in |
| **Evidence** | Type a written answer (saved as `text/plain`, same path as a photo) or attach a file up to 25 MB. Both queue offline as Blobs in IndexedDB |
| **Hand in** | The assessment criteria up front, a 1–5 confidence rating, and the submit button — disabled, with the reason in words, until the steps are ticked and evidence is attached |

Handing in is blocked rather than warned about. "You cannot hand in yet: 2 of
your 4 steps are not ticked, and you have not attached any evidence" is
something a learner can act on; a rejected submission an hour later is not.

Results come back as **Competent** or **Not yet competent** with the assessor's
reasons. No percentage appears anywhere — `tests/Browser/uat.mjs` asserts that.

## Not built yet

The assessor's side: a queue of hand-ins, the evidence beside the criteria, and
a Competent / Not yet competent decision with reasons. The learner half of that
conversation is finished and waiting for it — `submissions.result` and
`.feedback` already render.

Also: reading the fuller lesson text from `lessons.json` (the workspace shows
the task-level lesson, not the paged reader the Android app has).
