# Naleli Task-Based Learning

A practical, task-based learning application where students learn a professional
role by completing daily workplace-style tasks, producing evidence, building a
portfolio, and completing a final capstone.

Naleli Task-Based Learning is the delivery engine for **Naleli Innovators
Business School (NIBS)** programmes, running on the **Katlehong Computer
School (KCS)** campus. The first course is **Digital Foundation** — a 90-day,
task-based digital literacy programme.

> Naleli Task-Based Learning is not a PDF reader, a spreadsheet viewer, or a
> generic LMS. It is a workplace learning engine. Every learning day answers:
> **What do I need to learn? What do I need to do? What do I need to
> produce? How do I prove I did it? What did I learn?**

## Status: V1 (first development pass)

V1 is a **local-first, offline-first Android application**. There is no
backend, no authentication server, no Firebase/Supabase, and no cloud
database. All learner data lives on the student's device.

V1 proves the learning engine on **Day 1**, with **Days 2–7** included as
sample content that proves the content-driven architecture scales. The full
90-day course is a V1.1+ content task (see [docs/ROADMAP.md](docs/ROADMAP.md)),
not a rebuild of the app.

Read [docs/V1-SPEC.md](docs/V1-SPEC.md) for the full V1 product specification,
[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the technical architecture,
and [docs/CONTENT-MODEL.md](docs/CONTENT-MODEL.md) for how course content is
authored and loaded.

## The 90-day journey

| Stage | Days | Focus |
|---|---|---|
| 1 — Learn the Role | 1–30 | High guidance, structured practice |
| 2 — Do the Work | 31–60 | Realistic workplace missions, reduced guidance |
| 3 — Operate Independently | 61–82 | Complex tasks, decisions, exceptions, escalation |
| 4 — Capstone & Portfolio | 83–90 | Final simulation, portfolio, final assessment |

Every day follows the same method: **Learn → Do → Check → Evidence → Reflect.**

## Repository structure

```
/
├── app/                     Android application (Kotlin, Jetpack Compose)
├── content/                 Content-driven course data (JSON), separate from UI
│   └── digital-foundation/
│       ├── course.json
│       ├── days/            day-01.json … day-90.json (Days 1–7 populated in V1)
│       ├── lessons/
│       ├── assessments/
│       ├── resources/
│       └── downloads/
├── branding/                 Logo, icons, splash assets
├── design/                    Design tokens / visual direction
├── docs/                       V1-SPEC, ARCHITECTURE, CONTENT-MODEL, ROADMAP
├── source/                       Workbook (course-design blueprint) + provenance notes
└── README.md
```

## Why local-first

Students may not have reliable data or connectivity. V1 must work fully
offline: profile creation, lesson content, task completion, evidence capture,
progress, portfolio, and backup all run entirely on-device using Room
(SQLite) and the device filesystem. Nothing is transmitted anywhere.

## Technology stack

- **Kotlin** + **Jetpack Compose** (Material 3) — modern, maintainable, single
  UI toolkit for a mobile-first, offline app.
- **Room** (SQLite) — local learner data: profile, progress, task status,
  evidence metadata, portfolio, certificates.
- **kotlinx.serialization** — parses the content-driven JSON course package
  bundled as Android assets.
- **Navigation Compose** — app shell, bottom navigation, deep day/task routes.
- **Android `PdfDocument`** (built-in, no third-party dependency) — local
  certificate PDF generation.
- **`java.util.zip`** (built-in) — backup/export and restore.

No backend, no analytics SDKs, no network permission requested beyond what's
required to open locally-generated files with other installed apps.

## Building the project

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#build-instructions) for full
build instructions (development, debug APK, release APK).

Quick start (once an Android SDK is available — see the note below):

```bash
./gradlew assembleDebug
# APK output: app/build/outputs/apk/debug/app-debug.apk
```

> **Note on this development pass:** this V1 pass was built inside a sandboxed
> cloud session whose network egress policy blocks `dl.google.com` — and, as
> a consequence, `maven.google.com` too, since it redirects there for actual
> file bytes. That blocks not just the Android SDK platform/build-tools
> download but Gradle's resolution of the Android Gradle Plugin and AndroidX
> itself. The full Android project is complete, was reviewed by hand for
> correctness, and is ready to build — but the debug APK itself could not be
> produced in this session. See
> [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#a-note-on-this-development-pass)
> for the exact failure and how to build it wherever Google's Android
> infrastructure is reachable (Android Studio, a local machine, or a normal
> CI runner).

## Course source and copyright

The first Digital Foundation course was designed using the **Naleli
Task-Based Digital Foundation 90-Day Workbook** (`/source/`) as the
course-design blueprint. The workbook itself is a Naleli-owned planning
document and is version-controlled here. It was built with reference to a
third-party course manual; **that third-party manual is not, and will not
be, included in this repository or the app** — see
[source/README.md](source/README.md) for the full provenance and copyright
notes.

## Naleli / KCS / NIBS

- **KCS (Katlehong Computer School)** — the campus / training environment.
- **NIBS (Naleli Innovators Business School)** — the professional programme
  and credential brand.
- **Naleli Task-Based Learning** — the learning methodology and this
  application.

See [branding/README.md](branding/README.md).

## What V1 does not include (by design)

Online accounts, a server, Firebase/Supabase, payment gateways, WhatsApp
integration, an AI tutor, a lecturer dashboard, online certificate
verification, multi-tenant SaaS, push notifications, or analytics. These are
explicitly out of scope for V1 — see [docs/ROADMAP.md](docs/ROADMAP.md).
