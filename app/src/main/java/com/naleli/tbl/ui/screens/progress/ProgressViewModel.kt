package com.naleli.tbl.ui.screens.progress

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.domain.ConfidenceCalculator
import com.naleli.tbl.domain.ConfidenceSummary
import com.naleli.tbl.domain.ProgressCalculator
import com.naleli.tbl.domain.ProgressSummary
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class StageProgress(val name: String, val daysCompleted: Int, val totalDays: Int) {
    val fraction: Float get() = if (totalDays == 0) 0f else daysCompleted.toFloat() / totalDays
}

class ProgressViewModel(private val container: AppContainer) : ViewModel() {
    private val _summary = MutableStateFlow<ProgressSummary?>(null)
    val summary: StateFlow<ProgressSummary?> = _summary.asStateFlow()

    private val _stages = MutableStateFlow<List<StageProgress>>(emptyList())
    val stages: StateFlow<List<StageProgress>> = _stages.asStateFlow()

    private val _confidence = MutableStateFlow(ConfidenceSummary(overallPercent = 0, byDay = emptyMap()))
    val confidence: StateFlow<ConfidenceSummary> = _confidence.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)
            val allDays = course.contentAvailableDays.sorted()
                .mapNotNull { container.contentRepository.getDay(profile.programmeId, it) }
            combine(
                container.progressRepository.observeAllDays(),
                container.progressRepository.observeAllTasks(),
                container.evidenceRepository.observeAll(),
                container.portfolioRepository.observeAll(),
            ) { days, tasks, evidence, portfolio ->
                _summary.value = ProgressCalculator.summarize(course, days, tasks, evidence.size, portfolio.size)
                _stages.value = course.stages.map { stage ->
                    val totalDays = stage.dayEnd - stage.dayStart + 1
                    val completed = days.count { it.dayNumber in stage.dayStart..stage.dayEnd && it.status == DayStatus.COMPLETE }
                    StageProgress(stage.name, completed, totalDays)
                }
                val evidenceCountByTask = evidence.groupBy { it.taskId }.mapValues { it.value.size }
                _confidence.value = ConfidenceCalculator.summarize(allDays, days, tasks, evidenceCountByTask)
            }.collect { }
        }
    }
}
