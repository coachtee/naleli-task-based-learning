package com.naleli.tbl.ui.screens.workspace

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
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
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.LessonStage
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.ChipShape
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NaleliGradients
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import com.naleli.tbl.ui.theme.SlateGray
import com.naleli.tbl.ui.theme.SurfaceWhite

/**
 * Replaces the old lesson-style Task Detail screen (brief §3): Brief →
 * Before You Start → Assignment → Deliverable, then the real work — a
 * sub-step checklist, evidence attachment, and a submit action that hands
 * off to Assessment rather than marking the task done on the spot.
 */
@Composable
fun TaskWorkspaceScreen(
    taskId: String,
    onBack: () -> Unit,
    onAddEvidence: () -> Unit,
    onSubmitted: () -> Unit,
    onOpenLesson: () -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: TaskWorkspaceViewModel = viewModel(
        factory = viewModelFactory { initializer { TaskWorkspaceViewModel(container, taskId) } },
    )
    val state by viewModel.state.collectAsState()
    var showConfidenceDialog by rememberSaveable { mutableStateOf(false) }

    if (state.isLoading || state.task == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }
    val task = state.task!!

    if (state.locked) {
        Column(Modifier.fillMaxSize()) {
            Column(Modifier.padding(20.dp)) { BackHeader(onBack = onBack) }
            Column(
                modifier = Modifier.fillMaxSize().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                Icon(Icons.Filled.Lock, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(12.dp))
                Text("This task is locked", style = MaterialTheme.typography.titleLarge)
            }
        }
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            // Blue hero header — the reference's "Task Workspace" moment:
            // this screen is where the actual work happens, so it opens
            // with the app's strongest surface rather than a plain title.
            Box(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(20.dp))
                    .background(NaleliGradients.missionCard)
                    .padding(20.dp),
            ) {
                Column {
                    Text(
                        state.workstreamName.uppercase(),
                        style = MaterialTheme.typography.labelMedium,
                        color = OnHeroSurfaceSoft,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Spacer(Modifier.height(4.dp))
                    Text(task.title, style = MaterialTheme.typography.headlineSmall, color = OnHeroSurface)
                    Spacer(Modifier.height(12.dp))
                    Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        Text(
                            task.tier.label(),
                            style = MaterialTheme.typography.labelSmall,
                            color = OnHeroSurface,
                            modifier = Modifier
                                .background(androidx.compose.ui.graphics.Color.White.copy(alpha = 0.18f), ChipShape)
                                .padding(horizontal = 10.dp, vertical = 5.dp),
                        )
                        Text("~${task.estimatedMinutes} min", style = MaterialTheme.typography.labelMedium, color = OnHeroSurfaceSoft)
                    }
                }
            }
        }

        // The lesson's one-line promise, in place of the old BRIEF card —
        // which restated the title and the Learn sub-step in longer form.
        item {
            Text(
                task.whatYoureDoing,
                style = MaterialTheme.typography.bodyLarge,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.padding(bottom = 4.dp),
            )
        }

        // YOUR STEPS is now the lesson's roadmap rather than a checklist
        // buried under five explanatory cards: the learner sees the whole
        // arc, where they are in it, and one way in.
        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("YOUR STEPS", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(12.dp))
                val reached = reachedStage(state.progressState, state.allStepsDone, state.evidenceCount > 0)
                LessonStage.entries.forEach { stage ->
                    StageRow(stage = stage, reachedOrdinal = reached)
                }
                Spacer(Modifier.height(16.dp))
                Button(
                    onClick = onOpenLesson,
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = NibsOrange, contentColor = SurfaceWhite),
                ) {
                    Text(if (reached > 1) "CONTINUE LEARNING" else "START LEARNING")
                }
            }
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Column {
                        Text("EVIDENCE", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                        Text(
                            if (state.evidenceCount == 0) "Nothing attached yet" else "${state.evidenceCount} file(s) attached",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                    if (state.evidenceCount > 0) Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                }
                Spacer(Modifier.height(10.dp))
                OutlinedButton(onClick = onAddEvidence, modifier = Modifier.fillMaxWidth()) {
                    Text(if (state.evidenceCount == 0) "Attach Evidence" else "Manage Evidence")
                }
            }
        }

        item {
            Spacer(Modifier.height(4.dp))
            when (state.progressState) {
                TaskProgressState.SUBMITTED, TaskProgressState.COMPETENT ->
                    Button(onClick = onSubmitted, modifier = Modifier.fillMaxWidth()) { Text("View Assessment") }
                TaskProgressState.NEEDS_REVISION -> {
                    Button(
                        onClick = { showConfidenceDialog = true },
                        modifier = Modifier.fillMaxWidth(),
                        enabled = state.readyToSubmit,
                    ) { Text("Resubmit for Assessment") }
                    Spacer(Modifier.height(4.dp))
                    OutlinedButton(onClick = onSubmitted, modifier = Modifier.fillMaxWidth()) { Text("View Last Assessment") }
                }
                else -> Button(
                    onClick = { showConfidenceDialog = true },
                    modifier = Modifier.fillMaxWidth(),
                    enabled = state.readyToSubmit,
                ) { Text("Submit for Assessment") }
            }
            // Name every outstanding requirement rather than one blanket
            // sentence: a learner staring at a disabled button must be able
            // to see exactly which line is stopping them.
            if (!state.readyToSubmit &&
                state.progressState != TaskProgressState.SUBMITTED &&
                state.progressState != TaskProgressState.COMPETENT
            ) {
                Spacer(Modifier.height(8.dp))
                Text(
                    "STILL TO DO",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(4.dp))
                state.missingRequirements.forEach { requirement ->
                    Row(
                        modifier = Modifier.fillMaxWidth().padding(vertical = 3.dp),
                        verticalAlignment = Alignment.CenterVertically,
                    ) {
                        Box(
                            Modifier
                                .size(14.dp)
                                .clip(CircleShape)
                                .border(1.5.dp, MaterialTheme.colorScheme.onSurfaceVariant, CircleShape),
                        )
                        Spacer(Modifier.width(10.dp))
                        Text(
                            requirement.label,
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurface,
                        )
                    }
                }
            }
        }
    }

    if (showConfidenceDialog) {
        var confidence by rememberSaveable { mutableIntStateOf(0) }
        AlertDialog(
            onDismissRequest = { showConfidenceDialog = false },
            title = { Text("How confident do you feel?") },
            text = {
                Column {
                    Text(task.skillDeveloped, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(8.dp))
                    ConfidenceRow("Not confident", 1, confidence) { confidence = it }
                    ConfidenceRow("Learning", 2, confidence) { confidence = it }
                    ConfidenceRow("Comfortable", 3, confidence) { confidence = it }
                    ConfidenceRow("Confident", 4, confidence) { confidence = it }
                    ConfidenceRow("Very confident", 5, confidence) { confidence = it }
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        viewModel.submitForAssessment(confidence)
                        showConfidenceDialog = false
                        onSubmitted()
                    },
                    enabled = confidence > 0,
                ) { Text("Submit") }
            },
            dismissButton = { OutlinedButton(onClick = { showConfidenceDialog = false }) { Text("Cancel") } },
        )
    }
}

/**
 * The 01 → 05 workflow stepper from the approved reference. It reads the
 * task's real derived state rather than a separate counter, so it can
 * never disagree with what the rest of the screen shows.
 */
@Composable
/**
 * How far the learner has actually got, expressed as a stage ordinal.
 * Derived from the real rows — sub-steps, evidence, assessment — so the
 * roadmap can never claim progress the data does not support.
 */
private fun reachedStage(progressState: TaskProgressState, allStepsDone: Boolean, hasEvidence: Boolean): Int = when (progressState) {
    TaskProgressState.COMPETENT, TaskProgressState.SUBMITTED, TaskProgressState.READY_TO_SUBMIT -> 5
    TaskProgressState.NEEDS_REVISION -> 5
    TaskProgressState.IN_PROGRESS -> when {
        hasEvidence -> 5
        allStepsDone -> 4
        else -> 2
    }
    TaskProgressState.NOT_STARTED -> 1
}

/** One row of the five-stage roadmap: what it is, and whether the learner
 * has been there. */
@Composable
private fun StageRow(stage: LessonStage, reachedOrdinal: Int) {
    val done = stage.ordinal1 < reachedOrdinal
    val active = stage.ordinal1 == reachedOrdinal
    val color = when {
        done -> HeroSurface
        active -> NibsOrange
        else -> SlateGray
    }
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 7.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier
                .size(26.dp)
                .clip(CircleShape)
                .background(if (active || done) color else androidx.compose.ui.graphics.Color.Transparent)
                .border(1.5.dp, color, CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            if (done) {
                Icon(Icons.Filled.Check, contentDescription = null, tint = SurfaceWhite, modifier = Modifier.size(14.dp))
            } else {
                Text(
                    "0${stage.ordinal1}",
                    style = MaterialTheme.typography.labelSmall,
                    color = if (active) SurfaceWhite else color,
                )
            }
        }
        Spacer(Modifier.width(14.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                stage.label,
                style = MaterialTheme.typography.titleSmall,
                color = if (active) NibsOrange else MaterialTheme.colorScheme.onSurface,
            )
            Text(
                stageHint(stage),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

private fun stageHint(stage: LessonStage): String = when (stage) {
    LessonStage.UNDERSTAND -> "Learn the concept, one idea at a time"
    LessonStage.SEE -> "See it working in a real example"
    LessonStage.TRY -> "Practise the skill yourself"
    LessonStage.APPLY -> "Complete a workplace-style task"
    LessonStage.SHOW -> "Submit the evidence of what you produced"
}


@Composable
private fun ConfidenceRow(label: String, level: Int, selectedLevel: Int, onSelect: (Int) -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth().padding(vertical = 2.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        RadioButton(selected = selectedLevel == level, onClick = { onSelect(level) })
        Text(label, style = MaterialTheme.typography.bodyMedium)
    }
}
