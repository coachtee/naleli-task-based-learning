package com.naleli.tbl.ui.screens.progress

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
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
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.rememberAppContainer

@Composable
fun ProgressScreen() {
    val container = rememberAppContainer()
    val viewModel: ProgressViewModel = viewModel(factory = viewModelFactory { initializer { ProgressViewModel(container) } })
    val summary by viewModel.summary.collectAsState()

    val progress = summary
    if (progress == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Progress", style = MaterialTheme.typography.headlineSmall)

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("${progress.overallPercent}% complete", style = MaterialTheme.typography.headlineSmall)
            Spacer(Modifier.height(8.dp))
            NaleliProgressBar(progressFraction = progress.overallPercent / 100f)
            Spacer(Modifier.height(8.dp))
            Text("${progress.daysCompleted} / ${progress.totalDays} days", style = MaterialTheme.typography.bodyMedium)
        }

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            StatRow("Tasks completed", progress.tasksCompleted.toString())
            StatRow("Evidence submitted", progress.evidenceCount.toString())
            StatRow("Portfolio items", progress.portfolioItemCount.toString())
            StatRow("Current stage", progress.currentStageName ?: "—")
            StatRow("Capstone status", if (progress.capstoneComplete) "Complete" else "Not yet complete")
        }
    }
}

@Composable
private fun StatRow(label: String, value: String) {
    androidx.compose.foundation.layout.Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, style = MaterialTheme.typography.bodyMedium)
    }
    Spacer(Modifier.height(6.dp))
}
