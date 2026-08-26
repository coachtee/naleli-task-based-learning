package com.naleli.tbl.ui.screens.evidence

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.CourseDay
import com.naleli.tbl.data.db.entity.AssessmentStatus
import com.naleli.tbl.data.db.entity.EvidenceEntity
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

enum class EvidenceKind { DOCUMENT, IMAGE, WORKSHEET, OTHER }

data class EvidenceRow(
    val evidence: EvidenceEntity,
    val taskTitle: String,
    val kind: EvidenceKind,
    val assessmentStatus: AssessmentStatus,
)

data class EvidenceUiState(val isLoading: Boolean = true, val items: List<EvidenceRow> = emptyList())

class EvidenceViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(EvidenceUiState())
    val state: StateFlow<EvidenceUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)
            val dayCache = mutableMapOf<Int, CourseDay?>()

            combine(
                container.evidenceRepository.observeAll(),
                container.progressRepository.observeAllTasks(),
            ) { evidenceList, taskStatuses ->
                val statusByTask = taskStatuses.associateBy { it.taskId }
                val rows = evidenceList.map { evidence ->
                    val day = dayCache.getOrPut(evidence.dayNumber) {
                        if (course.isDayAvailable(evidence.dayNumber)) {
                            container.contentRepository.getDay(course.programmeId, evidence.dayNumber)
                        } else null
                    }
                    val task = day?.tasks?.firstOrNull { it.taskId == evidence.taskId }
                    EvidenceRow(
                        evidence = evidence,
                        taskTitle = task?.title ?: "Task",
                        kind = classify(evidence),
                        assessmentStatus = statusByTask[evidence.taskId]?.assessmentStatus ?: AssessmentStatus.NOT_YET_ASSESSED,
                    )
                }
                EvidenceUiState(isLoading = false, items = rows)
            }.collect { _state.value = it }
        }
    }

    private fun classify(evidence: EvidenceEntity): EvidenceKind = when {
        evidence.description?.contains("worksheet", ignoreCase = true) == true -> EvidenceKind.WORKSHEET
        evidence.fileType.startsWith("image/") -> EvidenceKind.IMAGE
        evidence.fileType.startsWith("application/") || evidence.fileType.startsWith("text/") -> EvidenceKind.DOCUMENT
        else -> EvidenceKind.OTHER
    }
}
