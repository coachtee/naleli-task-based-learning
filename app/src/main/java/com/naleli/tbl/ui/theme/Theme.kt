package com.naleli.tbl.ui.theme

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable

private val LightColors = lightColorScheme(
    primary = NaleliPurple,
    onPrimary = SurfaceWhite,
    primaryContainer = NaleliPurpleLight,
    onPrimaryContainer = NaleliPurpleDark,
    secondary = NaleliNavySoft,
    onSecondary = SurfaceWhite,
    background = SurfaceWhite,
    onBackground = NaleliNavy,
    surface = SurfaceWhite,
    onSurface = NaleliNavy,
    surfaceVariant = SurfaceGrey,
    onSurfaceVariant = NaleliNavySoft,
    outline = BorderGrey,
    error = ErrorRed,
    onError = SurfaceWhite,
    errorContainer = ErrorRedBg,
    onErrorContainer = ErrorRed,
)

private val DarkColors = darkColorScheme(
    primary = NaleliPurpleLight,
    onPrimary = NaleliPurpleDark,
    primaryContainer = NaleliPurpleDark,
    onPrimaryContainer = NaleliPurpleLight,
    secondary = BorderGrey,
    onSecondary = NaleliNavyDeep,
    background = NaleliNavyDeep,
    onBackground = SurfaceWhite,
    surface = NaleliNavySurface,
    onSurface = SurfaceWhite,
    // Was equal to `surface` — every surfaceVariant-styled element (progress
    // track fills, the segmented tab background) was literally invisible
    // against its own card. Now a distinct, lighter navy step.
    surfaceVariant = DarkSurfaceVariant,
    onSurfaceVariant = BorderGrey,
    // Was NaleliNavyBorder, ~1.3:1 against `surface` — card borders and
    // outlined-button strokes were effectively invisible in dark mode.
    outline = DarkOutline,
    // The light-theme ErrorRed reads as under ~2.5:1 on the dark surfaces —
    // below WCAG AA for text. Uses the M3-standard dark error pairing.
    error = DarkError,
    onError = DarkOnError,
    errorContainer = DarkErrorContainer,
    onErrorContainer = DarkOnErrorContainer,
)

/** Light / Dark / System — the only persisted UI preference in the app. */
@Composable
fun NaleliTheme(
    themeMode: ThemeMode = ThemeMode.SYSTEM,
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
