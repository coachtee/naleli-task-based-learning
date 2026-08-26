package com.naleli.tbl.ui.screens.home

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.content.CourseDay
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import com.naleli.tbl.domain.ProgressCalculator
import com.naleli.tbl.domain.ProgressSummary
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class HomeUiState(
    val isLoading: Boolean = true,
    val profile: LearnerProfileEntity? = null,
    val course: Course? = null,
    val currentDay: CourseDay? = null,
    val incompleteTaskCount: Int = 0,
    val progress: ProgressSummary? = null,
    val upcomingDay: CourseDay? = null,
    val recentPortfolioItem: PortfolioItemEntity? = null,
)

class HomeViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(HomeUiState())
    val state: StateFlow<HomeUiState> = _state.asStateFlow()

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
                val summary = ProgressCalculator.summarize(course, days, tasks, evidence.size, portfolio.size)
                val currentDay = container.contentRepository.getDay(profile.programmeId, summary.currentDayNumber)
                val incompleteTasks = currentDay?.tasks?.count { task ->
                    tasks.none { it.taskId == task.taskId && it.status == DayStatus.COMPLETE }
                } ?: 0
                val upcomingDayNumber = summary.currentDayNumber + 1
                val upcomingDay = if (course.isDayAvailable(upcomingDayNumber)) {
                    container.contentRepository.getDay(profile.programmeId, upcomingDayNumber)
                } else null

                HomeUiState(
                    isLoading = false,
                    profile = profile,
                    course = course,
                    currentDay = currentDay,
                    incompleteTaskCount = incompleteTasks,
                    progress = summary,
                    upcomingDay = upcomingDay,
                    recentPortfolioItem = portfolio.maxByOrNull { it.createdAt },
                )
            }.collect { _state.value = it }
        }
    }
}
