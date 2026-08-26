# Design tokens — Naleli Task-Based Learning

Primary visual direction (brief §3): white/light backgrounds, deep
navy/dark text, a Naleli purple accent, subtle grey surfaces, green for
completed states, orange/red reserved for warnings or incomplete states.

These tokens are implemented directly in
`app/src/main/java/com/naleli/tbl/ui/theme/Color.kt` and `Theme.kt` — this
document is the source of truth for what those values mean and why.

## Colour

| Token | Hex | Use |
|---|---|---|
| `NaleliPurple` | `#5B2A86` | Primary accent — buttons, active nav, key progress indicators |
| `NaleliPurpleDark` | `#3E1D5E` | Pressed/dark-variant of primary |
| `NaleliPurpleLight` | `#EFE6F6` | Primary-tinted surfaces (selected chips, subtle highlight) |
| `NaleliNavy` | `#101828` | Primary text, headings |
| `NaleliNavySoft` | `#475467` | Secondary text |
| `SurfaceWhite` | `#FFFFFF` | Screen background |
| `SurfaceGrey` | `#F5F5F8` | Card/section background |
| `BorderGrey` | `#E4E4EA` | Card borders, dividers |
| `SuccessGreen` | `#1A7F52` | Completed status, competent assessment |
| `SuccessGreenBg` | `#E7F6EE` | Completed status chip background |
| `WarningOrange` | `#B4530A` | Needs-review / incomplete status |
| `WarningOrangeBg` | `#FCEEE1` | Needs-review chip background |
| `ErrorRed` | `#B3261E` | Destructive actions, not-yet-competent |

Dark theme uses the same relationships (navy becomes the surface, purple
stays the accent) — implemented in `Theme.kt`'s dark color scheme, not
detailed token-by-token here since it follows Material 3's standard
light/dark derivation.

## Typography

Material 3 default type scale (Roboto) with the following role mapping:

- `headlineSmall` — screen titles ("DAY 1 OF 90", "My Learning")
- `titleMedium` / `titleLarge` — card titles, task titles
- `bodyMedium` — lesson content, instructions
- `labelLarge` — buttons
- `labelSmall` — status chips, metadata (estimated time, day count)

## Shape & spacing

- Card corner radius: `16dp`
- Chip/badge corner radius: `999dp` (fully rounded)
- Screen horizontal padding: `20dp`
- Standard vertical gap between cards: `12dp`
- Section gap: `24dp`

## Elevation

Subtle only — Material 3 tonal elevation for cards (`elevation = 1.dp`), no
heavy drop shadows. Keeps the "professional productivity app" feel rather
than a skeuomorphic school-app look.

## Status colour mapping (used everywhere a day/task status is shown)

| Status | Colour | Chip background |
|---|---|---|
| `NOT_STARTED` | `NaleliNavySoft` | `SurfaceGrey` |
| `IN_PROGRESS` | `NaleliPurple` | `NaleliPurpleLight` |
| `COMPLETE` | `SuccessGreen` | `SuccessGreenBg` |
| `NEEDS_REVIEW` | `WarningOrange` | `WarningOrangeBg` |

## Assessment status colour mapping

| Status | Colour |
|---|---|
| `NOT_YET_ASSESSED` | `NaleliNavySoft` |
| `COMPETENT` | `SuccessGreen` |
| `NOT_YET_COMPETENT` | `ErrorRed` |
| `RESUBMIT` | `WarningOrange` |
