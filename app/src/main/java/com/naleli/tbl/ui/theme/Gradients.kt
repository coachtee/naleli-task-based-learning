package com.naleli.tbl.ui.theme

import androidx.compose.ui.graphics.Brush

/** Shared gradients for "hero" moments (Current Focus card, splash). */
object NaleliGradients {
    val missionCard = Brush.linearGradient(listOf(NaleliBlue, NaleliBlueDark))
    val heroBackground = Brush.verticalGradient(listOf(HeroSurface, NaleliNavySurface))
}
