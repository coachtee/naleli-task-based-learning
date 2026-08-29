package com.naleli.tbl.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.content.TaskTier
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.currentTaskId
import com.naleli.tbl.domain.projectDayNumber
import com.naleli.tbl.domain.taskProgressState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class ActivityEvent(val title: String, val subtitle: String, val timestamp: Long)

data class HomeUiState(
    val isLoading: Boolean = true,
    val profile: LearnerProfileEntity? = null,
    val course: Course? = null,
    val dayNumber: Int = 1,
    val nextMilestoneTitle: String = "",
    val priorityTask: WorkTask? = null,
    val priorityTaskState: TaskProgressState = TaskProgressState.NOT_STARTED,
    val priorityStepsDone: Int = 0,
    val priorityStepsTotal: Int = 0,
    val priorityHint: String? = null,
    val recentAchievement: ActivityEvent? = null,
)

class HomeViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(HomeUiState())
    val state: StateFlow<HomeUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)

            combine(
                container.profileRepository.observeProfile(),
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
            ) { currentProfile, subSteps, assessments ->
                val activeProfile = currentProfile ?: profile
                val subStepStatuses = subSteps.associateBy { it.subStepId }
                val assessmentByTask = assessments.associateBy { it.taskId }
                buildState(activeProfile, course, subStepStatuses, assessmentByTask)
            }.collect { _state.value = it }
        }
    }

    private fun buildState(
        profile: LearnerProfileEntity,
        course: Course,
        subStepStatuses: Map<String, SubStepStatusEntity>,
        assessmentByTask: Map<String, AssessmentEntity>,
    ): HomeUiState {
        val dayNumber = projectDayNumber(profile, course.totalDays)
        val allTasks = WorkspaceCurriculum.allTasks()

        val priorityTask = currentTaskId(subStepStatuses, assessmentByTask)?.let { WorkspaceCurriculum.taskById(it) }
        val priorityState = priorityTask?.let { taskProgressState(it, subStepStatuses, assessmentByTask[it.taskId]) }
            ?: TaskProgressState.NOT_STARTED
        val priorityStepsDone = priorityTask?.subSteps?.count { subStepStatuses[it.subStepId]?.complete == true } ?: 0
        val priorityStepsTotal = priorityTask?.subSteps?.size ?: 0
        val priorityNextStepTitle = priorityTask?.subSteps?.firstOrNull { subStepStatuses[it.subStepId]?.complete != true }?.title
        val priorityHint = when {
            priorityTask == null -> null
            priorityNextStepTitle != null -> "Next: $priorityNextStepTitle"
            priorityState == TaskProgressState.SUBMITTED -> "Awaiting assessment."
            priorityState == TaskProgressState.NEEDS_REVISION -> "Resubmit for assessment."
            else -> "Ready to submit for assessment."
        }

        // The nearest real checkpoint is finishing the workstream in hand —
        // a few days out. The capstone tasks are the only ASSESSMENT-tier
        // ones in the 90-day curriculum, so keying off those would show the
        // same day-80 milestone for the first seventy-nine days.
        val currentWorkstream = priorityTask?.let { WorkspaceCurriculum.workstreamFor(it.taskId) }
        val nextMilestone = when {
            currentWorkstream != null -> {
                val lastDay = currentWorkstream.tasks.maxOfOrNull { it.dayNumber } ?: 0
                "Finish ${currentWorkstream.name} · Day $lastDay"
            }
            else -> allTasks.firstOrNull {
                it.tier == TaskTier.ASSESSMENT &&
                    (assessmentByTask[it.taskId]?.result ?: CompetenceResult.NOT_YET_ASSESSED) != CompetenceResult.COMPETENT
            }?.title ?: course.stages.lastOrNull()?.name ?: "Next Phase"
        }

        val recentAchievement = assessmentByTask.values
            .filter { it.assessedAt != null && it.result == CompetenceResult.COMPETENT }
            .maxByOrNull { it.assessedAt ?: 0L }
            ?.let { assessment ->
                WorkspaceCurriculum.taskById(assessment.taskId)?.let { task ->
                    ActivityEvent(title = task.title, subtitle = "Competence recorded", timestamp = assessment.assessedAt ?: 0L)
                }
            }

        return HomeUiState(
            isLoading = false,
            profile = profile,
            course = course,
            dayNumber = dayNumber,
            nextMilestoneTitle = nextMilestone,
            priorityTask = priorityTask,
            priorityTaskState = priorityState,
            priorityStepsDone = priorityStepsDone,
            priorityStepsTotal = priorityStepsTotal,
            priorityHint = priorityHint,
            recentAchievement = recentAchievement,
        )
    }
}
