package com.naleli.tbl.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.naleli.tbl.data.content.TaskTier
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.domain.ProjectHealth
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.ui.theme.ChipShape
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

/** The status-badge marks from the approved design system. */
enum class BadgeMark { DOT, RING, CHECK, LOCK }

/**
 * A pill-shaped status badge: a small mark plus a label, on a tinted wash
 * of its own colour. This is the design system's one status component —
 * every screen that shows "what state is this in" (My Work, Journey,
 * Portfolio, Task Workspace) uses it, so a Required task reads identically
 * everywhere instead of each screen inventing its own dot-and-text row.
 */
@Composable
fun StatusBadge(
    label: String,
    color: Color,
    mark: BadgeMark = BadgeMark.DOT,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier
            .background(color.copy(alpha = 0.14f), ChipShape)
            .padding(horizontal = 10.dp, vertical = 5.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        when (mark) {
            BadgeMark.DOT -> Box(Modifier.size(7.dp).background(color, CircleShape))
            BadgeMark.RING -> Box(
                Modifier.size(7.dp).background(color.copy(alpha = 0.35f), CircleShape),
            )
            BadgeMark.CHECK -> Icon(Icons.Filled.Check, contentDescription = null, tint = color, modifier = Modifier.size(12.dp))
            BadgeMark.LOCK -> Icon(Icons.Filled.Lock, contentDescription = null, tint = color, modifier = Modifier.size(11.dp))
        }
        Text(label, style = MaterialTheme.typography.labelSmall, color = color)
    }
}

/** The badge for a task's live state — the single mapping every screen
 * shares, so progress language can't drift between screens. */
@Composable
fun TaskStateBadge(state: TaskProgressState, locked: Boolean, modifier: Modifier = Modifier) {
    if (locked) {
        StatusBadge("Locked", MaterialTheme.colorScheme.onSurfaceVariant, BadgeMark.LOCK, modifier)
        return
    }
    when (state) {
        TaskProgressState.NOT_STARTED -> StatusBadge("Available", MaterialTheme.colorScheme.onSurfaceVariant, BadgeMark.RING, modifier)
        TaskProgressState.IN_PROGRESS -> StatusBadge("In Progress", WarningOrange, BadgeMark.DOT, modifier)
        TaskProgressState.NEEDS_REVISION -> StatusBadge("Needs Revision", WarningOrange, BadgeMark.DOT, modifier)
        TaskProgressState.SUBMITTED -> StatusBadge("Submitted", MaterialTheme.colorScheme.primary, BadgeMark.DOT, modifier)
        TaskProgressState.COMPETENT -> StatusBadge("Competent", SuccessGreen, BadgeMark.CHECK, modifier)
    }
}

/** The tier badge — what KIND of task this is, kept visually separate from
 * the state badge above (which says where it is right now). */
@Composable
fun TierBadge(tier: TaskTier, modifier: Modifier = Modifier) {
    StatusBadge(tier.label(), tier.color(), BadgeMark.DOT, modifier)
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
