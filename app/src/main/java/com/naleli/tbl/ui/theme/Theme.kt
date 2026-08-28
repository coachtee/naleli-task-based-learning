package com.naleli.tbl.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

// primary = Orange, not navy: in the NIBS spec the primary button IS the
// vibrant orange CTA, so mapping primary->orange makes every default
// Material Button the hero action without each screen restating colours.
// Navy is `secondary` — it carries structure (top bars, the Current Focus
// anchor, secondary-button outlines), which screens reference via the
// Hero* tokens.
private val LightColors = lightColorScheme(
    primary = NibsOrange,
    onPrimary = SurfaceWhite,
    primaryContainer = NibsOrangeTint,
    onPrimaryContainer = NibsOrangeDark,
    secondary = NibsNavy,
    onSecondary = SurfaceWhite,
    secondaryContainer = SlateSurface,
    onSecondaryContainer = NibsNavy,
    background = CanvasBackground,
    onBackground = NibsNavy,
    surface = SurfaceWhite,
    onSurface = NibsNavy,
    surfaceVariant = SlateSurface,
    onSurfaceVariant = SlateGray,
    outline = BorderSlate,
    error = ErrorRed,
    onError = SurfaceWhite,
    errorContainer = ErrorRedBg,
    onErrorContainer = ErrorRed,
)

private val DarkColors = darkColorScheme(
    primary = NibsOrange,
    onPrimary = SurfaceWhite,
    primaryContainer = NibsOrangeDark,
    onPrimaryContainer = NibsOrangeTint,
    secondary = NaleliTextSecondaryDark,
    onSecondary = NaleliNavyDeep,
    background = NaleliNavyDeep,
    onBackground = SurfaceWhite,
    surface = NaleliNavySurface,
    onSurface = SurfaceWhite,
    // Distinct from `surface`: progress-track fills and grouped row
    // backgrounds were invisible against their own card when these matched.
    surfaceVariant = DarkSurfaceVariant,
    onSurfaceVariant = NaleliTextSecondaryDark,
    outline = DarkOutline,
    error = DarkError,
    onError = DarkOnError,
    errorContainer = DarkErrorContainer,
    onErrorContainer = DarkOnErrorContainer,
)

/**
 * Light / Dark / System, persisted as the app's one UI preference.
 *
 * Deliberately no `dynamicColor` param: Material You's wallpaper-derived
 * palette (Android 12+) is the usual Compose default, but it would
 * replace the NIBS navy/orange identity with whatever colour the
 * learner's wallpaper happens to be.
 */
@Composable
fun NaleliTheme(
    themeMode: ThemeMode = ThemeMode.LIGHT,
    content: @Composable () -> Unit,
) {
    val darkTheme = when (themeMode) {
        ThemeMode.LIGHT -> false
        ThemeMode.DARK -> true
        ThemeMode.SYSTEM -> isSystemInDarkTheme()
    }
    val colorScheme = if (darkTheme) DarkColors else LightColors
    MaterialTheme(
        colorScheme = colorScheme,
        typography = NaleliTypography,
        shapes = NaleliShapes,
        content = content,
    )
}
