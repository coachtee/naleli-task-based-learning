package com.naleli.tbl.ui.screens.evidence

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.db.entity.EvidenceEntity
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class EvidenceUiState(val isLoading: Boolean = true, val items: List<EvidenceEntity> = emptyList())

class EvidenceViewModel(private val container: AppContainer) : ViewModel() {
    private val _state = MutableStateFlow(EvidenceUiState())
    val state: StateFlow<EvidenceUiState> = _state.asStateFlow()

    init {
        viewModelScope.launch {
            container.evidenceRepository.observeAll().collect { items ->
                _state.value = EvidenceUiState(isLoading = false, items = items)
            }
        }
    }
}
