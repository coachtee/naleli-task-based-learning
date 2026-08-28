package com.naleli.tbl.ui.theme

import androidx.compose.ui.graphics.Color

// Mirrors /design/DESIGN-TOKENS.md — that document is the source of truth
// for what these tokens mean; keep the two in sync if either changes.
//
// Naleli Workspace visual identity (approved redesign): deep navy / dark
// blue surfaces with Naleli blue as the one accent for primary actions and
// navigation. No purple — status colour is restricted to exactly three
// meanings everywhere in the app: green = competent/completed/success,
// amber = in progress/attention, red = required/warning/destructive.

val NaleliBlue = Color(0xFF1677FF)
val NaleliBlueDark = Color(0xFF0B4DB8)
val NaleliBlueLight = Color(0xFFE1EEFF)
val NaleliBlueActive = Color(0xFF2997FF)

val NaleliNavy = Color(0xFF101828)
val NaleliNavySoft = Color(0xFF475467)

val SurfaceWhite = Color(0xFFFFFFFF)
val SurfaceGrey = Color(0xFFF5F5F8)
val BorderGrey = Color(0xFFE4E4EA)

val SuccessGreen = Color(0xFF059669)
val SuccessGreenBg = Color(0xFFE3F6EF)

val WarningOrange = Color(0xFFD97706)
val WarningOrangeBg = Color(0xFFFCEEE1)

val ErrorRed = Color(0xFFDC2626)
val ErrorRedBg = Color(0xFFFBEAEA)

// Dark theme surfaces — the approved navy palette, exact hexes.
val NaleliNavyDeep = Color(0xFF071525)
val NaleliNavySurface = Color(0xFF0D2138)
val NaleliNavyElevated = Color(0xFF132A45)
val NaleliNavyBorder = Color(0xFF29415A)
val NaleliLightBlue = Color(0xFF8CCBFF)
val NaleliTextSecondaryDark = Color(0xFFAAB8C8)

// NIBS brand marks — sampled directly (pixel-picked) from the supplied
// academic-mark logo (branding/logo/nibs-academic-mark.png), re-verified
// against the logo file itself, not eyeballed. Reserved for brand/logo
// moments (splash, Welcome wordmark, certificate seal) only — fixed brand
// asset colours, independent of the app's interactive blue/amber tokens.
// Never used to recolor the logo itself.
val NibsOrange = Color(0xFFEE5A00)
val NibsOrangeLight = Color(0xFFF17830)
val NibsOrangeDark = Color(0xFFC24A00)
// The deep navy the "nibs" wordmark and institutional name are actually
// printed in — much darker/more indigo than any interactive UI blue, so
// it's kept as its own token rather than reused from MaterialTheme.
val NibsWordmarkNavy = Color(0xFF02084B)

// "Hero" surface — the dark navy background used for high-emphasis
// moments (Home screen, bottom navigation, splash), while other screens
// keep a light surface in Light mode. Same palette as
// NaleliNavy/NaleliNavyDeep, just named for how it's used.
val HeroSurface = NaleliNavyDeep
val HeroSurfaceRaised = NaleliNavySurface
val OnHeroSurface = SurfaceWhite
val OnHeroSurfaceSoft = Color(0xFFB8BFCC)

// Dark-theme-only tokens (V1.5.1 §6 contrast audit, updated for the navy
// redesign). Not simple inversions of their light-theme counterparts —
// chosen so outlines, track fills and error text hold real contrast
// against the dark navy surfaces, where the light-theme values (tuned for
// a white background) go nearly invisible. See the outline/surfaceVariant/
// error entries in ui/theme/Theme.kt.
val DarkOutline = NaleliNavyBorder
val DarkSurfaceVariant = NaleliNavyElevated
// A lighter tint of ErrorRed, not the saturated value itself — #DC2626
// reads too dark to stay legible as small text on the near-black
// background. Documented in the approved design system as "Danger Text."
val DarkError = Color(0xFFF87171)
val DarkOnError = Color(0xFF450A0A)
val DarkErrorContainer = Color(0xFF7F1D1D)
val DarkOnErrorContainer = Color(0xFFFFDAD6)
