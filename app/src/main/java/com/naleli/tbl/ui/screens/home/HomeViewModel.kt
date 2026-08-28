package com.naleli.tbl.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.content.TaskTier
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import com.naleli.tbl.domain.ProjectHealth
import com.naleli.tbl.domain.ProjectHealthCalculator
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.currentTaskId
import com.naleli.tbl.domain.isTaskLocked
import com.naleli.tbl.domain.projectDayNumber
import com.naleli.tbl.domain.taskProgressState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class WorkspaceStatBoard(val toDo: Int, val inProgress: Int, val submitted: Int, val competent: Int)

data class ActivityEvent(val title: String, val subtitle: String, val timestamp: Long)

data class HomeUiState(
    val isLoading: Boolean = true,
    val profile: LearnerProfileEntity? = null,
    val course: Course? = null,
    val dayNumber: Int = 1,
    val progressPercent: Int = 0,
    val health: ProjectHealth = ProjectHealth.ON_TRACK,
    val daysRemaining: Int = 89,
    val nextMilestoneTitle: String = "",
    val statBoard: WorkspaceStatBoard = WorkspaceStatBoard(0, 0, 0, 0),
    val priorityTask: WorkTask? = null,
    val priorityTaskState: TaskProgressState = TaskProgressState.NOT_STARTED,
    val recentActivity: List<ActivityEvent> = emptyList(),
)

class HomeViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(HomeUiState())
    val state: StateFlow<HomeUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            container.workspaceRepository.seedDemoHistoryIfNeeded(profile, container.profileRepository)
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
        val allTasks = WorkspaceMockContent.allTasks()
        val reachableTasks = allTasks.filterNot { isTaskLocked(it.taskId, assessmentByTask) }

        val stateByTask = reachableTasks.associate { it.taskId to taskProgressState(it, subStepStatuses, assessmentByTask[it.taskId]) }
        val competentCount = stateByTask.values.count { it == TaskProgressState.COMPETENT }

        val statBoard = WorkspaceStatBoard(
            toDo = stateByTask.values.count { it == TaskProgressState.NOT_STARTED || it == TaskProgressState.NEEDS_REVISION },
            inProgress = stateByTask.values.count { it == TaskProgressState.IN_PROGRESS },
            submitted = stateByTask.values.count { it == TaskProgressState.SUBMITTED },
            competent = competentCount,
        )

        val priorityTask = currentTaskId(subStepStatuses, assessmentByTask)?.let { WorkspaceMockContent.taskById(it) }

        val nextMilestone = allTasks.firstOrNull {
            it.tier == TaskTier.ASSESSMENT && (assessmentByTask[it.taskId]?.result ?: CompetenceResult.NOT_YET_ASSESSED) != CompetenceResult.COMPETENT
        }?.title ?: course.stages.getOrNull(1)?.name ?: "Next Phase"

        val recentActivity = assessmentByTask.values
            .filter { it.assessedAt != null }
            .sortedByDescending { it.assessedAt }
            .take(3)
            .mapNotNull { assessment ->
                val task = WorkspaceMockContent.taskById(assessment.taskId) ?: return@mapNotNull null
                ActivityEvent(
                    title = task.title,
                    subtitle = if (assessment.result == CompetenceResult.COMPETENT) "Marked Competent" else "Needs another look",
                    timestamp = assessment.assessedAt ?: 0L,
                )
            }

        return HomeUiState(
            isLoading = false,
            profile = profile,
            course = course,
            dayNumber = dayNumber,
            progressPercent = (dayNumber * 100) / course.totalDays,
            health = ProjectHealthCalculator.evaluate(dayNumber, course.totalDays, competentCount, WorkspaceMockContent.PHASE_1_PLANNED_TASK_COUNT),
            daysRemaining = course.totalDays - dayNumber,
            nextMilestoneTitle = nextMilestone,
            statBoard = statBoard,
            priorityTask = priorityTask,
            priorityTaskState = priorityTask?.let { stateByTask[it.taskId] } ?: TaskProgressState.NOT_STARTED,
            recentActivity = recentActivity,
        )
    }
}
