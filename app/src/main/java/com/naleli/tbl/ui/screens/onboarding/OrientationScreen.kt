package com.naleli.tbl.ui.screens.onboarding

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
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
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.naleli.tbl.ui.components.JourneyFlow
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import com.naleli.tbl.ui.theme.SurfaceWhite

/**
 * The professional orientation between profile creation and Day 1.
 *
 * Deliberately not a carousel of marketing slides: one idea per screen, a
 * visible position in the sequence, and a Back that works — a learner who
 * wants to re-read what a portfolio is for should not have to restart.
 *
 * The visual system is the approved one, unchanged: navy anchor surface,
 * orange for the single forward action, generous spacing, quiet type.
 */
@Composable
fun OrientationScreen(onFinished: () -> Unit) {
    val steps = OrientationContent.steps
    var index by rememberSaveable { mutableIntStateOf(0) }
    val step = steps[index]
    val isLast = index == steps.lastIndex

    // System Back walks the orientation backwards rather than dumping the
    // learner out of it; on the first step it is left alone.
    BackHandler(enabled = index > 0) { index -= 1 }

    Column(
        Modifier
            .fillMaxSize()
            .background(HeroSurface)
            .padding(horizontal = 24.dp, vertical = 20.dp),
    ) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                "${index + 1} of ${steps.size}",
                style = MaterialTheme.typography.labelMedium,
                color = OnHeroSurfaceSoft,
            )
            if (!isLast) {
                TextButton(onClick = { index = steps.lastIndex }) {
                    Text("Skip", color = OnHeroSurfaceSoft)
                }
            }
        }

        Spacer(Modifier.height(12.dp))
        StepDots(count = steps.size, activeIndex = index)

        Column(
            modifier = Modifier
                .weight(1f)
                .verticalScroll(rememberScrollState())
                .padding(top = 32.dp),
        ) {
            Text(step.eyebrow, style = MaterialTheme.typography.labelLarge, color = NibsOrange)
            Spacer(Modifier.height(10.dp))
            Text(
                step.title,
                style = MaterialTheme.typography.headlineMedium,
                color = OnHeroSurface,
                lineHeight = 36.sp,
            )
            Spacer(Modifier.height(16.dp))
            Text(
                step.body,
                style = MaterialTheme.typography.bodyLarge,
                color = OnHeroSurfaceSoft,
                lineHeight = 26.sp,
            )
            Spacer(Modifier.height(24.dp))

            when (step.layout) {
                OrientationLayout.FLOW -> JourneyFlow(steps = step.points, onDark = true)
                OrientationLayout.PRINCIPLE -> Principle(step.points)
                OrientationLayout.PROSE -> Column {
                    step.points.forEach { point -> PointRow(point) }
                }
            }

            if (step.closing.isNotBlank()) {
                Spacer(Modifier.height(24.dp))
                Text(
                    step.closing,
                    style = MaterialTheme.typography.titleMedium,
                    color = OnHeroSurface,
                    lineHeight = 26.sp,
                )
            }
            Spacer(Modifier.height(24.dp))
        }

        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            if (index > 0) {
                TextButton(onClick = { index -= 1 }) { Text("Back", color = OnHeroSurfaceSoft) }
                Spacer(Modifier.width(8.dp))
            }
            Button(
                onClick = { if (isLast) onFinished() else index += 1 },
                modifier = Modifier.weight(1f),
                colors = ButtonDefaults.buttonColors(containerColor = NibsOrange, contentColor = SurfaceWhite),
            ) {
                Text(if (isLast) OrientationContent.FINAL_BUTTON else OrientationContent.CONTINUE_BUTTON)
            }
        }
    }
}

@Composable
private fun StepDots(count: Int, activeIndex: Int) {
    Row(horizontalArrangement = Arrangement.spacedBy(6.dp), modifier = Modifier.fillMaxWidth()) {
        repeat(count) { i ->
            Box(
                Modifier
                    .weight(1f)
                    .height(3.dp)
                    .clip(CircleShape)
                    .background(if (i <= activeIndex) NibsOrange else SurfaceWhite.copy(alpha = 0.22f)),
            )
        }
    }
}

@Composable
private fun PointRow(text: String) {
    Row(Modifier.fillMaxWidth().padding(vertical = 7.dp), verticalAlignment = Alignment.Top) {
        Box(
            Modifier
                .padding(top = 8.dp)
                .size(6.dp)
                .clip(CircleShape)
                .background(NibsOrange),
        )
        Spacer(Modifier.width(12.dp))
        Text(
            text,
            style = MaterialTheme.typography.bodyLarge,
            color = OnHeroSurface,
            lineHeight = 24.sp,
        )
    }
}

/**
 * Knowledge / Practice / Tasks, given the weight of a stated principle
 * rather than three more bullets — this is the sentence the whole method
 * rests on.
 */
@Composable
private fun Principle(lines: List<String>) {
    Column(
        Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(16.dp))
            .background(SurfaceWhite.copy(alpha = 0.06f))
            .padding(20.dp),
    ) {
        lines.forEachIndexed { i, line ->
            Text(
                line,
                style = MaterialTheme.typography.titleMedium,
                // The third line is the one that names what this app is
                // built to do, so it carries the accent.
                color = if (i == lines.lastIndex) NibsOrange else OnHeroSurface,
                fontWeight = if (i == lines.lastIndex) FontWeight.SemiBold else FontWeight.Normal,
                lineHeight = 26.sp,
            )
            if (i != lines.lastIndex) Spacer(Modifier.height(12.dp))
        }
    }
}
