# Roadmap

## V1.5 (this pass) — UI/UX redesign + evidence-first learning experience

Scope: a deliberate visual/UX redesign on top of the working V1 app — no
functionality was thrown away, the local-first architecture and data model
are unchanged. See the project brief for the full spec; summary of what
changed:

- [x] Official NIBS academic-mark logo wired in (splash + adaptive launcher
      icon), replacing the V1 placeholder wordmark — see `branding/README.md`.
- [x] "Hero" dark-navy surface for Home/splash/bottom-nav; content screens
      stay light — same palette, different composition (`design/DESIGN-TOKENS.md`).
- [x] Bottom navigation relabelled Home / **Learn** / **Work** / Portfolio /
      Profile (routes kept as `my_learning`/`evidence` internally — labels
      and screen content changed, not the underlying route names).
- [x] Home redesigned around "Today's Work": progress ring, gradient
      Today's Mission card (estimated time, task count, evidence-required
      count), Up Next, Recent Work.
- [x] My Learning: stage filter chips + compact status-icon day rows
      (replacing large repeated cards).
- [x] Day detail restructured as a segmented Learn / Tasks / Review /
      Reflect tab flow (was one long scroll), with a circular
      tasks-completed progress ring.
- [x] New **Task Detail** drill-down screen (instructions, files-to-use,
      evidence status, mark complete) — tasks are no longer only inline
      cards on the Day screen.
- [x] New **"Prove Your Work"** dedicated evidence screen: Take Photo,
      Choose from Gallery, Upload File, Scan Worksheet, From Computer,
      with an uploaded-evidence list (delete supported).
- [x] Worksheet QR-code identifier scheme (`DF-D24-T02`) — parser +
      manual-entry lookup screen fully working; **camera-based auto-scan is
      not yet implemented** (see "What's next" below).
- [x] "From Computer" pairing — UI entry point + explanatory dialog only;
      **no computer-pairing transport exists yet** (see below).
- [x] Evidence ("Work") screen redesigned: filter chips (All/Documents/
      Images/Worksheets), grouped by day, type icons, assessment-status
      chips — not raw filename/mime-type rows.
- [x] Portfolio redesigned: item-count stat card, per-item type tag and
      assessment-status chip.
- [x] Profile flattened into a single identity header + menu-row list
      (Progress, Certificate, My Portfolio, Backup & Export, Help, Privacy,
      Delete My Data) — the separate "Settings" screen was retired as
      redundant.
- [x] Progress screen: per-stage breakdown bars (Learn the Role / Do the
      Work / Operate Independently / Capstone & Portfolio), not just one
      aggregate number.

### What's next (explicitly deferred, not silently dropped)

- **Camera QR auto-scan** for worksheet codes. The identifier scheme and
  parser (`domain/WorksheetCode.kt`) and the manual-entry lookup screen are
  real and working today; wiring a camera preview to auto-decode a QR code
  needs a barcode-decoding dependency (e.g. ML Kit's on-device barcode
  scanning). Deliberately not added in the same pass that was still
  fighting through Gradle/Kotlin build issues — a focused follow-up.
- **"From Computer" evidence transport.** The brief is explicit that V1.5
  should not build a server for this. The UI entry point exists and tells
  the learner it's not available yet rather than pretending to work.
  A real implementation needs the "future architecture" described in the
  brief (a small HTTPS upload endpoint) — out of scope until that
  infrastructure decision is made.
- **Worksheet scan quality.** "Scan Worksheet" captures a photo via the
  camera today (tagged as a worksheet in Evidence/Work), but does not yet
  do page/edge detection, perspective correction, or multi-page PDF
  assembly — the brief explicitly allows this to land incrementally.

## V1 — application shell + proven learning engine

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
