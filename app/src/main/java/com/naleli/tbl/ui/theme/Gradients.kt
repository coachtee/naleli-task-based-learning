package com.naleli.tbl.ui.theme

import androidx.compose.ui.graphics.Brush

/** Shared gradients for "hero" moments (Today's Mission card, splash) — V1.5. */
object NaleliGradients {
    val missionCard = Brush.linearGradient(listOf(NaleliPurple, NaleliPurpleDark))
    val heroBackground = Brush.verticalGradient(listOf(HeroSurface, NaleliNavySurface))
}
