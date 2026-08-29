package com.naleli.tbl.data.preferences

import android.content.Context
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

enum class StorageChoice { THIS_DEVICE, GOOGLE_DRIVE, ONEDRIVE }

/**
 * Where the learner's evidence lives. Per the Workspace build brief: This
 * Device is the default and what the app works fully offline with — Google
 * Drive / OneDrive are an optional upgrade a learner can connect later, not
 * a requirement to start using the app.
 */
class WorkspacePreferences(context: Context) {
    private val prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)

    var storageChoice: StorageChoice by mutableStateOf(readStorageChoice())
        private set

    var portfolioSetupComplete: Boolean by mutableStateOf(prefs.getBoolean(KEY_SETUP_DONE, false))
        private set

    /** The orientation is shown once, between creating a profile and Day 1.
     * A learner who has seen it should never be made to sit through it
     * again on a relaunch. */
    var orientationComplete: Boolean by mutableStateOf(prefs.getBoolean(KEY_ORIENTATION_DONE, false))
        private set

    // Named updateStorageChoice, not setStorageChoice: a `var storageChoice`
    // property auto-generates a JVM setStorageChoice(StorageChoice) accessor
    // (even with a private setter), which collides with an explicitly
    // declared function of that name at the bytecode level ("Platform
    // declaration clash") — the same bug class already fixed once this
    // session in ThemePreferences.updateMode.
    fun updateStorageChoice(choice: StorageChoice) {
        storageChoice = choice
        prefs.edit().putString(KEY_STORAGE, choice.name).apply()
    }

    fun markOrientationComplete() {
        orientationComplete = true
        prefs.edit().putBoolean(KEY_ORIENTATION_DONE, true).apply()
    }

    fun markPortfolioSetupComplete() {
        portfolioSetupComplete = true
        prefs.edit().putBoolean(KEY_SETUP_DONE, true).apply()
    }

    /** Called alongside deleting all learner data — otherwise a freshly
     * created profile would silently inherit the previous learner's storage
     * choice and skip onboarding, since this preference lives outside Room. */
    fun resetForNewLearner() {
        storageChoice = StorageChoice.THIS_DEVICE
        portfolioSetupComplete = false
        orientationComplete = false
        prefs.edit().clear().apply()
    }

    private fun readStorageChoice(): StorageChoice {
        val stored = prefs.getString(KEY_STORAGE, null) ?: return StorageChoice.THIS_DEVICE
        return runCatching { StorageChoice.valueOf(stored) }.getOrDefault(StorageChoice.THIS_DEVICE)
    }

    private companion object {
        const val PREFS_NAME = "naleli_workspace_prefs"
        const val KEY_STORAGE = "storage_choice"
        const val KEY_SETUP_DONE = "portfolio_setup_complete"
        const val KEY_ORIENTATION_DONE = "orientation_complete"
    }
}
