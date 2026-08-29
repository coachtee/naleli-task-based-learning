package com.naleli.tbl.domain

import com.naleli.tbl.data.content.WorkSubStep
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import java.time.LocalDate

/**
 * Naleli Workspace's core product rule, enforced here rather than left as a
 * UI convention: progress (activity), competence (assessed ability) and
 * portfolio (accumulated evidence) are three different computations over
 * the same underlying rows — never one flag standing in for all three.
 */

enum class TaskProgressState { NOT_STARTED, IN_PROGRESS, SUBMITTED, NEEDS_REVISION, COMPETENT }

fun taskProgressState(task: WorkTask, subStepStatuses: Map<String, SubStepStatusEntity>, assessment: AssessmentEntity?): TaskProgressState = when {
    assessment?.result == CompetenceResult.COMPETENT -> TaskProgressState.COMPETENT
    assessment?.result == CompetenceResult.REQUIRES_IMPROVEMENT -> TaskProgressState.NEEDS_REVISION
    assessment?.submittedAt != null -> TaskProgressState.SUBMITTED
    task.subSteps.any { subStepStatuses[it.subStepId]?.complete == true } -> TaskProgressState.IN_PROGRESS
    else -> TaskProgressState.NOT_STARTED
}

fun isTaskLocked(taskId: String, assessmentByTask: Map<String, AssessmentEntity>): Boolean {
    val prerequisiteTaskId = WorkspaceCurriculum.prerequisiteFor(taskId) ?: return false
    return assessmentByTask[prerequisiteTaskId]?.result != CompetenceResult.COMPETENT
}

/** The one task Home/My Work/Journey all point to as "what's current" —
 * kept in one place so the three screens can never quietly disagree with
 * each other about which task the learner is meant to be doing right now. */
fun currentTaskId(subStepStatuses: Map<String, SubStepStatusEntity>, assessmentByTask: Map<String, AssessmentEntity>): String? {
    val reachable = WorkspaceCurriculum.allTasks().filterNot { isTaskLocked(it.taskId, assessmentByTask) }
    val stateByTask = reachable.associate { it.taskId to taskProgressState(it, subStepStatuses, assessmentByTask[it.taskId]) }
    return reachable.firstOrNull { it.tier == com.naleli.tbl.data.content.TaskTier.REQUIRED && stateByTask[it.taskId] != TaskProgressState.COMPETENT }?.taskId
        ?: reachable.firstOrNull { stateByTask[it.taskId] != TaskProgressState.COMPETENT }?.taskId
}

fun allSubStepsDone(subSteps: List<WorkSubStep>, subStepStatuses: Map<String, SubStepStatusEntity>): Boolean =
    subSteps.isNotEmpty() && subSteps.all { subStepStatuses[it.subStepId]?.complete == true }

/** Real elapsed-time day count since the profile's start date, clamped to
 * the project's duration — never a hardcoded display number. */
fun projectDayNumber(profile: LearnerProfileEntity, totalDays: Int): Int {
    val startDate = LocalDate.ofEpochDay(profile.startDateEpochDay)
    val elapsedDays = java.time.temporal.ChronoUnit.DAYS.between(startDate, LocalDate.now()).toInt() + 1
    return elapsedDays.coerceIn(1, totalDays)
}

enum class ProjectHealth { ON_TRACK, ATTENTION_REQUIRED, BEHIND_SCHEDULE }

object ProjectHealthCalculator {
    /** Compares elapsed-time fraction against completed-work fraction — a
     * learner who's used 60% of the 90 days but done 20% of planned work
     * reads as behind, not "on track because nothing's overdue yet". */
    fun evaluate(dayNumber: Int, totalDays: Int, tasksCompleted: Int, plannedTaskCount: Int): ProjectHealth {
        if (plannedTaskCount <= 0 || totalDays <= 0) return ProjectHealth.ON_TRACK
        val expectedFraction = dayNumber.toFloat() / totalDays
        val actualFraction = tasksCompleted.toFloat() / plannedTaskCount
        val gap = expectedFraction - actualFraction
        return when {
            gap > 0.15f -> ProjectHealth.BEHIND_SCHEDULE
            gap > 0.05f -> ProjectHealth.ATTENTION_REQUIRED
            else -> ProjectHealth.ON_TRACK
        }
    }
}

/**
 * The whole point of a "competence must be assessed, not claimed" rule in an
 * offline, no-backend, no-AI-grading app: the checklist has to be a real,
 * objective, deterministic rubric over what was actually submitted — not a
 * learner self-tick, and not an LLM judging the writing. Every criterion but
 * the last checks structural facts (steps done, evidence attached); the last
 * checks the one thing worth a type heuristic (does the file look like the
 * deliverable asked for).
 */
object AssessmentEngine {
    data class CriterionCheck(val label: String, val met: Boolean)
    data class Outcome(val result: CompetenceResult, val checks: List<CriterionCheck>)

    fun evaluate(task: WorkTask, subStepStatuses: Map<String, SubStepStatusEntity>, evidence: List<EvidenceEntity>): Outcome {
        val stepsDone = allSubStepsDone(task.subSteps, subStepStatuses)
        val hasEvidence = evidence.isNotEmpty()
        val checks = task.assessmentCriteria.mapIndexed { index, label ->
            val met = when {
                !stepsDone || !hasEvidence -> false
                index == task.assessmentCriteria.lastIndex -> evidenceLooksRight(task.deliverableLabel, evidence)
                else -> true
            }
            CriterionCheck(label, met)
        }
        val result = if (checks.all { it.met }) CompetenceResult.COMPETENT else CompetenceResult.REQUIRES_IMPROVEMENT
        return Outcome(result, checks)
    }

    private fun evidenceLooksRight(deliverableLabel: String, evidence: List<EvidenceEntity>): Boolean {
        val label = deliverableLabel.lowercase()
        if ("screenshot" !in label) return true
        return evidence.any { it.fileType.startsWith("image/") }
    }
}

data class PortfolioSkill(
    val skillName: String,
    val result: CompetenceResult,
    val evidenceCount: Int,
    val confidenceLabel: String,
)

object PortfolioSkillCalculator {
    fun summarize(assessments: List<AssessmentEntity>, evidenceCountByTask: Map<String, Int>): List<PortfolioSkill> {
        val assessmentByTask = assessments.associateBy { it.taskId }
        val tasksBySkill = WorkspaceCurriculum.allTasks().groupBy { it.skillDeveloped }
        return tasksBySkill.map { (skill, tasks) ->
            val taskAssessments = tasks.mapNotNull { assessmentByTask[it.taskId] }
            val result = when {
                taskAssessments.any { it.result == CompetenceResult.COMPETENT } -> CompetenceResult.COMPETENT
                taskAssessments.any { it.result == CompetenceResult.REQUIRES_IMPROVEMENT } -> CompetenceResult.REQUIRES_IMPROVEMENT
                else -> CompetenceResult.NOT_YET_ASSESSED
            }
            val evidenceCount = tasks.sumOf { evidenceCountByTask[it.taskId] ?: 0 }
            val avgConfidence = taskAssessments.mapNotNull { it.confidenceRating }
                .takeIf { it.isNotEmpty() }?.average()
            PortfolioSkill(skill, result, evidenceCount, confidenceLabel(avgConfidence))
        }
    }

    fun portfolioStrengthPercent(skills: List<PortfolioSkill>): Int {
        if (skills.isEmpty()) return 0
        return (skills.count { it.result == CompetenceResult.COMPETENT } * 100) / skills.size
    }

    private fun confidenceLabel(avg: Double?): String = when {
        avg == null -> "Not yet rated"
        avg < 2 -> "Learning"
        avg < 3.5 -> "Growing"
        avg < 4.5 -> "Confident"
        else -> "Very confident"
    }
}
