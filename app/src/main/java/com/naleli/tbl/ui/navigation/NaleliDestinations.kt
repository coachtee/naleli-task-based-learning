package com.naleli.tbl.ui.navigation

/** Route constants for Navigation Compose. Kept as plain strings/functions —
 * the app is small enough that a type-safe nav library would be extra
 * ceremony.
 *
 * Renamed for the Naleli Workspace rebuild (MY_LEARNING -> JOURNEY,
 * EVIDENCE -> MY_WORK): unlike the V1.5 relabel, this is a genuine
 * information-architecture change — the screens underneath are new, not
 * renamed, so keeping the old route names here would be the confusing
 * choice, not the safe one. */
object NaleliDestinations {
    const val WELCOME = "welcome"
    const val CREATE_PROFILE = "create_profile"
    const val EDIT_PROFILE = "edit_profile"
    const val PORTFOLIO_SETUP = "portfolio_setup"

    const val HOME = "home"
    const val MY_WORK = "my_work"
    const val JOURNEY = "journey"
    const val PORTFOLIO = "portfolio"
    const val PROFILE = "profile"

    const val PROGRESS = "progress"
    const val CERTIFICATE = "certificate"
    const val HELP = "help"
    const val BACKUP = "backup"
    const val PRIVACY = "privacy"

    const val TASK_WORKSPACE_PATTERN = "task/{taskId}"
    fun taskWorkspace(taskId: String) = "task/$taskId"

    const val ASSESSMENT_PATTERN = "task/{taskId}/assessment"
    fun assessment(taskId: String) = "task/$taskId/assessment"

    const val ADD_EVIDENCE_PATTERN = "task/{taskId}/evidence"
    fun addEvidence(taskId: String) = "task/$taskId/evidence"

    val BOTTOM_NAV_ROUTES = listOf(HOME, MY_WORK, JOURNEY, PORTFOLIO, PROFILE)
}
