# Content Model — Naleli Task-Based Learning

Course content is data, not code. The Android app never hard-codes lesson
text, task instructions, or review questions — it renders whatever a
content package describes. This is what lets Naleli add a new programme
(Digital Office Administration, Data Capturing, IT Support, Junior
Cybersecurity, ...) by authoring a new content package, without touching
the app.

```
EXCEL WORKBOOK  →  COURSE CONTENT MODEL  →  JSON content package  →  Android learning experience
```

The workbook (`/source/`) is the **course-design blueprint**: it defines
days, stages, tasks, evidence requirements, review questions, portfolio and
assessor structure, and the capstone. It is never overwritten by content
work — see `source/README.md`. The JSON files under `content/` are the
**transformed, app-consumable form** of that design, authored by Naleli
(not copied from any third-party manual).

## Package layout

```
content/
  digital-foundation/
    course.json
    workspace-content.json              generated from the workbook — see below
    days/
      day-01.json ... day-90.json     (Days 1–7 populated in V1)
    lessons/                            reserved for future longer-form lesson bodies
    assessments/                        reserved for future formal assessment definitions
    resources/                          read-only reference files a task can point at
    downloads/                          read-only templates a task can point at
```

A future second programme is `content/<programme-id>/` with the same shape.

## `course.json`

```jsonc
{
  "programmeId": "digital-foundation",
  "programmeName": "Digital Foundation",
  "shortDescription": "...",
  "totalDays": 90,
  "method": ["LEARN", "DO", "CHECK", "EVIDENCE", "REFLECT"],
  "progressionRule": "FREE_NAVIGATION",   // or "SEQUENTIAL_UNLOCK"
  "stages": [
    { "stageId": "stage-1", "name": "Learn the Role", "dayStart": 1, "dayEnd": 30,
      "description": "High guidance: learn the basics and practise with step-by-step tasks." },
    { "stageId": "stage-2", "name": "Do the Work", "dayStart": 31, "dayEnd": 60,
      "description": "Reduced guidance: realistic workplace missions and outputs." },
    { "stageId": "stage-3", "name": "Operate Independently", "dayStart": 61, "dayEnd": 82,
      "description": "Minimal guidance: solve problems, make decisions and document them." },
    { "stageId": "stage-4", "name": "Capstone & Portfolio", "dayStart": 83, "dayEnd": 90,
      "description": "Final simulation, presentation, evidence and portfolio submission." }
  ],
  "certificateEligibility": {
    "requireAllDaysComplete": true,
    "requireCapstoneComplete": true,
    "requireFinalAssessmentComplete": true,
    "minimumPortfolioItems": 1
  },
  "credential": {
    "issuingBody": "Naleli Innovators Business School",
    "campus": "Katlehong Computer School (KCS)",
    "programmeTitle": "Digital Foundation"
  },
  "contentAvailableDays": [1, 2, 3, 4, 5, 6, 7]
}
```

`contentAvailableDays` lets the app tell a learner "Day 8 isn't available
yet" honestly instead of crashing or silently showing nothing when they page
past the shipped content — this is how V1 stays truthful about being a
first pass.

## `days/day-NN.json`

```jsonc
{
  "dayNumber": 1,
  "stageId": "stage-1",
  "title": "Starting the Course",
  "learningFocus": "Foundations",
  "sourceReference": "Lesson 1A — Starting the Course",
  "objective": "Understand what digital literacy and digital citizenship mean, and start building the habits of a proactive learner.",
  "lessonSummary": "<Naleli-authored short lesson text, 1–3 short paragraphs>",
  "keyFocusAreas": [
    "What is digital literacy?",
    "What is digital citizenship?",
    "What can you do to be a proactive learner?"
  ],
  "tasks": [
    {
      "taskId": "day-01-task-1",
      "orderIndex": 1,
      "title": "Learn — Starting the Course",
      "taskType": "READ",
      "instructions": "...",
      "learningObjective": "...",
      "estimatedTime": "15 min",
      "required": true,
      "evidenceRequired": false,
      "responseType": "TEXT",
      "portfolioEligible": false,
      "supportContent": []
    }
    // ... Practice (PRACTICE), Work Mission (WORK_MISSION), Review (SELF_CHECK)
  ],
  "reviewQuestions": [
    "What is digital literacy?",
    "What is digital citizenship?",
    "What can you do to be a proactive learner?",
    "What is the role of practice in learning new skills?"
  ],
  "reflectionPrompt": "What did you learn today, and what would you do differently?",
  "dailyDeliverableSummary": "3 key points / notes; a working file, screenshot, answer or recorded result; a short workplace-style output with an explanation."
}
```

### Task fields (matches the brief's task model, §10)

| Field | Type | Notes |
|---|---|---|
| `taskId` | string | Globally unique, e.g. `day-01-task-2` |
| `orderIndex` | int | Display order within the day |
| `title` | string | |
| `taskType` | enum | `READ, WATCH, PRACTICE, WORK_MISSION, RESEARCH, CREATE, UPLOAD_EVIDENCE, SELF_CHECK, REFLECTION, ASSESSMENT, CAPSTONE` |
| `instructions` | string | |
| `learningObjective` | string | |
| `estimatedTime` | string | Free text, e.g. `"15 min"` |
| `required` | bool | Whether day completion requires this task |
| `evidenceRequired` | bool | Whether a file/photo evidence attachment is required |
| `responseType` | enum | `TEXT, FILE, REVIEW, NONE` — drives which input UI the Day screen shows |
| `portfolioEligible` | bool | Whether a completed instance of this task (with evidence) becomes a portfolio item |
| `supportContent` | array | References into `resources/`/`downloads/` (file name + label) |
| `reviewQuestions` | array (SELF_CHECK only) | |

`status`, `assessmentStatus`, and `feedback` are **not** in the content
JSON — they are runtime state stored in Room, keyed by `taskId` (see
`docs/ARCHITECTURE.md`).

## `workspace-content.json` — the 90-day workspace curriculum

The Workspace screens (Home, My Work, Journey, Portfolio) read a second,
generated package: `content/digital-foundation/workspace-content.json`.
Where `days/day-NN.json` is hand-authored per day, this file is the whole
90-day programme **converted straight out of the workbook**, so the app and
the curriculum cannot drift apart.

```
source/Naleli_Task_Based_Digital_Foundation_90_Day_Workbook.xlsx
        │
        │  source/build_workspace_content.py     (the only thing that writes the JSON)
        ▼
content/digital-foundation/workspace-content.json   →  data/content/WorkspaceCurriculum.kt
```

**Never hand-edit the JSON.** Edit the workbook, re-run
`python3 source/build_workspace_content.py`, and commit both.

### Shape

`Phase → Workstream → Task (a day) → Sub-step`, matching
`data/content/WorkspaceContent.kt`:

| Model | Source |
|---|---|
| Phase (`stageId`) | the ROADMAP's Stage column → `course.json`'s four stages |
| Workstream | one module (M1–M20) within one stage, named for the module's Main Skill |
| Task | one workbook day (`day-01` … `day-90`) |
| Sub-step | one row of that day's TASKS block (Learn / Practice / Work Mission / Review) |
| `assessmentCriteria` | that day's SOURCE LEARNING QUESTIONS |
| `deliverableLabel` | the day's first Evidence / Output cell |

A module that crosses a stage boundary (M9, M16, M20) becomes two
workstreams — the later one suffixed "(continued)" — so no day is dragged
into the wrong phase.

### What is derived, not authored

These are computed by the converter and are **not** curriculum text:

- `tier` — `ASSESSMENT` for the capstone days (80–90), `REQUIRED` otherwise.
- `estimatedMinutes` — 45 for a standard day, 90 for a capstone day, split
  across that day's sub-steps so the parts always re-add to the whole.
- `whyItMatters` — a one-line frame built from the day's skill and stage.
- Unlocking — sequential, derived from `dayNumber` (`prerequisiteFor` in
  `WorkspaceContentPackage`), not stored as 89 prerequisite rows.
- Task title on capstone days — days 80–90 share one source lesson name, so
  the title comes from the day's own first non-review task
  ("Capstone Brief", "Build — Part 1", "Present").

### Current package

23 workstreams · 90 tasks · 371 sub-steps · 20 portfolio skills.

## Mapping the workbook onto this model

The workbook's per-day tab (`Step, Task, Instructions, Evidence/Output,
...`) maps directly:

| Workbook task name | `taskType` | `responseType` |
|---|---|---|
| `Learn — <lesson>` | `READ` | `TEXT` (the "3 key points / notes") |
| `Practice — <lesson>` | `PRACTICE` | `FILE` |
| `Work Mission — <lesson>` | `WORK_MISSION` | `FILE` |
| `Review — Prove you can do it` | `SELF_CHECK` | `REVIEW` |
| `Capstone Brief` / `Portfolio Submission` (Days 83–90) | `CAPSTONE` | `FILE` |

The workbook's "SOURCE LEARNING QUESTIONS" become each day's
`reviewQuestions`. The workbook's short topic phrases (e.g. "What is
Input?") are the **workbook's own structure**, not the third-party manual's
text, and are safe to reuse. **Lesson content itself
(`lessonSummary`) is always written fresh by Naleli** — never copied from
the source manual — see `source/README.md`.

## Adding a new programme (future)

1. Create `content/<programme-id>/course.json` + `days/day-01.json ...`.
2. No app code changes are required for the core learning flow — screens
   read `programmeId` from the active learner profile and load that
   package's content.
3. If a programme needs a genuinely new task type or response type, extend
   the shared `TaskType`/`ResponseType` enums once; every programme benefits.
