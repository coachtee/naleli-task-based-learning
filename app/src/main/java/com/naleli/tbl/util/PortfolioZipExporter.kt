package com.naleli.tbl.util

import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import kotlinx.serialization.json.Json
import kotlinx.serialization.Serializable
import java.io.File
import java.util.zip.ZipEntry
import java.util.zip.ZipOutputStream

/**
 * Builds Naleli_<Programme>_Portfolio.zip (brief §15): profile, a portfolio
 * index, the evidence files behind each portfolio item, and the certificate
 * PDF if one has been issued. Distinct from the full "Backup My Learning"
 * export (data.repository.BackupRepository), which also carries day/task
 * progress for restoring the whole app — this is the learner-facing,
 * shareable artefact.
 */
object PortfolioZipExporter {

    @Serializable
    private data class PortfolioIndexEntry(
        val dayNumber: Int,
        val title: String,
        val skillDemonstrated: String,
        val description: String?,
        val evidenceFileName: String?,
    )

    @Serializable
    private data class PortfolioIndex(
        val learnerName: String,
        val learnerCode: String,
        val programmeId: String,
        val items: List<PortfolioIndexEntry>,
    )

    fun export(
        destination: File,
        profile: LearnerProfileEntity,
        portfolioItems: List<PortfolioItemEntity>,
        evidenceById: Map<String, EvidenceEntity>,
        certificate: CertificateEntity?,
    ): File {
        val json = Json { prettyPrint = true }
        val index = PortfolioIndex(
            learnerName = "${profile.firstName} ${profile.surname}",
            learnerCode = profile.learnerCode,
            programmeId = profile.programmeId,
            items = portfolioItems.map { item ->
                PortfolioIndexEntry(
                    dayNumber = item.dayNumber,
                    title = item.title,
                    skillDemonstrated = item.skillDemonstrated,
                    description = item.description,
                    evidenceFileName = item.evidenceId?.let { evidenceById[it]?.fileName },
                )
            },
        )

        ZipOutputStream(destination.outputStream()).use { zip ->
            zip.putNextEntry(ZipEntry("portfolio_index.json"))
            zip.write(json.encodeToString(PortfolioIndex.serializer(), index).toByteArray(Charsets.UTF_8))
            zip.closeEntry()

            portfolioItems.forEach { item ->
                val evidence = item.evidenceId?.let { evidenceById[it] } ?: return@forEach
                val sourceFile = File(evidence.localPath)
                if (sourceFile.exists()) {
                    zip.putNextEntry(ZipEntry("evidence/day-${item.dayNumber}/${evidence.fileName}"))
                    sourceFile.inputStream().use { it.copyTo(zip) }
                    zip.closeEntry()
                }
            }

            certificate?.let { cert ->
                val certFile = File(cert.filePath)
                if (certFile.exists()) {
                    zip.putNextEntry(ZipEntry("certificate/${certFile.name}"))
                    certFile.inputStream().use { it.copyTo(zip) }
                    zip.closeEntry()
                }
            }
        }
        return destination
    }
}
