package com.naleli.tbl.data.repository

import android.content.Context
import com.naleli.tbl.data.db.dao.CertificateDao
import com.naleli.tbl.data.db.dao.DayProgressDao
import com.naleli.tbl.data.db.dao.EvidenceDao
import com.naleli.tbl.data.db.dao.LearnerProfileDao
import com.naleli.tbl.data.db.dao.PortfolioDao
import com.naleli.tbl.data.db.dao.TaskStatusDao
import com.naleli.tbl.data.db.entity.AssessmentStatus
import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import com.naleli.tbl.data.db.entity.TaskStatusEntity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import java.io.File
import java.util.zip.ZipEntry
import java.util.zip.ZipInputStream
import java.util.zip.ZipOutputStream

/**
 * "Backup My Learning" / "Restore My Learning" (brief §19). Offline-only:
 * writes/reads a ZIP file on-device; the caller decides where that file
 * ends up (Storage Access Framework "create document" / "open document").
 */
class BackupRepository(
    private val context: Context,
    private val profileDao: LearnerProfileDao,
    private val dayDao: DayProgressDao,
    private val taskDao: TaskStatusDao,
    private val evidenceDao: EvidenceDao,
    private val portfolioDao: PortfolioDao,
    private val certificateDao: CertificateDao,
) {
    private val json = Json { prettyPrint = true; ignoreUnknownKeys = true }

    suspend fun exportTo(destination: File, includeEvidenceFiles: Boolean): File = withContext(Dispatchers.IO) {
        val profile = profileDao.getProfile() ?: error("No learner profile to back up")
        val dayProgress = dayDao.observeAll().first()
        val taskStatus = taskDao.observeAll().first()
        val evidenceList = evidenceDao.observeAll().first()
        val portfolio = portfolioDao.observeAll().first()
        val certificates = certificateDao.observeAll().first()

        val manifest = BackupManifest(
            exportedAtEpochMillis = System.currentTimeMillis(),
            appVersionName = "1.0.0-v1",
            learnerCode = profile.learnerCode,
            includesEvidenceFiles = includeEvidenceFiles,
        )

        val data = LearningDataBackup(
            profile = profile.toBackup(),
            dayProgress = dayProgress.map { it.toBackup() },
            taskStatus = taskStatus.map { it.toBackup() },
            evidence = evidenceList.map { it.toBackup(includeEvidenceFiles) },
            portfolio = portfolio.map { it.toBackup() },
            certificates = certificates.map { it.toBackup() },
        )

        ZipOutputStream(destination.outputStream()).use { zip ->
            writeJsonEntry(zip, "manifest.json", json.encodeToString(BackupManifest.serializer(), manifest))
            writeJsonEntry(zip, "learning_data.json", json.encodeToString(LearningDataBackup.serializer(), data))

            if (includeEvidenceFiles) {
                evidenceList.forEach { evidence ->
                    val sourceFile = File(evidence.localPath)
                    if (sourceFile.exists()) {
                        zip.putNextEntry(ZipEntry("evidence/${evidence.taskId}/${evidence.fileName}"))
                        sourceFile.inputStream().use { it.copyTo(zip) }
                        zip.closeEntry()
                    }
                }
            }
        }
        destination
    }

    /** Reads a backup ZIP's manifest without applying anything — used to show the confirmation dialog. */
    suspend fun peekManifest(source: File): BackupManifest = withContext(Dispatchers.IO) {
        ZipInputStream(source.inputStream()).use { zip ->
            var entry = zip.nextEntry
            while (entry != null) {
                if (entry.name == "manifest.json") {
                    val text = zip.readBytes().toString(Charsets.UTF_8)
                    return@withContext json.decodeFromString(BackupManifest.serializer(), text)
                }
                entry = zip.nextEntry
            }
            error("Not a valid Naleli backup file — manifest.json not found")
        }
    }

    /** Overwrites all current local learner data with the contents of the backup ZIP. Caller must warn first. */
    suspend fun restoreFrom(source: File) = withContext(Dispatchers.IO) {
        var learningData: LearningDataBackup? = null
        val evidenceFileBytes = mutableMapOf<String, ByteArray>()

        ZipInputStream(source.inputStream()).use { zip ->
            var entry = zip.nextEntry
            while (entry != null) {
                when {
                    entry.name == "learning_data.json" ->
                        learningData = json.decodeFromString(LearningDataBackup.serializer(), zip.readBytes().toString(Charsets.UTF_8))
                    entry.name.startsWith("evidence/") ->
                        evidenceFileBytes[entry.name.removePrefix("evidence/")] = zip.readBytes()
                }
                entry = zip.nextEntry
            }
        }

        val data = requireNotNull(learningData) { "Backup file is missing learning_data.json" }

        // Clear current local data (the caller already warned this is destructive).
        profileDao.deleteAll()
        dayDao.deleteAll()
        taskDao.deleteAll()
        evidenceDao.deleteAll()
        portfolioDao.deleteAll()
        certificateDao.deleteAll()
        File(context.filesDir, "evidence").deleteRecursively()

        val now = System.currentTimeMillis()
        profileDao.upsert(data.profile.toEntity())
        data.dayProgress.forEach { dayDao.upsert(it.toEntity()) }
        data.taskStatus.forEach { taskDao.upsert(it.toEntity()) }
        data.evidence.forEach { backup ->
            val destDir = File(context.filesDir, "evidence/${backup.taskId}").apply { mkdirs() }
            val destFile = File(destDir, backup.fileName)
            evidenceFileBytes["${backup.taskId}/${backup.fileName}"]?.let { bytes ->
                destFile.writeBytes(bytes)
            }
            evidenceDao.insert(backup.toEntity(destFile.absolutePath))
        }
        data.portfolio.forEach { portfolioDao.upsert(it.toEntity()) }
        data.certificates.forEach { backup ->
            val filePath = File(context.filesDir, "certificates/${backup.certificateNumber}.pdf").absolutePath
            certificateDao.insert(backup.toEntity(filePath))
        }
        now
    }

    private fun writeJsonEntry(zip: ZipOutputStream, name: String, content: String) {
        zip.putNextEntry(ZipEntry(name))
        zip.write(content.toByteArray(Charsets.UTF_8))
        zip.closeEntry()
    }
}

// --- Entity <-> backup DTO mapping -----------------------------------------

private fun LearnerProfileEntity.toBackup() = ProfileBackup(
    firstName = firstName, surname = surname, learnerCode = learnerCode,
    studentNumber = studentNumber, email = email, phone = phone,
    programmeId = programmeId, startDateEpochDay = startDateEpochDay,
    createdAt = createdAt, updatedAt = updatedAt,
)

private fun ProfileBackup.toEntity() = LearnerProfileEntity(
    firstName = firstName, surname = surname, learnerCode = learnerCode,
    studentNumber = studentNumber, email = email, phone = phone,
    programmeId = programmeId, startDateEpochDay = startDateEpochDay,
    createdAt = createdAt, updatedAt = updatedAt,
)

private fun DayProgressEntity.toBackup() = DayProgressBackup(
    dayNumber = dayNumber, status = status.name, startedAt = startedAt,
    completedAt = completedAt, reflectionText = reflectionText,
)

private fun DayProgressBackup.toEntity() = DayProgressEntity(
    dayNumber = dayNumber, status = DayStatus.valueOf(status), startedAt = startedAt,
    completedAt = completedAt, reflectionText = reflectionText,
)

private fun TaskStatusEntity.toBackup() = TaskStatusBackup(
    taskId = taskId, dayNumber = dayNumber, status = status.name,
    assessmentStatus = assessmentStatus.name, feedback = feedback,
    textResponse = textResponse, reviewAnswers = reviewAnswers,
    confidenceRating = confidenceRating, updatedAt = updatedAt,
)

private fun TaskStatusBackup.toEntity() = TaskStatusEntity(
    taskId = taskId, dayNumber = dayNumber, status = DayStatus.valueOf(status),
    assessmentStatus = AssessmentStatus.valueOf(assessmentStatus), feedback = feedback,
    textResponse = textResponse, reviewAnswers = reviewAnswers,
    confidenceRating = confidenceRating, updatedAt = updatedAt,
)

private fun EvidenceEntity.toBackup(includeFile: Boolean) = EvidenceBackup(
    evidenceId = evidenceId, taskId = taskId, dayNumber = dayNumber, fileName = fileName,
    fileType = fileType, relativeFilePath = if (includeFile) "$taskId/$fileName" else null,
    createdAt = createdAt, description = description,
)

private fun EvidenceBackup.toEntity(localPath: String) = EvidenceEntity(
    evidenceId = evidenceId, taskId = taskId, dayNumber = dayNumber, fileName = fileName,
    fileType = fileType, localPath = localPath, createdAt = createdAt, description = description,
)

private fun PortfolioItemEntity.toBackup() = PortfolioItemBackup(
    dayNumber = dayNumber, taskId = taskId, title = title, skillDemonstrated = skillDemonstrated,
    evidenceId = evidenceId, description = description, createdAt = createdAt,
)

private fun PortfolioItemBackup.toEntity() = PortfolioItemEntity(
    dayNumber = dayNumber, taskId = taskId, title = title, skillDemonstrated = skillDemonstrated,
    evidenceId = evidenceId, description = description, createdAt = createdAt,
)

private fun CertificateEntity.toBackup() = CertificateBackup(
    certificateNumber = certificateNumber, learnerId = learnerId, programmeId = programmeId, issuedAt = issuedAt,
)

private fun CertificateBackup.toEntity(filePath: String) = CertificateEntity(
    certificateNumber = certificateNumber, learnerId = learnerId, programmeId = programmeId,
    issuedAt = issuedAt, filePath = filePath,
)
