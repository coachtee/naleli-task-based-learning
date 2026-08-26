package com.naleli.tbl.ui.navigation

/** Route constants for Navigation Compose. Kept as plain strings/functions — the
 * app is small enough that a type-safe nav library would be extra ceremony. */
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
    const val SETTINGS = "settings"
    const val HELP = "help"
    const val BACKUP = "backup"
    const val PRIVACY = "privacy"

    const val DAY_DETAIL_PATTERN = "day/{dayNumber}"
    fun dayDetail(dayNumber: Int) = "day/$dayNumber"

    val BOTTOM_NAV_ROUTES = listOf(HOME, MY_LEARNING, EVIDENCE, PORTFOLIO, PROFILE)
}
