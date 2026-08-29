package com.naleli.tbl.ui.screens.journey

import androidx.compose.foundation.clickable
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
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Folder
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.BadgeMark
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.components.StatusBadge
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.SuccessGreen

@Composable
fun JourneyScreen(onOpenTask: (taskId: String) -> Unit) {
    val container = rememberAppContainer()
    val viewModel: JourneyViewModel = viewModel(factory = viewModelFactory { initializer { JourneyViewModel(container) } })
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
            Text("My Journey", style = MaterialTheme.typography.headlineSmall)
            Text(
                "${state.projectTitle} · 90-day project",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        items(state.phases, key = { it.stage.stageId }) { phase -> PhaseCard(phase, onOpenTask) }
    }
}

@Composable
private fun PhaseCard(phase: PhaseUi, onOpenTask: (String) -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth()) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
            Text(
                "PHASE ${phase.stage.stageNumber.toString().padStart(2, '0')}",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            when {
                phase.isActive -> Text("Active", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
                phase.isComplete -> StatusBadge("Complete", SuccessGreen, BadgeMark.CHECK)
                else -> Icon(
                    Icons.Filled.Lock,
                    contentDescription = "Locked",
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(16.dp),
                )
            }
        }
        Text(
            phase.stage.name,
            style = if (phase.isActive) MaterialTheme.typography.titleLarge else MaterialTheme.typography.titleMedium,
            color = if (phase.isActive) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Text(phase.stage.description, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(10.dp))
        NaleliProgressBar(
            progressFraction = if (phase.plannedCount == 0) 0f else phase.completedCount / phase.plannedCount.toFloat(),
        )
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(
                "Days ${phase.stage.dayStart}–${phase.stage.dayEnd}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Text(
                "${phase.completedCount} / ${phase.plannedCount} tasks",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        // Only the phase being worked opens into its workstreams — the rest
        // stay a one-line summary so all four phases stay readable at once.
        if (phase.isActive && phase.workstreams.isNotEmpty()) {
            Spacer(Modifier.height(12.dp))
            Text("WORKSTREAMS", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.height(4.dp))
            phase.workstreams.forEach { ws -> WorkstreamRow(ws, onOpenTask) }
        }
    }
}

@Composable
private fun WorkstreamRow(ws: WorkstreamUi, onOpenTask: (String) -> Unit) {
    val allDone = ws.completedCount == ws.totalCount
    val isCurrent = ws.currentTaskId != null
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(enabled = isCurrent) { ws.currentTaskId?.let(onOpenTask) }
            .padding(vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            if (allDone) Icons.Filled.CheckCircle else Icons.Filled.Folder,
            contentDescription = null,
            tint = if (isCurrent) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(18.dp),
        )
        Column(modifier = Modifier.weight(1f).padding(start = 10.dp, end = 8.dp)) {
            Text(
                ws.workstream.name,
                style = MaterialTheme.typography.bodyMedium,
                color = if (isCurrent) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
            )
            // A workstream the learner has work sitting in should say so.
            // Without this a stream reading "0/4" looks identical whether
            // it has never been opened or has three tasks awaiting review.
            if (!allDone && ws.openWorkCount > 0) {
                Text(
                    "${ws.completedCount} of ${ws.totalCount} complete · ${ws.openWorkCount} in progress",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
        when {
            isCurrent -> StatusBadge("Here", MaterialTheme.colorScheme.primary, BadgeMark.DOT)
            allDone -> StatusBadge("Complete", SuccessGreen, BadgeMark.CHECK)
            else -> Text("${ws.completedCount}/${ws.totalCount}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
