package com.naleli.tbl.ui.screens.progress

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
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
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.rememberAppContainer

@Composable
fun ProgressScreen(onBack: () -> Unit) {
    val container = rememberAppContainer()
    val viewModel: ProgressViewModel = viewModel(factory = viewModelFactory { initializer { ProgressViewModel(container) } })
    val summary by viewModel.summary.collectAsState()
    val stages by viewModel.stages.collectAsState()

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
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        BackHeader(title = "Progress", onBack = onBack)

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("Overall Progress", style = MaterialTheme.typography.titleMedium)
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("${progress.overallPercent}%", style = MaterialTheme.typography.headlineSmall, color = MaterialTheme.colorScheme.primary)
            }
            Spacer(Modifier.height(4.dp))
            NaleliProgressBar(progressFraction = progress.overallPercent / 100f)
            Spacer(Modifier.height(6.dp))
            Text("${progress.daysCompleted} of ${progress.totalDays} days completed", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("By Stage", style = MaterialTheme.typography.titleMedium)
            Spacer(Modifier.height(8.dp))
            stages.forEach { stage ->
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(stage.name, style = MaterialTheme.typography.bodyMedium)
                    Text("${stage.daysCompleted} / ${stage.totalDays}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Spacer(Modifier.height(4.dp))
                NaleliProgressBar(progressFraction = stage.fraction)
                Spacer(Modifier.height(14.dp))
            }
        }

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            StatRow("Tasks completed", progress.tasksCompleted.toString())
            StatRow("Evidence submitted", progress.evidenceCount.toString())
            StatRow("Portfolio items", progress.portfolioItemCount.toString())
            StatRow("Capstone status", if (progress.capstoneComplete) "Complete" else "Not yet complete")
        }
    }
}

@Composable
private fun StatRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Text(label, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value, style = MaterialTheme.typography.bodyMedium)
    }
    Spacer(Modifier.height(6.dp))
}
