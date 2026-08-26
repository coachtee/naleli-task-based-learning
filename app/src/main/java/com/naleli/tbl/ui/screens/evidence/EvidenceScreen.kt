package com.naleli.tbl.ui.screens.evidence

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.DocumentScanner
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.InsertDriveFile
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.AssessmentStatus
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.StatusChip
import com.naleli.tbl.ui.components.colors
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.util.viewIntent
import java.io.File
import java.text.DateFormat
import java.util.Date

@Composable
fun EvidenceScreen(onScanWorksheetCode: () -> Unit = {}) {
    val container = rememberAppContainer()
    val viewModel: EvidenceViewModel = viewModel(factory = viewModelFactory { initializer { EvidenceViewModel(container) } })
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current

    var filter by remember { mutableStateOf<EvidenceKind?>(null) }
    val filtered = if (filter == null) state.items else state.items.filter { it.kind == filter }
    val grouped = filtered.groupBy { it.evidence.dayNumber }.toSortedMap()

    Column(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.padding(20.dp)) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text("My Work", style = MaterialTheme.typography.headlineSmall)
                    Text(
                        "Everything you've submitted as proof of your work.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
                IconButton(onClick = onScanWorksheetCode) {
                    Icon(Icons.Filled.QrCodeScanner, contentDescription = "Find a worksheet task by code")
                }
            }
            Spacer(Modifier.height(12.dp))
            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                item { WorkFilterChip("All", filter == null) { filter = null } }
                item { WorkFilterChip("Documents", filter == EvidenceKind.DOCUMENT) { filter = EvidenceKind.DOCUMENT } }
                item { WorkFilterChip("Images", filter == EvidenceKind.IMAGE) { filter = EvidenceKind.IMAGE } }
                item { WorkFilterChip("Worksheets", filter == EvidenceKind.WORKSHEET) { filter = EvidenceKind.WORKSHEET } }
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(horizontal = 20.dp, vertical = 4.dp),
            verticalArrangement = Arrangement.spacedBy(10.dp),
        ) {
            if (filtered.isEmpty()) {
                item {
                    NaleliCard(modifier = Modifier.fillMaxWidth()) {
                        Text("No evidence here yet. Attach evidence from a task's \"Prove Your Work\" step.", style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
            grouped.forEach { (dayNumber, rows) ->
                item {
                    Spacer(Modifier.height(8.dp))
                    Text("DAY $dayNumber", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                }
                items(rows) { row ->
                    EvidenceRowCard(row) {
                        context.startActivity(viewIntent(context, File(row.evidence.localPath), row.evidence.fileType))
                    }
                }
            }
        }
    }
}

@Composable
private fun WorkFilterChip(label: String, selected: Boolean, onClick: () -> Unit) {
    FilterChip(
        selected = selected,
        onClick = onClick,
        label = { Text(label) },
        colors = FilterChipDefaults.filterChipColors(
            selectedContainerColor = MaterialTheme.colorScheme.primary,
            selectedLabelColor = MaterialTheme.colorScheme.onPrimary,
        ),
    )
}

@Composable
private fun EvidenceRowCard(row: EvidenceRow, onOpen: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth()) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(row.kind.icon(), contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Column(modifier = Modifier.weight(1f).padding(horizontal = 12.dp)) {
                Text(row.taskTitle, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(row.evidence.fileName, style = MaterialTheme.typography.titleMedium)
                Text(
                    DateFormat.getDateInstance().format(Date(row.evidence.createdAt)),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                StatusChip(text = row.assessmentStatus.statusLabel(), colors = row.assessmentStatus.colors())
                Spacer(Modifier.height(6.dp))
                OutlinedButton(onClick = onOpen) { Text("Open") }
            }
        }
    }
}

private fun AssessmentStatus.statusLabel(): String = when (this) {
    AssessmentStatus.NOT_YET_ASSESSED -> "Submitted"
    AssessmentStatus.COMPETENT -> "Competent"
    AssessmentStatus.NOT_YET_COMPETENT -> "Not Yet Competent"
    AssessmentStatus.RESUBMIT -> "Resubmit"
}

private fun EvidenceKind.icon() = when (this) {
    EvidenceKind.DOCUMENT -> Icons.Filled.Description
    EvidenceKind.IMAGE -> Icons.Filled.Image
    EvidenceKind.WORKSHEET -> Icons.Filled.DocumentScanner
    EvidenceKind.OTHER -> Icons.Filled.InsertDriveFile
}
