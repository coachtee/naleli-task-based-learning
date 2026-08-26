package com.naleli.tbl.ui.screens.portfolio

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import com.naleli.tbl.util.PortfolioZipExporter
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import java.io.File

data class PortfolioUiState(
    val isLoading: Boolean = true,
    val profile: LearnerProfileEntity? = null,
    val items: List<PortfolioItemEntity> = emptyList(),
    val evidenceById: Map<String, EvidenceEntity> = emptyMap(),
    val latestCertificate: CertificateEntity? = null,
)

class PortfolioViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(PortfolioUiState())
    val state: StateFlow<PortfolioUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile()
            combine(
                container.portfolioRepository.observeAll(),
                container.evidenceRepository.observeAll(),
                container.certificateRepository.observeAll(),
            ) { items, evidence, certificates ->
                PortfolioUiState(
                    isLoading = false,
                    profile = profile,
                    items = items,
                    evidenceById = evidence.associateBy { it.evidenceId },
                    latestCertificate = certificates.maxByOrNull { it.issuedAt },
                )
            }.collect { _state.value = it }
        }
    }

    /** Builds the portfolio ZIP into app cache; the caller streams it to wherever the user picked. */
    suspend fun buildExportFile(): File = withContext(Dispatchers.IO) {
        val current = _state.value
        val profile = requireNotNull(current.profile) { "No learner profile" }
        val cacheDir = File(container.context.cacheDir, "portfolio_export").apply { mkdirs() }
        val destination = File(cacheDir, "Naleli_Digital_Foundation_Portfolio.zip")
        PortfolioZipExporter.export(
            destination = destination,
            profile = profile,
            portfolioItems = current.items,
            evidenceById = current.evidenceById,
            certificate = current.latestCertificate,
        )
    }
}
