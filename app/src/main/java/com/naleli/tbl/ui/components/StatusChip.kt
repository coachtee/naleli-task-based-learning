package com.naleli.tbl.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.naleli.tbl.data.db.entity.AssessmentStatus
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.ui.theme.ChipShape
import com.naleli.tbl.ui.theme.ErrorRed
import com.naleli.tbl.ui.theme.ErrorRedBg
import com.naleli.tbl.ui.theme.NaleliBlue
import com.naleli.tbl.ui.theme.NaleliBlueLight
import com.naleli.tbl.ui.theme.NaleliNavySoft
import com.naleli.tbl.ui.theme.SuccessGreen
import com.naleli.tbl.ui.theme.SuccessGreenBg
import com.naleli.tbl.ui.theme.SurfaceGrey
import com.naleli.tbl.ui.theme.WarningOrange
import com.naleli.tbl.ui.theme.WarningOrangeBg

data class StatusColors(val foreground: Color, val background: Color)

fun DayStatus.colors(): StatusColors = when (this) {
    DayStatus.NOT_STARTED -> StatusColors(NaleliNavySoft, SurfaceGrey)
    DayStatus.IN_PROGRESS -> StatusColors(NaleliBlue, NaleliBlueLight)
    DayStatus.COMPLETE -> StatusColors(SuccessGreen, SuccessGreenBg)
    DayStatus.NEEDS_REVIEW -> StatusColors(WarningOrange, WarningOrangeBg)
}

fun DayStatus.label(): String = when (this) {
    DayStatus.NOT_STARTED -> "NOT STARTED"
    DayStatus.IN_PROGRESS -> "IN PROGRESS"
    DayStatus.COMPLETE -> "COMPLETE"
    DayStatus.NEEDS_REVIEW -> "NEEDS REVIEW"
}

fun AssessmentStatus.colors(): StatusColors = when (this) {
    AssessmentStatus.NOT_YET_ASSESSED -> StatusColors(NaleliNavySoft, SurfaceGrey)
    AssessmentStatus.COMPETENT -> StatusColors(SuccessGreen, SuccessGreenBg)
    AssessmentStatus.NOT_YET_COMPETENT -> StatusColors(ErrorRed, ErrorRedBg)
    AssessmentStatus.RESUBMIT -> StatusColors(WarningOrange, WarningOrangeBg)
}

fun AssessmentStatus.label(): String = name.replace('_', ' ')

@Composable
fun StatusChip(text: String, colors: StatusColors, modifier: Modifier = Modifier) {
    Text(
        text = text,
        style = MaterialTheme.typography.labelSmall,
        color = colors.foreground,
        modifier = modifier
            .background(colors.background, ChipShape)
            .padding(horizontal = 10.dp, vertical = 4.dp),
    )
}
