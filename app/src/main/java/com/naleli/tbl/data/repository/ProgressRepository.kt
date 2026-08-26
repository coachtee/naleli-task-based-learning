package com.naleli.tbl.data.repository

import com.naleli.tbl.data.content.CourseTask
import com.naleli.tbl.data.db.dao.DayProgressDao
import com.naleli.tbl.data.db.dao.TaskStatusDao
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.TaskStatusEntity
import kotlinx.coroutines.flow.Flow

/**
 * Owns day/task *runtime* state. Completion (`status`) and assessment
 * result (`assessmentStatus`) are always written independently — marking a
 * task COMPLETE never touches assessmentStatus (brief §17).
 */
class ProgressRepository(
    private val dayDao: DayProgressDao,
    private val taskDao: TaskStatusDao,
) {
    fun observeAllDays(): Flow<List<DayProgressEntity>> = dayDao.observeAll()
    fun observeDay(dayNumber: Int): Flow<DayProgressEntity?> = dayDao.observe(dayNumber)
    fun observeTasksForDay(dayNumber: Int): Flow<List<TaskStatusEntity>> = taskDao.observeForDay(dayNumber)
    fun observeAllTasks(): Flow<List<TaskStatusEntity>> = taskDao.observeAll()

    suspend fun getDay(dayNumber: Int): DayProgressEntity? = dayDao.get(dayNumber)
    suspend fun getTask(taskId: String): TaskStatusEntity? = taskDao.get(taskId)
    suspend fun getTasksForDay(dayNumber: Int): List<TaskStatusEntity> = taskDao.getForDay(dayNumber)

    suspend fun markDayStarted(dayNumber: Int) {
        val existing = dayDao.get(dayNumber)
        if (existing == null) {
            dayDao.upsert(
                DayProgressEntity(
                    dayNumber = dayNumber,
                    status = DayStatus.IN_PROGRESS,
                    startedAt = System.currentTimeMillis(),
                ),
            )
        } else if (existing.status == DayStatus.NOT_STARTED) {
            dayDao.upsert(existing.copy(status = DayStatus.IN_PROGRESS, startedAt = System.currentTimeMillis()))
        }
    }

    suspend fun saveTextResponse(task: CourseTask, dayNumber: Int, text: String) {
        upsertTaskStatus(task.taskId, dayNumber) { it.copy(textResponse = text) }
    }

    suspend fun saveReviewAnswers(task: CourseTask, dayNumber: Int, answersJson: String, confidence: Int?) {
        upsertTaskStatus(task.taskId, dayNumber) { it.copy(reviewAnswers = answersJson, confidenceRating = confidence) }
    }

    suspend fun setTaskComplete(taskId: String, dayNumber: Int, complete: Boolean) {
        upsertTaskStatus(taskId, dayNumber) {
            it.copy(status = if (complete) DayStatus.COMPLETE else DayStatus.IN_PROGRESS)
        }
    }

    suspend fun completeDay(dayNumber: Int, needsReview: Boolean, reflectionText: String?) {
        val existing = dayDao.get(dayNumber) ?: DayProgressEntity(dayNumber = dayNumber)
        dayDao.upsert(
            existing.copy(
                status = if (needsReview) DayStatus.NEEDS_REVIEW else DayStatus.COMPLETE,
                completedAt = System.currentTimeMillis(),
                reflectionText = reflectionText ?: existing.reflectionText,
            ),
        )
    }

    suspend fun deleteAll() {
        dayDao.deleteAll()
        taskDao.deleteAll()
    }

    private suspend fun upsertTaskStatus(taskId: String, dayNumber: Int, mutate: (TaskStatusEntity) -> TaskStatusEntity) {
        val existing = taskDao.get(taskId) ?: TaskStatusEntity(
            taskId = taskId,
            dayNumber = dayNumber,
            updatedAt = System.currentTimeMillis(),
        )
        taskDao.upsert(mutate(existing).copy(updatedAt = System.currentTimeMillis()))
    }
}
