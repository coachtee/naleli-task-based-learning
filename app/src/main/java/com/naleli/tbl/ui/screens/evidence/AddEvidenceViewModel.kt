package com.naleli.tbl.ui.screens.evidence

import android.net.Uri
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.naleli.tbl.AppContainer
import com.naleli.tbl.data.db.entity.EvidenceEntity
import kotlinx.coroutines.flow.SharingStarted
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.stateIn
import kotlinx.coroutines.launch

/** Evidence capture is generic over any taskId — this view model has no
 * dependency on which content model (day-based or workstream-based) the
 * task came from, so Naleli Workspace's Task Workspace screen can reuse the
 * same "Prove Your Work" flow as everything else. */
class AddEvidenceViewModel(container: AppContainer, private val taskId: String) : ViewModel() {
    private val evidenceRepository = container.evidenceRepository

    val evidence: StateFlow<List<EvidenceEntity>> = evidenceRepository.observeForTask(taskId)
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5_000), emptyList())

    fun attach(uri: Uri, description: String?) {
        viewModelScope.launch { evidenceRepository.attachFromUri(taskId, 0, uri, null, description) }
    }

    fun delete(item: EvidenceEntity) {
        viewModelScope.launch { evidenceRepository.delete(item) }
    }
}
