package com.naleli.tbl.data.repository

import kotlinx.serialization.Serializable

/**
 * Backup/export DTOs (brief §19). Deliberately separate from the Room
 * entities: evidence's on-device absolute path is never portable across
 * installs/devices, so the backup stores a relative
 * "<taskId>/<fileName>" pointer instead and the restore step re-derives
 * the real local path from the current app's filesDir.
 */

const val BACKUP_SCHEMA_VERSION = 1

@Serializable
data class BackupManifest(
    val schemaVersion: Int = BACKUP_SCHEMA_VERSION,
    val exportedAtEpochMillis: Long,
    val appVersionName: String,
    val learnerCode: String,
    val includesEvidenceFiles: Boolean,
)

@Serializable
data class ProfileBackup(
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

@Serializable
data class DayProgressBackup(
    val dayNumber: Int,
    val status: String,
    val startedAt: Long?,
    val completedAt: Long?,
    val reflectionText: String?,
)

@Serializable
data class TaskStatusBackup(
    val taskId: String,
    val dayNumber: Int,
    val status: String,
    val assessmentStatus: String,
    val feedback: String?,
    val textResponse: String?,
    val reviewAnswers: String?,
    val confidenceRating: Int?,
    val updatedAt: Long,
)

@Serializable
data class EvidenceBackup(
    val evidenceId: String,
    val taskId: String,
    val dayNumber: Int,
    val fileName: String,
    val fileType: String,
    /** Relative path inside the zip's evidence/ folder when files are included; null otherwise. */
    val relativeFilePath: String?,
    val createdAt: Long,
    val description: String?,
)

@Serializable
data class PortfolioItemBackup(
    val dayNumber: Int,
    val taskId: String,
    val title: String,
    val skillDemonstrated: String,
    val evidenceId: String?,
    val description: String?,
    val createdAt: Long,
)

@Serializable
data class CertificateBackup(
    val certificateNumber: String,
    val learnerId: String,
    val programmeId: String,
    val issuedAt: Long,
)

@Serializable
data class LearningDataBackup(
    val profile: ProfileBackup,
    val dayProgress: List<DayProgressBackup>,
    val taskStatus: List<TaskStatusBackup>,
    val evidence: List<EvidenceBackup>,
    val portfolio: List<PortfolioItemBackup>,
    val certificates: List<CertificateBackup>,
)
