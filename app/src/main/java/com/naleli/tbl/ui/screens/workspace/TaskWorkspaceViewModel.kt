package com.naleli.tbl.ui.screens.workspace

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.WorkSubStep
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.LessonLibrary
import com.naleli.tbl.data.content.LessonStage
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.domain.SubmissionChecklist
import com.naleli.tbl.domain.SubmissionRequirement
import com.naleli.tbl.domain.WorkspaceSnapshot
import com.naleli.tbl.domain.allSubStepsDone
import com.naleli.tbl.domain.isWrittenAnswer
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

data class TaskWorkspaceUiState(
    val isLoading: Boolean = true,
    val task: WorkTask? = null,
    val workstreamName: String = "",
    val locked: Boolean = false,
    val subStepStatuses: Map<String, SubStepStatusEntity> = emptyMap(),
    val evidence: List<EvidenceEntity> = emptyList(),
    val assessment: AssessmentEntity? = null,
    val progressState: TaskProgressState = TaskProgressState.NOT_STARTED,
    /** Titles of the source lessons behind this day, empty when the day is
     * practical work with no reading. */
    val lessonTitles: List<String> = emptyList(),
) {
    val evidenceCount: Int get() = evidence.size
    val allStepsDone: Boolean get() = task?.let { allSubStepsDone(it.subSteps, subStepStatuses) } ?: false

    /** Files the learner attached, kept apart from answers they typed — the
     * Show screen presents them as two different steps because they are two
     * different things the day asks for. */
    val attachedFiles: List<EvidenceEntity> get() = evidence.filterNot { it.isWrittenAnswer() }
    val writtenAnswers: List<EvidenceEntity> get() = evidence.filter { it.isWrittenAnswer() }

    /** Every requirement with its own tick, so "what is still missing" is
     * always nameable rather than one greyed-out button. */
    val submissionRequirements: List<SubmissionRequirement>
        get() = task?.let { SubmissionChecklist.requirements(it, subStepStatuses, evidence) }.orEmpty()
    val missingRequirements: List<SubmissionRequirement> get() = submissionRequirements.filterNot { it.met }

    /** Read straight off the shared state rather than recomputed here — the
     * button the learner sees and the badge the other screens see are now
     * the same decision. */
    val readyToSubmit: Boolean get() = progressState == TaskProgressState.READY_TO_SUBMIT
}

class TaskWorkspaceViewModel(private val container: AppContainer, private val taskId: String) : ViewModel() {
    private val _state = MutableStateFlow(TaskWorkspaceUiState())
    val state: StateFlow<TaskWorkspaceUiState> = _state.asStateFlow()
    val task: WorkTask? = WorkspaceCurriculum.taskById(taskId)

    init {
        viewModelScope.launch {
            // The lesson package is ~1.4 MB, so the first parse goes to IO
            // rather than freezing the frame that opens the task. It also
            // warms LessonLibrary's cache, so the reading screen this hands
            // off to renders from memory.
            val lessonTitles = withContext(Dispatchers.IO) {
                LessonLibrary.lessonsForTask(container.context, taskId).map { it.title }
            }

            combine(
                container.workspaceRepository.observeSubSteps(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeAll(),
            ) { subSteps, assessments, allEvidence ->
                // The same snapshot Home, My Work and Journey build, from
                // the same three flows — this screen is where a learner acts,
                // so it must not be the one computing state its own way.
                val snapshot = WorkspaceSnapshot.of(subSteps, assessments, allEvidence)
                TaskWorkspaceUiState(
                    isLoading = false,
                    task = task,
                    workstreamName = WorkspaceCurriculum.workstreamFor(taskId)?.name ?: "",
                    locked = snapshot.isLocked(taskId),
                    subStepStatuses = subSteps.filter { it.taskId == taskId }.associateBy { it.subStepId },
                    evidence = allEvidence.filter { it.taskId == taskId },
                    assessment = snapshot.assessmentOf(taskId),
                    progressState = task?.let { snapshot.stateOf(it) } ?: TaskProgressState.NOT_STARTED,
                    lessonTitles = lessonTitles,
                )
            }.collect { _state.value = it }
        }
    }

    fun toggleSubStep(subStep: WorkSubStep, complete: Boolean) {
        viewModelScope.launch { container.workspaceRepository.setSubStepComplete(subStep, taskId, complete) }
    }

    /**
     * Marks the sub-step a lesson stage corresponds to.
     *
     * The workbook already names each day's steps Learn / Practice / Work
     * Mission / Review, which is the same arc the lesson pages walk. So
     * passing a stage completes its step rather than asking the learner to
     * tick a checklist describing work they have just done.
     */
    fun completeStageSubStep(stage: LessonStage) {
        val steps = task?.subSteps.orEmpty()
        if (steps.isEmpty()) return

        val targets = if (stage == LessonStage.SHOW) {
            // Reaching Show means the learner has walked the whole arc, so
            // everything the day asked for is done. This is also what makes
            // the eleven capstone days submittable: they name their steps
            // for the artefact ("Role-to-Skill Map"), which no prefix below
            // matches, and without this they could never satisfy
            // allStepsDone and would be unable to submit at all.
            steps
        } else {
            val prefixes = when (stage) {
                LessonStage.UNDERSTAND, LessonStage.SEE -> listOf("learn")
                LessonStage.TRY -> listOf("practice", "practise")
                LessonStage.APPLY -> listOf("work mission", "portfolio", "capstone")
                LessonStage.SHOW -> emptyList()
            }
            steps.filter { step -> prefixes.any { step.title.lowercase().startsWith(it) } }
        }

        viewModelScope.launch {
            targets.forEach { container.workspaceRepository.setSubStepComplete(it, taskId, complete = true) }
        }
    }

    /** Saves a typed answer as evidence for this task. */
    fun saveWrittenAnswer(text: String) {
        val trimmed = text.trim()
        if (trimmed.isEmpty()) return
        viewModelScope.launch {
            container.evidenceRepository.attachWrittenResponse(
                taskId = taskId,
                text = trimmed,
                description = task?.deliverableLabel,
            )
        }
    }

    /** Removes one piece of evidence. Replacing is remove-then-attach: the
     * learner can always undo a wrong file rather than submitting it because
     * the app gave them no way back. */
    fun removeEvidence(evidence: EvidenceEntity) {
        viewModelScope.launch { container.evidenceRepository.delete(evidence) }
    }

    fun submitForAssessment(confidenceRating: Int) {
        viewModelScope.launch { container.workspaceRepository.submitForAssessment(taskId, confidenceRating) }
    }
}
