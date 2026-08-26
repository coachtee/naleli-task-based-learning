package com.naleli.tbl.ui.screens.certificate

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.domain.CertificateEligibilityEvaluator
import com.naleli.tbl.domain.CertificateEligibilityResult
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.combine
import kotlinx.coroutines.launch

data class CertificateUiState(
    val isLoading: Boolean = true,
    val profile: LearnerProfileEntity? = null,
    val course: Course? = null,
    val eligibility: CertificateEligibilityResult? = null,
    val latestCertificate: CertificateEntity? = null,
    val isGenerating: Boolean = false,
)

class CertificateViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(CertificateUiState())
    val state: StateFlow<CertificateUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            val profile = container.profileRepository.getProfile() ?: return@launch
            val course = container.contentRepository.getCourse(profile.programmeId)

            combine(
                container.progressRepository.observeAllDays(),
                container.portfolioRepository.observeAll(),
                container.certificateRepository.observeAll(),
            ) { days, portfolio, certificates ->
                CertificateUiState(
                    isLoading = false,
                    profile = profile,
                    course = course,
                    eligibility = CertificateEligibilityEvaluator.evaluate(course, days, portfolio.size),
                    latestCertificate = certificates.maxByOrNull { it.issuedAt },
                )
            }.collect { _state.value = it }
        }
    }

    fun generate() {
        val current = _state.value
        val profile = current.profile ?: return
        val course = current.course ?: return
        if (current.eligibility?.isEligible != true) return

        _state.value = current.copy(isGenerating = true)
        viewModelScope.launch {
            container.certificateRepository.generate(profile, course.credential, course.programmeName)
            _state.value = _state.value.copy(isGenerating = false)
        }
    }
}
