package com.naleli.tbl.ui.screens.portfolio

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.WorkspacePremium
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.AssessmentStatus
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.StatusChip
import com.naleli.tbl.ui.components.colors
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.NaleliPurpleLight
import com.naleli.tbl.util.shareIntent
import com.naleli.tbl.util.viewIntent
import kotlinx.coroutines.launch
import java.io.File

@Composable
fun PortfolioScreen() {
    val container = rememberAppContainer()
    val viewModel: PortfolioViewModel = viewModel(factory = viewModelFactory { initializer { PortfolioViewModel(container) } })
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    val createZipLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("application/zip")) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        scope.launch {
            val builtFile = viewModel.buildExportFile()
            context.contentResolver.openOutputStream(uri)?.use { out ->
                builtFile.inputStream().use { it.copyTo(out) }
            }
        }
    }

    if (state.isLoading) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Text("My Portfolio", style = MaterialTheme.typography.headlineSmall)
            Text(
                "These are things you can actually do — built automatically from your evidence.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        Icons.Filled.WorkspacePremium,
                        contentDescription = null,
                        tint = MaterialTheme.colorScheme.primary,
                        modifier = Modifier.size(32.dp),
                    )
                    Column(modifier = Modifier.padding(start = 12.dp)) {
                        Text("${state.items.size}", style = MaterialTheme.typography.headlineSmall)
                        Text("Portfolio items", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            Spacer(Modifier.height(4.dp))
            Button(
                onClick = { createZipLauncher.launch("Naleli_Digital_Foundation_Portfolio.zip") },
                enabled = state.items.isNotEmpty(),
            ) {
                Text("Export Portfolio (ZIP)")
            }
        }

        if (state.items.isEmpty()) {
            item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("No portfolio items yet. Complete a Practice or Work Mission task with evidence to add one.", style = MaterialTheme.typography.bodyMedium)
                }
            }
        }

        items(state.items) { item ->
            val evidence = item.evidenceId?.let { state.evidenceById[it] }
            val assessment = state.assessmentByTask[item.taskId] ?: AssessmentStatus.NOT_YET_ASSESSED
            PortfolioItemCard(
                item = item,
                fileType = evidence?.fileType,
                assessment = assessment,
                onOpen = { evidence?.let { context.startActivity(viewIntent(context, File(it.localPath), it.fileType)) } },
                onShare = { evidence?.let { context.startActivity(shareIntent(context, File(it.localPath), it.fileType)) } },
            )
        }
    }
}

@Composable
private fun PortfolioItemCard(
    item: PortfolioItemEntity,
    fileType: String?,
    assessment: AssessmentStatus,
    onOpen: () -> Unit,
    onShare: () -> Unit,
) {
    NaleliCard(modifier = Modifier.fillMaxWidth()) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Column(modifier = Modifier.weight(1f)) {
                Text("DAY ${item.dayNumber}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(item.title, style = MaterialTheme.typography.titleMedium)
                Text(item.skillDemonstrated, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            fileType?.let { TypeTag(it) }
        }
        item.description?.let {
            Spacer(Modifier.height(6.dp))
            Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        Spacer(Modifier.height(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            StatusChip(text = if (assessment == AssessmentStatus.NOT_YET_ASSESSED) "Evidence submitted" else assessment.name.replace('_', ' '), colors = assessment.colors())
        }
        Spacer(Modifier.height(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            OutlinedButton(onClick = onOpen) { Text("Open") }
            OutlinedButton(onClick = onShare) { Text("Share") }
        }
    }
}

@Composable
private fun TypeTag(mimeType: String) {
    val label = when {
        mimeType.startsWith("image/") -> "Image"
        mimeType.contains("spreadsheet") || mimeType.contains("csv") || mimeType.contains("excel") -> "Spreadsheet"
        mimeType.contains("presentation") || mimeType.contains("powerpoint") -> "Presentation"
        mimeType.contains("word") || mimeType.contains("document") || mimeType == "text/plain" -> "Document"
        mimeType == "application/pdf" -> "PDF"
        else -> "File"
    }
    androidx.compose.material3.Surface(
        color = NaleliPurpleLight,
        shape = MaterialTheme.shapes.extraSmall,
    ) {
        Text(
            label,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.primary,
            modifier = Modifier.padding(horizontal = 8.dp, vertical = 4.dp),
        )
    }
}
