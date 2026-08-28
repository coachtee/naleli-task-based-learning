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
import com.naleli.tbl.ui.theme.SuccessGreen
import com.naleli.tbl.ui.theme.WarningOrange

/** Naleli Workspace's three task tiers, labelled consistently everywhere a
 * task appears (My Work, Task Workspace, Journey). Colour is reserved for
 * the approved four-colour system (blue = action, green = competent, amber
 * = attention, red = required/warning) — so only Required gets a colour
 * here; Supporting and Assessment are neutral, tier is conveyed by label. */
fun TaskTier.label(): String = when (this) {
    TaskTier.REQUIRED -> "Required"
    TaskTier.SUPPORTING -> "Supporting"
    TaskTier.ASSESSMENT -> "Assessment"
}

@Composable
fun TaskTier.color(): Color = when (this) {
    TaskTier.REQUIRED -> MaterialTheme.colorScheme.error
    TaskTier.SUPPORTING -> MaterialTheme.colorScheme.onSurfaceVariant
    TaskTier.ASSESSMENT -> MaterialTheme.colorScheme.onSurfaceVariant
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
    CompetenceResult.NOT_YET_ASSESSED -> "Submitted"
    CompetenceResult.REQUIRES_IMPROVEMENT -> "Not Yet Competent"
    CompetenceResult.COMPETENT -> "Competent"
}

// The green "Competent" state should feel meaningful and earned — it is
// the only competence outcome that gets green anywhere in the app.
@Composable
fun CompetenceResult.color(): Color = when (this) {
    CompetenceResult.NOT_YET_ASSESSED -> MaterialTheme.colorScheme.onSurfaceVariant
    CompetenceResult.REQUIRES_IMPROVEMENT -> WarningOrange
    CompetenceResult.COMPETENT -> SuccessGreen
}

fun ProjectHealth.label(): String = when (this) {
    ProjectHealth.ON_TRACK -> "On Track"
    ProjectHealth.ATTENTION_REQUIRED -> "Attention Required"
    ProjectHealth.BEHIND_SCHEDULE -> "Behind Schedule"
}

@Composable
fun ProjectHealth.color(): Color = when (this) {
    ProjectHealth.ON_TRACK -> SuccessGreen
    ProjectHealth.ATTENTION_REQUIRED -> WarningOrange
    ProjectHealth.BEHIND_SCHEDULE -> MaterialTheme.colorScheme.error
}
