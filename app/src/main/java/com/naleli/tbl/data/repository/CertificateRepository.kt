package com.naleli.tbl.data.repository

import android.content.Context
import com.naleli.tbl.data.content.CredentialInfo
import com.naleli.tbl.data.db.dao.CertificateDao
import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.util.CertificatePdfGenerator
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.withContext

class CertificateRepository(
    private val context: Context,
    private val dao: CertificateDao,
) {
    fun observeAll(): Flow<List<CertificateEntity>> = dao.observeAll()

    /** Certificate number is unique within this local app install (brief §18). */
    suspend fun generate(
        profile: LearnerProfileEntity,
        credential: CredentialInfo,
        programmeName: String,
    ): CertificateEntity = withContext(Dispatchers.IO) {
        val sequence = dao.count() + 1
        val certificateNumber = "NIBS-${profile.learnerCode}-${sequence.toString().padStart(3, '0')}"
        val file = CertificatePdfGenerator.generate(
            context = context,
            profile = profile,
            credential = credential,
            programmeName = programmeName,
            certificateNumber = certificateNumber,
        )
        val entity = CertificateEntity(
            certificateNumber = certificateNumber,
            learnerId = profile.id,
            programmeId = profile.programmeId,
            issuedAt = System.currentTimeMillis(),
            filePath = file.absolutePath,
        )
        dao.insert(entity)
        entity
    }

    suspend fun deleteAll() = dao.deleteAll()
}
