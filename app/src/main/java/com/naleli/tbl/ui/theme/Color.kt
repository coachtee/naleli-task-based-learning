package com.naleli.tbl.ui.theme

import androidx.compose.ui.graphics.Color

// Mirrors /design/DESIGN-TOKENS.md — that document is the source of truth
// for what these tokens mean; keep the two in sync if either changes.

val NaleliPurple = Color(0xFF5B2A86)
val NaleliPurpleDark = Color(0xFF3E1D5E)
val NaleliPurpleLight = Color(0xFFEFE6F6)

val NaleliNavy = Color(0xFF101828)
val NaleliNavySoft = Color(0xFF475467)

val SurfaceWhite = Color(0xFFFFFFFF)
val SurfaceGrey = Color(0xFFF5F5F8)
val BorderGrey = Color(0xFFE4E4EA)

val SuccessGreen = Color(0xFF1A7F52)
val SuccessGreenBg = Color(0xFFE7F6EE)

val WarningOrange = Color(0xFFB4530A)
val WarningOrangeBg = Color(0xFFFCEEE1)

val ErrorRed = Color(0xFFB3261E)
val ErrorRedBg = Color(0xFFFBEAE9)

// Dark theme surfaces
val NaleliNavyDeep = Color(0xFF0B111D)
val NaleliNavySurface = Color(0xFF1A2233)
val NaleliNavyBorder = Color(0xFF2C3646)

// NIBS brand orange — sampled directly from the supplied academic-mark
// logo (V1.5, see branding/logo/nibs-academic-mark.png). Reserved for
// brand/logo moments (splash, certificate seal) and used sparingly as a
// warm accent — primary actions stay Naleli purple, matching the V1.5
// mockup reference. Never used to recolor the logo itself.
val NibsOrange = Color(0xFFF2540A)
val NibsOrangeLight = Color(0xFFF66D02)
val NibsOrangeDark = Color(0xFFEB4C02)

// "Hero" surface — the dark navy background used for high-emphasis
// moments (Home screen, bottom navigation, splash) per the V1.5 mockup,
// while other screens keep a light surface. Same palette as
// NaleliNavy/NaleliNavyDeep, just named for how it's used.
val HeroSurface = NaleliNavyDeep
val HeroSurfaceRaised = NaleliNavySurface
val OnHeroSurface = SurfaceWhite
val OnHeroSurfaceSoft = Color(0xFFB8BFCC)

// Dark-theme-only tokens (V1.5.1 §6 contrast audit). Not simple inversions
// of their light-theme counterparts — chosen so outlines, track fills and
// error text hold real contrast against the dark navy surfaces, where the
// light-theme values (tuned for a white background) go nearly invisible.
// See the outline/surfaceVariant/error entries in ui/theme/Theme.kt.
val DarkOutline = Color(0xFF5B6472)
val DarkSurfaceVariant = Color(0xFF2E3B54)
val DarkError = Color(0xFFFFB4AB)
val DarkOnError = Color(0xFF690005)
val DarkErrorContainer = Color(0xFF93000A)
val DarkOnErrorContainer = Color(0xFFFFDAD6)
