package com.naleli.tbl.ui.screens.day

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
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.RadioButtonUnchecked
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.CourseTask
import com.naleli.tbl.data.content.TaskType
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.TaskStatusEntity
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.CircularProgressRing
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.SegmentedTabRow
import com.naleli.tbl.ui.rememberAppContainer

private val TAB_LABELS = listOf("Learn", "Tasks", "Review", "Reflect")

@Composable
fun DayDetailScreen(
    dayNumber: Int,
    onBack: () -> Unit,
    onOpenTask: (taskId: String) -> Unit,
    onDayCompleted: () -> Unit,
) {
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
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(24.dp))
            Text("Day $dayNumber isn't part of this build yet.", style = MaterialTheme.typography.titleMedium)
            Text(
                "Days 1–7 are available now; the full 90 days ship in a later content update.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        return
    }

    val day = state.day!!

    if (state.isLocked) {
        Column(Modifier.fillMaxSize()) {
            Column(modifier = Modifier.padding(20.dp)) {
                BackHeader(onBack = onBack)
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
                    "Complete Day ${dayNumber - 1} to unlock this day.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(20.dp))
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("DAY $dayNumber", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                    Text(day.title, style = MaterialTheme.typography.titleMedium)
                    Text(
                        day.learningFocus,
                        style = MaterialTheme.typography.bodySmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
        }
        return
    }

    val nonReviewTasks = remember(day.dayNumber) { day.tasks.filter { it.taskType != TaskType.SELF_CHECK } }
    val reviewTask = remember(day.dayNumber) { day.tasks.firstOrNull { it.taskType == TaskType.SELF_CHECK } }
    val completedCount = nonReviewTasks.count { state.taskStatuses[it.taskId]?.status == DayStatus.COMPLETE }

    var selectedTab by remember(day.dayNumber) { mutableIntStateOf(0) }
    var reflectionInput by remember(day.dayNumber) { mutableStateOf(state.reflectionText) }

    Column(modifier = Modifier.fillMaxSize().padding(top = 12.dp)) {
        Column(modifier = Modifier.padding(horizontal = 20.dp)) {
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text("DAY ${day.dayNumber}", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                    Text(day.title, style = MaterialTheme.typography.headlineSmall)
                }
                CircularProgressRing(
                    progressFraction = if (nonReviewTasks.isEmpty()) 0f else completedCount.toFloat() / nonReviewTasks.size,
                ) {
                    Text("$completedCount/${nonReviewTasks.size}", style = MaterialTheme.typography.labelSmall)
                }
            }
            Text(
                "Tasks completed",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(16.dp))
            SegmentedTabRow(tabs = TAB_LABELS, selectedIndex = selectedTab, onSelect = { selectedTab = it })
            Spacer(Modifier.height(12.dp))
        }

        when (selectedTab) {
            0 -> LearnTab(day.lessonSummary, day.keyFocusAreas)
            1 -> TasksTab(nonReviewTasks, state.taskStatuses, onOpenTask)
            2 -> ReviewTab(
                task = reviewTask,
                statusEntity = reviewTask?.let { state.taskStatuses[it.taskId] },
                onSubmit = { answers, confidence, confident ->
                    reviewTask?.let { viewModel.saveReviewAnswers(it, answers, confidence, confident) }
                },
            )
            3 -> ReflectTab(
                prompt = day.reflectionPrompt,
                reflectionInput = reflectionInput,
                onReflectionChange = { reflectionInput = it },
                canComplete = state.requiredTasksComplete,
                onCompleteDay = {
                    val needsReview = state.taskStatuses.values.any { it.confidenceRating != null && it.confidenceRating!! <= 2 }
                    viewModel.completeDay(needsReview, reflectionInput)
                    onDayCompleted()
                },
            )
        }
    }
}

@Composable
private fun LearnTab(lessonSummary: String, keyFocusAreas: List<String>) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(lessonSummary, style = MaterialTheme.typography.bodyLarge)
                if (keyFocusAreas.isNotEmpty()) {
                    Spacer(Modifier.height(12.dp))
                    HorizontalDivider()
                    Spacer(Modifier.height(12.dp))
                    Text("KEY IDEAS", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                    Spacer(Modifier.height(6.dp))
                    keyFocusAreas.forEach { Text("•  $it", style = MaterialTheme.typography.bodyMedium) }
                }
            }
        }
    }
}

@Composable
private fun TasksTab(
    tasks: List<CourseTask>,
    statuses: Map<String, TaskStatusEntity>,
    onOpenTask: (String) -> Unit,
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        items(tasks) { task ->
            val isComplete = statuses[task.taskId]?.status == DayStatus.COMPLETE
            NaleliCard(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { onOpenTask(task.taskId) },
            ) {
                Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = if (isComplete) Icons.Filled.CheckCircle else Icons.Filled.RadioButtonUnchecked,
                        contentDescription = null,
                        tint = if (isComplete) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Column(modifier = Modifier.weight(1f).padding(horizontal = 12.dp)) {
                        Text(task.title, style = MaterialTheme.typography.titleMedium)
                        Text(
                            task.instructions,
                            style = MaterialTheme.typography.bodySmall,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                            maxLines = 2,
                        )
                    }
                    Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
        }
    }
}

@Composable
private fun ReviewTab(
    task: CourseTask?,
    statusEntity: TaskStatusEntity?,
    onSubmit: (List<ReviewAnswer>, Int, Boolean) -> Unit,
) {
    if (task == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            Text("No self-check for this day.", color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        return
    }
    val isComplete = statusEntity?.status == DayStatus.COMPLETE
    val answers = remember(task.taskId) { task.reviewQuestions.map { q -> mutableStateOf("") to q } }
    var confidenceLevel by remember(task.taskId) { mutableIntStateOf(statusEntity?.confidenceRating ?: 0) }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("SELF CHECK", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(task.instructions, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        items(answers) { (answerState, question) ->
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(question, style = MaterialTheme.typography.titleMedium)
                Spacer(Modifier.height(8.dp))
                OutlinedTextField(
                    value = answerState.value,
                    onValueChange = { answerState.value = it },
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 2,
                )
            }
        }
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("How confident are you now?", style = MaterialTheme.typography.titleMedium)
                Spacer(Modifier.height(4.dp))
                ConfidenceOption("Yes, I'm confident", 5, confidenceLevel) { confidenceLevel = it }
                ConfidenceOption("Almost, I need more practice", 3, confidenceLevel) { confidenceLevel = it }
                ConfidenceOption("Not yet, I need help", 1, confidenceLevel) { confidenceLevel = it }
            }
        }
        item {
            Button(
                onClick = {
                    val result = answers.map { (s, q) -> ReviewAnswer(q, s.value) }
                    onSubmit(result, confidenceLevel, confidenceLevel >= 3)
                },
                modifier = Modifier.fillMaxWidth(),
                enabled = !isComplete && confidenceLevel > 0 && answers.all { it.first.value.isNotBlank() },
            ) {
                Text(if (isComplete) "Review Submitted" else "Save and Continue")
            }
        }
    }
}

@Composable
private fun ConfidenceOption(label: String, level: Int, selectedLevel: Int, onSelect: (Int) -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onSelect(level) },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        RadioButton(selected = selectedLevel == level, onClick = { onSelect(level) })
        Text(label, style = MaterialTheme.typography.bodyMedium)
    }
}

@Composable
private fun ReflectTab(
    prompt: String,
    reflectionInput: String,
    onReflectionChange: (String) -> Unit,
    canComplete: Boolean,
    onCompleteDay: () -> Unit,
) {
    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("REFLECT", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(6.dp))
                Text(prompt, style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(10.dp))
                OutlinedTextField(
                    value = reflectionInput,
                    onValueChange = onReflectionChange,
                    modifier = Modifier.fillMaxWidth(),
                    minLines = 4,
                    label = { Text("Your reflection") },
                )
            }
        }
        item {
            if (!canComplete) {
                Text(
                    "Complete all required tasks and your self-check before finishing the day.",
                    style = MaterialTheme.typography.bodySmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(8.dp))
            }
            Button(onClick = onCompleteDay, modifier = Modifier.fillMaxWidth(), enabled = canComplete) {
                Text("Complete Day")
            }
        }
    }
}
