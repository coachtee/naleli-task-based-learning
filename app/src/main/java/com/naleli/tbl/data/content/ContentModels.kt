package com.naleli.tbl.data.content

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * Static, content-driven course models — parsed from the JSON package under
 * content/<programmeId>/ (see docs/CONTENT-MODEL.md). None of these types
 * carry learner-specific runtime state (that lives in Room — see
 * data.db.entity). The UI renders whatever these describe; it never
 * hard-codes lesson or task text.
 */

@Serializable
enum class TaskType {
    READ, WATCH, PRACTICE, WORK_MISSION, RESEARCH, CREATE,
    UPLOAD_EVIDENCE, SELF_CHECK, REFLECTION, ASSESSMENT, CAPSTONE,
}

@Serializable
enum class ResponseType { TEXT, FILE, REVIEW, NONE }

@Serializable
enum class ProgressionRule { FREE_NAVIGATION, SEQUENTIAL_UNLOCK }

@Serializable
data class SupportContentRef(
    val fileName: String,
    val label: String,
    val location: String, // "resources" or "downloads" under the programme's content package
)

@Serializable
data class CourseTask(
    val taskId: String,
    val orderIndex: Int,
    val title: String,
    val taskType: TaskType,
    val instructions: String,
    val learningObjective: String,
    val estimatedTime: String,
    val required: Boolean,
    val evidenceRequired: Boolean,
    val responseType: ResponseType,
    val portfolioEligible: Boolean,
    val supportContent: List<SupportContentRef> = emptyList(),
    val reviewQuestions: List<String> = emptyList(),
)

@Serializable
data class CourseDay(
    val dayNumber: Int,
    val stageId: String,
    val title: String,
    val learningFocus: String,
    val sourceReference: String,
    val objective: String,
    val lessonSummary: String,
    val keyFocusAreas: List<String> = emptyList(),
    val tasks: List<CourseTask>,
    val reviewQuestions: List<String> = emptyList(),
    val reflectionPrompt: String,
    val dailyDeliverableSummary: String = "",
)

@Serializable
data class CourseStage(
    val stageId: String,
    val stageNumber: Int,
    val name: String,
    val dayStart: Int,
    val dayEnd: Int,
    val description: String,
)

@Serializable
data class CertificateEligibilityConfig(
    val requireAllDaysComplete: Boolean = true,
    val requireCapstoneComplete: Boolean = true,
    val requireFinalAssessmentComplete: Boolean = true,
    val minimumPortfolioItems: Int = 0,
)

@Serializable
data class CredentialInfo(
    val issuingBody: String,
    val campus: String,
    val programmeTitle: String,
)

@Serializable
data class Course(
    val programmeId: String,
    val programmeName: String,
    val shortDescription: String,
    val credential: CredentialInfo,
    val totalDays: Int,
    val method: List<String> = emptyList(),
    val progressionRule: ProgressionRule = ProgressionRule.FREE_NAVIGATION,
    val stages: List<CourseStage>,
    val certificateEligibility: CertificateEligibilityConfig = CertificateEligibilityConfig(),
    val contentAvailableDays: List<Int> = emptyList(),
    val contentVersion: String = "",
    @SerialName("sourceBlueprint") val sourceBlueprintPath: String = "",
) {
    fun stageFor(dayNumber: Int): CourseStage? =
        stages.firstOrNull { dayNumber in it.dayStart..it.dayEnd }

    fun isDayAvailable(dayNumber: Int): Boolean = dayNumber in contentAvailableDays
}
