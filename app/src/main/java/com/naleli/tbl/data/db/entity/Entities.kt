package com.naleli.tbl.data.db.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

/**
 * Runtime learner data (Room / SQLite, fully local). Course *content*
 * (lesson text, task instructions) never lives here — see
 * data.content.ContentModels. These tables only ever record what a real
 * learner actually did.
 */

enum class DayStatus { NOT_STARTED, IN_PROGRESS, COMPLETE, NEEDS_REVIEW }

enum class AssessmentStatus { NOT_YET_ASSESSED, COMPETENT, NOT_YET_COMPETENT, RESUBMIT }

@Entity(tableName = "learner_profile")
data class LearnerProfileEntity(
    @PrimaryKey val id: String = "active-learner",
    val firstName: String,
    val surname: String,
    val learnerCode: String,
    val studentNumber: String?,
    val email: String?,
    val phone: String?,
    val programmeId: String,
    val startDateEpochDay: Long,
    val createdAt: Long,
    val updatedAt: Long,
)

@Entity(tableName = "day_progress")
data class DayProgressEntity(
    @PrimaryKey val dayNumber: Int,
    val status: DayStatus = DayStatus.NOT_STARTED,
    val startedAt: Long? = null,
    val completedAt: Long? = null,
    val reflectionText: String? = null,
)

@Entity(tableName = "task_status")
data class TaskStatusEntity(
    @PrimaryKey val taskId: String,
    val dayNumber: Int,
    val status: DayStatus = DayStatus.NOT_STARTED,
    val assessmentStatus: AssessmentStatus = AssessmentStatus.NOT_YET_ASSESSED,
    val feedback: String? = null,
    val textResponse: String? = null,
    val reviewAnswers: String? = null, // JSON-encoded list of {question, answer}
    val confidenceRating: Int? = null, // 1-5, SELF_CHECK tasks
    val updatedAt: Long,
)

@Entity(tableName = "evidence")
data class EvidenceEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val evidenceId: String,
    val taskId: String,
    val dayNumber: Int,
    val fileName: String,
    val fileType: String,
    val localPath: String,
    val createdAt: Long,
    val description: String? = null,
)

@Entity(tableName = "portfolio_item")
data class PortfolioItemEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val dayNumber: Int,
    val taskId: String,
    val title: String,
    val skillDemonstrated: String,
    val evidenceId: String?,
    val description: String?,
    val createdAt: Long,
)

@Entity(tableName = "certificate")
data class CertificateEntity(
    @PrimaryKey(autoGenerate = true) val id: Long = 0,
    val certificateNumber: String,
    val learnerId: String,
    val programmeId: String,
    val issuedAt: Long,
    val filePath: String,
)
