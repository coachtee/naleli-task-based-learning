# Naleli Workspace API — v1 contract

Base URL: `https://api.kcs.edu.za/api/v1`

This document is written **before** the Android app is connected, so the app
work can be specified and started against a fixed contract. Nothing in the app
calls any of this yet.

## Authentication

Two different tokens, deliberately:

| | What it is | Lifetime |
|---|---|---|
| **Access token** | `KCS-XXXX-XXXX-XXXX`, printed or emailed. Grants access to **one enrolment**. Redeemed once. | Until used, expired or revoked |
| **Device token** | Bearer token issued in exchange, identifies **this phone**. | Until revoked |
| **Lab session** | Issued for a **student number + 6-digit PIN**, at a shared computer. | 12 hours, or until logout |

The phone and the lab PC need opposite things, so they get different doors. One
handset belongs to one learner for good, and making them type a PIN every
morning to reach work already on the device would be theatre. A lab machine
carries three learners a day and must keep nothing behind, so it gets a session
that expires by itself and is destroyed on `DELETE /sessions`.

`POST /sessions` answers identically for a wrong PIN and an unknown student
number — references run in sequence, so distinguishable answers would let
anyone count upwards and read off the roll. Guessing is limited to 5 attempts
per student number per minute, and separately per address, so one learner
fumbling their PIN cannot lock out the room.

PINs are hashed and there is no way to read one back. A facilitator issues a
new one from the learner's row in the dashboard and reads it out once.

The access token is not the learner's identity. A learner keeps one permanent
reference (`NAL-2026-00001`) and collects a new access token for each
programme they enrol in — which is how the same person opens a second
programme in the same app without registering again.

Authenticated requests send `Authorization: Bearer <device token>`.

## Endpoints

### Public

```
GET  /health                 liveness
GET  /programmes             the catalogue
GET  /content/{code}         a course content pack
POST /tokens/activate        redeem an access token, receive a device token (phone)
POST /sessions               sign in with student number + PIN (shared computer)
```

`POST /tokens/activate`

```json
{ "token": "KCS-7F3A-92XK-4MQP", "device_name": "Learner phone",
  "platform": "android", "app_version": "1.0.0" }
```

Accepts the token as typed — lower case, spaces instead of hyphens, with or
without the `KCS` prefix. Rate limited to 10 attempts per IP per minute.

**201**
```json
{
  "device_token": "12|abc...",
  "learner": { "learner_ref": "NAL-2026-00001", "first_name": "…", "status": "active" },
  "entitlements": [ { "programme_code": "PPO", "state": "active",
                      "content_code": "digital-foundation" } ],
  "activated_programme": "PPO"
}
```
**422** invalid, already used, or revoked. **429** too many attempts.

### Authenticated

```
GET  /me                     learner profile
GET  /me/entitlements        what this learner may open

GET  /me/progress            the learning record
POST /me/progress            merge this device's changes, get the record back
POST /me/evidence            upload one file (multipart)
GET  /me/evidence/{id}       fetch one back
```

`entitlement.state` is one of `locked`, `available`, `active`, `completed`,
`expired`. **This is the only thing the app reads to decide what to show.** It
is recomputed server-side from enrolments and payments, so a modified phone
cannot grant itself a programme.

`content_code` binds an entitlement to the lesson content already bundled in
the APK (`digital-foundation`), so the app never maps programme names to
content files itself.

## The learning record (Phase 3)

The rule the whole thing rests on:

> **The learner account owns the work. A phone and a lab PC are working
> copies. This backend holds the record.**

That is what lets one learner work at home on Android, walk into KCS, sit at
whichever machine is free, log in, and carry on — and lets thirty learners
share one lab PC without ever seeing each other's work.

Every sync is scoped to one programme. Name it with `?programme=PPO` (or
`"programme"` in the body); if the learner has exactly one programme open, it
is taken as read.

### `GET /me/progress` · `POST /me/progress`

Both return **the same shape** — the whole record for that learner and
programme. A client pushes its batch and replaces its local state with the
response; there is no second round trip and nothing to merge twice.

Push:

```json
{
  "programme": "PPO",
  "device": "KCS Lab PC 17",
  "sub_steps": [
    { "sub_step_id": "day-01-task-1-step-1", "task_id": "day-01-task-1",
      "complete": true, "completed_at": "2026-09-01T08:00:00+02:00",
      "client_updated_at": "2026-09-01T08:00:00+02:00" }
  ],
  "submissions": [
    { "task_id": "day-01-task-1", "submitted_at": "2026-09-01T08:30:00+02:00",
      "confidence_rating": 4 }
  ]
}
```

**200** — the record:

```json
{
  "server_time": "2026-09-01T06:31:00+00:00",
  "programme": { "code": "PPO", "content_code": "digital-foundation",
                 "content_version": 1, "entitlement_state": "active",
                 "expires_at": "2026-11-30T00:00:00+00:00" },
  "sub_steps":   [ { "sub_step_id": "…", "task_id": "…", "complete": true,
                     "completed_at": "…" } ],
  "submissions": [ { "task_id": "…", "submitted_at": "…", "confidence_rating": 4,
                     "result": "not_yet_assessed", "assessed_at": null,
                     "feedback": null } ],
  "evidence":    [ { "client_evidence_id": "…", "task_id": "…", "file_name": "…",
                     "mime_type": "…", "byte_size": 128400, "checksum": "…",
                     "captured_at": "…", "download_url": "…" } ]
}
```

Batches are capped (`config/sync.php`, 500 each); past the cap the client
chunks. **422** if the learner has several programmes open and named none.

### Merge rules

Two devices can both be right — a phone that was offline all morning and a lab
PC that was offline all afternoon are not stale in any way the other can see.
So nothing here is last-write-wins:

| | Rule | Because |
|---|---|---|
| Sub-step completion | **Ratchet.** Complete anywhere is complete; the earliest `completed_at` is kept. | Half the lab clocks have never been set, and a wrong clock must not erase an afternoon. |
| Submissions | Latest `submitted_at` wins; a rating is never overwritten with nothing. | A resubmission is a real later hand-in; silence is not an answer. |
| Evidence | Additive, idempotent on `client_evidence_id`. | A retry over a bad line must not double a photo. |
| Competence | **Not writable at all.** | See below. |

Every rule is idempotent, so a client that never saw its response just pushes
again. Trade-off worth naming: because completion is a ratchet, un-ticking a
step needs the learner to be online. Losing work costs more than re-ticking.

### `GET /content/{code}`

The course pack — the same JSON the APK bundles, so the phone and the browser
render one body of content rather than two that can drift. Not learner data:
no authentication, an `ETag` so a machine that already has it downloads
nothing, and cacheable by anything in between. `code` is whitelisted against
`[a-z0-9-]`, so it names a pack and can never name a path.

### `POST /me/evidence`

`multipart/form-data`: `file`, `client_evidence_id`, `task_id`, and optionally
`description`, `captured_at`, `programme`, `device`. **201** on first receipt,
**200** if that `client_evidence_id` is already held — a retry is not a
failure. Written answers arrive here too, as `text/plain`; the app keeps one
evidence path and so does this.

Limits and the extension allowlist live in `config/sync.php` (25 MB default).
Nothing the client sends shapes a path: the directory is the learner reference
we allocated and the filename is a UUID we generate.

`GET /me/evidence/{client_evidence_id}` streams it back, scoped to the caller —
another learner's id is a **404**, not a 403, because a permission message
would confirm the file exists.

### Who may sync

Not "is the entitlement active". Writing is allowed for any programme that has
ever been unlocked — including an **expired** one. Expiry stops a learner
opening new content, which the client enforces from `entitlement_state`; it
must not destroy work already done by a device that has been offline since
before it lapsed. Only a programme that was never opened is refused (**403**).

## Not in v1 — and why

| Endpoint | Phase | Why not yet |
|---|---|---|
| `GET /me/assessments` | 4 | Needs the assessor workflow to exist |
| `GET /me/certificates` | 5 | Depends on moderated human assessment |

**There is no write route for entitlements, assessment results or
certificates, and there never will be.** On `learner_submissions` this is
structural rather than a policy: `result`, `assessed_at`, `assessed_by` and
`feedback` are absent from the synchroniser's upsert column list, so no shape
of request can move them. The app is authoritative about what
the learner *did*; this backend is authoritative about what that *counts for*.
Holding that asymmetry in the routing table rather than in policy is what
stops a rooted phone awarding itself a qualification.

## Website intake (not called by the app)

```
POST /intake/application     Fluent Forms form 8 → learner + application
```

Requires `X-KCS-Signature: sha256=<HMAC-SHA256 of the raw body>`. Idempotent
on `(source, form_id, submission_id)`: a retried delivery returns
`duplicate_ignored` and writes nothing.
