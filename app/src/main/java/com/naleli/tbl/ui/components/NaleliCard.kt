package com.naleli.tbl.ui.components

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Card
import androidx.compose.material3.CardDefaults
import androidx.compose.material3.MaterialTheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp

/**
 * The shared card surface: white, rounded, with a single hairline border
 * per the NIBS spec ("1px subtle borders").
 *
 * Deliberately no shadow elevation. A 1px border and a drop shadow
 * together read as two competing edges on the soft canvas — the border
 * alone gives the crisp, scannable separation the spec asks for, and the
 * canvas being off-white rather than stark white is what supplies the
 * depth a shadow would otherwise carry.
 */
@Composable
fun NaleliCard(
    modifier: Modifier = Modifier,
    contentPadding: PaddingValues = PaddingValues(16.dp),
    content: @Composable () -> Unit,
) {
    Card(
        modifier = modifier,
        shape = MaterialTheme.shapes.medium,
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 0.dp),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outline),
    ) {
        Column(modifier = Modifier.padding(contentPadding)) {
            content()
        }
    }
}
