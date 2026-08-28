package com.naleli.tbl.ui.screens.mywork

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkSubStep
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

data class SubStepRow(val subStep: WorkSubStep, val complete: Boolean, val locked: Boolean)
data class TodayTaskUi(val task: WorkTask, val subSteps: List<SubStepRow>, val state: TaskProgressState)
data class NextTaskUi(val task: WorkTask, val state: TaskProgressState, val locked: Boolean, val lockReason: String?)

data class MyWorkUiState(
    val isLoading: Boolean = true,
    val today: TodayTaskUi? = null,
    val next: List<NextTaskUi> = emptyList(),
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
        val reachable = allTasks.filterNot { isTaskLocked(it.taskId, assessmentByTask) }
        val stateByTask = reachable.associate { it.taskId to taskProgressState(it, subStepStatuses, assessmentByTask[it.taskId]) }
        val completedCount = stateByTask.values.count { it == TaskProgressState.COMPETENT }

        val todayTask = currentTaskId(subStepStatuses, assessmentByTask)?.let { WorkspaceMockContent.taskById(it) }

        val today = todayTask?.let { task ->
            var priorIncomplete = false
            val rows = task.subSteps.map { subStep ->
                val complete = subStepStatuses[subStep.subStepId]?.complete == true
                val row = SubStepRow(subStep, complete, locked = priorIncomplete)
                if (!complete) priorIncomplete = true
                row
            }
            TodayTaskUi(task, rows, stateByTask[task.taskId] ?: TaskProgressState.NOT_STARTED)
        }

        val next = allTasks.filter { it.taskId != todayTask?.taskId && stateByTask[it.taskId] != TaskProgressState.COMPETENT }
            .map { task ->
                val locked = isTaskLocked(task.taskId, assessmentByTask)
                val prereqTitle = WorkspaceMockContent.UNLOCK_REQUIRES[task.taskId]?.let { WorkspaceMockContent.taskById(it)?.title }
                NextTaskUi(
                    task = task,
                    state = stateByTask[task.taskId] ?: TaskProgressState.NOT_STARTED,
                    locked = locked,
                    lockReason = prereqTitle?.let { "Unlocks after \"$it\"" },
                )
            }

        return MyWorkUiState(
            isLoading = false,
            today = today,
            next = next,
            phaseCompletedCount = completedCount,
        )
    }
}
