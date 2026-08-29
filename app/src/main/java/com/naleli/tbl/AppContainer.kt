package com.naleli.tbl

import android.content.Context
import com.naleli.tbl.data.content.ContentRepository
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.NaleliDatabase
import com.naleli.tbl.data.preferences.WorkspacePreferences
import com.naleli.tbl.data.repository.BackupRepository
import com.naleli.tbl.data.repository.CertificateRepository
import com.naleli.tbl.data.repository.EvidenceRepository
import com.naleli.tbl.data.repository.PortfolioRepository
import com.naleli.tbl.data.repository.ProfileRepository
import com.naleli.tbl.data.repository.ProgressRepository
import com.naleli.tbl.data.repository.WorkspaceRepository
import com.naleli.tbl.ui.theme.ThemePreferences

/**
 * A small hand-rolled DI container instead of Hilt/Koin — the app is a
 * single module with a handful of repositories in V1; a DI framework would
 * be pure ceremony here. Swapping one in later (if the app grows) is a
 * mechanical change since every screen already receives its dependencies
 * through this one seam. See docs/ARCHITECTURE.md.
 */
class AppContainer(val context: Context) {
    private val database = NaleliDatabase.getInstance(context)

    init {
        // Parse the 90-day curriculum once, before any screen composes.
        // NaleliApplication builds this container in onCreate, so every
        // ViewModel and calculator can read WorkspaceCurriculum directly
        // without a not-loaded-yet state to handle.
        WorkspaceCurriculum.load(context)
    }

    val themePreferences = ThemePreferences(context)
    val workspacePreferences = WorkspacePreferences(context)

    val contentRepository = ContentRepository(context)
    val profileRepository = ProfileRepository(database.learnerProfileDao())
    val progressRepository = ProgressRepository(database.dayProgressDao(), database.taskStatusDao())
    val evidenceRepository = EvidenceRepository(context, database.evidenceDao())
    val portfolioRepository = PortfolioRepository(database.portfolioDao())
    val workspaceRepository = WorkspaceRepository(database.subStepStatusDao(), database.assessmentDao(), evidenceRepository)
    val certificateRepository = CertificateRepository(context, database.certificateDao())
    val backupRepository = BackupRepository(
        context = context,
        profileDao = database.learnerProfileDao(),
        dayDao = database.dayProgressDao(),
        taskDao = database.taskStatusDao(),
        evidenceDao = database.evidenceDao(),
        portfolioDao = database.portfolioDao(),
        certificateDao = database.certificateDao(),
    )
}
