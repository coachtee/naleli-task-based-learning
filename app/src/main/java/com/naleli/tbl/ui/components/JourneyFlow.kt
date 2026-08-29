package com.naleli.tbl.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import com.naleli.tbl.ui.theme.SlateGray
import com.naleli.tbl.ui.theme.SurfaceWhite

/**
 * The Naleli Task-Based Learning sequence, drawn as a connected flow rather
 * than a list — the point of the diagram is that each step feeds the next,
 * which a bulleted list does not say.
 *
 * Used by the orientation (where it explains the whole 90 days) and at the
 * foot of a lesson (where it shows reading is step one of five), so both
 * places state the same sequence in the same shape.
 *
 * [onDark] switches the palette for the navy hero surface; everything else
 * is the approved system — navy markers, orange for the active step.
 */
@Composable
fun JourneyFlow(
    steps: List<String>,
    modifier: Modifier = Modifier,
    activeIndex: Int = -1,
    onDark: Boolean = false,
) {
    val doneInk = if (onDark) OnHeroSurface else HeroSurface
    val restInk = if (onDark) OnHeroSurfaceSoft else SlateGray
    val connector = if (onDark) OnHeroSurfaceSoft.copy(alpha = 0.35f) else SlateGray.copy(alpha = 0.35f)

    Column(modifier.fillMaxWidth()) {
        steps.forEachIndexed { index, step ->
            val isActive = index == activeIndex
            val markerColor = if (isActive) NibsOrange else if (onDark) OnHeroSurfaceSoft else SlateGray
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(if (isActive) 12.dp else 8.dp)
                        .clip(CircleShape)
                        .background(markerColor),
                )
                Spacer(Modifier.width(if (isActive) 10.dp else 12.dp))
                Text(
                    step,
                    style = MaterialTheme.typography.bodyMedium,
                    color = if (isActive) NibsOrange else if (index < activeIndex) doneInk else restInk,
                    fontWeight = if (isActive) FontWeight.SemiBold else FontWeight.Normal,
                )
            }
            if (index != steps.lastIndex) {
                // The connector is the diagram's whole argument: these are
                // stages of one sequence, not five separate activities.
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Box(Modifier.width(8.dp), contentAlignment = Alignment.Center) {
                        Box(
                            Modifier
                                .width(2.dp)
                                .height(14.dp)
                                .background(connector),
                        )
                    }
                }
            }
        }
    }
}

/** The same sequence as a single compact strip, for headers where a full
 * vertical flow would dominate the screen. */
@Composable
fun JourneyFlowStrip(
    steps: List<String>,
    activeIndex: Int,
    modifier: Modifier = Modifier,
    onDark: Boolean = false,
) {
    Row(modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        steps.forEachIndexed { index, _ ->
            val fill: Color = when {
                index == activeIndex -> NibsOrange
                index < activeIndex -> if (onDark) OnHeroSurface else HeroSurface
                else -> if (onDark) SurfaceWhite.copy(alpha = 0.25f) else SlateGray.copy(alpha = 0.3f)
            }
            Box(
                Modifier
                    .weight(1f)
                    .height(4.dp)
                    .clip(CircleShape)
                    .background(fill),
            )
        }
    }
}
