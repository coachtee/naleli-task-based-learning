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
POST /tokens/activate        redeem an access token, receive a device token
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
GET /me                      learner profile
GET /me/entitlements         what this learner may open
```

`entitlement.state` is one of `locked`, `available`, `active`, `completed`,
`expired`. **This is the only thing the app reads to decide what to show.** It
is recomputed server-side from enrolments and payments, so a modified phone
cannot grant itself a programme.

`content_code` binds an entitlement to the lesson content already bundled in
the APK (`digital-foundation`), so the app never maps programme names to
content files itself.

## Not in v1 — and why

| Endpoint | Phase | Why not yet |
|---|---|---|
| `POST /sync/progress` | 3 | Needs the app; contract shape is the existing backup DTOs |
| `POST /sync/evidence` | 3 | Multipart, idempotent on `client_evidence_id` |
| `GET /me/assessments` | 4 | Needs the assessor workflow to exist |
| `GET /me/certificates` | 5 | Depends on moderated human assessment |

**There is no write route for entitlements, assessment results or
certificates, and there never will be.** The app is authoritative about what
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
