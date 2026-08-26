package com.naleli.tbl.data.repository

import com.naleli.tbl.data.db.dao.LearnerProfileDao
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.domain.LearnerCodeGenerator
import kotlinx.coroutines.flow.Flow
import java.time.LocalDate

class ProfileRepository(private val dao: LearnerProfileDao) {

    fun observeProfile(): Flow<LearnerProfileEntity?> = dao.observeProfile()

    suspend fun getProfile(): LearnerProfileEntity? = dao.getProfile()

    suspend fun hasProfile(): Boolean = dao.getProfile() != null

    suspend fun createProfile(
        firstName: String,
        surname: String,
        studentNumber: String?,
        email: String?,
        phone: String?,
        programmeId: String,
        startDate: LocalDate,
    ): LearnerProfileEntity {
        val now = System.currentTimeMillis()
        val entity = LearnerProfileEntity(
            firstName = firstName.trim(),
            surname = surname.trim(),
            learnerCode = LearnerCodeGenerator.generate(programmeId, startDate.year),
            studentNumber = studentNumber?.trim()?.ifBlank { null },
            email = email?.trim()?.ifBlank { null },
            phone = phone?.trim()?.ifBlank { null },
            programmeId = programmeId,
            startDateEpochDay = startDate.toEpochDay(),
            createdAt = now,
            updatedAt = now,
        )
        dao.upsert(entity)
        return entity
    }

    suspend fun updateProfile(updated: LearnerProfileEntity) {
        dao.upsert(updated.copy(updatedAt = System.currentTimeMillis()))
    }

    suspend fun deleteProfile() = dao.deleteAll()

    companion object {
        fun startDateOf(entity: LearnerProfileEntity): LocalDate =
            LocalDate.ofEpochDay(entity.startDateEpochDay)
    }
}
