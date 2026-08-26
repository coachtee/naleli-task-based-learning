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
    surfaceVariant = NaleliNavySurface,
    onSurfaceVariant = BorderGrey,
    outline = NaleliNavyBorder,
    error = ErrorRed,
    onError = SurfaceWhite,
)

@Composable
fun NaleliTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    content: @Composable () -> Unit,
) {
    val colorScheme = if (darkTheme) DarkColors else LightColors
    MaterialTheme(
        colorScheme = colorScheme,
        typography = NaleliTypography,
        shapes = NaleliShapes,
        content = content,
    )
}
