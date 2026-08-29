package com.naleli.tbl.ui.screens.lesson

import androidx.activity.compose.BackHandler
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
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.Lightbulb
import androidx.compose.material.icons.filled.PlayCircleOutline
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextFieldDefaults
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.BlockType
import com.naleli.tbl.data.content.ContentBlock
import com.naleli.tbl.data.content.LessonLibrary
import com.naleli.tbl.data.content.LessonMission
import com.naleli.tbl.data.content.LessonPractice
import com.naleli.tbl.data.content.MissionStep
import com.naleli.tbl.data.content.LessonPage
import com.naleli.tbl.data.content.LessonStage
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.domain.SubmissionRequirement
import com.naleli.tbl.ui.components.LessonImage
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.screens.workspace.TaskWorkspaceViewModel
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import com.naleli.tbl.ui.theme.SuccessGreen
import com.naleli.tbl.ui.theme.SurfaceWhite

/**
 * The lesson, as a guided sequence rather than a document.
 *
 * One idea per screen, advanced with Continue — the same shape as the
 * orientation, because the same thing is true of both: a learner reading a
 * wall of extracted textbook is not being taught, they are being handed a
 * PDF. Screens come from the content package (see LessonContent.kt); this
 * file only decides how each stage looks.
 *
 * The sequence runs the full Naleli Task-Based Learning arc — Understand,
 * See, Try, Apply, Show — so the last learning screen IS the hand-off to
 * the work. There is deliberately no quiz stage: competence is shown by
 * producing evidence, not by answering questions about the reading.
 *
 * Surface is navy throughout, matching the orientation. Reading is the one
 * place in the app that is a focused, full-screen experience rather than a
 * card on a canvas, and it does not follow the light/dark preference.
 */
@Composable
fun LessonScreen(
    taskId: String,
    onBack: () -> Unit,
    onAddEvidence: () -> Unit,
    onSubmitted: () -> Unit,
) {
    val context = LocalContext.current
    val container = rememberAppContainer()
    val viewModel: TaskWorkspaceViewModel = viewModel(
        key = "lesson-$taskId",
        factory = viewModelFactory { initializer { TaskWorkspaceViewModel(container, taskId) } },
    )
    val state by viewModel.state.collectAsState()
    val task = state.task

    val lessons = remember(taskId) { LessonLibrary.lessonsForTask(context, taskId) }
    val readingPages = remember(lessons) { lessons.flatMap { it.pages } }

    // Reading screens first, then the three working stages. Every lesson
    // ends in the same place: attach what you made.
    val stages: List<Stage> = remember(readingPages, task) {
        buildList {
            readingPages.forEach { add(Stage.Reading(it)) }
            task?.let {
                val practice = lessons.firstNotNullOfOrNull { it.practice }
                val mission = lessons.firstNotNullOfOrNull { it.mission }
                if (practice != null || it.practiseText.isNotBlank()) {
                    add(Stage.Try(practice, it.practiseText))
                }
                add(Stage.Apply(it, mission))
                add(Stage.Show(it))
            }
        }
    }

    var index by rememberSaveable(taskId) { mutableIntStateOf(0) }
    var answerDraft by rememberSaveable(taskId) { mutableStateOf("") }
    var showConfidence by rememberSaveable(taskId) { mutableStateOf(false) }
    BackHandler(enabled = index > 0) { index -= 1 }

    if (state.isLoading || task == null || stages.isEmpty()) {
        Box(Modifier.fillMaxSize().background(HeroSurface), contentAlignment = Alignment.Center) {
            CircularProgressIndicator(color = NibsOrange)
        }
        return
    }

    val safeIndex = index.coerceIn(0, stages.lastIndex)
    val stage = stages[safeIndex]
    val isLast = safeIndex == stages.lastIndex

    // Reaching Show means the learner has walked the whole arc, so the
    // day's steps are genuinely done — mark them on arrival rather than at
    // submit. Without this the first requirement on the Show checklist
    // ("work through every stage") could never tick from inside the lesson,
    // and SUBMIT would be permanently disabled.
    LaunchedEffect(stage) {
        if (stage is Stage.Show) viewModel.completeStageSubStep(LessonStage.SHOW)
    }

    Column(
        Modifier
            .fillMaxSize()
            .background(HeroSurface),
    ) {
        // Position within this stage, not the whole lesson: the question
        // a learner deep in a long Understand stage is actually asking.
        val sameStage = stages.filter { it.lessonStage == stage.lessonStage }
        val positionInStage = stages.take(safeIndex + 1).count { it.lessonStage == stage.lessonStage }
        // Only when the NEXT screen belongs to a LATER stage. Worked
        // examples interleave with explanation, so a SEE screen is often
        // followed by more UNDERSTAND — which read as "SEE complete — next
        // is UNDERSTAND", telling the learner the arc had gone backwards.
        val nextStage = stages.getOrNull(safeIndex + 1)?.lessonStage
        val isStageEnd = nextStage != null && nextStage.ordinal1 > stage.lessonStage.ordinal1

        LessonTopBar(
            task = task,
            stage = stage.lessonStage,
            positionInStage = positionInStage,
            stageTotal = sameStage.size,
            onBack = { if (safeIndex == 0) onBack() else index -= 1 },
        )

        Column(
            Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp)
                .padding(top = 18.dp, bottom = 8.dp),
        ) {
            when (stage) {
                is Stage.Reading -> ReadingStage(stage.page)
                is Stage.Try -> TryStage(stage.practice, stage.fallback)
                is Stage.Apply -> ApplyStage(stage.task, stage.mission)
                is Stage.Show -> ShowStage(
                    task = stage.task,
                    mission = stages.filterIsInstance<Stage.Apply>().firstOrNull()?.mission,
                    attachedFiles = state.attachedFiles,
                    writtenAnswers = state.writtenAnswers,
                    requirements = state.submissionRequirements,
                    answerDraft = answerDraft,
                    onAnswerChange = { answerDraft = it },
                    onSaveAnswer = {
                        viewModel.saveWrittenAnswer(answerDraft)
                        answerDraft = ""
                    },
                    onAddEvidence = onAddEvidence,
                    onRemoveEvidence = viewModel::removeEvidence,
                )
            }
            if (isStageEnd && nextStage != null) {
                Spacer(Modifier.height(18.dp))
                StageComplete(finished = stage.lessonStage, next = nextStage)
            }
            Spacer(Modifier.height(16.dp))
        }

        LessonFooter(
            isLast = isLast,
            // The same rule the badge on every other screen uses, and the
            // same list Show ticks off — so a disabled SUBMIT always has a
            // named reason sitting directly above it.
            canSubmit = state.missingRequirements.isEmpty(),
            blockingReason = state.missingRequirements.firstOrNull()?.label,
            canGoBack = safeIndex > 0,
            onBack = { index -= 1 },
            onContinue = {
                // Passing a stage is what marks its sub-step done, so the
                // checklist reflects the journey instead of asking the
                // learner to tick the same work twice.
                viewModel.completeStageSubStep(stage.lessonStage)
                index = (safeIndex + 1).coerceAtMost(stages.lastIndex)
            },
            // Asking how confident they feel is the learner's own read on
            // the work, recorded beside the result and never standing in for
            // it. The paged rebuild dropped this and submitted a hardcoded 3.
            onSubmit = { showConfidence = true },
        )
    }

    if (showConfidence) {
        ConfidenceDialog(
            onDismiss = { showConfidence = false },
            onRate = { rating ->
                showConfidence = false
                viewModel.completeStageSubStep(LessonStage.SHOW)
                viewModel.submitForAssessment(rating)
                onSubmitted()
            },
        )
    }
}

/** The learner's own confidence, asked once, at submission. It is recorded
 * alongside the assessment and never used as the result — competence is
 * what the rubric found, not how the learner felt. */
@Composable
private fun ConfidenceDialog(onDismiss: () -> Unit, onRate: (Int) -> Unit) {
    AlertDialog(
        onDismissRequest = onDismiss,
        title = { Text("How confident do you feel?") },
        text = {
            Column {
                Text(
                    "Your answer is recorded with your work. It does not change your result.",
                    style = MaterialTheme.typography.bodySmall,
                )
                Spacer(Modifier.height(12.dp))
                CONFIDENCE_LABELS.forEachIndexed { i, label ->
                    TextButton(
                        onClick = { onRate(i + 1) },
                        modifier = Modifier.fillMaxWidth(),
                    ) { Text("${i + 1} — $label", modifier = Modifier.fillMaxWidth()) }
                }
            }
        },
        confirmButton = {},
        dismissButton = { TextButton(onClick = onDismiss) { Text("Cancel") } },
    )
}

private val CONFIDENCE_LABELS = listOf(
    "Not confident yet",
    "Still learning",
    "Getting there",
    "Confident",
    "Very confident",
)

/** One screen of the lesson. Reading screens come from content; the last
 * three are built from the day's own task record. */
private sealed interface Stage {
    val lessonStage: LessonStage

    data class Reading(val page: LessonPage) : Stage {
        override val lessonStage: LessonStage get() = page.lessonStage
    }

    data class Try(val practice: LessonPractice?, val fallback: String) : Stage {
        override val lessonStage: LessonStage get() = LessonStage.TRY
    }

    data class Apply(val task: WorkTask, val mission: LessonMission?) : Stage {
        override val lessonStage: LessonStage get() = LessonStage.APPLY
    }

    data class Show(val task: WorkTask) : Stage {
        override val lessonStage: LessonStage get() = LessonStage.SHOW
    }
}

/**
 * Two levels of progress, which answer two different questions.
 *
 * Level 1 — the five-stage rail — answers "where am I in the lesson's
 * shape". Level 2 — "UNDERSTAND · 3 of 7" — answers "how much of this is
 * left", which the rail alone cannot: a learner eleven screens into a long
 * Understand stage sees the same rail on every one of them.
 *
 * Built compact deliberately. The previous version handed BackHeader a Row
 * with SpaceBetween, but BackHeader fills its own width, so the position
 * text was pushed off-screen entirely and left a band of empty navy where
 * the progress should have been.
 */
@Composable
private fun LessonTopBar(
    task: WorkTask,
    stage: LessonStage,
    positionInStage: Int,
    stageTotal: Int,
    onBack: () -> Unit,
) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(top = 4.dp, bottom = 2.dp)) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack, modifier = Modifier.size(36.dp)) {
                Icon(
                    Icons.AutoMirrored.Filled.ArrowBack,
                    contentDescription = "Back",
                    tint = OnHeroSurface,
                    modifier = Modifier.size(22.dp),
                )
            }
            Spacer(Modifier.width(10.dp))
            Text(
                task.title,
                style = MaterialTheme.typography.labelMedium,
                color = OnHeroSurfaceSoft,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
        }

        Spacer(Modifier.height(10.dp))
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(5.dp)) {
            LessonStage.entries.forEach { entry ->
                Box(
                    Modifier
                        .weight(1f)
                        .height(3.dp)
                        .clip(CircleShape)
                        .background(
                            when {
                                entry == stage -> NibsOrange
                                entry.ordinal1 < stage.ordinal1 -> OnHeroSurface
                                else -> SurfaceWhite.copy(alpha = 0.20f)
                            },
                        ),
                )
            }
        }

        Spacer(Modifier.height(8.dp))
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text(
                stage.label.uppercase(),
                style = MaterialTheme.typography.labelMedium,
                color = NibsOrange,
                fontWeight = FontWeight.SemiBold,
            )
            if (stageTotal > 1) {
                Text(
                    "  ·  $positionInStage of $stageTotal",
                    style = MaterialTheme.typography.labelMedium,
                    color = OnHeroSurfaceSoft,
                )
            }
            Spacer(Modifier.weight(1f))
            Text(
                "Step ${stage.ordinal1} of 5",
                style = MaterialTheme.typography.labelSmall,
                color = OnHeroSurfaceSoft,
            )
        }
    }
}

// ---------------------------------------------------------------- stages

@Composable
private fun ReadingStage(page: LessonPage) {
    if (page.title.isNotBlank()) {
        Text(
            page.title,
            style = MaterialTheme.typography.headlineMedium,
            color = OnHeroSurface,
            lineHeight = 34.sp,
        )
        Spacer(Modifier.height(14.dp))
    }
    page.blocks.forEach { block -> Block(block) }
}

/**
 * TRY — "do it with me". Numbered, specific instructions and one worked
 * answer. The workbook's own practice text is the same paragraph on most
 * days ("identify, explain and demonstrate the concept"), which tells a
 * learner nothing about what to actually open or type, so the generated
 * steps lead and that paragraph is the fallback.
 */
@Composable
private fun TryStage(practice: LessonPractice?, fallback: String) {
    StageIntro(
        eyebrow = "TRY IT",
        title = "Practise it with guidance",
        body = practice?.goal?.ifBlank { null } ?: "Follow this on your own device.",
    )
    Spacer(Modifier.height(22.dp))

    if (practice != null && practice.steps.isNotEmpty()) {
        Text("YOUR PRACTICE", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(10.dp))
        practice.steps.forEachIndexed { i, step -> NumberedStep(i + 1, step) }

        if (practice.exampleAnswer.isNotBlank()) {
            Spacer(Modifier.height(18.dp))
            Column(
                Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(12.dp))
                    .background(SurfaceWhite.copy(alpha = 0.06f))
                    .padding(horizontal = 14.dp, vertical = 12.dp),
            ) {
                Text("EXAMPLE ANSWER", style = MaterialTheme.typography.labelSmall, color = OnHeroSurfaceSoft)
                Spacer(Modifier.height(4.dp))
                Text(
                    practice.exampleAnswer,
                    style = MaterialTheme.typography.bodyMedium,
                    color = OnHeroSurface,
                    lineHeight = 23.sp,
                )
            }
        }
    } else if (fallback.isNotBlank()) {
        Text(fallback, style = MaterialTheme.typography.bodyLarge, color = OnHeroSurface, lineHeight = 27.sp)
    }
}

/**
 * APPLY — "solve a realistic problem yourself". A fixed four-part shape so
 * the learner never has to guess what to produce: the situation, a numbered
 * task, what good work looks like, and exactly what to hand in.
 *
 * "Brief" is reserved for this screen alone; everywhere else it would just
 * be another word for the lesson.
 */
@Composable
private fun ApplyStage(task: WorkTask, mission: LessonMission?) {
    StageIntro(eyebrow = "WORK MISSION", title = "Apply it to real work", body = "")
    Spacer(Modifier.height(22.dp))

    if (mission == null) {
        MissionSection("THE BRIEF", task.whyItMatters)
        MissionSection("YOUR TASK", task.assignmentText)
        MissionSection("WHAT TO SUBMIT", task.deliverableLabel)
        return
    }

    MissionSection("THE SITUATION", mission.situation)

    if (mission.steps.isNotEmpty()) {
        Text("YOUR TASK", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(10.dp))
        mission.steps.forEachIndexed { i, step -> MissionAction(i + 1, step) }
        Spacer(Modifier.height(18.dp))
    }

    if (mission.successCriteria.isNotEmpty()) {
        Text("WHAT GOOD WORK LOOKS LIKE", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(8.dp))
        mission.successCriteria.forEach { Bullet(it) }
        Spacer(Modifier.height(18.dp))
    }

    Text("WHAT TO SUBMIT", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
    Spacer(Modifier.height(8.dp))
    // The day's own deliverable leads, because the curriculum defines it and
    // the generated mission must never contradict it.
    Bullet(task.deliverableLabel)
    mission.submit.forEach { Bullet(it) }
}

@Composable
private fun MissionSection(label: String, body: String) {
    if (body.isBlank()) return
    Column(Modifier.padding(bottom = 18.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(6.dp))
        Text(body, style = MaterialTheme.typography.bodyLarge, color = OnHeroSurface, lineHeight = 27.sp)
    }
}

@Composable
private fun NumberedStep(number: Int, text: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.Top) {
        Box(
            Modifier
                .size(24.dp)
                .clip(CircleShape)
                .background(NibsOrange.copy(alpha = 0.18f)),
            contentAlignment = Alignment.Center,
        ) {
            Text("$number", style = MaterialTheme.typography.labelSmall, color = NibsOrange, fontWeight = FontWeight.SemiBold)
        }
        Spacer(Modifier.width(12.dp))
        Text(text, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurface, lineHeight = 23.sp)
    }
}

/**
 * One action of the Work Mission: "01 — Describe the situation", with the
 * how-to underneath where there is one.
 *
 * The six actions used to render as six equal-weight sentences, which read
 * as one instruction block however carefully they were written. Lifting the
 * number and the title out gives the learner something to scan and a place
 * to resume.
 */
@Composable
private fun MissionAction(number: Int, step: MissionStep) {
    Row(Modifier.fillMaxWidth().padding(vertical = 7.dp), verticalAlignment = Alignment.Top) {
        Text(
            "%02d".format(number),
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.Bold,
            color = NibsOrange,
        )
        Spacer(Modifier.width(12.dp))
        Column(Modifier.weight(1f)) {
            Text(
                step.title,
                style = MaterialTheme.typography.titleSmall,
                color = OnHeroSurface,
                lineHeight = 22.sp,
            )
            if (step.detail.isNotBlank()) {
                Spacer(Modifier.height(3.dp))
                Text(
                    step.detail,
                    style = MaterialTheme.typography.bodyMedium,
                    color = OnHeroSurfaceSoft,
                    lineHeight = 21.sp,
                )
            }
        }
    }
}

/**
 * SHOW — "prove it". No new learning here: three numbered steps, in the
 * order they have to happen, each with its own tick.
 *
 * This used to be one long screen with a disabled SUBMIT at the bottom and
 * a single sentence explaining why. A learner who had attached a photo but
 * written nothing was told only that something was wrong, not what.
 */
@Composable
private fun ShowStage(
    task: WorkTask,
    mission: LessonMission?,
    attachedFiles: List<EvidenceEntity>,
    writtenAnswers: List<EvidenceEntity>,
    requirements: List<SubmissionRequirement>,
    answerDraft: String,
    onAnswerChange: (String) -> Unit,
    onSaveAnswer: () -> Unit,
    onAddEvidence: () -> Unit,
    onRemoveEvidence: (EvidenceEntity) -> Unit,
) {
    StageIntro(
        eyebrow = "SHOW YOUR WORK",
        title = "Submit your evidence",
        body = "Nothing new to learn here — this is where you prove what you just did.",
    )
    Spacer(Modifier.height(18.dp))

    Text("THIS TASK ASKS FOR", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
    Spacer(Modifier.height(6.dp))
    ChecklistItem(task.deliverableLabel)
    mission?.submit?.forEach { ChecklistItem(it) }

    Spacer(Modifier.height(20.dp))
    ShowStep(
        number = "01",
        title = "Attach your evidence",
        subtitle = "A file, screenshot or photo of the work you produced.",
        done = attachedFiles.isNotEmpty(),
    ) {
        attachedFiles.forEach { EvidenceRow(it, onRemove = { onRemoveEvidence(it) }) }
        if (attachedFiles.isNotEmpty()) Spacer(Modifier.height(10.dp))
        OutlinedButton(onClick = onAddEvidence, modifier = Modifier.fillMaxWidth()) {
            Text(if (attachedFiles.isEmpty()) "ATTACH A FILE" else "ATTACH ANOTHER")
        }
    }

    Spacer(Modifier.height(14.dp))
    ShowStep(
        number = "02",
        title = "Explain your work",
        subtitle = "What you did, what you decided, and why you decided it.",
        done = writtenAnswers.isNotEmpty(),
    ) {
        writtenAnswers.forEach { EvidenceRow(it, onRemove = { onRemoveEvidence(it) }) }
        if (writtenAnswers.isNotEmpty()) Spacer(Modifier.height(10.dp))

        mission?.steps?.takeIf { it.isNotEmpty() }?.let { steps ->
            Text(
                "Answer the ${steps.size} points from your Work Mission.",
                style = MaterialTheme.typography.labelSmall,
                color = OnHeroSurfaceSoft,
            )
            Spacer(Modifier.height(8.dp))
        }
        // Typing the answer here is the whole point: most of what this
        // programme asks for is written explanation, and sending a learner
        // off to another app to write it — then back with a photograph —
        // is where submissions get lost.
        OutlinedTextField(
            value = answerDraft,
            onValueChange = onAnswerChange,
            modifier = Modifier.fillMaxWidth().heightIn(min = 130.dp),
            placeholder = {
                Text(
                    "Type your answer here…",
                    style = MaterialTheme.typography.bodyMedium,
                    color = OnHeroSurfaceSoft,
                )
            },
            textStyle = MaterialTheme.typography.bodyMedium.copy(color = OnHeroSurface),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor = NibsOrange,
                unfocusedBorderColor = SurfaceWhite.copy(alpha = 0.30f),
                cursorColor = NibsOrange,
                focusedContainerColor = SurfaceWhite.copy(alpha = 0.06f),
                unfocusedContainerColor = SurfaceWhite.copy(alpha = 0.06f),
            ),
            shape = RoundedCornerShape(12.dp),
        )
        Spacer(Modifier.height(8.dp))
        OutlinedButton(
            onClick = onSaveAnswer,
            enabled = answerDraft.isNotBlank(),
            modifier = Modifier.fillMaxWidth(),
        ) { Text(if (writtenAnswers.isEmpty()) "SAVE MY ANSWER" else "SAVE ANOTHER ANSWER") }
    }

    Spacer(Modifier.height(14.dp))
    ShowStep(
        number = "03",
        title = "Submit your work",
        subtitle = "SUBMIT unlocks once every line below is ticked.",
        done = requirements.all { it.met },
    ) {
        requirements.forEach { RequirementRow(it) }
    }
}

/** One numbered step of Show. The number is the sequence; the tick is the
 * state — a learner can see at a glance which of the three still needs
 * them, without reading a word. */
@Composable
private fun ShowStep(
    number: String,
    title: String,
    subtitle: String,
    done: Boolean,
    content: @Composable () -> Unit,
) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(14.dp))
            .background(SurfaceWhite.copy(alpha = 0.06f))
            .padding(horizontal = 16.dp, vertical = 14.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text(
                number,
                style = MaterialTheme.typography.labelLarge,
                fontWeight = FontWeight.Bold,
                color = if (done) SuccessGreen else NibsOrange,
            )
            Spacer(Modifier.width(10.dp))
            Text(
                title,
                style = MaterialTheme.typography.titleSmall,
                color = OnHeroSurface,
                modifier = Modifier.weight(1f),
            )
            if (done) {
                Icon(Icons.Filled.Check, contentDescription = "Done", tint = SuccessGreen, modifier = Modifier.size(18.dp))
            }
        }
        Spacer(Modifier.height(4.dp))
        Text(subtitle, style = MaterialTheme.typography.labelSmall, color = OnHeroSurfaceSoft, lineHeight = 17.sp)
        Spacer(Modifier.height(12.dp))
        content()
    }
}

/** One attached item, with the way to take it off again. Replacing is
 * remove-then-attach — the learner is never stuck with the wrong file. */
@Composable
private fun EvidenceRow(evidence: EvidenceEntity, onRemove: () -> Unit) {
    Row(
        Modifier.fillMaxWidth().padding(vertical = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(
            if (evidence.fileType.startsWith("image/")) Icons.Filled.Image else Icons.Filled.Description,
            contentDescription = null,
            tint = SuccessGreen,
            modifier = Modifier.size(16.dp),
        )
        Spacer(Modifier.width(10.dp))
        Text(
            evidence.fileName,
            style = MaterialTheme.typography.bodyMedium,
            color = OnHeroSurface,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
            modifier = Modifier.weight(1f),
        )
        TextButton(onClick = onRemove) {
            Text("REMOVE", style = MaterialTheme.typography.labelSmall, color = OnHeroSurfaceSoft)
        }
    }
}

/** One submission requirement, ticked or not. Naming each one is what
 * makes "you cannot submit yet" answerable. */
@Composable
private fun RequirementRow(requirement: SubmissionRequirement) {
    Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
        if (requirement.met) {
            Icon(Icons.Filled.Check, contentDescription = null, tint = SuccessGreen, modifier = Modifier.size(16.dp))
        } else {
            Box(
                Modifier
                    .size(16.dp)
                    .clip(CircleShape)
                    .border(1.5.dp, OnHeroSurfaceSoft, CircleShape),
            )
        }
        Spacer(Modifier.width(10.dp))
        Text(
            requirement.label,
            style = MaterialTheme.typography.bodyMedium,
            color = if (requirement.met) OnHeroSurfaceSoft else OnHeroSurface,
            lineHeight = 21.sp,
        )
    }
}

@Composable
private fun ChecklistItem(text: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.Top) {
        Box(
            Modifier
                .padding(top = 2.dp)
                .size(16.dp)
                .clip(RoundedCornerShape(4.dp))
                .border(1.5.dp, OnHeroSurfaceSoft, RoundedCornerShape(4.dp)),
        )
        Spacer(Modifier.width(12.dp))
        Text(text, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurface, lineHeight = 23.sp)
    }
}

@Composable
private fun StageIntro(eyebrow: String, title: String, body: String) {
    Text(eyebrow, style = MaterialTheme.typography.labelLarge, color = NibsOrange)
    Spacer(Modifier.height(10.dp))
    Text(title, style = MaterialTheme.typography.headlineMedium, color = OnHeroSurface, lineHeight = 34.sp)
    if (body.isNotBlank()) {
        Spacer(Modifier.height(12.dp))
        Text(body, style = MaterialTheme.typography.bodyLarge, color = OnHeroSurfaceSoft, lineHeight = 26.sp)
    }
}

// ---------------------------------------------------------------- blocks

/**
 * One block, one layout. Every colour here is explicit against the navy
 * surface — an earlier version let body text inherit the theme's on-surface
 * ink while hardcoding a pale callout background, which rendered white on
 * near-white for anyone in dark mode.
 */
@Composable
private fun Block(block: ContentBlock) {
    when (block.type) {
        BlockType.PARAGRAPH -> Text(
            block.text,
            style = MaterialTheme.typography.bodyLarge,
            color = OnHeroSurface,
            lineHeight = 26.sp,
            modifier = Modifier.padding(bottom = 12.dp),
        )

        BlockType.HEADING -> Text(
            block.text,
            style = MaterialTheme.typography.titleLarge,
            color = OnHeroSurface,
            modifier = Modifier.padding(top = 6.dp, bottom = 8.dp),
        )

        BlockType.LIST -> Column(Modifier.padding(bottom = 12.dp)) {
            block.items.forEach { Bullet(it) }
        }

        BlockType.LEARNING_OUTCOMES -> PlainBlock(
            title = block.title.ifBlank { "What you should be able to answer" },
        ) {
            block.items.forEach { Bullet(it) }
        }

        BlockType.KEY_CONCEPT -> PlainBlock(block.title.ifBlank { "Key concept" }) {
            if (block.text.isNotBlank()) CalloutBody(block.text)
            block.items.forEach { Bullet(it) }
        }

        BlockType.EXAMPLE -> Callout(block.title.ifBlank { "Example" }, Icons.Filled.Visibility) {
            if (block.text.isNotBlank()) CalloutBody(block.text)
            block.items.forEach { Bullet(it) }
        }

        BlockType.REFLECTION -> Callout(block.title.ifBlank { "Think about it" }, Icons.Filled.Lightbulb) {
            if (block.text.isNotBlank()) CalloutBody(block.text)
            block.items.forEach { Bullet(it) }
        }

        BlockType.PRACTICE, BlockType.TASK -> Callout(
            block.title.ifBlank { if (block.type == BlockType.TASK) "Your task" else "Practise this" },
            Icons.Filled.Check,
        ) {
            if (block.text.isNotBlank()) CalloutBody(block.text)
            block.items.forEach { Bullet(it) }
        }

        BlockType.IMAGE -> LessonImage(assetPath = block.url, caption = block.caption)

        BlockType.VIDEO -> Callout(block.title.ifBlank { "Watch" }, Icons.Filled.PlayCircleOutline) {
            CalloutBody(block.caption.ifBlank { "Video lesson" })
        }

        BlockType.RESOURCE -> if (block.caption.isNotBlank() || block.title.isNotBlank()) {
            Callout(block.title.ifBlank { "Resource" }, Icons.Filled.AttachFile) {
                CalloutBody(block.caption)
            }
        }

        else -> Unit
    }
}

@Composable
private fun CalloutBody(text: String) {
    Text(text, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurface, lineHeight = 23.sp)
}

@Composable
private fun Bullet(text: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp), verticalAlignment = Alignment.Top) {
        Box(
            Modifier
                .padding(top = 8.dp)
                .size(5.dp)
                .clip(CircleShape)
                .background(NibsOrange),
        )
        Spacer(Modifier.width(10.dp))
        Text(text, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurface, lineHeight = 22.sp)
    }
}

/**
 * Reference material — outcomes, objectives — as a labelled block rather
 * than a boxed card. Cards are reserved for what the learner must act on or
 * pay special attention to; boxing everything made the lesson feel like
 * tapping through containers instead of moving through learning.
 */
@Composable
private fun PlainBlock(title: String, content: @Composable () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(bottom = 14.dp)) {
        Text(
            title.uppercase(),
            style = MaterialTheme.typography.labelMedium,
            color = NibsOrange,
            fontWeight = FontWeight.SemiBold,
        )
        Spacer(Modifier.height(6.dp))
        content()
    }
}

/** A raised panel on the navy surface — never a pale tint, which is what
 * made these unreadable in dark mode before. */
@Composable
private fun Callout(title: String, icon: ImageVector, content: @Composable () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp)
            .clip(RoundedCornerShape(12.dp))
            .background(SurfaceWhite.copy(alpha = 0.06f))
            .border(1.dp, NibsOrange.copy(alpha = 0.30f), RoundedCornerShape(12.dp))
            .padding(horizontal = 14.dp, vertical = 12.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, contentDescription = null, tint = NibsOrange, modifier = Modifier.size(15.dp))
            Spacer(Modifier.width(7.dp))
            Text(
                title.uppercase(),
                style = MaterialTheme.typography.labelMedium,
                color = NibsOrange,
                fontWeight = FontWeight.SemiBold,
            )
        }
        Spacer(Modifier.height(6.dp))
        content()
    }
}

/** Quiet confirmation that a stage is finished — no celebration, just the
 * fact and what comes next. */
@Composable
private fun StageComplete(finished: LessonStage, next: LessonStage) {
    Row(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(8.dp))
            .background(SuccessGreen.copy(alpha = 0.12f))
            .padding(horizontal = 10.dp, vertical = 6.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Icon(Icons.Filled.Check, contentDescription = null, tint = SuccessGreen, modifier = Modifier.size(13.dp))
        Spacer(Modifier.width(7.dp))
        Text(
            "${finished.label.uppercase()} complete — next is ${next.label.uppercase()}",
            style = MaterialTheme.typography.labelSmall,
            color = OnHeroSurfaceSoft,
        )
    }
}

@Composable
private fun LessonFooter(
    isLast: Boolean,
    canSubmit: Boolean,
    blockingReason: String?,
    canGoBack: Boolean,
    onBack: () -> Unit,
    onContinue: () -> Unit,
    onSubmit: () -> Unit,
) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp).padding(bottom = 20.dp, top = 6.dp)) {
        if (isLast && !canSubmit && blockingReason != null) {
            Text(
                "Still to do: $blockingReason",
                style = MaterialTheme.typography.labelSmall,
                color = OnHeroSurfaceSoft,
                modifier = Modifier.padding(bottom = 8.dp),
            )
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            if (canGoBack) {
                OutlinedButton(onClick = onBack) { Text("BACK") }
            }
            Button(
                onClick = if (isLast) onSubmit else onContinue,
                enabled = !isLast || canSubmit,
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(
                    containerColor = NibsOrange,
                    contentColor = SurfaceWhite,
                    disabledContainerColor = SurfaceWhite.copy(alpha = 0.12f),
                    disabledContentColor = OnHeroSurfaceSoft,
                ),
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 12.dp),
            ) {
                Text(
                    if (isLast) "SUBMIT" else "CONTINUE",
                    style = MaterialTheme.typography.labelLarge,
                    maxLines = 1,
                )
            }
        }
    }
}
