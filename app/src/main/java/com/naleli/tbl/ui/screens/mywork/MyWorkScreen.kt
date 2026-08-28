package com.naleli.tbl.ui.screens.mywork

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
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.RadioButtonUnchecked
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.style.TextDecoration
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.TierDot
import com.naleli.tbl.ui.components.color
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer

/**
 * A real task workspace, not a syllabus (brief §2): today's task with its
 * sub-step checklist front and centre, then what's queued, then a one-line
 * phase summary.
 */
@Composable
fun MyWorkScreen(onOpenTask: (taskId: String) -> Unit) {
    val container = rememberAppContainer()
    val viewModel: MyWorkViewModel = viewModel(factory = viewModelFactory { initializer { MyWorkViewModel(container) } })
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
            Text("My Work", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Your responsibilities on this project",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        state.today?.let { today ->
            item { Text("TODAY", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary) }
            item {
                NaleliCard(modifier = Modifier.fillMaxWidth().clickable { onOpenTask(today.task.taskId) }) {
                    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        TierDot(today.task.tier)
                        Column(modifier = Modifier.weight(1f).padding(start = 10.dp)) {
                            Text(today.task.title, style = MaterialTheme.typography.titleMedium)
                            Text(
                                "${today.task.tier.label()} · ~${today.task.estimatedMinutes} min",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        Text(today.state.label(), style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
                    }
                    Spacer(Modifier.height(10.dp))
                    today.subSteps.forEach { row -> SubStepRowView(row) }
                }
            }
        }

        if (state.next.isNotEmpty()) {
            item {
                Spacer(Modifier.height(4.dp))
                Text("NEXT", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            }
            items(state.next) { row -> NextTaskRow(row, onOpenTask) }
        }

        item {
            Spacer(Modifier.height(4.dp))
            Text("THIS PHASE", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text(state.phaseName, style = MaterialTheme.typography.titleMedium)
                    Text("${state.phaseCompletedCount} / ${state.phasePlannedCount} tasks", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
private fun SubStepRowView(row: SubStepRow) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
        val icon = when {
            row.complete -> Icons.Filled.CheckCircle
            row.locked -> Icons.Filled.Lock
            else -> Icons.Filled.RadioButtonUnchecked
        }
        val tint = when {
            row.complete -> MaterialTheme.colorScheme.primary
            row.locked -> MaterialTheme.colorScheme.onSurfaceVariant
            else -> MaterialTheme.colorScheme.onSurfaceVariant
        }
        Icon(icon, contentDescription = null, tint = tint, modifier = Modifier.size(18.dp))
        Column(modifier = Modifier.padding(start = 10.dp)) {
            Text(
                row.subStep.title,
                style = MaterialTheme.typography.bodyMedium,
                textDecoration = if (row.complete) TextDecoration.LineThrough else TextDecoration.None,
                color = if (row.locked) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
            )
            Text("${row.subStep.estimatedMinutes} min", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}

@Composable
private fun NextTaskRow(row: NextTaskUi, onOpenTask: (String) -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(enabled = !row.locked) { onOpenTask(row.task.taskId) }) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            if (row.locked) {
                Icon(Icons.Filled.Lock, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(16.dp))
            } else {
                TierDot(row.task.tier)
            }
            Column(modifier = Modifier.weight(1f).padding(start = 10.dp)) {
                Text(row.task.title, style = MaterialTheme.typography.titleMedium, color = if (row.locked) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface)
                Text(
                    row.lockReason ?: "${row.task.tier.label()} · ~${row.task.estimatedMinutes} min",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }
    }
}
