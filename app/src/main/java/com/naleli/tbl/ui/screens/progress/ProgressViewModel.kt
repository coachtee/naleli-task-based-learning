package com.naleli.tbl.ui.screens.progress

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.domain.ProgressCalculator
import com.naleli.tbl.domain.ProgressSummary
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

class ProgressViewModel(private val container: AppContainer) : ViewModel() {
    private val _summary = MutableStateFlow<ProgressSummary?>(null)
    val summary: StateFlow<ProgressSummary?> = _summary.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)
            combine(
                container.progressRepository.observeAllDays(),
                container.progressRepository.observeAllTasks(),
                container.evidenceRepository.observeAll(),
                container.portfolioRepository.observeAll(),
            ) { days, tasks, evidence, portfolio ->
                ProgressCalculator.summarize(course, days, tasks, evidence.size, portfolio.size)
            }.collect { _summary.value = it }
        }
    }
}
