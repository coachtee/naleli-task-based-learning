package com.naleli.tbl.domain

import com.naleli.tbl.data.content.CourseDay
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.TaskStatusEntity
import kotlin.math.roundToInt

/**
 * "Learning Confidence" (brief V1.5.1 §4) — a deterministic, explainable
 * read on how solidly a learner has actually engaged with a day's work, not
 * a proxy for "day marked complete". A day can be COMPLETE (or NEEDS_REVIEW)
 * while still scoring well under 100 here, because completion alone is only
 * one of four signals blended in: required-task completion, evidence
 * actually attached where required, self-reported confidence on self-check
 * tasks, and whether the day's reflection was written. No randomness, no
 * network call — everything comes from what's already recorded locally.
 */
data class ConfidenceSummary(
    val overallPercent: Int,
    val byDay: Map<Int, Int>,
)

object ConfidenceCalculator {

    fun summarize(
        days: List<CourseDay>,
        dayProgress: List<DayProgressEntity>,
        taskStatuses: List<TaskStatusEntity>,
        evidenceCountByTask: Map<String, Int>,
    ): ConfidenceSummary {
        val progressByDay = dayProgress.associateBy { it.dayNumber }
        val statusByTask = taskStatuses.associateBy { it.taskId }

        val byDay = days.mapNotNull { day ->
            val progress = progressByDay[day.dayNumber] ?: return@mapNotNull null
            dayConfidencePercent(day, progress, statusByTask, evidenceCountByTask)
                ?.let { day.dayNumber to it }
        }.toMap()

        val overall = if (byDay.isEmpty()) 0 else byDay.values.sum() / byDay.size
        return ConfidenceSummary(overallPercent = overall, byDay = byDay)
    }

    /** Null only when a day has been "started" in name only, with no scorable
     * signal yet recorded (shouldn't normally happen — markDayStarted only
     * runs when the day screen opens, but this keeps the function total). */
    private fun dayConfidencePercent(
        day: CourseDay,
        progress: DayProgressEntity,
        statusByTask: Map<String, TaskStatusEntity>,
        evidenceCountByTask: Map<String, Int>,
    ): Int? {
        val components = mutableListOf<Double>()

        val requiredTasks = day.tasks.filter { it.required }
        if (requiredTasks.isNotEmpty()) {
            val completeCount = requiredTasks.count { statusByTask[it.taskId]?.status == DayStatus.COMPLETE }
            components += completeCount.toDouble() / requiredTasks.size * 100.0
        }

        val evidenceTasks = day.tasks.filter { it.evidenceRequired }
        if (evidenceTasks.isNotEmpty()) {
            val withEvidence = evidenceTasks.count { (evidenceCountByTask[it.taskId] ?: 0) > 0 }
            components += withEvidence.toDouble() / evidenceTasks.size * 100.0
        }

        val ratings = day.tasks.mapNotNull { statusByTask[it.taskId]?.confidenceRating }.filter { it in 1..5 }
        if (ratings.isNotEmpty()) {
            components += ratings.average() / 5.0 * 100.0
        }

        components += if (progress.reflectionText.isNullOrBlank()) 0.0 else 100.0

        if (components.isEmpty()) return null
        return components.average().roundToInt().coerceIn(0, 100)
    }
}
