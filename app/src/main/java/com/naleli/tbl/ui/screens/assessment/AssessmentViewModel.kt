package com.naleli.tbl.ui.screens.assessment

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.domain.AssessmentEngine
import com.naleli.tbl.domain.currentTaskId
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
    /** Where CONTINUE JOURNEY goes. Null once there is no reachable task
     * left, in which case the learner is sent to Journey instead. */
    val nextTaskId: String? = null,
    val nextTaskTitle: String? = null,
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
    private val task: WorkTask? = WorkspaceCurriculum.taskById(taskId)

    init {
        viewModelScope.launch {
            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeForTask(taskId),
            ) { subSteps, assessments, evidence ->
                val subStepStatuses = subSteps.filter { it.taskId == taskId }.associateBy { it.subStepId }
                val assessment = assessments.firstOrNull { it.taskId == taskId }
                val checks = task?.let { AssessmentEngine.evaluate(it, subStepStatuses, evidence).checks }.orEmpty()

                // Deliberately the same currentTaskId() Home, My Work and
                // Journey use, over the whole workspace rather than this
                // task's rows: once this assessment is recorded COMPETENT
                // the next day unlocks and becomes current, so CONTINUE
                // JOURNEY cannot disagree with what the rest of the app
                // says the learner should do next.
                val nextId = currentTaskId(assessments.associateBy { it.taskId })?.takeIf { it != taskId }

                // Run the rubric the moment a submitted-but-unassessed row
                // appears. Evaluating once in init raced the submit that sent
                // the learner here: the write had not landed, evaluate found
                // no row, bailed, and the screen sat on "Submitted" with every
                // criterion ticked and nothing that would ever assess it.
                if (assessment != null &&
                    assessment.submittedAt != null &&
                    assessment.result == CompetenceResult.NOT_YET_ASSESSED
                ) {
                    task?.let { container.workspaceRepository.evaluateAssessment(it) }
                }

                AssessmentUiState(
                    isLoading = false,
                    task = task,
                    assessment = assessment,
                    evidenceCount = evidence.size,
                    checks = checks,
                    nextTaskId = nextId,
                    nextTaskTitle = nextId?.let { WorkspaceCurriculum.taskById(it)?.title },
                )
            }.collect { _state.value = it }
        }
    }
}
