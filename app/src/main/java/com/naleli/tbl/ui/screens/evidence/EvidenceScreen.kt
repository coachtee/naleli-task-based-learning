package com.naleli.tbl.ui.screens.evidence

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import java.text.DateFormat
import java.util.Date

@Composable
fun EvidenceScreen() {
    val container = rememberAppContainer()
    val viewModel: EvidenceViewModel = viewModel(factory = viewModelFactory { initializer { EvidenceViewModel(container) } })
    val state by viewModel.state.collectAsState()

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
            Text("Evidence", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Everything you've attached to a task, across every day.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        if (state.items.isEmpty()) {
            item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("No evidence attached yet. Attach evidence from a task on your Day screen.", style = MaterialTheme.typography.bodyMedium)
                }
            }
        }
        items(state.items) { evidence ->
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("Day ${evidence.dayNumber}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(evidence.fileName, style = MaterialTheme.typography.titleMedium)
                Text(evidence.fileType, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(
                    DateFormat.getDateTimeInstance().format(Date(evidence.createdAt)),
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                evidence.description?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
            }
        }
    }
}
