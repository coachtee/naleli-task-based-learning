package com.naleli.tbl.ui.screens.portfolio

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.WorkspaceSnapshot
import com.naleli.tbl.domain.isWrittenAnswer
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

/** One piece of evidence, with the text itself when the learner typed it.
 * A written answer whose words are hidden behind a filename is not a
 * portfolio entry — it is a receipt for one. */
data class EvidenceItem(
    val entity: EvidenceEntity,
    val writtenText: String? = null,
) {
    val isWritten: Boolean get() = writtenText != null
}

data class SkillTaskRow(
    val task: WorkTask,
    val state: TaskProgressState,
    val evidence: List<EvidenceItem>,
    val assessedAt: Long?,
)

data class PortfolioSkillUiState(
    val isLoading: Boolean = true,
    val skillName: String = "",
    val result: CompetenceResult = CompetenceResult.NOT_YET_ASSESSED,
    val tasksCompetent: Int = 0,
    val tasksTotal: Int = 0,
    val evidenceCount: Int = 0,
    val rows: List<SkillTaskRow> = emptyList(),
)

/**
 * What actually backs one portfolio skill: the days that build it, the
 * evidence produced on each, and whether competence was recorded.
 *
 * The portfolio list could say "Competent · 3 evidence items" but had no
 * way to show the three items, which made the claim unverifiable by the
 * person it belongs to.
 */
class PortfolioSkillViewModel(
    private val container: AppContainer,
    private val skillName: String,
) : ViewModel() {
    private val _state = MutableStateFlow(PortfolioSkillUiState())
    val state: StateFlow<PortfolioSkillUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val tasks = WorkspaceCurriculum.allTasks().filter { it.skillDeveloped == skillName }

            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeAll(),
            ) { subSteps, assessments, evidence ->
                val snapshot = WorkspaceSnapshot.of(subSteps, assessments, evidence)
                val rows = tasks.map { task ->
                    SkillTaskRow(
                        task = task,
                        state = snapshot.stateOf(task),
                        evidence = snapshot.evidenceFor(task.taskId).map { it.toItem() },
                        assessedAt = snapshot.assessmentOf(task.taskId)?.assessedAt,
                    )
                }
                val results = tasks.mapNotNull { snapshot.assessmentOf(it.taskId)?.result }
                PortfolioSkillUiState(
                    isLoading = false,
                    skillName = skillName,
                    result = when {
                        results.any { it == CompetenceResult.COMPETENT } -> CompetenceResult.COMPETENT
                        results.any { it == CompetenceResult.REQUIRES_IMPROVEMENT } -> CompetenceResult.REQUIRES_IMPROVEMENT
                        else -> CompetenceResult.NOT_YET_ASSESSED
                    },
                    tasksCompetent = rows.count { it.state == TaskProgressState.COMPETENT },
                    tasksTotal = rows.size,
                    evidenceCount = rows.sumOf { it.evidence.size },
                    rows = rows,
                )
            }.collect { _state.value = it }
        }
    }

    /** Written answers are small text files the app wrote itself, so they
     * are safe to read inline; anything else stays a reference. A file the
     * learner has since deleted from disk reads as null rather than
     * throwing — the row still shows what was submitted. */
    private suspend fun EvidenceEntity.toItem(): EvidenceItem {
        if (!isWrittenAnswer()) return EvidenceItem(this)
        val text = withContext(Dispatchers.IO) {
            runCatching { File(localPath).readText() }.getOrNull()
        }
        return EvidenceItem(this, writtenText = text)
    }
}
