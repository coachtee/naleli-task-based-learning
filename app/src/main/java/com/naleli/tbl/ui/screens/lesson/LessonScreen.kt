package com.naleli.tbl.ui.screens.lesson

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
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Lightbulb
import androidx.compose.material.icons.filled.PlayCircleOutline
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.naleli.tbl.data.content.BlockType
import com.naleli.tbl.data.content.ContentBlock
import com.naleli.tbl.data.content.Lesson
import com.naleli.tbl.data.content.LessonLibrary
import com.naleli.tbl.data.content.WorkTask
import com.naleli.tbl.data.content.WorkspaceCurriculum
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.JourneyFlow
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.NibsOrangeTint
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import com.naleli.tbl.ui.theme.SlateSurface
import com.naleli.tbl.ui.theme.SurfaceWhite

/**
 * The reading half of a day: the lesson, laid out from typed content blocks
 * (see data/content/LessonContent.kt). No lesson text is written here — this
 * file only decides how each block type looks.
 *
 * A lesson is never a dead end. It opens by answering why the learner is
 * reading it and closes on the work it exists to prepare them for, so
 * reading always leads into UNDERSTAND -> PRACTISE -> TASK -> EVIDENCE ->
 * ASSESSMENT rather than finishing as its own reward.
 */
@Composable
fun LessonScreen(taskId: String, onBack: () -> Unit, onStartWork: () -> Unit) {
    val context = LocalContext.current
    val task = WorkspaceCurriculum.taskById(taskId)
    val lessons = remember(taskId) { LessonLibrary.lessonsForTask(context, taskId) }
    val listState = rememberLazyListState()

    // Reading position, from the real scroll state rather than a counter
    // that could drift from what is on screen.
    val totalBlocks = lessons.sumOf { it.blocks.size }.coerceAtLeast(1)
    val readFraction by remember {
        derivedStateOf {
            val index = listState.firstVisibleItemIndex.toFloat()
            (index / (totalBlocks + HEADER_ITEMS + FOOTER_ITEMS)).coerceIn(0f, 1f)
        }
    }

    Box(Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        LazyColumn(
            state = listState,
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(bottom = 32.dp),
        ) {
            item { LessonHeader(task, lessons, readFraction, onBack) }

            if (lessons.isEmpty()) {
                item {
                    NaleliCard(modifier = Modifier.fillMaxWidth().padding(20.dp)) {
                        Text("No reading for this day", style = MaterialTheme.typography.titleMedium)
                        Spacer(Modifier.height(6.dp))
                        Text(
                            "This day is practical work rather than a lesson. Open the workspace to see what to do.",
                            style = MaterialTheme.typography.bodyMedium,
                            color = MaterialTheme.colorScheme.onSurfaceVariant,
                        )
                    }
                }
            }

            task?.let { item { WhyThisLesson(it) } }

            lessons.forEach { lesson ->
                if (lessons.size > 1) {
                    item { LessonDivider(lesson) }
                }
                items(lesson.blocks) { block -> Block(block) }
            }

            item { WhatHappensNext(task, onStartWork) }
        }
    }
}

/** Items rendered around the blocks, so the progress fraction counts the
 * whole scrollable list rather than the blocks alone. */
private const val HEADER_ITEMS = 2
private const val FOOTER_ITEMS = 1

@Composable
private fun LessonHeader(task: WorkTask?, lessons: List<Lesson>, readFraction: Float, onBack: () -> Unit) {
    val lesson = lessons.firstOrNull()
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(HeroSurface)
            .padding(horizontal = 20.dp, vertical = 16.dp),
    ) {
        BackHeader(onBack = onBack, tint = OnHeroSurface)
        Spacer(Modifier.height(10.dp))
        Text(
            lessons.joinToString(" · ") { "Lesson ${it.lessonCode}" }
                .ifBlank { task?.let { "Day ${it.dayNumber}" }.orEmpty() },
            style = MaterialTheme.typography.labelLarge,
            color = NibsOrange,
        )
        Spacer(Modifier.height(4.dp))
        Text(
            lesson?.title ?: task?.title.orEmpty(),
            style = MaterialTheme.typography.headlineSmall,
            color = OnHeroSurface,
        )
        lesson?.moduleTitle?.takeIf { it.isNotBlank() }?.let {
            Spacer(Modifier.height(4.dp))
            Text(
                "Module ${lesson.moduleNumber} — $it",
                style = MaterialTheme.typography.bodyMedium,
                color = OnHeroSurfaceSoft,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
        }
        Spacer(Modifier.height(14.dp))
        NaleliProgressBar(
            progressFraction = readFraction,
            trackColor = SurfaceWhite.copy(alpha = 0.22f),
        )
        Spacer(Modifier.height(6.dp))
        Text(
            "${(readFraction * 100).toInt()}% read",
            style = MaterialTheme.typography.labelSmall,
            color = OnHeroSurfaceSoft,
        )
    }
}

/**
 * The five questions the brief says every module must answer, on the screen
 * where the learner starts. Built from the day's own curriculum record, so
 * it stays true as content changes.
 */
@Composable
private fun WhyThisLesson(task: WorkTask) {
    NaleliCard(modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 16.dp)) {
        Text("WHY YOU ARE READING THIS", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        Spacer(Modifier.height(10.dp))
        QuestionRow("What am I learning?", task.skillDeveloped)
        QuestionRow("Why does it matter?", task.whyItMatters)
        QuestionRow("What will I have to do?", task.assignmentText)
        QuestionRow("What proves I can do it?", task.deliverableLabel)
    }
}

@Composable
private fun QuestionRow(question: String, answer: String) {
    if (answer.isBlank()) return
    Column(Modifier.padding(bottom = 12.dp)) {
        Text(question, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Spacer(Modifier.height(2.dp))
        Text(answer, style = MaterialTheme.typography.bodyMedium, lineHeight = 21.sp)
    }
}

@Composable
private fun LessonDivider(lesson: Lesson) {
    Column(Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 12.dp)) {
        Text(
            "LESSON ${lesson.lessonCode}",
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.primary,
        )
        Text(lesson.title, style = MaterialTheme.typography.titleLarge)
    }
}

/**
 * One block, one layout. An unrecognised type renders nothing rather than
 * failing — content is authored outside this app and may run ahead of it.
 */
@Composable
private fun Block(block: ContentBlock) {
    val sidePadding = Modifier.fillMaxWidth().padding(horizontal = 20.dp)
    when (block.type) {
        BlockType.HEADING -> Text(
            block.text,
            style = MaterialTheme.typography.titleLarge,
            modifier = sidePadding.padding(top = 24.dp, bottom = 8.dp),
        )

        BlockType.PARAGRAPH -> Text(
            block.text,
            style = MaterialTheme.typography.bodyLarge,
            // Long-form reading needs more line height than the app's
            // default UI text: this is a page, not a label.
            lineHeight = 26.sp,
            modifier = sidePadding.padding(bottom = 14.dp),
        )

        BlockType.LIST -> Column(sidePadding.padding(bottom = 14.dp)) {
            block.items.forEach { item -> BulletRow(item) }
        }

        BlockType.LEARNING_OUTCOMES -> Callout(
            title = block.title.ifBlank { "Learning outcomes" },
            accent = MaterialTheme.colorScheme.primary,
            tint = NibsOrangeTint,
            icon = Icons.Filled.Lightbulb,
        ) {
            block.items.forEach { item -> BulletRow(item) }
        }

        BlockType.KEY_CONCEPT -> Callout(
            title = block.title.ifBlank { "Key concept" },
            accent = HeroSurface,
            tint = SlateSurface,
            icon = Icons.Filled.Lightbulb,
        ) {
            if (block.text.isNotBlank()) {
                Text(block.text, style = MaterialTheme.typography.bodyMedium, lineHeight = 22.sp)
            }
            block.items.forEach { item -> BulletRow(item) }
        }

        BlockType.EXAMPLE -> Callout(
            title = block.title.ifBlank { "Example" },
            accent = HeroSurface,
            tint = SlateSurface,
            icon = Icons.Filled.Description,
        ) {
            if (block.text.isNotBlank()) {
                Text(block.text, style = MaterialTheme.typography.bodyMedium, lineHeight = 22.sp)
            }
            block.items.forEach { item -> BulletRow(item) }
        }

        BlockType.REFLECTION -> Callout(
            title = block.title.ifBlank { "Think about it" },
            accent = MaterialTheme.colorScheme.primary,
            tint = NibsOrangeTint,
            icon = Icons.Filled.Lightbulb,
        ) {
            if (block.text.isNotBlank()) {
                Text(block.text, style = MaterialTheme.typography.bodyMedium, lineHeight = 22.sp)
            }
            block.items.forEach { item -> BulletRow(item) }
        }

        BlockType.PRACTICE, BlockType.TASK -> Callout(
            title = block.title.ifBlank { if (block.type == BlockType.TASK) "Your task" else "Practise this" },
            accent = MaterialTheme.colorScheme.primary,
            tint = NibsOrangeTint,
            icon = Icons.Filled.Description,
        ) {
            if (block.text.isNotBlank()) {
                Text(block.text, style = MaterialTheme.typography.bodyMedium, lineHeight = 22.sp)
            }
            block.items.forEach { item -> BulletRow(item) }
        }

        // Media the app cannot play inline offline. Rather than pretend, the
        // block states plainly what it is and where it lives, which is the
        // honest offline-first behaviour.
        BlockType.VIDEO -> Callout(
            title = block.title.ifBlank { "Watch" },
            accent = HeroSurface,
            tint = SlateSurface,
            icon = Icons.Filled.PlayCircleOutline,
        ) {
            Text(
                block.caption.ifBlank { "Video lesson" },
                style = MaterialTheme.typography.bodyMedium,
                lineHeight = 22.sp,
            )
            if (block.url.isNotBlank()) {
                Spacer(Modifier.height(4.dp))
                Text(block.url, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }

        BlockType.IMAGE, BlockType.RESOURCE -> if (block.caption.isNotBlank() || block.title.isNotBlank()) {
            Callout(
                title = block.title.ifBlank { "Resource" },
                accent = HeroSurface,
                tint = SlateSurface,
                icon = Icons.Filled.Description,
            ) {
                Text(block.caption, style = MaterialTheme.typography.bodyMedium)
            }
        }

        else -> Unit
    }
}

@Composable
private fun BulletRow(text: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.Top) {
        Box(
            Modifier
                .padding(top = 9.dp)
                .size(5.dp)
                .clip(RoundedCornerShape(3.dp))
                .background(MaterialTheme.colorScheme.primary),
        )
        Spacer(Modifier.width(10.dp))
        Text(text, style = MaterialTheme.typography.bodyMedium, lineHeight = 22.sp)
    }
}

@Composable
private fun Callout(
    title: String,
    accent: androidx.compose.ui.graphics.Color,
    tint: androidx.compose.ui.graphics.Color,
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    content: @Composable () -> Unit,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 20.dp, vertical = 10.dp)
            .clip(RoundedCornerShape(16.dp))
            .background(tint)
            .border(1.dp, accent.copy(alpha = 0.25f), RoundedCornerShape(16.dp))
            .padding(16.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, contentDescription = null, tint = accent, modifier = Modifier.size(18.dp))
            Spacer(Modifier.width(8.dp))
            Text(
                title.uppercase(),
                style = MaterialTheme.typography.labelLarge,
                color = accent,
                fontWeight = FontWeight.SemiBold,
            )
        }
        Spacer(Modifier.height(8.dp))
        content()
    }
}

/** Reading is never the end of a day — this is the hand-off into the work. */
@Composable
private fun WhatHappensNext(task: WorkTask?, onStartWork: () -> Unit) {
    Column(Modifier.fillMaxWidth().padding(20.dp)) {
        Spacer(Modifier.height(8.dp))
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(20.dp))
                .background(HeroSurface)
                .padding(20.dp),
        ) {
            Text("NOW PUT IT TO WORK", style = MaterialTheme.typography.labelLarge, color = NibsOrange)
            Spacer(Modifier.height(6.dp))
            Text(
                "Reading tells you what. The work is how you show you can do it.",
                style = MaterialTheme.typography.bodyMedium,
                color = OnHeroSurfaceSoft,
                lineHeight = 21.sp,
            )
            Spacer(Modifier.height(16.dp))
            JourneyFlow(
                steps = listOf("Understand", "Practise", "Complete task", "Submit evidence", "Assessment"),
                activeIndex = 0,
                onDark = true,
            )
            Spacer(Modifier.height(18.dp))
            Button(
                onClick = onStartWork,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(containerColor = NibsOrange, contentColor = SurfaceWhite),
            ) { Text("START THE WORK") }
        }
        task?.deliverableLabel?.takeIf { it.isNotBlank() }?.let {
            Spacer(Modifier.height(12.dp))
            Text(
                "You will finish today with: $it",
                style = MaterialTheme.typography.labelMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}
