package com.naleli.tbl.data.content

import kotlinx.serialization.Serializable

/**
 * Naleli Workspace content: Phase → Workstream → Task → Sub-step.
 *
 * This deliberately does not reuse the day-based CourseDay/CourseTask model
 * in ContentModels.kt — that model is "Day 1..90, 3-4 tasks each"; this one
 * is "a phase opens into workstreams, each workstream holds real tasks".
 *
 * The content itself is authored in
 * source/Naleli_Task_Based_Digital_Foundation_90_Day_Workbook.xlsx and
 * converted by source/build_workspace_content.py into
 * content/digital-foundation/workspace-content.json, which ships as an
 * Android asset (see app/build.gradle.kts sourceSets.assets.srcDirs). Never
 * hand-edit that JSON — re-run the converter, or the app and the workbook
 * drift apart.
 */

@Serializable
enum class TaskTier { REQUIRED, SUPPORTING, ASSESSMENT }

@Serializable
data class WorkSubStep(
    val subStepId: String,
    val title: String,
    val estimatedMinutes: Int,
    /** What the learner actually does for this step, from the workbook. */
    val instructions: String = "",
    /** What this step should produce. */
    val evidence: String = "",
)

@Serializable
data class WorkTask(
    val taskId: String,
    /** 1..90 — the workbook day this task came from. Drives sequential
     * unlocking and "Day N of 90" without a separate ordering field. */
    val dayNumber: Int = 0,
    val title: String,
    val tier: TaskTier,
    val estimatedMinutes: Int,
    val whatYoureDoing: String,
    val whyItMatters: String,
    val skillDeveloped: String,
    val understandText: String,
    val watchLabel: String? = null,
    val practiseText: String = "",
    val assignmentText: String,
    val deliverableLabel: String,
    val subSteps: List<WorkSubStep> = emptyList(),
    val assessmentCriteria: List<String> = emptyList(),
)

@Serializable
data class Workstream(
    val workstreamId: String,
    val name: String,
    /** The course.json stage (phase) this workstream belongs to. */
    val stageId: String = "stage-1",
    /** The source workbook's module code (M1..M20), kept for traceability
     * back to the curriculum. */
    val moduleCode: String = "",
    val tasks: List<WorkTask> = emptyList(),
)

@Serializable
data class WorkspaceContentPackage(
    val programmeId: String = "digital-foundation",
    val totalDays: Int = 90,
    val workstreams: List<Workstream> = emptyList(),
) {
    val allTasks: List<WorkTask> get() = workstreams.flatMap { it.tasks }

    fun taskById(taskId: String): WorkTask? = allTasks.firstOrNull { it.taskId == taskId }

    fun workstreamFor(taskId: String): Workstream? =
        workstreams.firstOrNull { ws -> ws.tasks.any { it.taskId == taskId } }

    fun workstreamsForStage(stageId: String): List<Workstream> =
        workstreams.filter { it.stageId == stageId }

    fun tasksForStage(stageId: String): List<WorkTask> =
        workstreamsForStage(stageId).flatMap { it.tasks }

    /**
     * The curriculum is sequential (course.json: SEQUENTIAL_UNLOCK), so a
     * task's prerequisite is simply the previous day. Derived here rather
     * than stored as 89 prerequisite rows in the content file.
     */
    fun prerequisiteFor(taskId: String): String? {
        val task = taskById(taskId) ?: return null
        if (task.dayNumber <= 1) return null
        return allTasks.firstOrNull { it.dayNumber == task.dayNumber - 1 }?.taskId
    }

    companion object {
        val EMPTY = WorkspaceContentPackage(workstreams = emptyList())
    }
}
