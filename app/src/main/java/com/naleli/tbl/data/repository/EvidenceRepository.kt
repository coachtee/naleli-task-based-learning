package com.naleli.tbl.data.repository

import android.content.Context
import android.net.Uri
import android.provider.OpenableColumns
import android.webkit.MimeTypeMap
import com.naleli.tbl.data.db.dao.EvidenceDao
import com.naleli.tbl.data.db.entity.EvidenceEntity
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext
import java.io.File
import java.util.UUID

/**
 * Evidence files are copied into app-private storage
 * (filesDir/evidence/<taskId>/...) so they survive independent of whatever
 * transient content:// the picker/camera handed back, and are always found
 * again by taskId (brief §14).
 */
class EvidenceRepository(
    private val context: Context,
    private val dao: EvidenceDao,
) {
    fun observeAll(): Flow<List<EvidenceEntity>> = dao.observeAll()
    fun observeForTask(taskId: String): Flow<List<EvidenceEntity>> = dao.observeForTask(taskId)
    suspend fun getForTask(taskId: String): List<EvidenceEntity> = dao.getForTask(taskId)

    fun evidenceDir(taskId: String): File =
        File(context.filesDir, "evidence/$taskId").apply { mkdirs() }

    /**
     * A written answer, saved as evidence.
     *
     * Not every deliverable is a file. A learner explaining Input,
     * Processing, Storage and Output has produced real evidence, and asking
     * them to write it elsewhere and then photograph it is a barrier that
     * costs the submission. Storing it as a text file keeps one evidence
     * path: the rubric, the portfolio and backup all see it as they see
     * anything else, with no second code path to keep in step.
     */
    suspend fun attachWrittenResponse(taskId: String, text: String, description: String?): EvidenceEntity =
        withContext(Dispatchers.IO) {
            val fileName = uniqueFileName(taskId, "written-answer.txt")
            val destFile = File(evidenceDir(taskId), fileName)
            destFile.writeText(text)
            val entity = EvidenceEntity(
                evidenceId = UUID.randomUUID().toString(),
                taskId = taskId,
                dayNumber = 0,
                fileName = fileName,
                fileType = "text/plain",
                localPath = destFile.absolutePath,
                createdAt = System.currentTimeMillis(),
                description = description,
            )
            dao.insert(entity)
            entity
        }

    suspend fun attachFromUri(
        taskId: String,
        dayNumber: Int,
        sourceUri: Uri,
        suggestedName: String?,
        description: String?,
    ): EvidenceEntity = withContext(Dispatchers.IO) {
        val evidenceId = UUID.randomUUID().toString()
        val mimeType = context.contentResolver.getType(sourceUri) ?: "application/octet-stream"
        val fileName = uniqueFileName(taskId, suggestedName ?: displayNameOf(sourceUri, mimeType))
        val destFile = File(evidenceDir(taskId), fileName)

        context.contentResolver.openInputStream(sourceUri).use { input ->
            requireNotNull(input) { "Could not open evidence source" }
            destFile.outputStream().use { output -> input.copyTo(output) }
        }

        val entity = EvidenceEntity(
            evidenceId = evidenceId,
            taskId = taskId,
            dayNumber = dayNumber,
            fileName = fileName,
            fileType = mimeType,
            localPath = destFile.absolutePath,
            createdAt = System.currentTimeMillis(),
            description = description,
        )
        dao.insert(entity)
        entity
    }

    /** Prefers the source's real display name (e.g. "Thabiso_Customer_Register.xlsx") over a generic one. */
    private fun displayNameOf(uri: Uri, mimeType: String): String {
        context.contentResolver.query(uri, arrayOf(OpenableColumns.DISPLAY_NAME), null, null, null)?.use { cursor ->
            val nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME)
            if (nameIndex >= 0 && cursor.moveToFirst()) {
                cursor.getString(nameIndex)?.let { return it }
            }
        }
        val extension = MimeTypeMap.getSingleton().getExtensionFromMimeType(mimeType)
        return "evidence-${System.currentTimeMillis()}${if (extension != null) ".$extension" else ""}"
    }

    /** Avoids silently overwriting a same-named evidence file already attached to this task. */
    private fun uniqueFileName(taskId: String, desiredName: String): String {
        val dir = evidenceDir(taskId)
        if (!File(dir, desiredName).exists()) return desiredName
        val dotIndex = desiredName.lastIndexOf('.')
        val base = if (dotIndex > 0) desiredName.substring(0, dotIndex) else desiredName
        val ext = if (dotIndex > 0) desiredName.substring(dotIndex) else ""
        var counter = 1
        var candidate: String
        do {
            candidate = "$base ($counter)$ext"
            counter++
        } while (File(dir, candidate).exists())
        return candidate
    }

    suspend fun delete(entity: EvidenceEntity) = withContext(Dispatchers.IO) {
        File(entity.localPath).delete()
        dao.delete(entity)
    }

    suspend fun deleteAll() = withContext(Dispatchers.IO) {
        File(context.filesDir, "evidence").deleteRecursively()
        dao.deleteAll()
    }
}
