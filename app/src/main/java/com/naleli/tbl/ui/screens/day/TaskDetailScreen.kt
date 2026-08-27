package com.naleli.tbl.ui.screens.day

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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Download
import androidx.compose.material.icons.filled.InsertDriveFile
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.ResponseType
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.StatusChip
import com.naleli.tbl.ui.components.colors
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.util.viewIntent
import kotlinx.coroutines.launch
import java.io.File

/**
 * Task drill-down: instructions, the course files a task needs, evidence
 * status, and completion — one focused work item at a time (brief V1.5
 * §8/§19). "Prove Your Work" hands off to AddEvidenceScreen.
 */
@Composable
fun TaskDetailScreen(
    dayNumber: Int,
    taskId: String,
    onBack: () -> Unit,
    onAddEvidence: () -> Unit,
    onTaskCompleted: () -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: DayViewModel = viewModel(
        factory = viewModelFactory { initializer { DayViewModel(container, dayNumber) } },
    )
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    val task = state.day?.tasks?.firstOrNull { it.taskId == taskId }
    val course = state.course

    if (task == null || course == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    if (state.isLocked) {
        Column(Modifier.fillMaxSize()) {
            Column(modifier = Modifier.padding(20.dp)) {
                com.naleli.tbl.ui.components.BackHeader(onBack = onBack)
            }
            Column(
                modifier = Modifier.fillMaxSize().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                Icon(
                    Icons.Filled.Lock,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.onSurfaceVariant,
                    modifier = Modifier.size(40.dp),
                )
                Spacer(Modifier.height(12.dp))
                Text("Day $dayNumber is locked", style = MaterialTheme.typography.titleLarge)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Complete Day ${dayNumber - 1} to unlock \"${task.title}\".",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                    textAlign = androidx.compose.ui.text.style.TextAlign.Center,
                )
            }
        }
        return
    }

    val statusEntity = state.taskStatuses[taskId]
    val isComplete = statusEntity?.status == DayStatus.COMPLETE
    val evidence = state.evidenceByTask[taskId].orEmpty()
    var textNotes by remember(taskId) { mutableStateOf(statusEntity?.textResponse.orEmpty()) }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            com.naleli.tbl.ui.components.BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            StatusChip(text = if (isComplete) "COMPLETE" else "TO DO", colors = (if (isComplete) DayStatus.COMPLETE else DayStatus.NOT_STARTED).colors())
            Spacer(Modifier.height(8.dp))
            Text(task.title, style = MaterialTheme.typography.headlineSmall)
            Text(
                "Estimated time: ${task.estimatedTime}",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("INSTRUCTIONS", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(6.dp))
                Text(task.instructions, style = MaterialTheme.typography.bodyMedium)
            }
        }

        if (task.supportContent.isNotEmpty()) {
            item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("FILES TO USE", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.height(6.dp))
                    task.supportContent.forEach { ref ->
                        Row(
                            modifier = Modifier.fillMaxWidth(),
                            verticalAlignment = Alignment.CenterVertically,
                            horizontalArrangement = Arrangement.SpaceBetween,
                        ) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Icon(Icons.Filled.InsertDriveFile, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                                Spacer(Modifier.width(8.dp))
                                Text(ref.label, style = MaterialTheme.typography.bodyMedium)
                            }
                            IconButton(onClick = {
                                scope.launch {
                                    val file = viewModel.copyResourceToDevice(ref)
                                    val mimeType = context.contentResolver.getType(com.naleli.tbl.util.fileProviderUri(context, file))
                                    context.startActivity(viewIntent(context, file, mimeType))
                                }
                            }) {
                                Icon(Icons.Filled.Download, contentDescription = "Open / copy ${ref.label}")
                            }
                        }
                    }
                }
            }
        }

        when (task.responseType) {
            ResponseType.TEXT -> item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("YOUR NOTES", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.height(6.dp))
                    OutlinedTextField(
                        value = textNotes,
                        onValueChange = { textNotes = it },
                        modifier = Modifier.fillMaxWidth(),
                        minLines = 3,
                        label = { Text("Key points, in your own words") },
                    )
                }
            }
            ResponseType.FILE -> item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("PROVE YOUR WORK", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                            Text(
                                if (evidence.isEmpty()) "Evidence required" else "${evidence.size} file(s) attached",
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                        if (evidence.isNotEmpty()) {
                            Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        }
                    }
                    Spacer(Modifier.height(10.dp))
                    Button(onClick = onAddEvidence, modifier = Modifier.fillMaxWidth()) {
                        Text(if (evidence.isEmpty()) "Add Evidence" else "Manage Evidence")
                    }
                }
            }
            else -> Unit
        }

        item {
            Spacer(Modifier.height(4.dp))
            Button(
                onClick = {
                    if (task.responseType == ResponseType.TEXT) viewModel.saveTextResponse(task, textNotes)
                    viewModel.markTaskComplete(task, true)
                    onTaskCompleted()
                },
                modifier = Modifier.fillMaxWidth(),
                enabled = !isComplete && when (task.responseType) {
                    ResponseType.TEXT -> textNotes.isNotBlank()
                    ResponseType.FILE -> evidence.isNotEmpty()
                    else -> true
                },
            ) {
                Text(if (isComplete) "Marked Complete" else "Mark as Complete")
            }
        }
    }
}
