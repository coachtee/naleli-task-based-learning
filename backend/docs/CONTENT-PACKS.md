# Adding a module

Thirteen programmes are sold. One — the Foundation — has its content written.
The other twelve are declared and waiting. This is how the twelfth becomes the
thirteenth.

```
php artisan content:check
```

```
 Programme                                    Content pack                 Status            Tasks
 DOPF  Digital Operations Professional Fou..   digital-foundation           ready             90
 PPO   People & Payroll Operations             people-payroll-operations    not written yet   —
 CRM   Customer & CRM Operations               customer-crm-operations      not written yet   —
 ...
 1 of 13 programmes can be taught.
```

That number is the roadmap. Nothing else in the system needs to change for a
module to go live — the record, the sync, the lab login, the assessor's queue
and the certificate rules are all programme-agnostic already. A module is
content, not code.

## What a pack is

A directory under `content/`, named in the catalogue:

```
content/people-payroll-operations/
├── course.json               the programme, its stages, how many days
└── workspace-content.json    the workstreams, tasks and sub-steps
```

Both files are read by the Android app (bundled in the APK) and served to the
browser at `GET /api/v1/content/{code}`, so a learner sees the same content
whichever they open. There is one copy, not two.

## The three steps

**1. Name it.** It is already named. `CatalogueManifest::CONTENT_PACKS` maps
every programme code to its pack, including the ones nobody has written:

```php
'PPO' => 'people-payroll-operations',
```

Naming all thirteen up front is deliberate. A declared-but-missing pack is a
gap the system reports; a programme with no entry at all is a silent one, and
silence is how a Payroll learner gets shown the Foundation course.

**2. Write the JSON.** Same shape as `content/digital-foundation/`. A task
carries what the learner reads, what they do, and what the assessor checks:

```json
{
  "taskId": "day-01",
  "dayNumber": 1,
  "title": "Lesson 1A — Reading a payslip",
  "estimatedMinutes": 45,
  "whatYoureDoing": "…",
  "whyItMatters": "…",
  "understandText": "…",
  "practiseText": "…",
  "assignmentText": "…",
  "deliverableLabel": "Workplace-style output + short explanation",
  "subSteps": [
    { "subStepId": "day-01-step-1", "title": "Learn — …",
      "instructions": "…", "evidence": "3 key points / notes" }
  ],
  "assessmentCriteria": [
    "All 4 steps of the day are complete",
    "Evidence of the work is attached",
    "The evidence is the deliverable asked for: …"
  ],
  "reviewQuestions": ["…"]
}
```

`assessmentCriteria` is the load-bearing one. It is what the learner is shown
before they hand in, and what the assessor ticks against — so it is written as
things that can be *observed in the evidence*, never as a mark out of ten.

**3. Check it.**

```bash
php artisan content:check
```

A pack that is missing, unparseable, or has no tasks in any workstream fails
here — with the reason — rather than in front of a class. `content:check
--strict` fails while any programme on sale is still unteachable; that is the
right check for a deployment once the catalogue is complete.

## Where packs live in production

`SYNC_CONTENT_PATH` in `config/sync.php`. The repository keeps them in
`content/`; a deployment copies that directory beside the application. Adding a
module to a running server is copying a directory and running `content:check`.

## What a learner sees before their module is written

Not a guess at somebody else's course. The entitlement carries
`content_installed`, and the workspace says so plainly:

> **Your course is not loaded yet**
> The content for PPO has not been published to this system. Your facilitator
> knows about it — nothing you have done is lost.

Their record still syncs. When the pack lands, their work is already there.
