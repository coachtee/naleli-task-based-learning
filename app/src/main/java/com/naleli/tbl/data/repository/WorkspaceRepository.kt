package com.naleli.tbl.data.repository

import com.naleli.tbl.data.content.WorkSubStep
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.data.db.dao.AssessmentDao
import com.naleli.tbl.data.db.dao.SubStepStatusDao
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.SubStepStatusEntity
import com.naleli.tbl.domain.AssessmentEngine
import kotlinx.coroutines.flow.Flow

/**
 * Owns Naleli Workspace's runtime state: which sub-steps are done (progress)
 * and what each task was assessed as (competence) — two separate tables,
 * never merged into one "done" flag (see domain/WorkspaceCalculators.kt).
 */
class WorkspaceRepository(
    private val subStepDao: SubStepStatusDao,
    private val assessmentDao: AssessmentDao,
    private val evidenceRepository: EvidenceRepository,
) {
    fun observeSubSteps(): Flow<List<SubStepStatusEntity>> = subStepDao.observeAll()
    fun observeAssessments(): Flow<List<AssessmentEntity>> = assessmentDao.observeAll()
    suspend fun getAssessment(taskId: String): AssessmentEntity? = assessmentDao.get(taskId)

    suspend fun setSubStepComplete(subStep: WorkSubStep, taskId: String, complete: Boolean) {
        subStepDao.upsert(
            SubStepStatusEntity(
                subStepId = subStep.subStepId,
                taskId = taskId,
                complete = complete,
                completedAt = if (complete) System.currentTimeMillis() else null,
            ),
        )
    }

    /** Submitting is a real, separate step from evaluation — a task sits as
     * SUBMITTED (progress) until [evaluateAssessment] actually runs the
     * rubric and records a competence result. */
    suspend fun submitForAssessment(taskId: String, confidenceRating: Int?) {
        val existing = assessmentDao.get(taskId)
        assessmentDao.upsert(
            (existing ?: AssessmentEntity(taskId = taskId)).copy(
                submittedAt = System.currentTimeMillis(),
                result = CompetenceResult.NOT_YET_ASSESSED,
                assessedAt = null,
                confidenceRating = confidenceRating ?: existing?.confidenceRating,
            ),
        )
    }

    /** Runs the deterministic rubric (AssessmentEngine) against what's
     * actually been submitted, and records the outcome. Safe to call
     * repeatedly — a no-op once a result is already recorded. */
    suspend fun evaluateAssessment(task: WorkTask): AssessmentEntity? {
        val current = assessmentDao.get(task.taskId) ?: return null
        if (current.result != CompetenceResult.NOT_YET_ASSESSED) return current
        val subStepStatuses = task.subSteps.associate { it.subStepId to subStepDao.get(it.subStepId) }
            .mapNotNull { (id, status) -> status?.let { id to it } }.toMap()
        val evidence = evidenceRepository.getForTask(task.taskId)
        val outcome = AssessmentEngine.evaluate(task, subStepStatuses, evidence)
        val updated = current.copy(result = outcome.result, assessedAt = System.currentTimeMillis())
        assessmentDao.upsert(updated)
        return updated
    }

    /** First-run only: seeds a realistic mid-journey history (per the
     * Workspace build brief's "Day 18 of 90... not an empty Day 1 screen")
     * so every screen shows real, queryable state immediately rather than a
     * blank slate. Each seeded task is genuinely complete and assessed —
     * these are real rows, not display numbers standing in for them.
     *
     * Also backdates the profile's own start date by the same amount, so
     * "Day 18 of 90" is a real computed value and not a UI lie sitting next
     * to an honest "Day 1" profile. This is a deliberate stand-in for this
     * mock-data-first pass — remove it once real onboarding + real content
     * ship, so a genuine new learner starts honestly at Day 1. */
    suspend fun seedDemoHistoryIfNeeded(profile: LearnerProfileEntity, profileRepository: ProfileRepository) {
        if (assessmentDao.get(WorkspaceMockContent.SEED_COMPLETE_TASK_IDS.first()) != null) return
        val seedTime = System.currentTimeMillis() - SEED_DAYS_AGO * DAY_MILLIS
        WorkspaceMockContent.SEED_COMPLETE_TASK_IDS.forEach { taskId ->
            val task = WorkspaceMockContent.taskById(taskId) ?: return@forEach
            task.subSteps.forEach { subStep ->
                subStepDao.upsert(SubStepStatusEntity(subStep.subStepId, taskId, complete = true, completedAt = seedTime))
            }
            evidenceRepository.attachPlaceholder(
                taskId = taskId,
                fileName = "${task.title.take(40)}.txt",
                createdAt = seedTime,
                description = task.deliverableLabel,
            )
            assessmentDao.upsert(
                AssessmentEntity(
                    taskId = taskId,
                    submittedAt = seedTime,
                    result = CompetenceResult.COMPETENT,
                    assessedAt = seedTime,
                    confidenceRating = 4,
                ),
            )
        }
        profileRepository.updateProfile(profile.copy(startDateEpochDay = profile.startDateEpochDay - SEED_DAYS_AGO))
    }

    suspend fun deleteAll() {
        subStepDao.deleteAll()
        assessmentDao.deleteAll()
    }

    private companion object {
        const val SEED_DAYS_AGO = 17L
        const val DAY_MILLIS = 24L * 60 * 60 * 1000
    }
}
