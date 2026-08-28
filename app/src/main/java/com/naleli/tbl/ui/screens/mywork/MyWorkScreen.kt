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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.TaskTier
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.TaskStateBadge
import com.naleli.tbl.ui.components.TierBadge
import com.naleli.tbl.ui.rememberAppContainer

/**
 * A real work list, not a syllabus (approved redesign §2): every task the
 * learner is responsible for, grouped into the four states a person
 * instantly understands — To Do, In Progress, Assessment, Done.
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

    val totalTasks = state.toDo.size + state.inProgress.size + state.assessment.size + state.done.size

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        item {
            Text("My Work", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Your responsibilities on this project",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(16.dp))
        }

        if (totalTasks == 0) {
            item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("Nothing here yet.", style = MaterialTheme.typography.bodyMedium)
                }
            }
        }

        taskSection("In Progress", state.inProgress, onOpenTask)
        taskSection("Assessment", state.assessment, onOpenTask)
        taskSection("To Do", state.toDo, onOpenTask)
        taskSection("Done", state.done, onOpenTask)

        item {
            Spacer(Modifier.height(12.dp))
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Text(state.phaseName, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f), maxLines = 1, overflow = TextOverflow.Ellipsis)
                    Text("${state.phaseCompletedCount} / ${state.phasePlannedCount} tasks", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

private fun androidx.compose.foundation.lazy.LazyListScope.taskSection(title: String, rows: List<WorkTaskRow>, onOpenTask: (String) -> Unit) {
    if (rows.isEmpty()) return
    item {
        Spacer(Modifier.height(12.dp))
        Text("${title.uppercase()} · ${rows.size}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
    }
    items(rows, key = { it.task.taskId }) { row -> TaskRow(row, onOpenTask) }
}

@Composable
private fun TaskRow(row: WorkTaskRow, onOpenTask: (String) -> Unit) {
    val subtitle = when {
        row.locked -> row.lockReason ?: "Locked"
        row.state == TaskProgressState.IN_PROGRESS || row.state == TaskProgressState.NEEDS_REVISION ->
            "${row.workstreamName} · Step ${row.stepsDone} of ${row.stepsTotal}"
        row.state == TaskProgressState.SUBMITTED -> "${row.workstreamName} · Awaiting assessment"
        row.state == TaskProgressState.COMPETENT -> "${row.workstreamName} · Competence recorded"
        else -> "${row.workstreamName} · ~${row.task.estimatedMinutes} min"
    }

    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(enabled = !row.locked) { onOpenTask(row.task.taskId) }) {
        // Title on its own full-width line, badges beneath — the previous
        // layout put a status label beside the title, which squeezed and
        // ellipsised real task names on a real device.
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.Top) {
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    row.task.title,
                    style = MaterialTheme.typography.titleMedium,
                    color = if (row.locked) MaterialTheme.colorScheme.onSurfaceVariant else MaterialTheme.colorScheme.onSurface,
                    maxLines = 2,
                    overflow = TextOverflow.Ellipsis,
                )
                Spacer(Modifier.height(2.dp))
                Text(subtitle, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant, maxLines = 2, overflow = TextOverflow.Ellipsis)
            }
            if (row.isCurrent) {
                Text(
                    "CURRENT",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.primary,
                    modifier = Modifier.padding(start = 8.dp, top = 2.dp),
                )
            }
        }
        Spacer(Modifier.height(10.dp))
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            if (row.task.tier == TaskTier.REQUIRED && !row.locked) TierBadge(row.task.tier)
            TaskStateBadge(row.state, row.locked)
        }
    }
}
