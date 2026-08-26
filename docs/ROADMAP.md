# Roadmap

## V1 (this pass) — application shell + proven learning engine

Scope: prove the full learner journey end-to-end on **Day 1**, with **Days
2–7** as sample content proving the content-driven architecture, per the
brief's explicit instruction not to build all 90 days on the first pass.

- [x] Repository structure, docs, content model
- [x] Android project (Kotlin, Compose, Material 3)
- [x] Naleli branding (placeholder logo/icons pending the official asset)
- [x] App shell + bottom navigation
- [x] Learner profile (create, edit, local learner ID)
- [x] Local storage (Room)
- [x] Home dashboard
- [x] My Learning (90-day journey, stage-grouped, Days 1–7 open)
- [x] Day 1 full learning experience
- [x] Task engine (Learn/Practice/Work Mission/Review, text + file responses)
- [x] Evidence attachment (file/image/camera)
- [x] Progress (real, computed)
- [x] Portfolio (auto-built from evidence-bearing completed tasks)
- [x] Certificate engine (configurable eligibility; naturally disabled until
      all 90 days + capstone exist)
- [x] Backup / export + restore
- [ ] Debug APK build — **blocked in this session** by network egress policy:
      `dl.google.com` is unreachable, and `maven.google.com` redirects there,
      so Gradle can't resolve the Android Gradle Plugin/AndroidX, let alone
      the SDK platform/build-tools; see
      `docs/ARCHITECTURE.md#a-note-on-this-development-pass`.

## V1.1 — full 90-day content

- Author `day-08.json` … `day-90.json` from the workbook, stage by stage.
- Extend `course.json.contentAvailableDays` as each stage is authored and
  QA'd against the workbook's ASSESSOR/PORTFOLIO sheets.
- Populate `content/digital-foundation/resources/` and `downloads/` with the
  real per-day "Files You Need" as those are authored (spreadsheets,
  templates, briefs).
- Capstone flow (Days 83–90) end-to-end, including the real portfolio
  export ZIP and certificate generation once genuinely eligible.
- Expand automated tests to cover the full day range, not just Days 1–7.

## V1.2 — polish & real-device hardening

- Real Naleli logo/icon/splash assets (replace the placeholder wordmark in
  `branding/`) once supplied.
- Accessibility pass (TalkBack labels, dynamic type, contrast audit).
- Low-end device performance pass (large evidence files, big portfolios).
- Release signing + Play Store listing assets (still local-first; no new
  backend).

## V2+ (explicitly out of scope until requested)

- Online accounts / lightweight sync (opt-in, still local-first by default)
- Assessor review flow (a human marking `assessment_status`)
- Online certificate verification (a certificate number is already reserved
  for this in V1's data model, but no server exists yet)
- Additional Naleli programmes (Digital Office Administration, Data
  Capturing Specialist, IT Support Specialist, Junior Cybersecurity
  Analyst, AI Workplace Administration, and others) as new content packages
  under the same app
- Lecturer/assessor dashboard
- Push notifications
- Analytics

None of the above will be started without an explicit decision to do so —
per the brief, V1's job is the shell and the proven learning engine, not
the complete product.
