package com.naleli.tbl.ui.screens.journey

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.CourseStage
import com.naleli.tbl.data.content.Workstream
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.currentTaskId
import com.naleli.tbl.domain.taskProgressState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class WorkstreamUi(val workstream: Workstream, val completedCount: Int, val totalCount: Int, val currentTaskId: String?)

/** One of the four project phases, with its real workstreams. [isActive] is
 * the phase holding the current task — it is the one that opens; the others
 * stay collapsed (past ones complete, future ones locked). */
data class PhaseUi(
    val stage: CourseStage,
    val workstreams: List<WorkstreamUi>,
    val completedCount: Int,
    val plannedCount: Int,
    val isActive: Boolean,
) {
    val isComplete: Boolean get() = plannedCount > 0 && completedCount == plannedCount
}

data class JourneyUiState(
    val isLoading: Boolean = true,
    val projectTitle: String = "",
    val phases: List<PhaseUi> = emptyList(),
)

/** The 90 days as four project phases, not a 90-row list (brief §5). Every
 * phase now carries real workbook content, so all four are built from the
 * curriculum's own stage ids rather than one live phase plus locked stubs. */
class JourneyViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(JourneyUiState())
    val state: StateFlow<JourneyUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)

            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
            ) { subSteps, assessments ->
                val subStepStatuses = subSteps.associateBy { it.subStepId }
                val assessmentByTask = assessments.associateBy { it.taskId }
                val currentId = currentTaskId(subStepStatuses, assessmentByTask)
                val activeStageId = currentId?.let { WorkspaceCurriculum.stageIdFor(it) }

                val phases = course.stages.map { stage ->
                    val workstreams = WorkspaceCurriculum.workstreamsForStage(stage.stageId).map { ws ->
                        val completed = ws.tasks.count {
                            taskProgressState(it, subStepStatuses, assessmentByTask[it.taskId]) == TaskProgressState.COMPETENT
                        }
                        val currentInThisWorkstream = currentId?.takeIf { id -> ws.tasks.any { it.taskId == id } }
                        WorkstreamUi(ws, completed, ws.tasks.size, currentTaskId = currentInThisWorkstream)
                    }
                    PhaseUi(
                        stage = stage,
                        workstreams = workstreams,
                        completedCount = workstreams.sumOf { it.completedCount },
                        plannedCount = workstreams.sumOf { it.totalCount },
                        isActive = stage.stageId == activeStageId,
                    )
                }

                JourneyUiState(
                    isLoading = false,
                    projectTitle = course.projectTitle,
                    phases = phases,
                )
            }.collect { _state.value = it }
        }
    }
}
