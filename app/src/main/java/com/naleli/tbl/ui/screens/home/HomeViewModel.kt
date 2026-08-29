package com.naleli.tbl.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.content.TaskTier
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.WorkspaceSnapshot
import com.naleli.tbl.domain.projectDayNumber
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
    /** The words on the one dominant button, derived from the state rather
     * than guessed at by the screen. */
    val priorityActionLabel: String = "OPEN TASK",
    val recentAchievement: ActivityEvent? = null,
    /** What the learner has actually banked so far — every figure counted
     * from real rows, never a display number. */
    val tasksCompetent: Int = 0,
    val tasksTotal: Int = 0,
    val evidenceCount: Int = 0,
    val phaseName: String = "",
    val phaseCompleted: Int = 0,
    val phaseTotal: Int = 0,
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
                container.evidenceRepository.observeAll(),
            ) { currentProfile, subSteps, assessments, evidence ->
                val activeProfile = currentProfile ?: profile
                buildState(activeProfile, course, WorkspaceSnapshot.of(subSteps, assessments, evidence), evidence.size)
            }.collect { _state.value = it }
        }
    }

    private fun buildState(
        profile: LearnerProfileEntity,
        course: Course,
        snapshot: WorkspaceSnapshot,
        evidenceCount: Int,
    ): HomeUiState {
        val assessmentByTask = snapshot.assessmentByTask
        val dayNumber = projectDayNumber(profile, course.totalDays)
        val allTasks = WorkspaceCurriculum.allTasks()

        val priorityTask = snapshot.currentTaskId?.let { WorkspaceCurriculum.taskById(it) }
        val priorityState = priorityTask?.let { snapshot.stateOf(it) } ?: TaskProgressState.NOT_STARTED
        val priorityStepsDone = priorityTask?.let { snapshot.stepsDone(it) } ?: 0
        val priorityStepsTotal = priorityTask?.subSteps?.size ?: 0
        val priorityNextStepTitle = priorityTask?.subSteps
            ?.firstOrNull { snapshot.subStepStatuses[it.subStepId]?.complete != true }?.title

        // The hint is ordered by state first, step second. Reading the next
        // unticked step first used to make a submitted task say "Next: Learn
        // the basics" while its badge read Submitted — the learner was told
        // to go redo work that was already with the assessor.
        val priorityHint = when {
            priorityTask == null -> null
            priorityState == TaskProgressState.SUBMITTED -> "Waiting for review. Nothing to do here yet."
            priorityState == TaskProgressState.NEEDS_REVISION -> "Feedback is waiting — open it to see what to change."
            priorityState == TaskProgressState.READY_TO_SUBMIT -> "Everything is done. Submit it for review."
            priorityNextStepTitle != null -> "Next: $priorityNextStepTitle"
            else -> "Attach your evidence, then submit."
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

        val competent = allTasks.count { assessmentByTask[it.taskId]?.result == CompetenceResult.COMPETENT }
        val phaseId = priorityTask?.let { WorkspaceCurriculum.stageIdFor(it.taskId) }
        val phaseTasks = phaseId?.let { WorkspaceCurriculum.tasksForStage(it) }.orEmpty()

        return HomeUiState(
            isLoading = false,
            tasksCompetent = competent,
            tasksTotal = allTasks.size,
            evidenceCount = evidenceCount,
            phaseName = course.stages.firstOrNull { it.stageId == phaseId }?.name.orEmpty(),
            phaseCompleted = phaseTasks.count { assessmentByTask[it.taskId]?.result == CompetenceResult.COMPETENT },
            phaseTotal = phaseTasks.size,
            profile = profile,
            course = course,
            dayNumber = dayNumber,
            nextMilestoneTitle = nextMilestone,
            priorityTask = priorityTask,
            priorityTaskState = priorityState,
            priorityStepsDone = priorityStepsDone,
            priorityStepsTotal = priorityStepsTotal,
            priorityHint = priorityHint,
            priorityActionLabel = when (priorityState) {
                TaskProgressState.NOT_STARTED -> "START TASK"
                TaskProgressState.IN_PROGRESS -> "CONTINUE WORK"
                TaskProgressState.READY_TO_SUBMIT -> "SUBMIT WORK"
                TaskProgressState.SUBMITTED -> "VIEW SUBMISSION"
                TaskProgressState.NEEDS_REVISION -> "SEE FEEDBACK"
                TaskProgressState.COMPETENT -> "OPEN TASK"
            },
            recentAchievement = recentAchievement,
        )
    }
}
