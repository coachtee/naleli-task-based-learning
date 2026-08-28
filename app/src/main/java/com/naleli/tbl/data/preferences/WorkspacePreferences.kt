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

    fun setStorageChoice(choice: StorageChoice) {
        storageChoice = choice
        prefs.edit().putString(KEY_STORAGE, choice.name).apply()
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
    }
}
