package com.naleli.tbl.data.repository

import com.naleli.tbl.data.content.CourseDay
import com.naleli.tbl.data.content.CourseTask
import com.naleli.tbl.data.db.dao.PortfolioDao
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import kotlinx.coroutines.flow.Flow

/**
 * The portfolio is built automatically from completed, evidence-bearing
 * tasks (brief §15) — never manually curated by the learner in V1.
 */
class PortfolioRepository(private val dao: PortfolioDao) {

    fun observeAll(): Flow<List<PortfolioItemEntity>> = dao.observeAll()

    suspend fun addOrUpdateFromTask(day: CourseDay, task: CourseTask, evidence: EvidenceEntity) {
        if (!task.portfolioEligible) return
        dao.upsert(
            PortfolioItemEntity(
                dayNumber = day.dayNumber,
                taskId = task.taskId,
                title = task.title,
                skillDemonstrated = day.learningFocus,
                evidenceId = evidence.evidenceId,
                description = evidence.description ?: task.instructions.take(140),
                createdAt = System.currentTimeMillis(),
            ),
        )
    }

    suspend fun removeForTask(taskId: String) = dao.deleteForTask(taskId)

    suspend fun deleteAll() = dao.deleteAll()
}
