# V1 Product Specification — Naleli Task-Based Learning

This document is the working specification for Version 1 of the Naleli
Task-Based Learning Android application. It is derived directly from the
project brief supplied for this build and is the reference used to make
implementation decisions. Where the brief was silent on a detail, the
simplest option that preserves **offline-first, content-driven, task-based,
portfolio-first, future-extensible** was chosen (see "Decisions" at the end).

## 1. Product

- **Name:** Naleli Task-Based Learning
- **First course:** Digital Foundation (90 days)
- **Progression:**
  - Days 1–30 — **Learn the Role** (high guidance, structured practice)
  - Days 31–60 — **Do the Work** (realistic workplace missions, reduced guidance)
  - Days 61–82 — **Operate Independently** (complex tasks, decisions, exceptions, escalation)
  - Days 83–90 — **Capstone & Portfolio** (final simulation, portfolio, final assessment)

## 2. Brand relationships

- **KCS — Katlehong Computer School** — campus / training environment.
- **NIBS — Naleli Innovators Business School** — professional programme and
  credential brand.
- **Naleli Task-Based Learning** — the learning methodology and this app.

The app must keep these three visually and textually distinct (see
`branding/README.md`). No accreditation, qualification, or certificate claims
are made beyond what the project brief itself states. No content was scraped
from kcs.edu.za; only the organisation names and the 90-day stage names
supplied directly in the brief are used.

## 3. Design direction

Modern, clean, professional, mobile-first. White/light backgrounds, deep
navy/charcoal text, a Naleli purple accent, subtle grey surfaces, green for
completed states, orange/red reserved for warnings/incomplete states.
Rounded cards, generous spacing, clear progress indicators, accessible
contrast. This is professional career training, not a childish school app.
See `design/DESIGN-TOKENS.md`.

## 4. V1 core experience (implemented this pass)

1. Install the APK.
2. Open the app → splash → welcome.
3. Create a learner profile locally (no account, no server).
4. Programme is Digital Foundation (the only programme shipped in V1).
5. See the 90-day journey (My Learning), grouped by stage.
6. Open today's / Day 1's learning task from Home.
7. Read the lesson content (Naleli-authored, not the source manual).
8. Complete practical tasks (Learn / Practice / Work Mission / Review).
9. Access supplied course resource files (read-only, sample resources included).
10. Create/attach evidence from the device (file picker, image picker, camera).
11. Complete review/self-check activities.
12. Mark tasks and days complete.
13. Track progress (computed from real completion data, not fake numbers).
14. Portfolio builds automatically from evidence-bearing completed tasks.
15. Capstone/certificate flow is implemented but naturally gated — V1 only
    ships Days 1–7 of content, so the "all required days complete" rule is
    never satisfied and the certificate stays disabled, exactly as the
    eligibility model intends.
16. Export/back up learner data and portfolio metadata to a ZIP file, and
    restore from one (with an explicit overwrite warning).

Everything above works fully offline.

## 5. Learner profile

First launch shows a welcome screen, then a profile form:

- First name, Surname, Student number (optional — auto-generated if blank),
  Email (optional), Phone (optional), Programme (fixed to Digital Foundation
  in V1), Start date (defaults to today, editable).

A local learner identifier is generated in the form `NAL-DF-2026-0001`
(`NAL-<programme code>-<year>-<sequence>`). No online account is required.
The learner can edit their profile later from the Profile tab.

## 6. Navigation

Bottom navigation: **Home · My Learning · Evidence · Portfolio · Profile**.
Also reachable (from Home quick actions and the Profile screen): **Progress,
Certificate, Help, Settings, Backup/Export**.

## 7. Home screen

Greeting + learner name, programme name, "DAY N OF 90", progress bar,
current stage label, Today's Mission card (task title + incomplete task
count) with a **Start Today's Task** primary button, an upcoming-task
preview, and the most recent portfolio item. No gamification beyond a simple
"days active" indicator — no streak pressure mechanics, no badges/points.

## 8. My Learning

The full 90-day journey grouped into the four stages above. Each day shows
its number, title, status (`NOT_STARTED / IN_PROGRESS / COMPLETE /
NEEDS_REVIEW`), task-completion fraction, and a lock icon when the
programme's configured progression rule locks it. The progression rule
(`SEQUENTIAL_UNLOCK` or `FREE_NAVIGATION`) is read from `course.json`, not
hard-coded.

## 9. Daily learning experience

Every day page follows **Learn → Do → Check → Evidence → Reflect**: day
number/stage, lesson title, learning objective, short Naleli-authored lesson
content, the day's tasks, evidence requirements, review questions, a
reflection prompt, and a completion state. It reads as a guided workday, not
a textbook chapter.

## 10. Task model

Each task carries: `task_id, day_id, title, instructions, learning_objective,
task_type, estimated_time, required, evidence_required, support_content`
(content-driven, static) plus runtime `status, assessment_status, feedback`
(stored locally, per learner). Task types implemented: `READ, PRACTICE,
WORK_MISSION, SELF_CHECK, REFLECTION, CAPSTONE` (the full enum from the brief
— `WATCH, RESEARCH, CREATE, UPLOAD_EVIDENCE, ASSESSMENT` — is modelled in the
`TaskType` enum for future content even though Days 1–7 don't use all of
them).

## 11. Course content architecture

Content is **not** hard-coded into UI. It lives in
`content/digital-foundation/*.json`, bundled as Android assets and parsed at
runtime (see `docs/CONTENT-MODEL.md`). Adding a new programme means adding a
new content package, not rewriting the app.

## 12–13. Course source and resource files

The Naleli-generated 90-Day Workbook (`/source/`) is the design blueprint.
The underlying third-party source manual is **not** included in this
repository (see `source/README.md`). Course resource files (read-only
samples proving the "Files You Need" pattern) live under
`content/digital-foundation/resources/` and `downloads/`; a task can list
them and the student can open/copy them to make their own working copy.

## 14. Evidence

`evidence_id, student_id, task_id, file_name, file_type, local_path,
created_at, description` — captured via file picker, image picker, or
camera, stored in app-private storage, and always associated with the task
that requested it.

## 15. Portfolio

Any task marked `evidenceRequired` (or explicitly `portfolioEligible`) in
its content definition contributes a portfolio item automatically when
completed with evidence attached. View/open/share supported; a full ZIP
export (`Naleli_Digital_Foundation_Portfolio.zip`) is available from
Backup/Export once the learner has portfolio items.

## 16. Progress

Computed live from Room data: days completed / 90, tasks completed, evidence
submitted, portfolio items, current stage, capstone status. No placeholder
numbers.

## 17. Assessment

Student **completion** (`status`) is stored separately from **assessment
result** (`assessment_status`: `NOT_YET_ASSESSED / COMPETENT /
NOT_YET_COMPETENT / RESUBMIT`). Marking a task complete never auto-sets
`COMPETENT` — that field is left for a future assessor flow and defaults to
`NOT_YET_ASSESSED`.

## 18. Certificate

Certificate eligibility is configurable in `course.json`
(`certificateEligibility`: all required days complete, required evidence
submitted, capstone complete, final assessment complete). The **Generate
Certificate** action is disabled until every configured rule is satisfied.
Generation produces a local PDF (Android's built-in `PdfDocument`, no
external dependency) containing NIBS, learner name, student number,
programme name, completion date, and a locally-unique certificate number.
No online verification is implemented in V1; the certificate number scheme
leaves room for it later (see `docs/ROADMAP.md`).

## 19. Backup

**Backup My Learning** exports profile, progress, task status, evidence
metadata, portfolio metadata, certificate metadata, and (optionally)
evidence files themselves into a ZIP. **Restore My Learning** reads such a
ZIP back in, with an explicit "this will overwrite your current local data"
confirmation before doing so.

## 20. Security / privacy

No account, no password, no analytics, nothing transmitted off-device.
Profile fields collected are limited to what the brief lists. Settings
includes a Privacy Notice, a Data Storage explanation, and **Delete My
Learning Data**.

## 21–23. Branding, technical direction, repository structure

See `branding/README.md`, `docs/ARCHITECTURE.md`, and the top-level
`README.md`.

## 24–25. First milestone and acceptance test

V1 targets exactly the acceptance flow in the brief (§25), for Day 1 through
Day 7 content. Full 90-day content is explicitly out of scope for this pass
— see `docs/ROADMAP.md`.

## 26. Explicitly out of scope for V1

Online accounts, server, Firebase, Supabase, payment gateway, WhatsApp
integration, AI tutor, lecturer dashboard, online certificate verification,
multi-tenant SaaS, push notifications, complex analytics.

## Decisions made where the brief was silent

- **Single active learner profile per device in V1.** The brief describes
  one student creating one local profile; it does not ask for multi-profile
  switching on one device. Data model keys evidence/portfolio/progress by a
  learner id so multi-profile support is additive later, not a rewrite.
- **Progression rule defaults to `FREE_NAVIGATION`** for the shipped Days
  1–7 sample content (a learner can open any unlocked day), because the
  brief explicitly says not to hard-code sequential completion — the rule
  itself is configurable per-programme in `course.json`.
- **Task 1 ("Learn") evidence is a short typed note**, not a file upload —
  the workbook's own "Evidence/Output" for that task type is "3 key points /
  notes", which is naturally a text response. Tasks 2–3 ("Practice"/"Work
  Mission") require a file/photo evidence attachment, matching the
  workbook's "working file, screenshot, answer, or recorded result" /
  "workplace-style output" evidence types.
- **Certificate PDF via Android's built-in `PdfDocument`**, not a third-party
  PDF library — avoids an extra dependency for a straightforward one-page
  layout, keeps the app fully offline-capable with zero added attack
  surface.
