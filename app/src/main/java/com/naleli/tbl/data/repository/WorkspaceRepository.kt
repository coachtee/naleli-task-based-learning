package com.naleli.tbl.data.repository

import com.naleli.tbl.data.content.WorkSubStep
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.db.dao.AssessmentDao
import com.naleli.tbl.data.db.dao.SubStepStatusDao
import com.naleli.tbl.data.db.entity.AssessmentEntity
import com.naleli.tbl.data.db.entity.CompetenceResult
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

    suspend fun deleteAll() {
        subStepDao.deleteAll()
        assessmentDao.deleteAll()
    }
}
