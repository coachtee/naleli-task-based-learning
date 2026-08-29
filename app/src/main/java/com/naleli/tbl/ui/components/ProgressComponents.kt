package com.naleli.tbl.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

/**
 * A plain flat track + fill, not Material3's LinearProgressIndicator: the
 * M3 component draws a "stop indicator" dot at the end of the track by
 * default, which reads as a stray dot floating on an empty bar at 0%
 * progress rather than a clean empty track.
 */
@Composable
fun NaleliProgressBar(
    progressFraction: Float,
    modifier: Modifier = Modifier,
    // The track must be given explicitly on dark surfaces. The default
    // surfaceVariant is a near-white slate in the light theme, so an empty
    // bar sitting on the navy Current Focus card rendered as a solid pale
    // bar — reading as 100% complete when it was 0 of 3.
    trackColor: Color = MaterialTheme.colorScheme.surfaceVariant,
) {
    val fraction = progressFraction.coerceIn(0f, 1f)
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(6.dp)
            .clip(RoundedCornerShape(50))
            .background(trackColor),
    ) {
        Box(
            modifier = Modifier
                .fillMaxHeight()
                .fillMaxWidth(fraction)
                .clip(RoundedCornerShape(50))
                .background(MaterialTheme.colorScheme.primary),
        )
    }
}
