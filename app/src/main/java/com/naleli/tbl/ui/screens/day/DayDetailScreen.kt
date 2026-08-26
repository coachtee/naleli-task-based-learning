package com.naleli.tbl.ui.screens.day

import android.net.Uri
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
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
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
import com.naleli.tbl.data.content.CourseTask
import com.naleli.tbl.data.content.ResponseType
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.StatusChip
import com.naleli.tbl.ui.components.colors
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.util.createCameraCaptureUri
import com.naleli.tbl.util.viewIntent
import kotlinx.coroutines.launch

@Composable
fun DayDetailScreen(dayNumber: Int, onDayCompleted: () -> Unit) {
    val container = rememberAppContainer()
    val viewModel: DayViewModel = viewModel(
        factory = viewModelFactory { initializer { DayViewModel(container, dayNumber) } },
    )
    val state by viewModel.state.collectAsState()

    if (state.isLoading) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    if (state.notAvailable || state.day == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            Text(
                "Day $dayNumber isn't part of this V1 build yet.",
                style = MaterialTheme.typography.titleMedium,
            )
            Text(
                "Days 1–7 are available now; the full 90 days ship in a later content update.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        return
    }

    val day = state.day!!
    var reflectionInput by remember(day.dayNumber) { mutableStateOf(state.reflectionText) }
    var pendingEvidenceTaskId by remember { mutableStateOf<String?>(null) }
    var pendingCameraUri by remember { mutableStateOf<Uri?>(null) }
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    val openDocumentLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri: Uri? ->
        val taskId = pendingEvidenceTaskId
        if (uri != null && taskId != null) {
            day.tasks.firstOrNull { it.taskId == taskId }?.let { viewModel.attachEvidence(it, uri, null) }
        }
        pendingEvidenceTaskId = null
    }
    val takePictureLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success: Boolean ->
        val taskId = pendingEvidenceTaskId
        val uri = pendingCameraUri
        if (success && uri != null && taskId != null) {
            day.tasks.firstOrNull { it.taskId == taskId }?.let { viewModel.attachEvidence(it, uri, "Camera photo") }
        }
        pendingEvidenceTaskId = null
        pendingCameraUri = null
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        item {
            StatusChip(text = state.dayStatus.label(), colors = state.dayStatus.colors())
            Spacer(Modifier.height(8.dp))
            Text("DAY ${day.dayNumber} · ${state.course?.stageFor(day.dayNumber)?.name?.uppercase()}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Text(day.title, style = MaterialTheme.typography.headlineSmall)
            Text(day.objective, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }

        item { SectionHeader("LEARN") }
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(day.lessonSummary, style = MaterialTheme.typography.bodyLarge)
                if (day.keyFocusAreas.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    day.keyFocusAreas.forEach { Text("• $it", style = MaterialTheme.typography.bodyMedium) }
                }
            }
        }

        item { SectionHeader("DO") }
        items(day.tasks.filter { it.taskType != com.naleli.tbl.data.content.TaskType.SELF_CHECK }) { task ->
            TaskCard(
                task = task,
                statusEntity = state.taskStatuses[task.taskId],
                evidence = state.evidenceByTask[task.taskId].orEmpty(),
                onSaveText = { viewModel.saveTextResponse(task, it) },
                onMarkComplete = { viewModel.markTaskComplete(task, it) },
                onChooseFile = {
                    pendingEvidenceTaskId = task.taskId
                    openDocumentLauncher.launch(arrayOf("*/*"))
                },
                onTakePhoto = {
                    val uri = createCameraCaptureUri(context)
                    pendingEvidenceTaskId = task.taskId
                    pendingCameraUri = uri
                    takePictureLauncher.launch(uri)
                },
                onOpenResource = { ref ->
                    scope.launch {
                        val file = viewModel.copyResourceToDevice(ref)
                        val mimeType = context.contentResolver.getType(com.naleli.tbl.util.fileProviderUri(context, file))
                        context.startActivity(viewIntent(context, file, mimeType))
                    }
                },
            )
        }

        val reviewTask = day.tasks.firstOrNull { it.taskType == com.naleli.tbl.data.content.TaskType.SELF_CHECK }
        if (reviewTask != null) {
            item { SectionHeader("CHECK") }
            item {
                ReviewTaskCard(
                    task = reviewTask,
                    statusEntity = state.taskStatuses[reviewTask.taskId],
                    onSubmit = { answers, confidence, confident -> viewModel.saveReviewAnswers(reviewTask, answers, confidence, confident) },
                )
            }
        }

        item { SectionHeader("EVIDENCE") }
        item {
            val allEvidence = state.evidenceByTask.values.flatten()
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                if (allEvidence.isEmpty()) {
                    Text("No evidence attached yet today.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                } else {
                    allEvidence.forEach { ev ->
                        Text("• ${ev.fileName}", style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
        }

        item { SectionHeader("REFLECT") }
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(day.reflectionPrompt, style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(
                    value = reflectionInput,
                    onValueChange = { reflectionInput = it },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3,
                    label = { Text("Your reflection") },
                )
            }
        }

        item {
            Button(
                onClick = {
                    val needsReview = state.taskStatuses.values.any { it.confidenceRating != null && it.confidenceRating!! <= 2 }
                    viewModel.completeDay(needsReview, reflectionInput)
                    onDayCompleted()
                },
                modifier = Modifier.fillMaxWidth(),
                enabled = state.requiredTasksComplete,
            ) {
                Text(if (state.requiredTasksComplete) "Complete Day" else "Complete all required tasks first")
            }
        }
    }
}

@Composable
private fun SectionHeader(text: String) {
    Text(text, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
}

@Composable
private fun TaskCard(
    task: CourseTask,
    statusEntity: com.naleli.tbl.data.db.entity.TaskStatusEntity?,
    evidence: List<com.naleli.tbl.data.db.entity.EvidenceEntity>,
    onSaveText: (String) -> Unit,
    onMarkComplete: (Boolean) -> Unit,
    onChooseFile: () -> Unit,
    onTakePhoto: () -> Unit,
    onOpenResource: (com.naleli.tbl.data.content.SupportContentRef) -> Unit,
) {
    val isComplete = statusEntity?.status == DayStatus.COMPLETE
    NaleliCard(modifier = Modifier.fillMaxWidth()) {
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
            Text(task.title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
            StatusChip(text = if (isComplete) "COMPLETE" else "TO DO", colors = (if (isComplete) DayStatus.COMPLETE else DayStatus.NOT_STARTED).colors())
        }
        Text(task.instructions, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(4.dp))
        Text("Estimated time: ${task.estimatedTime}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)

        if (task.supportContent.isNotEmpty()) {
            Spacer(Modifier.height(8.dp))
            Text("FILES YOU NEED", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            task.supportContent.forEach { ref ->
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(ref.label, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
                    OutlinedButton(onClick = { onOpenResource(ref) }) { Text("Open / Copy") }
                }
            }
        }
        Spacer(Modifier.height(12.dp))

        when (task.responseType) {
            ResponseType.TEXT -> {
                var text by remember(task.taskId) { mutableStateOf(statusEntity?.textResponse.orEmpty()) }
                OutlinedTextField(
                    value = text,
                    onValueChange = { text = it },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 3,
                    label = { Text("Your notes / key points") },
                )
                Spacer(Modifier.height(8.dp))
                Button(
                    onClick = { onSaveText(text); onMarkComplete(true) },
                    enabled = text.isNotBlank() && !isComplete,
                ) {
                    Text(if (isComplete) "Marked Complete" else "Mark Task Complete")
                }
            }
            ResponseType.FILE -> {
                if (evidence.isNotEmpty()) {
                    evidence.forEach { Text("• ${it.fileName}", style = MaterialTheme.typography.bodyMedium) }
                    Spacer(Modifier.height(8.dp))
                }
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(onClick = onChooseFile) { Text("Choose File") }
                    OutlinedButton(onClick = onTakePhoto) { Text("Take Photo") }
                }
                Spacer(Modifier.height(8.dp))
                Button(onClick = { onMarkComplete(true) }, enabled = evidence.isNotEmpty() && !isComplete) {
                    Text(if (isComplete) "Marked Complete" else "Mark Task Complete")
                }
            }
            else -> Unit
        }
    }
}

@Composable
private fun ReviewTaskCard(
    task: CourseTask,
    statusEntity: com.naleli.tbl.data.db.entity.TaskStatusEntity?,
    onSubmit: (List<ReviewAnswer>, Int, Boolean) -> Unit,
) {
    val isComplete = statusEntity?.status == DayStatus.COMPLETE
    val answers = remember(task.taskId) {
        task.reviewQuestions.map { q -> mutableStateOf("") to q }
    }
    var confidence by remember(task.taskId) { mutableStateOf(statusEntity?.confidenceRating ?: 3) }

    NaleliCard(modifier = Modifier.fillMaxWidth()) {
        Text(task.title, style = MaterialTheme.typography.titleMedium)
        Text(task.instructions, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(12.dp))
        answers.forEach { (answerState, question) ->
            Text(question, style = MaterialTheme.typography.labelLarge)
            OutlinedTextField(
                value = answerState.value,
                onValueChange = { answerState.value = it },
                modifier = Modifier.fillMaxWidth(),
                minLines = 2,
            )
            Spacer(Modifier.height(8.dp))
        }
        HorizontalDivider(modifier = Modifier.fillMaxWidth())
        Spacer(Modifier.height(8.dp))
        Text("How confident are you? (1 = need review, 5 = very confident)", style = MaterialTheme.typography.labelLarge)
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            (1..5).forEach { level ->
                OutlinedButton(onClick = { confidence = level }) {
                    Text(level.toString(), color = if (confidence == level) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurface)
                }
            }
        }
        Spacer(Modifier.height(12.dp))
        Button(
            onClick = {
                val result = answers.map { (state, q) -> ReviewAnswer(q, state.value) }
                onSubmit(result, confidence, confidence >= 3)
            },
            enabled = answers.all { it.first.value.isNotBlank() } && !isComplete,
        ) {
            Text(if (isComplete) "Review Submitted" else "Submit Review")
        }
    }
}
