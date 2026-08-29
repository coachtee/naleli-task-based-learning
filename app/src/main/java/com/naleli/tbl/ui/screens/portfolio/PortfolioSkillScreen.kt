package com.naleli.tbl.ui.screens.portfolio

import androidx.compose.foundation.background
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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Image
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.BadgeMark
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.StatusBadge
import com.naleli.tbl.ui.components.TaskStateBadge
import com.naleli.tbl.ui.components.color
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.SuccessGreen
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter

/**
 * What backs one portfolio skill.
 *
 * The portfolio list claims "Competent · 3 evidence items" and had a
 * chevron that went nowhere, so the claim could not be checked by the
 * person it belongs to. This is where the three items are: which day
 * produced each, what was written, and when competence was recorded.
 */
@Composable
fun PortfolioSkillScreen(
    skillName: String,
    onBack: () -> Unit,
    onOpenTask: (String) -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: PortfolioSkillViewModel = viewModel(
        key = "portfolio-skill-$skillName",
        factory = viewModelFactory { initializer { PortfolioSkillViewModel(container, skillName) } },
    )
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
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            Text(state.skillName, style = MaterialTheme.typography.headlineSmall)
            Text(
                "What backs this skill",
                style = MaterialTheme.typography.labelLarge,
                color = MaterialTheme.colorScheme.primary,
            )
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                if (state.result == CompetenceResult.COMPETENT) {
                    StatusBadge("Competent", SuccessGreen, BadgeMark.CHECK)
                } else {
                    StatusBadge(state.result.label(), state.result.color(), BadgeMark.DOT)
                }
                Spacer(Modifier.height(10.dp))
                Text(
                    "${state.tasksCompetent} of ${state.tasksTotal} days assessed competent",
                    style = MaterialTheme.typography.bodyMedium,
                )
                Text(
                    if (state.evidenceCount == 0) {
                        "No evidence attached yet."
                    } else {
                        "${state.evidenceCount} piece(s) of evidence in your portfolio."
                    },
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        item {
            Text("THE WORK BEHIND IT", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        }
        items(state.rows, key = { it.task.taskId }) { row -> SkillTaskCard(row, onOpenTask) }
        item { Spacer(Modifier.height(8.dp)) }
    }
}

@Composable
private fun SkillTaskCard(row: SkillTaskRow, onOpenTask: (String) -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable { onOpenTask(row.task.taskId) }) {
        Text(
            row.task.title,
            style = MaterialTheme.typography.titleMedium,
            maxLines = 2,
            overflow = TextOverflow.Ellipsis,
        )
        Spacer(Modifier.height(2.dp))
        Text(
            "Day ${row.task.dayNumber}" + (row.assessedAt?.let { " · Assessed ${formatDate(it)}" } ?: ""),
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )
        Spacer(Modifier.height(8.dp))
        TaskStateBadge(row.state, locked = false)

        if (row.evidence.isEmpty()) {
            Spacer(Modifier.height(8.dp))
            Text(
                "Nothing attached from this day yet.",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            return@NaleliCard
        }

        row.evidence.forEach { item ->
            Spacer(Modifier.height(10.dp))
            EvidenceEntry(item)
        }
    }
}

/** A written answer shows its words; a file shows what it is and when it
 * arrived. Both are evidence, and the portfolio treats them as such. */
@Composable
private fun EvidenceEntry(item: EvidenceItem) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(12.dp))
            .background(MaterialTheme.colorScheme.surfaceVariant)
            .padding(horizontal = 12.dp, vertical = 10.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(
                if (item.entity.fileType.startsWith("image/")) Icons.Filled.Image else Icons.Filled.Description,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.onSurfaceVariant,
                modifier = Modifier.size(16.dp),
            )
            Spacer(Modifier.width(8.dp))
            Text(
                if (item.isWritten) "Written answer" else item.entity.fileName,
                style = MaterialTheme.typography.labelLarge,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis,
                modifier = Modifier.weight(1f),
            )
            Text(
                formatDate(item.entity.createdAt),
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        item.writtenText?.takeIf { it.isNotBlank() }?.let { text ->
            Spacer(Modifier.height(6.dp))
            Text(
                text,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurface,
                maxLines = 8,
                overflow = TextOverflow.Ellipsis,
            )
        }
    }
}

private val DATE_FORMAT: DateTimeFormatter = DateTimeFormatter.ofPattern("d MMM yyyy")

private fun formatDate(epochMillis: Long): String =
    Instant.ofEpochMilli(epochMillis).atZone(ZoneId.systemDefault()).format(DATE_FORMAT)
