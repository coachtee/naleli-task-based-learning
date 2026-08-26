package com.naleli.tbl.ui.navigation

/** Route constants for Navigation Compose. Kept as plain strings/functions — the
 * app is small enough that a type-safe nav library would be extra ceremony.
 *
 * Route names stay close to their V1 meaning (MY_LEARNING, EVIDENCE) even
 * though V1.5 relabels them in the UI (Learn, Work) — see
 * docs/ARCHITECTURE.md for why: renaming routes/files this late is pure
 * churn risk for zero user-facing benefit; only the displayed labels and
 * screen content change. */
object NaleliDestinations {
    const val SPLASH = "splash"
    const val WELCOME = "welcome"
    const val CREATE_PROFILE = "create_profile"
    const val EDIT_PROFILE = "edit_profile"

    const val HOME = "home"
    const val MY_LEARNING = "my_learning"
    const val EVIDENCE = "evidence"
    const val PORTFOLIO = "portfolio"
    const val PROFILE = "profile"

    const val PROGRESS = "progress"
    const val CERTIFICATE = "certificate"
    const val HELP = "help"
    const val BACKUP = "backup"
    const val PRIVACY = "privacy"

    const val DAY_DETAIL_PATTERN = "day/{dayNumber}"
    fun dayDetail(dayNumber: Int) = "day/$dayNumber"

    const val TASK_DETAIL_PATTERN = "day/{dayNumber}/task/{taskId}"
    fun taskDetail(dayNumber: Int, taskId: String) = "day/$dayNumber/task/$taskId"

    const val ADD_EVIDENCE_PATTERN = "day/{dayNumber}/task/{taskId}/evidence"
    fun addEvidence(dayNumber: Int, taskId: String) = "day/$dayNumber/task/$taskId/evidence"

    const val QR_LOOKUP = "qr_lookup"

    val BOTTOM_NAV_ROUTES = listOf(HOME, MY_LEARNING, EVIDENCE, PORTFOLIO, PROFILE)
}
