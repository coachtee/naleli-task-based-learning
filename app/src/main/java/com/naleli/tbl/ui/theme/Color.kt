package com.naleli.tbl.ui.theme

import androidx.compose.ui.graphics.Color

// NIBS Branding & UI System Specification — this file is the code-side
// source of truth for that spec; keep the two in sync if either changes.
//
// The identity is: Deep Navy structure, Vibrant Orange action, on a soft
// off-white canvas. Orange is the ONE call-to-action colour (primary
// buttons, the active workflow step, focus rings); navy carries structure
// (top bars, the Current Focus anchor, secondary-button outlines).

/** Primary Deep Navy — top bars, primary headings, dark surfaces. */
val NibsNavy = Color(0xFF0A1140)
val NibsNavyRaised = Color(0xFF141C55)

/** Vibrant Innovation Orange — hero CTAs, active step, high-priority
 * progression. The spec value; the logo asset itself samples #EE5A00,
 * near-identical, and the spec wins for UI. */
val NibsOrange = Color(0xFFF05A00)
val NibsOrangeDark = Color(0xFFC24700)
val NibsOrangeTint = Color(0xFFFFEDE3)

/** Canvas — soft off-white, chosen over stark white to cut glare. */
val CanvasBackground = Color(0xFFF8FAFC)
val SurfaceWhite = Color(0xFFFFFFFF)

/** 1px card borders and hairline dividers. */
val BorderSlate = Color(0xFFE2E8F0)

/** Locked / subordinate text. */
val SlateGray = Color(0xFF64748B)

/** Soft slate fill — grouped setting-row icon backgrounds. */
val SlateSurface = Color(0xFFF1F5F9)

// Semantic status colours. Each has exactly one meaning, everywhere.
/** Competent / Completed / Verified evidence. */
val SuccessGreen = Color(0xFF059669)
val SuccessGreenBg = Color(0xFFE3F6EF)
// The same success signal, lightened for navy surfaces. SuccessGreen is
// tuned for white cards and goes muddy on the hero; this is that colour on
// dark, not a second green in the system.
val SuccessGreenOnDark = Color(0xFF34D399)

/** Required tasks. */
val ErrorRed = Color(0xFFDC2626)
val ErrorRedBg = Color(0xFFFBEAEA)

/** Checkpoint assessments. */
val AssessmentPurple = Color(0xFF7C3AED)

/** In-progress / attention — the brand orange doing semantic duty, which
 * is why "high-priority progression states" sits in the orange spec entry. */
val WarningOrange = NibsOrange
val WarningOrangeBg = NibsOrangeTint

// Dark-theme surfaces. The spec designs a light app; dark mode is kept as
// a learner preference and re-tuned onto the same navy/orange identity
// rather than a separate palette, so the brand reads the same either way.
val NaleliNavyDeep = Color(0xFF060B27)
val NaleliNavySurface = NibsNavy
val NaleliNavyElevated = NibsNavyRaised
val NaleliNavyBorder = Color(0xFF2A3266)
val NaleliTextSecondaryDark = Color(0xFFAAB8C8)

// "Hero" surface — the navy used for the top bar, bottom navigation and
// the Current Focus anchor card, on both light and dark.
val HeroSurface = NibsNavy
val HeroSurfaceRaised = NibsNavyRaised
val OnHeroSurface = SurfaceWhite
val OnHeroSurfaceSoft = Color(0xFFB8BFCC)

// Dark-theme-only tokens: chosen so outlines, track fills and error text
// hold real contrast against the dark navy surfaces, where the light-theme
// values (tuned for an off-white canvas) go nearly invisible.
val DarkOutline = NaleliNavyBorder
val DarkSurfaceVariant = NibsNavyRaised
// A lighter tint of the crimson, not the saturated value — #DC2626 reads
// too dark to stay legible as small text on near-black.
val DarkError = Color(0xFFF87171)
val DarkOnError = Color(0xFF450A0A)
val DarkErrorContainer = Color(0xFF7F1D1D)
val DarkOnErrorContainer = Color(0xFFFFDAD6)

// The wordmark's real ink, sampled from the logo asset — used only for the
// institutional wordmark on Welcome, never as a UI colour.
val NibsWordmarkNavy = Color(0xFF02084B)
val NaleliLightBlue = Color(0xFF8CCBFF)
