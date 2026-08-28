package com.naleli.tbl.ui.screens.journey

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.CourseStage
import com.naleli.tbl.data.content.Workstream
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.currentTaskId
import com.naleli.tbl.domain.taskProgressState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class WorkstreamUi(val workstream: Workstream, val completedCount: Int, val totalCount: Int, val currentTaskId: String?)

data class JourneyUiState(
    val isLoading: Boolean = true,
    val projectTitle: String = "",
    val phase1Name: String = "",
    val phase1Description: String = "",
    val phase1DayRange: String = "",
    val phase1CompletedCount: Int = 0,
    val phase1PlannedCount: Int = WorkspaceMockContent.PHASE_1_PLANNED_TASK_COUNT,
    val workstreams: List<WorkstreamUi> = emptyList(),
    val lockedPhases: List<CourseStage> = emptyList(),
)

/** The 90 days as four project phases, not a 90-row list (brief §5) — Phase
 * 1 opens into its workstreams; the rest stay locked stubs sourced from the
 * course's existing stage definitions. */
class JourneyViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(JourneyUiState())
    val state: StateFlow<JourneyUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)
            val phase1Stage = course.stages.firstOrNull { it.stageId == WorkspaceMockContent.PHASE_1_STAGE_ID }
            val lockedPhases = course.stages.filter { it.stageId != WorkspaceMockContent.PHASE_1_STAGE_ID }

            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
            ) { subSteps, assessments ->
                val subStepStatuses = subSteps.associateBy { it.subStepId }
                val assessmentByTask = assessments.associateBy { it.taskId }
                val currentId = currentTaskId(subStepStatuses, assessmentByTask)

                val workstreams = WorkspaceMockContent.phase1Workstreams.map { ws ->
                    val completed = ws.tasks.count { taskProgressState(it, subStepStatuses, assessmentByTask[it.taskId]) == TaskProgressState.COMPETENT }
                    val currentInThisWorkstream = currentId?.takeIf { id -> ws.tasks.any { it.taskId == id } }
                    WorkstreamUi(ws, completed, ws.tasks.size, currentTaskId = currentInThisWorkstream)
                }
                val phase1Completed = workstreams.sumOf { it.completedCount }

                JourneyUiState(
                    isLoading = false,
                    projectTitle = course.projectTitle,
                    phase1Name = phase1Stage?.name ?: "Learn the Role",
                    phase1Description = phase1Stage?.description.orEmpty(),
                    phase1DayRange = phase1Stage?.let { "Days ${it.dayStart}–${it.dayEnd}" } ?: "",
                    phase1CompletedCount = phase1Completed,
                    workstreams = workstreams,
                    lockedPhases = lockedPhases,
                )
            }.collect { _state.value = it }
        }
    }
}
