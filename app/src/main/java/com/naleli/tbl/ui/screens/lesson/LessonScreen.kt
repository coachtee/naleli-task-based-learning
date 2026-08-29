package com.naleli.tbl.ui.screens.lesson

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AttachFile
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lightbulb
import androidx.compose.material.icons.filled.PlayCircleOutline
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
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
import com.naleli.tbl.data.content.LessonPage
import com.naleli.tbl.data.content.LessonStage
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.ui.components.BackHeader
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

    val readingPages = remember(taskId) {
        LessonLibrary.lessonsForTask(context, taskId).flatMap { it.pages }
    }

    // Reading screens first, then the three working stages. Every lesson
    // ends in the same place: attach what you made.
    val stages: List<Stage> = remember(readingPages, task) {
        buildList {
            readingPages.forEach { add(Stage.Reading(it)) }
            task?.let {
                if (it.practiseText.isNotBlank()) add(Stage.Try(it.practiseText))
                add(Stage.Apply(it))
                add(Stage.Show(it))
            }
        }
    }

    var index by rememberSaveable(taskId) { mutableIntStateOf(0) }
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

    Column(
        Modifier
            .fillMaxSize()
            .background(HeroSurface),
    ) {
        LessonTopBar(
            task = task,
            stage = stage.lessonStage,
            position = safeIndex + 1,
            total = stages.size,
            onBack = { if (safeIndex == 0) onBack() else index -= 1 },
        )

        Column(
            Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 24.dp)
                .padding(top = 26.dp, bottom = 12.dp),
        ) {
            when (stage) {
                is Stage.Reading -> ReadingStage(stage.page)
                is Stage.Try -> TryStage(stage.instructions)
                is Stage.Apply -> ApplyStage(stage.task)
                is Stage.Show -> ShowStage(
                    task = stage.task,
                    evidenceCount = state.evidenceCount,
                    onAddEvidence = onAddEvidence,
                )
            }
            Spacer(Modifier.height(24.dp))
        }

        LessonFooter(
            isLast = isLast,
            canSubmit = state.evidenceCount > 0,
            onContinue = {
                // Passing a stage is what marks its sub-step done, so the
                // checklist reflects the journey instead of asking the
                // learner to tick the same work twice.
                viewModel.completeStageSubStep(stage.lessonStage)
                index = (safeIndex + 1).coerceAtMost(stages.lastIndex)
            },
            onSubmit = {
                viewModel.completeStageSubStep(LessonStage.SHOW)
                viewModel.submitForAssessment(SUBMIT_CONFIDENCE)
                onSubmitted()
            },
        )
    }
}

/** Recorded alongside the submission; the rubric never uses it as a result,
 * only as the learner's own read on how the work went. */
private const val SUBMIT_CONFIDENCE = 3

/** One screen of the lesson. Reading screens come from content; the last
 * three are built from the day's own task record. */
private sealed interface Stage {
    val lessonStage: LessonStage

    data class Reading(val page: LessonPage) : Stage {
        override val lessonStage: LessonStage get() = page.lessonStage
    }

    data class Try(val instructions: String) : Stage {
        override val lessonStage: LessonStage get() = LessonStage.TRY
    }

    data class Apply(val task: WorkTask) : Stage {
        override val lessonStage: LessonStage get() = LessonStage.APPLY
    }

    data class Show(val task: WorkTask) : Stage {
        override val lessonStage: LessonStage get() = LessonStage.SHOW
    }
}

@Composable
private fun LessonTopBar(task: WorkTask, stage: LessonStage, position: Int, total: Int, onBack: () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 24.dp).padding(top = 8.dp)) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            BackHeader(onBack = onBack, tint = OnHeroSurface)
            Text(
                "$position of $total",
                style = MaterialTheme.typography.labelMedium,
                color = OnHeroSurfaceSoft,
            )
        }
        Spacer(Modifier.height(6.dp))
        // The five stages, always visible: the learner can see where this
        // screen sits in the arc and that reading is only the first part.
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            LessonStage.entries.forEach { entry ->
                val reached = entry.ordinal1 <= stage.ordinal1
                Column(Modifier.weight(1f), horizontalAlignment = Alignment.CenterHorizontally) {
                    Box(
                        Modifier
                            .fillMaxWidth()
                            .height(3.dp)
                            .clip(CircleShape)
                            .background(
                                when {
                                    entry == stage -> NibsOrange
                                    reached -> OnHeroSurface
                                    else -> SurfaceWhite.copy(alpha = 0.22f)
                                },
                            ),
                    )
                    Spacer(Modifier.height(5.dp))
                    Text(
                        entry.label,
                        style = MaterialTheme.typography.labelSmall,
                        color = if (entry == stage) NibsOrange else OnHeroSurfaceSoft,
                        fontWeight = if (entry == stage) FontWeight.SemiBold else FontWeight.Normal,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                }
            }
        }
        Spacer(Modifier.height(10.dp))
        Text(
            task.title,
            style = MaterialTheme.typography.labelSmall,
            color = OnHeroSurfaceSoft,
            maxLines = 1,
            overflow = TextOverflow.Ellipsis,
        )
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
        Spacer(Modifier.height(18.dp))
    }
    page.blocks.forEach { block -> Block(block) }
}

@Composable
private fun TryStage(instructions: String) {
    StageIntro(
        eyebrow = "TRY IT",
        title = "Now do it yourself",
        body = "Follow this with your own device. Reading it is not the same as having done it.",
    )
    Spacer(Modifier.height(20.dp))
    Text(instructions, style = MaterialTheme.typography.bodyLarge, color = OnHeroSurface, lineHeight = 27.sp)
}

/**
 * The word "Brief" is reserved for this screen alone: a workplace
 * assignment with a requester, a task and a deliverable — not another
 * restatement of the lesson.
 */
@Composable
private fun ApplyStage(task: WorkTask) {
    StageIntro(
        eyebrow = "WORK MISSION",
        title = "Apply it to real work",
        body = "",
    )
    Spacer(Modifier.height(20.dp))
    MissionSection("THE BRIEF", task.whyItMatters)
    MissionSection("YOUR TASK", task.assignmentText)
    MissionSection("WHAT TO SUBMIT", task.deliverableLabel)
}

@Composable
private fun MissionSection(label: String, body: String) {
    if (body.isBlank()) return
    Column(Modifier.padding(bottom = 22.dp)) {
        Text(label, style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(6.dp))
        Text(body, style = MaterialTheme.typography.bodyLarge, color = OnHeroSurface, lineHeight = 27.sp)
    }
}

@Composable
private fun ShowStage(task: WorkTask, evidenceCount: Int, onAddEvidence: () -> Unit) {
    StageIntro(
        eyebrow = "SHOW YOUR WORK",
        title = "Submit your evidence",
        body = "This is what proves you can do it — and it becomes part of your portfolio.",
    )
    Spacer(Modifier.height(20.dp))

    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(SurfaceWhite.copy(alpha = 0.07f))
            .padding(18.dp),
    ) {
        Text("WHAT TO SUBMIT", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(6.dp))
        Text(task.deliverableLabel, style = MaterialTheme.typography.bodyLarge, color = OnHeroSurface, lineHeight = 26.sp)
        Spacer(Modifier.height(16.dp))
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                if (evidenceCount > 0) Icons.Filled.Check else Icons.Filled.AttachFile,
                contentDescription = null,
                tint = if (evidenceCount > 0) SuccessGreen else OnHeroSurfaceSoft,
                modifier = Modifier.size(18.dp),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                if (evidenceCount == 0) "Nothing attached yet" else "$evidenceCount file(s) attached",
                style = MaterialTheme.typography.bodyMedium,
                color = OnHeroSurfaceSoft,
            )
        }
        Spacer(Modifier.height(12.dp))
        OutlinedButton(onClick = onAddEvidence, modifier = Modifier.fillMaxWidth()) {
            Text(if (evidenceCount == 0) "ATTACH EVIDENCE" else "ADD ANOTHER FILE")
        }
    }

    if (task.reviewQuestions.isNotEmpty()) {
        Spacer(Modifier.height(22.dp))
        Text("BEFORE YOU SUBMIT", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
        Spacer(Modifier.height(4.dp))
        Text(
            "Answer these in your own words. If you cannot, go back through the lesson.",
            style = MaterialTheme.typography.bodyMedium,
            color = OnHeroSurfaceSoft,
            lineHeight = 22.sp,
        )
        Spacer(Modifier.height(10.dp))
        task.reviewQuestions.forEach { question -> Bullet(question) }
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
            lineHeight = 27.sp,
            modifier = Modifier.padding(bottom = 16.dp),
        )

        BlockType.HEADING -> Text(
            block.text,
            style = MaterialTheme.typography.titleLarge,
            color = OnHeroSurface,
            modifier = Modifier.padding(top = 8.dp, bottom = 10.dp),
        )

        BlockType.LIST -> Column(Modifier.padding(bottom = 12.dp)) {
            block.items.forEach { Bullet(it) }
        }

        BlockType.LEARNING_OUTCOMES -> Callout(
            title = block.title.ifBlank { "What you should be able to answer" },
            icon = Icons.Filled.Lightbulb,
        ) {
            block.items.forEach { Bullet(it) }
        }

        BlockType.KEY_CONCEPT -> Callout(block.title.ifBlank { "Key concept" }, Icons.Filled.Lightbulb) {
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
    Row(Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.Top) {
        Box(
            Modifier
                .padding(top = 9.dp)
                .size(6.dp)
                .clip(CircleShape)
                .background(NibsOrange),
        )
        Spacer(Modifier.width(12.dp))
        Text(text, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurface, lineHeight = 23.sp)
    }
}

/** A raised panel on the navy surface — never a pale tint, which is what
 * made these unreadable in dark mode before. */
@Composable
private fun Callout(title: String, icon: ImageVector, content: @Composable () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 8.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(SurfaceWhite.copy(alpha = 0.07f))
            .border(1.dp, NibsOrange.copy(alpha = 0.35f), RoundedCornerShape(16.dp))
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, contentDescription = null, tint = NibsOrange, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(
                title.uppercase(),
                style = MaterialTheme.typography.labelLarge,
                color = NibsOrange,
                fontWeight = FontWeight.SemiBold,
            )
        }
        Spacer(Modifier.height(8.dp))
        content()
    }
}

@Composable
private fun LessonFooter(isLast: Boolean, canSubmit: Boolean, onContinue: () -> Unit, onSubmit: () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 24.dp).padding(bottom = 24.dp, top = 8.dp)) {
        if (isLast) {
            Button(
                onClick = onSubmit,
                enabled = canSubmit,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(
                    containerColor = NibsOrange,
                    contentColor = SurfaceWhite,
                    disabledContainerColor = SurfaceWhite.copy(alpha = 0.12f),
                    disabledContentColor = OnHeroSurfaceSoft,
                ),
            ) { Text("SUBMIT FOR ASSESSMENT") }
            if (!canSubmit) {
                Spacer(Modifier.height(8.dp))
                Text(
                    "Attach your evidence first.",
                    style = MaterialTheme.typography.labelSmall,
                    color = OnHeroSurfaceSoft,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        } else {
            Button(
                onClick = onContinue,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(containerColor = NibsOrange, contentColor = SurfaceWhite),
            ) { Text("CONTINUE") }
        }
    }
}
