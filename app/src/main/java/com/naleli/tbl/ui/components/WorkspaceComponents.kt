package com.naleli.tbl.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.naleli.tbl.data.content.TaskTier
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.domain.ProjectHealth
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.ui.theme.WarningOrange

/** Naleli Workspace's three task tiers, colour-coded consistently everywhere
 * a task appears (My Work, Task Workspace, Journey). */
fun TaskTier.label(): String = when (this) {
    TaskTier.REQUIRED -> "Required"
    TaskTier.SUPPORTING -> "Supporting"
    TaskTier.ASSESSMENT -> "Assessment"
}

@Composable
fun TaskTier.color(): Color = when (this) {
    TaskTier.REQUIRED -> Color(0xFFE0453A)
    TaskTier.SUPPORTING -> Color(0xFF3B8FE0)
    TaskTier.ASSESSMENT -> MaterialTheme.colorScheme.primary
}

@Composable
fun TierDot(tier: TaskTier, modifier: Modifier = Modifier) {
    Box(modifier = modifier.size(8.dp).background(tier.color(), CircleShape))
}

fun TaskProgressState.label(): String = when (this) {
    TaskProgressState.NOT_STARTED -> "To Do"
    TaskProgressState.IN_PROGRESS -> "In Progress"
    TaskProgressState.SUBMITTED -> "Submitted"
    TaskProgressState.NEEDS_REVISION -> "Needs Revision"
    TaskProgressState.COMPETENT -> "Competent"
}

fun CompetenceResult.label(): String = when (this) {
    CompetenceResult.NOT_YET_ASSESSED -> "Not Yet Assessed"
    CompetenceResult.REQUIRES_IMPROVEMENT -> "Requires Improvement"
    CompetenceResult.COMPETENT -> "Competent"
}

@Composable
fun CompetenceResult.color(): Color = when (this) {
    CompetenceResult.NOT_YET_ASSESSED -> MaterialTheme.colorScheme.onSurfaceVariant
    CompetenceResult.REQUIRES_IMPROVEMENT -> WarningOrange
    CompetenceResult.COMPETENT -> MaterialTheme.colorScheme.primary
}

fun ProjectHealth.label(): String = when (this) {
    ProjectHealth.ON_TRACK -> "On Track"
    ProjectHealth.ATTENTION_REQUIRED -> "Attention Required"
    ProjectHealth.BEHIND_SCHEDULE -> "Behind Schedule"
}

@Composable
fun ProjectHealth.color(): Color = when (this) {
    ProjectHealth.ON_TRACK -> MaterialTheme.colorScheme.primary
    ProjectHealth.ATTENTION_REQUIRED -> WarningOrange
    ProjectHealth.BEHIND_SCHEDULE -> MaterialTheme.colorScheme.error
}
