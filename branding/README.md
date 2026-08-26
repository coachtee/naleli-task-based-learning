# Branding — Naleli / KCS / NIBS

## The three names, kept distinct

| Name | What it is | Where it appears in the app |
|---|---|---|
| **Naleli Task-Based Learning** | The learning methodology and this application | App name, splash screen, Help screen |
| **NIBS — Naleli Innovators Business School** | The professional programme and credential brand | Splash, certificate, Help screen |
| **KCS — Katlehong Computer School** | The campus / training environment | Help screen |

The app never blends these into one generic "Naleli" brand — the certificate
and Help screen explicitly label which is which, matching the project
brief. No accreditation, qualification, or certificate claim beyond what
the brief itself states is made anywhere in the app.

## Logo — the official NIBS academic mark (V1.5)

`logo/nibs-academic-mark.png` is the **authoritative, supplied logo asset**:
an orange academic-cap-on-a-column mark. It replaces the placeholder
purple "N" wordmark used in the first V1 pass.

- **Splash screen** — the full mark (cap + circle + column), shown at its
  natural tall aspect ratio above the institutional wordmark. Source:
  `app/src/main/res/drawable-nodpi/nibs_mark.png`.
- **Android launcher icon** — a square crop of just the cap-and-circle
  "head" portion of the mark (the column reads better as a tall logo
  lockup than as a square icon), centered on a solid Naleli-navy
  background per Android's adaptive-icon safe-zone convention (foreground
  content ≈62% of the canvas). Source:
  `app/src/main/res/drawable-xxxhdpi/ic_launcher_foreground.png`, with a
  matching white-silhouette `ic_launcher_monochrome.png` for Android 13+
  themed icons.

The logo's own colour and proportions were never altered — no gradients
were added to it, no recolouring — the processing done was strictly
technical: background removal (flood-fill to transparency), autocropping
to content, and resizing/cropping into the specific asset shapes Android
requires (a tall splash mark, a square adaptive-icon foreground). See
`branding/logo/nibs-academic-mark.png` for the canonical, full,
transparent-background version any future asset should be derived from.

**If a newer/higher-resolution source file is supplied later**, regenerate
these derived assets from it rather than re-deriving from the current
cropped versions, to avoid compounding quality loss.

## Colour direction (V1.5)

The V1.5 UI/UX redesign (see `docs/ROADMAP.md`) introduced a "hero" dark
navy surface (Home screen, bottom navigation, splash) using the palette's
existing `NaleliNavy`/`NaleliNavyDeep` tokens — this is a change in
*emphasis and composition*, not a new colour palette: the same purple/navy/
white/green/orange-for-warnings palette from V1 is reused, just applied
with a bolder dark/light relationship matching the supplied UI mockup
reference. Content screens (My Learning, Day, Evidence, Portfolio, Profile,
Progress, Certificate) stay light/white. The NIBS orange from the logo is
reserved for brand moments (splash, certificate) — primary actions and
navigation highlights stay Naleli purple, matching the mockup reference.
See `design/DESIGN-TOKENS.md`.

## Design characteristics (brief §3)

Modern, clean, professional, mobile-first, simple navigation, strong
typography, generous spacing, rounded cards, clear progress indicators,
subtle shadows, accessible contrast — a professional education/productivity
aesthetic, not a childish school interface.
