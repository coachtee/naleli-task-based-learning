package com.naleli.tbl.domain

import com.naleli.tbl.data.content.TaskTier
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

/**
 * The six states a task can be in — and the only vocabulary any screen is
 * allowed to use for one. [label] lives on the state itself rather than in
 * each screen, because "Submitted" on Home and "Awaiting assessment" on My
 * Work were the same row telling the learner two different things.
 *
 * Declared in order of progression, so a screen can compare states rather
 * than re-deriving them.
 */
enum class TaskProgressState(val label: String) {
    NOT_STARTED("Not Started"),
    IN_PROGRESS("In Progress"),
    READY_TO_SUBMIT("Ready to Submit"),
    SUBMITTED("Submitted"),
    NEEDS_REVISION("Needs Changes"),
    COMPETENT("Competent"),
}

/**
 * The one definition of where a task stands.
 *
 * [evidence] is a required parameter rather than a defaulted one on purpose:
 * READY_TO_SUBMIT means every requirement in [SubmissionChecklist] is met,
 * and a default would let one screen quietly compute a state the others
 * disagree with — the exact bug this is fixing. It is the same checklist the
 * Show screen ticks off, so a task can never badge "Ready to Submit" while
 * the submit button is still disabled. Prefer [WorkspaceSnapshot.stateOf],
 * which supplies every input from the same observed data.
 */
fun taskProgressState(
    task: WorkTask,
    subStepStatuses: Map<String, SubStepStatusEntity>,
    assessment: AssessmentEntity?,
    evidence: List<EvidenceEntity>,
): TaskProgressState = when {
    assessment?.result == CompetenceResult.COMPETENT -> TaskProgressState.COMPETENT
    assessment?.result == CompetenceResult.REQUIRES_IMPROVEMENT -> TaskProgressState.NEEDS_REVISION
    assessment?.submittedAt != null -> TaskProgressState.SUBMITTED
    SubmissionChecklist.missing(task, subStepStatuses, evidence).isEmpty() -> TaskProgressState.READY_TO_SUBMIT
    evidence.isNotEmpty() || task.subSteps.any { subStepStatuses[it.subStepId]?.complete == true } -> TaskProgressState.IN_PROGRESS
    else -> TaskProgressState.NOT_STARTED
}

/**
 * The three tables the whole product reads, resolved once.
 *
 * Home, My Work, Journey and Portfolio each used to observe their own
 * subset of these and derive state their own way — which is how one task
 * could read "In Progress" on one screen and "Submitted" on the next. They
 * now build this from the same three flows and ask it the same questions,
 * so there is one answer per task in the whole app.
 */
data class WorkspaceSnapshot(
    val subStepStatuses: Map<String, SubStepStatusEntity> = emptyMap(),
    val assessmentByTask: Map<String, AssessmentEntity> = emptyMap(),
    val evidenceByTask: Map<String, List<EvidenceEntity>> = emptyMap(),
) {
    fun stateOf(task: WorkTask): TaskProgressState =
        taskProgressState(task, subStepStatuses, assessmentByTask[task.taskId], evidenceFor(task.taskId))

    fun stateOf(taskId: String): TaskProgressState =
        WorkspaceCurriculum.taskById(taskId)?.let { stateOf(it) } ?: TaskProgressState.NOT_STARTED

    fun evidenceFor(taskId: String): List<EvidenceEntity> = evidenceByTask[taskId].orEmpty()

    fun evidenceCount(taskId: String): Int = evidenceFor(taskId).size

    fun assessmentOf(taskId: String): AssessmentEntity? = assessmentByTask[taskId]

    fun isLocked(taskId: String): Boolean = isTaskLocked(taskId, assessmentByTask)

    fun stepsDone(task: WorkTask): Int = task.subSteps.count { subStepStatuses[it.subStepId]?.complete == true }

    fun allStepsDone(task: WorkTask): Boolean = allSubStepsDone(task.subSteps, subStepStatuses)

    /** Fully qualified so it reads as a delegation to the single top-level
     * definition of "what's next", not a recursive member. */
    val currentTaskId: String? get() = com.naleli.tbl.domain.currentTaskId(assessmentByTask)

    companion object {
        fun of(
            subSteps: List<SubStepStatusEntity>,
            assessments: List<AssessmentEntity>,
            evidence: List<EvidenceEntity>,
        ) = WorkspaceSnapshot(
            subStepStatuses = subSteps.associateBy { it.subStepId },
            assessmentByTask = assessments.associateBy { it.taskId },
            evidenceByTask = evidence.groupBy { it.taskId },
        )
    }
}

fun isTaskLocked(taskId: String, assessmentByTask: Map<String, AssessmentEntity>): Boolean {
    val prerequisiteTaskId = WorkspaceCurriculum.prerequisiteFor(taskId) ?: return false
    return assessmentByTask[prerequisiteTaskId]?.result != CompetenceResult.COMPETENT
}

/** The one task Home/My Work/Journey all point to as "what's current" —
 * kept in one place so the three screens can never quietly disagree with
 * each other about which task the learner is meant to be doing right now.
 *
 * Only a recorded competence result closes a task, so this reads the
 * assessment rows alone: it needs no sub-step or evidence input and
 * therefore cannot drift from the state badges those inputs produce. */
fun currentTaskId(assessmentByTask: Map<String, AssessmentEntity>): String? {
    val reachable = WorkspaceCurriculum.allTasks().filterNot { isTaskLocked(it.taskId, assessmentByTask) }
    fun competent(taskId: String) = assessmentByTask[taskId]?.result == CompetenceResult.COMPETENT
    return reachable.firstOrNull { it.tier == TaskTier.REQUIRED && !competent(it.taskId) }?.taskId
        ?: reachable.firstOrNull { !competent(it.taskId) }?.taskId
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
 * A written answer typed into the app, as opposed to a file the learner
 * attached. Both are evidence and both go to the portfolio; only this one
 * satisfies "explain your work", which 79 of the 90 days ask for by name
 * ("Workplace-style output + short explanation").
 */
fun EvidenceEntity.isWrittenAnswer(): Boolean =
    fileType == "text/plain" && fileName.startsWith("written-answer")

/** One thing that must be true before a task can be submitted, in the
 * words the learner sees. */
data class SubmissionRequirement(val label: String, val met: Boolean)

/**
 * What "ready to submit" actually means, defined once.
 *
 * The old rule was "all steps done AND some evidence", which the screen
 * summarised as a single greyed-out button and a sentence. A learner who
 * had attached a photo but written nothing was told only that something was
 * wrong. These requirements are each shown with their own tick, so what is
 * still missing is always nameable.
 *
 * A typed answer counts as attached evidence, so a learner with no camera
 * and no document app is never locked out — they simply have one tick to
 * clear instead of two.
 */
object SubmissionChecklist {
    fun requirements(
        task: WorkTask,
        subStepStatuses: Map<String, SubStepStatusEntity>,
        evidence: List<EvidenceEntity>,
    ): List<SubmissionRequirement> = listOf(
        SubmissionRequirement(
            "Work through every stage of this task",
            allSubStepsDone(task.subSteps, subStepStatuses),
        ),
        SubmissionRequirement(
            "Attach the work you produced",
            evidence.isNotEmpty(),
        ),
        SubmissionRequirement(
            "Explain what you did in your own words",
            evidence.any { it.isWrittenAnswer() },
        ),
    )

    fun missing(
        task: WorkTask,
        subStepStatuses: Map<String, SubStepStatusEntity>,
        evidence: List<EvidenceEntity>,
    ): List<SubmissionRequirement> = requirements(task, subStepStatuses, evidence).filterNot { it.met }
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

    /** Reads the day's own deliverable label and checks the evidence
     * against it, rather than accepting any file for any task. */
    private fun evidenceLooksRight(deliverableLabel: String, evidence: List<EvidenceEntity>): Boolean {
        val label = deliverableLabel.lowercase()
        if ("screenshot" in label && evidence.none { it.fileType.startsWith("image/") }) return false
        // 79 of the 90 days ask for "output + short explanation" — so on
        // those days an attachment with nothing written is genuinely not
        // what was asked for, and the rubric should say so.
        if ("explanation" in label && evidence.none { it.isWrittenAnswer() }) return false
        return true
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
