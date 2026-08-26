package com.naleli.tbl.ui.screens.day

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.content.CourseDay
import com.naleli.tbl.data.content.CourseTask
import com.naleli.tbl.data.content.SupportContentRef
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.TaskStatusEntity
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import kotlinx.serialization.Serializable

@Serializable
data class ReviewAnswer(val question: String, val answer: String)

data class DayDetailUiState(
    val isLoading: Boolean = true,
    val notAvailable: Boolean = false,
    val course: Course? = null,
    val day: CourseDay? = null,
    val taskStatuses: Map<String, TaskStatusEntity> = emptyMap(),
    val evidenceByTask: Map<String, List<EvidenceEntity>> = emptyMap(),
    val dayStatus: DayStatus = DayStatus.NOT_STARTED,
    val reflectionText: String = "",
) {
    val requiredTasksComplete: Boolean
        get() = day?.tasks?.filter { it.required }?.all { taskStatuses[it.taskId]?.status == DayStatus.COMPLETE } ?: false
}

class DayViewModel(private val container: AppContainer, private val dayNumber: Int) : ViewModel() {
    private val _state = MutableStateFlow(DayDetailUiState())
    val state: StateFlow<DayDetailUiState> = _state.asStateFlow()
    private val json = Json { ignoreUnknownKeys = true }

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)
            val day = container.contentRepository.getDay(profile.programmeId, dayNumber)

            if (day == null) {
                _state.value = DayDetailUiState(isLoading = false, notAvailable = true, course = course)
                return@launch
            }

            container.progressRepository.markDayStarted(dayNumber)

            combine(
                container.progressRepository.observeTasksForDay(dayNumber),
                container.progressRepository.observeDay(dayNumber),
                container.evidenceRepository.observeAll(),
            ) { taskStatuses, dayProgress, allEvidence ->
                val statusMap = taskStatuses.associateBy { it.taskId }
                val evidenceByTask = allEvidence.filter { it.dayNumber == dayNumber }.groupBy { it.taskId }
                DayDetailUiState(
                    isLoading = false,
                    course = course,
                    day = day,
                    taskStatuses = statusMap,
                    evidenceByTask = evidenceByTask,
                    dayStatus = dayProgress?.status ?: DayStatus.NOT_STARTED,
                    reflectionText = dayProgress?.reflectionText ?: "",
                )
            }.collect { _state.value = it }
        }
    }

    fun saveTextResponse(task: CourseTask, text: String) {
        viewModelScope.launch { container.progressRepository.saveTextResponse(task, dayNumber, text) }
    }

    fun markTaskComplete(task: CourseTask, complete: Boolean) {
        viewModelScope.launch { container.progressRepository.setTaskComplete(task.taskId, dayNumber, complete) }
    }

    fun attachEvidence(task: CourseTask, uri: Uri, description: String?) {
        viewModelScope.launch {
            val day = _state.value.day ?: return@launch
            val evidence = container.evidenceRepository.attachFromUri(task.taskId, dayNumber, uri, null, description)
            container.portfolioRepository.addOrUpdateFromTask(day, task, evidence)
        }
    }

    fun deleteEvidence(evidence: EvidenceEntity) {
        viewModelScope.launch { container.evidenceRepository.delete(evidence) }
    }

    fun saveReviewAnswers(task: CourseTask, answers: List<ReviewAnswer>, confidence: Int, confident: Boolean) {
        viewModelScope.launch {
            val answersJson = json.encodeToString(answers)
            container.progressRepository.saveReviewAnswers(task, dayNumber, answersJson, confidence)
            container.progressRepository.setTaskComplete(task.taskId, dayNumber, complete = true)
        }
    }

    fun completeDay(needsReview: Boolean, reflectionText: String) {
        viewModelScope.launch { container.progressRepository.completeDay(dayNumber, needsReview, reflectionText) }
    }

    /** Copies a read-only course resource into the learner's own working files (brief §13). */
    suspend fun copyResourceToDevice(ref: SupportContentRef): java.io.File {
        val course = _state.value.course ?: error("Course not loaded")
        val destDir = java.io.File(container.context.filesDir, "course_files/$dayNumber")
        return container.contentRepository.copyResourceToDevice(course.programmeId, ref, destDir)
    }
}
