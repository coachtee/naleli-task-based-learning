package com.naleli.tbl.ui.screens.profile

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.domain.PortfolioSkillCalculator
import com.naleli.tbl.domain.projectDayNumber
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class ProfileHubUiState(
    val isLoading: Boolean = true,
    val profile: LearnerProfileEntity? = null,
    val programmeName: String = "",
    val dayNumber: Int = 1,
    val totalDays: Int = 0,
    val daysCompetent: Int = 0,
    val daysTotal: Int = 0,
    val evidenceCount: Int = 0,
    /** Skills with a recorded COMPETENT result — the things this learner
     * can actually claim, in the order the curriculum builds them. */
    val competencies: List<String> = emptyList(),
    val skillsTotal: Int = 0,
)

/**
 * The Me tab as the learner's professional profile rather than a settings
 * menu: who they are, how far into the programme they are, and what they
 * can now claim to be able to do.
 *
 * Every figure is counted from real rows — competence comes from assessment
 * results alone, never from days marked done.
 */
class ProfileHubViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(ProfileHubUiState())
    val state: StateFlow<ProfileHubUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val initialProfile = container.profileRepository.getProfile()
            val course = initialProfile?.let { container.contentRepository.getCourse(it.programmeId) }

            combine(
                container.profileRepository.observeProfile(),
                container.workspaceRepository.observeAssessments(),
                container.evidenceRepository.observeAll(),
            ) { profile, assessments, evidence ->
                val activeProfile = profile ?: initialProfile
                val skills = PortfolioSkillCalculator.summarize(
                    assessments,
                    evidence.groupBy { it.taskId }.mapValues { it.value.size },
                )
                ProfileHubUiState(
                    isLoading = false,
                    profile = activeProfile,
                    programmeName = course?.programmeName.orEmpty(),
                    dayNumber = if (activeProfile != null && course != null) {
                        projectDayNumber(activeProfile, course.totalDays)
                    } else {
                        1
                    },
                    totalDays = course?.totalDays ?: 0,
                    daysCompetent = assessments.count { it.result == CompetenceResult.COMPETENT },
                    daysTotal = com.naleli.tbl.data.content.WorkspaceCurriculum.allTasks().size,
                    evidenceCount = evidence.size,
                    competencies = skills.filter { it.result == CompetenceResult.COMPETENT }.map { it.skillName },
                    skillsTotal = skills.size,
                )
            }.collect { _state.value = it }
        }
    }
}
