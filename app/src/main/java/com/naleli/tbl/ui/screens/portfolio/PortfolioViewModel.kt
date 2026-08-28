package com.naleli.tbl.ui.screens.portfolio

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.domain.PortfolioSkill
import com.naleli.tbl.domain.PortfolioSkillCalculator
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class PortfolioUiState(
    val isLoading: Boolean = true,
    val strengthPercent: Int = 0,
    val skills: List<PortfolioSkill> = emptyList(),
)

/** Evidence of what the learner can actually do — a competence result and
 * an evidence count per skill, never a completion percentage standing in
 * for either (brief §6). */
class PortfolioViewModel(container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(PortfolioUiState())
    val state: StateFlow<PortfolioUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            combine(
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeAll(),
            ) { assessments, evidence ->
                val evidenceCountByTask = evidence.groupBy { it.taskId }.mapValues { it.value.size }
                val skills = PortfolioSkillCalculator.summarize(assessments, evidenceCountByTask)
                PortfolioUiState(
                    isLoading = false,
                    strengthPercent = PortfolioSkillCalculator.portfolioStrengthPercent(skills),
                    skills = skills,
                )
            }.collect { _state.value = it }
        }
    }
}
