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
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
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
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
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
            Text("Portfolio", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Automatically built from your completed, evidence-bearing tasks.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(8.dp))
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
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("Day ${item.dayNumber}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(item.title, style = MaterialTheme.typography.titleMedium)
                Text(item.skillDemonstrated, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                item.description?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
                if (evidence != null) {
                    Spacer(Modifier.height(8.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                        OutlinedButton(onClick = {
                            context.startActivity(viewIntent(context, File(evidence.localPath), evidence.fileType))
                        }) { Text("Open") }
                        OutlinedButton(onClick = {
                            context.startActivity(shareIntent(context, File(evidence.localPath), evidence.fileType))
                        }) { Text("Share") }
                    }
                }
            }
        }
    }
}
