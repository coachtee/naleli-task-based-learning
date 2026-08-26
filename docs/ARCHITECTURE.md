# Architecture — Naleli Task-Based Learning (Android, V1)

## Guiding constraints

- **Offline-first.** No network permission is required for the app to
  function. Nothing about a learner is ever transmitted anywhere.
- **Content-driven.** The UI never hard-codes lesson/task text. It renders
  whatever a course content package (JSON) describes.
- **Local storage only.** Room (SQLite) for structured learner data;
  app-private filesystem for evidence files and generated PDFs/ZIPs.
- **Single Gradle module for V1** (`app/`). The content-driven design means
  a second programme is a new JSON package, not a new module — module
  splitting is deferred until there's an actual second consumer (e.g. a
  `core-content` module) rather than speculatively built now.

## Technology stack

| Concern | Choice | Why |
|---|---|---|
| Language | Kotlin | Standard, modern Android target |
| UI | Jetpack Compose + Material 3 | Declarative, fast to build a polished mobile-first UI, first-class offline app support |
| Navigation | Navigation Compose | Type-safe-enough routing for the app shell + deep day/task links |
| Local DB | Room (SQLite) | Stable, well-understood, offline relational storage for learner data |
| Content parsing | kotlinx.serialization (JSON) | Parses the bundled content package from Android assets |
| File handling | Android Storage Access Framework (`ActivityResultContracts.OpenDocument`, `TakePicture`), `FileProvider` | Evidence attachment (files/images/camera) and safely sharing generated files with other apps |
| PDF generation | `android.graphics.pdf.PdfDocument` (built-in) | Certificate PDF, no third-party dependency |
| Backup/export | `java.util.zip` (built-in) | ZIP backup/restore and portfolio export, no third-party dependency |
| DI | Manual (a small `AppContainer` held by the `Application` class) | The app is small enough in V1 that Hilt/Koin would be pure ceremony; swapping in Hilt later is a mechanical change if the app grows |
| Min/Target/Compile SDK | minSdk 26 (Android 8.0), target/compile SDK 34 | 26+ covers the practical device range for this audience while keeping modern Compose/Material3 APIs available; 34 is a current stable compileSdk |

No Firebase, no Supabase, no backend SDKs, no analytics SDKs, no
authentication libraries.

## Module / package layout

```
app/src/main/java/com/naleli/tbl/
├── NaleliApplication.kt          Application class, AppContainer wiring
├── data/
│   ├── db/                       Room: NaleliDatabase, entities, DAOs
│   ├── repository/               ProfileRepository, ProgressRepository,
│   │                              EvidenceRepository, PortfolioRepository,
│   │                              CertificateRepository, BackupRepository
│   └── content/                  Content models + ContentRepository (asset JSON loader)
├── domain/                        Pure logic: progress calculation, certificate
│                                   eligibility evaluation, learner-id generation
├── ui/
│   ├── theme/                    Color.kt, Type.kt, Shape.kt, Theme.kt
│   ├── navigation/                NaleliNavHost.kt, NaleliDestinations.kt
│   ├── components/                 Shared composables (cards, progress rings, badges)
│   └── screens/
│       ├── splash/ welcome/ profile/
│       ├── home/ mylearning/ day/
│       ├── evidence/ portfolio/ progress/
│       ├── certificate/ settings/ help/
└── util/                           PDF, ZIP, file, date helpers
```

`content/digital-foundation/` at the **repository root** is the single
source of truth for course content. The app module's `assets` source set
points at it directly (`build.gradle.kts`: `sourceSets["main"].assets.srcDirs("../content")`)
rather than duplicating the files into `app/src/main/assets`, so the content
team and the app never drift out of sync.

## Data model (Room)

- **`learner_profile`** — id, firstName, surname, learnerCode
  (`NAL-DF-2026-0001`), studentNumber, email, phone, programmeId,
  startDateEpochDay, createdAt, updatedAt. Single active row in V1.
- **`day_progress`** — dayNumber (PK), status (`NOT_STARTED / IN_PROGRESS /
  COMPLETE / NEEDS_REVIEW`), startedAt, completedAt.
- **`task_status`** — taskId (PK, matches content `task_id`), dayNumber,
  status, assessmentStatus, feedback, textResponse (nullable — used by
  text-based evidence like Task 1's "3 key points"), reflectionText,
  updatedAt.
- **`evidence`** — evidenceId (PK), taskId, dayNumber, fileName, fileType,
  localPath, createdAt, description.
- **`portfolio_item`** — id (PK), dayNumber, taskId, title,
  skillDemonstrated, evidenceId (FK, nullable), description, createdAt.
- **`certificate`** — id (PK), certificateNumber (unique), learnerId,
  programmeId, issuedAt, filePath.

Runtime state (the four tables above besides `learner_profile`) is always
separate from the static content definition — task/day *content* comes from
JSON; task/day *progress* comes from Room. This is what lets a future
assessor feature, or a content update, land without a schema migration for
unrelated data.

## Content-driven course package

See `docs/CONTENT-MODEL.md` for the full JSON schema. In short:
`course.json` defines the programme, its stages, its progression rule, and
certificate eligibility; `days/day-NN.json` defines one day's lesson +
tasks + review questions + reflection prompt; `resources/` and `downloads/`
hold read-only sample files a task can reference.

## Certificate eligibility

`CertificateEligibilityEvaluator` (domain layer) reads
`course.json.certificateEligibility` and Room progress data and returns a
pass/fail per rule plus an overall boolean. The Certificate screen renders
the checklist either way, and only enables **Generate Certificate** when
every configured rule passes. This keeps the rule set data-driven instead of
hard-coding "must complete 90 days" in a button's `enabled` check.

## Backup / export format

`Naleli_Backup_<learnerCode>_<date>.zip` containing:

```
manifest.json         schemaVersion, exportedAt, appVersion
profile.json
progress.json         all day_progress + task_status rows
evidence.json         evidence metadata
portfolio.json        portfolio_item metadata
certificates.json     certificate metadata
evidence/              (optional) copies of the actual evidence files
```

Restore parses `manifest.json` first (schema-version gated), shows the
learner what it's about to overwrite, and only proceeds after explicit
confirmation.

## Build instructions

### Development

```bash
git clone https://github.com/coachtee/naleli-task-based-learning
cd naleli-task-based-learning
./gradlew tasks   # sanity check
```

Open the repository root in Android Studio (Koala/2024.1+ or newer) and let
it sync — `app/` is the single module.

### Debug APK

```bash
./gradlew assembleDebug
# app/build/outputs/apk/debug/app-debug.apk
```

### Release APK

```bash
./gradlew assembleRelease
```

A release build must be signed. Create `keystore.properties` (already
git-ignored) alongside `app/build.gradle.kts`:

```properties
storeFile=/absolute/path/to/your.jks
storePassword=...
keyAlias=...
keyPassword=...
```

`app/build.gradle.kts` reads this file when present and wires a `release`
signing config; without it, `assembleRelease` produces an unsigned APK.

### Requirements

- JDK 17+
- Android SDK: platform-tools, `platforms;android-34`, `build-tools;34.0.0`
  (install via Android Studio's SDK Manager, or `sdkmanager` from the
  command-line tools package)

### A note on this development pass

This V1 pass was built inside a sandboxed cloud session. The Android
project itself (Gradle config, manifest, all Kotlin sources, resources) is
complete and was reviewed by hand line-by-line for import/type
correctness, but `./gradlew assembleDebug` could not be run to completion
in this session, and here's exactly why: the session's network egress
policy blocks the host `dl.google.com`. That's not just where
`sdkmanager` fetches SDK platforms/build-tools from — `maven.google.com`
(the host Gradle's `google()` repository actually uses to resolve the
Android Gradle Plugin and every AndroidX artifact) issues an HTTP 301
redirect to `dl.google.com` for the real file bytes on every request, so
the block reaches AGP/AndroidX resolution too, not only the SDK platform
download. Concretely: `./gradlew assembleDebug` fails immediately while
resolving the `com.android.application` Gradle plugin, before Gradle even
gets to the point of needing a platform/build-tools install. Maven Central
and the Gradle Plugin Portal are reachable and unaffected — this is
specific to Google's Android infrastructure. This is reported plainly
rather than worked around, per this session's operating rules on policy
denials (403/407 responses are not retried or routed around).

To produce the debug APK, run `./gradlew assembleDebug` (or open the
project in Android Studio) anywhere `dl.google.com`/`maven.google.com` are
reachable — a developer machine or a CI runner with normal internet access.
No project changes are needed for that to work; this is purely a network
policy characteristic of this particular sandboxed session.
