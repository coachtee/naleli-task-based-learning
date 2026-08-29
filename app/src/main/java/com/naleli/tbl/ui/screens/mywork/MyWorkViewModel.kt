package com.naleli.tbl.ui.screens.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.CourseStage
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.WorkspaceSnapshot
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class WorkTaskRow(
    val task: WorkTask,
    val workstreamName: String,
    val state: TaskProgressState,
    val isCurrent: Boolean,
    val locked: Boolean,
    val lockReason: String?,
    val stepsDone: Int,
    val stepsTotal: Int,
    val evidenceCount: Int,
)

/** Four buckets a learner instantly understands (approved redesign §2):
 * To Do, In Progress, Assessment (submitted, awaiting a result), Done
 * (competent). A task's [TaskProgressState] maps to exactly one bucket. */
data class MyWorkUiState(
    val isLoading: Boolean = true,
    val toDo: List<WorkTaskRow> = emptyList(),
    val inProgress: List<WorkTaskRow> = emptyList(),
    val assessment: List<WorkTaskRow> = emptyList(),
    val done: List<WorkTaskRow> = emptyList(),
    val phaseName: String = "",
    val phaseCompletedCount: Int = 0,
    val phasePlannedCount: Int = 0,
)

class MyWorkViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(MyWorkUiState())
    val state: StateFlow<MyWorkUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile()
            val stages = profile?.let { container.contentRepository.getCourse(it.programmeId).stages }.orEmpty()

            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeAll(),
            ) { subSteps, assessments, evidence ->
                buildState(stages, WorkspaceSnapshot.of(subSteps, assessments, evidence))
            }.collect { _state.value = it }
        }
    }

    private fun buildState(stages: List<CourseStage>, snapshot: WorkspaceSnapshot): MyWorkUiState {
        val allTasks = WorkspaceCurriculum.allTasks()
        val currentId = snapshot.currentTaskId

        val rows = allTasks.map { task ->
            val prereqTitle = WorkspaceCurriculum.prerequisiteFor(task.taskId)?.let { WorkspaceCurriculum.taskById(it)?.title }
            WorkTaskRow(
                task = task,
                workstreamName = WorkspaceCurriculum.workstreamFor(task.taskId)?.name ?: "",
                state = snapshot.stateOf(task),
                isCurrent = task.taskId == currentId,
                locked = snapshot.isLocked(task.taskId),
                lockReason = prereqTitle?.let { "Unlocks after \"$it\"" },
                stepsDone = snapshot.stepsDone(task),
                stepsTotal = task.subSteps.size,
                evidenceCount = snapshot.evidenceCount(task.taskId),
            )
        }

        // The phase header tracks the phase the learner is actually in —
        // the stage owning the current task — not a hardcoded Phase 1, now
        // that all four phases carry real content.
        val activeStageId = currentId?.let { WorkspaceCurriculum.stageIdFor(it) }
            ?: WorkspaceCurriculum.stageIdFor(allTasks.lastOrNull()?.taskId.orEmpty())
        val phaseTaskIds = activeStageId?.let { stageId ->
            WorkspaceCurriculum.tasksForStage(stageId).map { it.taskId }.toSet()
        }.orEmpty()

        // Scoped to the current phase: the real curriculum is 90 days, so
        // an unscoped To Do would be one unlocked task followed by 89 locked
        // rows. Journey is where all four phases are seen at once.
        val phaseRows = rows.filter { it.task.taskId in phaseTaskIds }

        return MyWorkUiState(
            isLoading = false,
            // Six states, four buckets: Ready to Submit and Needs Changes
            // are both "open work in the learner's hands", so they sit with
            // In Progress. The badge on the row still names the exact state,
            // so the bucket never overrides what the task actually says.
            toDo = phaseRows.filter { it.state == TaskProgressState.NOT_STARTED },
            inProgress = phaseRows.filter {
                it.state == TaskProgressState.IN_PROGRESS ||
                    it.state == TaskProgressState.READY_TO_SUBMIT ||
                    it.state == TaskProgressState.NEEDS_REVISION
            },
            assessment = phaseRows.filter { it.state == TaskProgressState.SUBMITTED },
            done = phaseRows.filter { it.state == TaskProgressState.COMPETENT },
            phaseName = stages.firstOrNull { it.stageId == activeStageId }?.name.orEmpty(),
            phaseCompletedCount = phaseRows.count { it.state == TaskProgressState.COMPETENT },
            phasePlannedCount = phaseTaskIds.size,
        )
    }
}
