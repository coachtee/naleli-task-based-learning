package com.naleli.tbl.ui.screens.mylearning

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.content.CourseStage
import com.naleli.tbl.data.content.ProgressionRule
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.DayStatus
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class DayListItem(
    val dayNumber: Int,
    val title: String?,
    val status: DayStatus,
    val taskCompleteFraction: Float,
    val isContentAvailable: Boolean,
    val isLocked: Boolean,
)

data class StageSection(val stage: CourseStage, val days: List<DayListItem>)

data class MyLearningUiState(
    val isLoading: Boolean = true,
    val course: Course? = null,
    val sections: List<StageSection> = emptyList(),
)

class MyLearningViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(MyLearningUiState())
    val state: StateFlow<MyLearningUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)

            container.progressRepository.observeAllDays().collect { dayProgress ->
                val progressByDay = dayProgress.associateBy { it.dayNumber }

                val sections = course.stages.map { stage ->
                    val days = (stage.dayStart..stage.dayEnd).map { dayNumber ->
                        buildDayItem(course, dayNumber, progressByDay)
                    }
                    StageSection(stage, days)
                }

                _state.value = MyLearningUiState(isLoading = false, course = course, sections = sections)
            }
        }
    }

    private suspend fun buildDayItem(
        course: Course,
        dayNumber: Int,
        progressByDay: Map<Int, DayProgressEntity>,
    ): DayListItem {
        val isAvailable = course.isDayAvailable(dayNumber)
        val day = if (isAvailable) container.contentRepository.getDay(course.programmeId, dayNumber) else null

        val fraction = if (day != null && day.tasks.isNotEmpty()) {
            val statuses = container.progressRepository.getTasksForDay(dayNumber)
            val completeIds = statuses.filter { it.status == DayStatus.COMPLETE }.map { it.taskId }.toSet()
            day.tasks.count { it.taskId in completeIds }.toFloat() / day.tasks.size
        } else 0f

        val isLocked = course.progressionRule == ProgressionRule.SEQUENTIAL_UNLOCK &&
            dayNumber > 1 &&
            progressByDay[dayNumber - 1]?.status != DayStatus.COMPLETE

        return DayListItem(
            dayNumber = dayNumber,
            title = day?.title,
            status = progressByDay[dayNumber]?.status ?: DayStatus.NOT_STARTED,
            taskCompleteFraction = fraction,
            isContentAvailable = isAvailable,
            isLocked = isLocked,
        )
    }
}
