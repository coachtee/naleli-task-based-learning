package com.naleli.tbl.ui.theme

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

enum class ThemeMode { LIGHT, DARK, SYSTEM }

/**
 * The app's one persisted UI preference (V1.5.1 §6). SharedPreferences is
 * enough for a single enum value — no new dependency, no DataStore. Backed
 * by a Compose State so anything reading [mode] recomposes the instant it
 * changes, without a ViewModel/Flow layer for one setting.
 */
class ThemePreferences(context: Context) {
    private val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    // Named "mode" (not "themeMode") so `NaleliTheme(themeMode = container.themePreferences.mode)`
    // reads cleanly — but a `var mode` property auto-generates a JVM
    // `setMode(ThemeMode)` accessor, which collides with an explicitly
    // declared `fun setMode(...)` at the bytecode level even though the
    // property setter is private ("Platform declaration clash"). Named the
    // mutator `updateMode` instead to avoid it.
    var mode: ThemeMode by mutableStateOf(readStored())
        private set

    fun updateMode(newMode: ThemeMode) {
        mode = newMode
        prefs.edit().putString(KEY_MODE, newMode.name).apply()
    }

    // Defaults to LIGHT, not SYSTEM: the NIBS spec designs one look — navy
    // structure and orange actions on a soft off-white canvas — so a
    // learner whose phone happens to be in dark mode would otherwise never
    // see the designed app. Dark remains available under Appearance and is
    // tuned onto the same navy/orange identity.
    private fun readStored(): ThemeMode {
        val stored = prefs.getString(KEY_MODE, null) ?: return ThemeMode.LIGHT
        return runCatching { ThemeMode.valueOf(stored) }.getOrDefault(ThemeMode.LIGHT)
    }

    private companion object {
        const val PREFS_NAME = "naleli_theme_prefs"
        const val KEY_MODE = "theme_mode"
    }
}
