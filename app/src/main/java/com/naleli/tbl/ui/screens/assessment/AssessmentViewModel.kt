package com.naleli.tbl.ui.screens.assessment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.domain.AssessmentEngine
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class AssessmentUiState(
    val isLoading: Boolean = true,
    val task: WorkTask? = null,
    val assessment: AssessmentEntity? = null,
    val evidenceCount: Int = 0,
    val checks: List<AssessmentEngine.CriterionCheck> = emptyList(),
)

/**
 * Progress and competence stay visibly separate here: this screen never
 * runs while a task is merely "done" — it only has anything to show once
 * the learner has explicitly submitted, and the result it displays comes
 * from AssessmentEngine's rubric, not from task/sub-step completion.
 */
class AssessmentViewModel(private val container: AppContainer, private val taskId: String) : ViewModel() {
    private val _state = MutableStateFlow(AssessmentUiState())
    val state: StateFlow<AssessmentUiState> = _state.asStateFlow()
    private val task: WorkTask? = WorkspaceMockContent.taskById(taskId)

    init {
        viewModelScope.launch {
            task?.let { container.workspaceRepository.evaluateAssessment(it) }

            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeForTask(taskId),
            ) { subSteps, assessments, evidence ->
                val subStepStatuses = subSteps.filter { it.taskId == taskId }.associateBy { it.subStepId }
                val assessment = assessments.firstOrNull { it.taskId == taskId }
                val checks = task?.let { AssessmentEngine.evaluate(it, subStepStatuses, evidence).checks }.orEmpty()
                AssessmentUiState(isLoading = false, task = task, assessment = assessment, evidenceCount = evidence.size, checks = checks)
            }.collect { _state.value = it }
        }
    }
}
