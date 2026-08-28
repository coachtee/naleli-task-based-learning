package com.naleli.tbl.ui.screens.workspace

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkSubStep
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.allSubStepsDone
import com.naleli.tbl.domain.isTaskLocked
import com.naleli.tbl.domain.taskProgressState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class TaskWorkspaceUiState(
    val isLoading: Boolean = true,
    val task: WorkTask? = null,
    val locked: Boolean = false,
    val subStepStatuses: Map<String, SubStepStatusEntity> = emptyMap(),
    val evidenceCount: Int = 0,
    val assessment: AssessmentEntity? = null,
    val progressState: TaskProgressState = TaskProgressState.NOT_STARTED,
) {
    val allStepsDone: Boolean get() = task?.let { allSubStepsDone(it.subSteps, subStepStatuses) } ?: false
    val readyToSubmit: Boolean
        get() = allStepsDone && evidenceCount > 0 &&
            (progressState == TaskProgressState.IN_PROGRESS || progressState == TaskProgressState.NEEDS_REVISION)
}

class TaskWorkspaceViewModel(private val container: AppContainer, private val taskId: String) : ViewModel() {
    private val _state = MutableStateFlow(TaskWorkspaceUiState())
    val state: StateFlow<TaskWorkspaceUiState> = _state.asStateFlow()
    val task: WorkTask? = WorkspaceMockContent.taskById(taskId)

    init {
        viewModelScope.launch {
            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeForTask(taskId),
            ) { subSteps, assessments, evidence ->
                val subStepStatuses = subSteps.filter { it.taskId == taskId }.associateBy { it.subStepId }
                val assessmentByTask = assessments.associateBy { it.taskId }
                val assessment = assessmentByTask[taskId]
                TaskWorkspaceUiState(
                    isLoading = false,
                    task = task,
                    locked = isTaskLocked(taskId, assessmentByTask),
                    subStepStatuses = subStepStatuses,
                    evidenceCount = evidence.size,
                    assessment = assessment,
                    progressState = task?.let { taskProgressState(it, subStepStatuses, assessment) } ?: TaskProgressState.NOT_STARTED,
                )
            }.collect { _state.value = it }
        }
    }

    fun toggleSubStep(subStep: WorkSubStep, complete: Boolean) {
        viewModelScope.launch { container.workspaceRepository.setSubStepComplete(subStep, taskId, complete) }
    }

    fun submitForAssessment(confidenceRating: Int) {
        viewModelScope.launch { container.workspaceRepository.submitForAssessment(taskId, confidenceRating) }
    }
}
