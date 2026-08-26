# Branding — Naleli / KCS / NIBS

## The three names, kept distinct

| Name | What it is | Where it appears in the app |
|---|---|---|
| **Naleli Task-Based Learning** | The learning methodology and this application | App name, splash screen, About screen |
| **NIBS — Naleli Innovators Business School** | The professional programme and credential brand | Certificate, Programme details, Help/About |
| **KCS — Katlehong Computer School** | The campus / training environment | Programme details, Help/About |

The app never blends these into one generic "Naleli" brand — Programme
details and the certificate explicitly label which is which, matching the
project brief (§2, §21).

No accreditation, qualification, or certificate claim beyond what the brief
itself states is made anywhere in the app. Public organisation information
used here is limited to what the brief supplied directly (organisation
names, the 90-day stage names, and the "Digital Operations Professional
Foundation" / progression terminology). No content was scraped from
kcs.edu.za for this pass.

## Current asset status — placeholder pending the official logo

**No official Naleli/NIBS/KCS logo file was supplied to this build.** Per
the brief ("if a logo asset is supplied directly by the project owner, use
that supplied asset as the authoritative logo"), this pass ships a simple,
original **placeholder wordmark** so the app has a coherent visual identity
to build and test against, rather than inventing a logo that could be
mistaken for an approved brand mark:

- `logo/naleli-wordmark.svg` — a plain text wordmark in the Naleli purple
  (`#5B2A86`), used on the splash screen and welcome screen.
- `icons/app-icon-foreground.xml` / `icons/app-icon-background.xml` —
  vector sources for the Android adaptive launcher icon: a rounded "N"
  monogram on a deep navy background.
- `splash/SPLASH-NOTES.md` — how the splash screen is composed.

**Replace these the moment an official logo file is supplied** — swap the
files in this folder and update the Android vector drawables referenced in
`docs/ARCHITECTURE.md`; no other app code needs to change, since the app
only references these assets by resource name, not by their visual content.

## Design characteristics (brief §3)

Modern, clean, professional, mobile-first, simple navigation, strong
typography, generous spacing, rounded cards, clear progress indicators,
subtle shadows, accessible contrast — a professional education/productivity
aesthetic, not a childish school interface. See `design/DESIGN-TOKENS.md`
for the concrete colour/typography/spacing values used to implement this in
Jetpack Compose.
