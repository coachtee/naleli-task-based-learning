package com.naleli.tbl.domain

import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.TaskStatusEntity

/**
 * Real progress, computed from actual completion data — never a placeholder
 * number (brief §16). Pure function: no I/O, easy to unit test.
 */
data class ProgressSummary(
    val totalDays: Int,
    val daysCompleted: Int,
    val tasksCompleted: Int,
    val evidenceCount: Int,
    val portfolioItemCount: Int,
    val currentDayNumber: Int,
    val currentStageName: String?,
    val capstoneComplete: Boolean,
) {
    val overallPercent: Int
        get() = if (totalDays == 0) 0 else ((daysCompleted * 100) / totalDays)
}

object ProgressCalculator {

    fun currentDayNumber(dayProgress: List<DayProgressEntity>, contentAvailableDays: List<Int>): Int {
        val firstIncomplete = dayProgress
            .filter { it.status != DayStatus.COMPLETE }
            .minByOrNull { it.dayNumber }
            ?.dayNumber
        val candidate = firstIncomplete ?: ((dayProgress.maxOfOrNull { it.dayNumber } ?: 0) + 1)
        val available = contentAvailableDays.sorted()
        if (available.isEmpty()) return candidate
        if (candidate in available) return candidate
        return available.firstOrNull { it >= candidate } ?: available.last()
    }

    fun summarize(
        course: Course,
        dayProgress: List<DayProgressEntity>,
        taskStatuses: List<TaskStatusEntity>,
        evidenceCount: Int,
        portfolioItemCount: Int,
    ): ProgressSummary {
        val daysCompleted = dayProgress.count { it.status == DayStatus.COMPLETE }
        val tasksCompleted = taskStatuses.count { it.status == DayStatus.COMPLETE }
        val currentDay = currentDayNumber(dayProgress, course.contentAvailableDays)
        val stage = course.stageFor(currentDay)
        val capstoneStage = course.stages.firstOrNull { it.stageId == "stage-4" }
        val capstoneComplete = capstoneStage != null && dayProgress
            .filter { it.dayNumber in capstoneStage.dayStart..capstoneStage.dayEnd }
            .let { it.isNotEmpty() && it.all { d -> d.status == DayStatus.COMPLETE } }

        return ProgressSummary(
            totalDays = course.totalDays,
            daysCompleted = daysCompleted,
            tasksCompleted = tasksCompleted,
            evidenceCount = evidenceCount,
            portfolioItemCount = portfolioItemCount,
            currentDayNumber = currentDay,
            currentStageName = stage?.name,
            capstoneComplete = capstoneComplete,
        )
    }
}
