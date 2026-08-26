package com.naleli.tbl.data.db.dao

import androidx.room.Dao
import androidx.room.Delete
import androidx.room.Insert
import androidx.room.OnConflictStrategy
import androidx.room.Query
import androidx.room.Update
import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import com.naleli.tbl.data.db.entity.TaskStatusEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface LearnerProfileDao {
    @Query("SELECT * FROM learner_profile WHERE id = 'active-learner' LIMIT 1")
    fun observeProfile(): Flow<LearnerProfileEntity?>

    @Query("SELECT * FROM learner_profile WHERE id = 'active-learner' LIMIT 1")
    suspend fun getProfile(): LearnerProfileEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(profile: LearnerProfileEntity)

    @Query("DELETE FROM learner_profile")
    suspend fun deleteAll()
}

@Dao
interface DayProgressDao {
    @Query("SELECT * FROM day_progress ORDER BY dayNumber ASC")
    fun observeAll(): Flow<List<DayProgressEntity>>

    @Query("SELECT * FROM day_progress WHERE dayNumber = :dayNumber LIMIT 1")
    suspend fun get(dayNumber: Int): DayProgressEntity?

    @Query("SELECT * FROM day_progress WHERE dayNumber = :dayNumber LIMIT 1")
    fun observe(dayNumber: Int): Flow<DayProgressEntity?>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: DayProgressEntity)

    @Query("DELETE FROM day_progress")
    suspend fun deleteAll()
}

@Dao
interface TaskStatusDao {
    @Query("SELECT * FROM task_status WHERE dayNumber = :dayNumber")
    fun observeForDay(dayNumber: Int): Flow<List<TaskStatusEntity>>

    @Query("SELECT * FROM task_status WHERE dayNumber = :dayNumber")
    suspend fun getForDay(dayNumber: Int): List<TaskStatusEntity>

    @Query("SELECT * FROM task_status WHERE taskId = :taskId LIMIT 1")
    suspend fun get(taskId: String): TaskStatusEntity?

    @Query("SELECT * FROM task_status")
    fun observeAll(): Flow<List<TaskStatusEntity>>

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: TaskStatusEntity)

    @Query("DELETE FROM task_status")
    suspend fun deleteAll()
}

@Dao
interface EvidenceDao {
    @Query("SELECT * FROM evidence ORDER BY createdAt DESC")
    fun observeAll(): Flow<List<EvidenceEntity>>

    @Query("SELECT * FROM evidence WHERE taskId = :taskId ORDER BY createdAt DESC")
    fun observeForTask(taskId: String): Flow<List<EvidenceEntity>>

    @Insert
    suspend fun insert(entity: EvidenceEntity): Long

    @Delete
    suspend fun delete(entity: EvidenceEntity)

    @Query("DELETE FROM evidence")
    suspend fun deleteAll()
}

@Dao
interface PortfolioDao {
    @Query("SELECT * FROM portfolio_item ORDER BY createdAt DESC")
    fun observeAll(): Flow<List<PortfolioItemEntity>>

    @Query("SELECT * FROM portfolio_item WHERE taskId = :taskId LIMIT 1")
    suspend fun getForTask(taskId: String): PortfolioItemEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun upsert(entity: PortfolioItemEntity)

    @Query("DELETE FROM portfolio_item WHERE taskId = :taskId")
    suspend fun deleteForTask(taskId: String)

    @Query("DELETE FROM portfolio_item")
    suspend fun deleteAll()
}

@Dao
interface CertificateDao {
    @Query("SELECT * FROM certificate ORDER BY issuedAt DESC")
    fun observeAll(): Flow<List<CertificateEntity>>

    @Query("SELECT COUNT(*) FROM certificate")
    suspend fun count(): Int

    @Insert
    suspend fun insert(entity: CertificateEntity): Long

    @Query("DELETE FROM certificate")
    suspend fun deleteAll()
}
