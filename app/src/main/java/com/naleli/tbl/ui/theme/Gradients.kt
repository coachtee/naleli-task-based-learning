package com.naleli.tbl.ui.theme

import androidx.compose.ui.graphics.Brush

/** Shared surfaces for "hero" moments. Per the NIBS spec the Current
 * Focus card is a solid Deep Navy anchor with white text and an orange
 * CTA — a flat navy, not a gradient. */
object NaleliGradients {
    val missionCard = Brush.linearGradient(listOf(NibsNavy, NibsNavyRaised))
    val heroBackground = Brush.verticalGradient(listOf(NibsNavy, NibsNavyRaised))
}
