package com.naleli.tbl.ui.screens.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.currentTaskId
import com.naleli.tbl.domain.isTaskLocked
import com.naleli.tbl.domain.taskProgressState
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
    val phaseName: String = "Learn the Role",
    val phaseCompletedCount: Int = 0,
    val phasePlannedCount: Int = WorkspaceMockContent.PHASE_1_PLANNED_TASK_COUNT,
)

class MyWorkViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(MyWorkUiState())
    val state: StateFlow<MyWorkUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
            ) { subSteps, assessments -> buildState(subSteps.associateBy { it.subStepId }, assessments.associateBy { it.taskId }) }
                .collect { _state.value = it }
        }
    }

    private fun buildState(subStepStatuses: Map<String, SubStepStatusEntity>, assessmentByTask: Map<String, AssessmentEntity>): MyWorkUiState {
        val allTasks = WorkspaceMockContent.allTasks()
        val currentId = currentTaskId(subStepStatuses, assessmentByTask)

        val rows = allTasks.map { task ->
            val locked = isTaskLocked(task.taskId, assessmentByTask)
            val state = taskProgressState(task, subStepStatuses, assessmentByTask[task.taskId])
            val prereqTitle = WorkspaceMockContent.UNLOCK_REQUIRES[task.taskId]?.let { WorkspaceMockContent.taskById(it)?.title }
            WorkTaskRow(
                task = task,
                workstreamName = WorkspaceMockContent.workstreamFor(task.taskId)?.name ?: "",
                state = state,
                isCurrent = task.taskId == currentId,
                locked = locked,
                lockReason = prereqTitle?.let { "Unlocks after \"$it\"" },
                stepsDone = task.subSteps.count { subStepStatuses[it.subStepId]?.complete == true },
                stepsTotal = task.subSteps.size,
            )
        }

        val completedCount = rows.count { it.state == TaskProgressState.COMPETENT }

        return MyWorkUiState(
            isLoading = false,
            toDo = rows.filter { it.state == TaskProgressState.NOT_STARTED },
            inProgress = rows.filter { it.state == TaskProgressState.IN_PROGRESS || it.state == TaskProgressState.NEEDS_REVISION },
            assessment = rows.filter { it.state == TaskProgressState.SUBMITTED },
            done = rows.filter { it.state == TaskProgressState.COMPETENT },
            phaseCompletedCount = completedCount,
        )
    }
}
