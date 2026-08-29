package com.naleli.tbl.ui.components

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color

/** Shared back-navigation row for drill-down screens (Day, Task, Add
 * Evidence) that sit below the bottom-nav tabs — matches the reference
 * mockup's inline back-arrow-plus-title header. */
@Composable
fun BackHeader(
    title: String? = null,
    onBack: () -> Unit,
    /** Set when the header sits on the navy hero surface, where the default
     * on-surface ink would be near-invisible. */
    tint: Color = MaterialTheme.colorScheme.onSurface,
    trailing: @Composable () -> Unit = {},
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.SpaceBetween,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            IconButton(onClick = onBack) {
                Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back", tint = tint)
            }
            title?.let { Text(it, style = MaterialTheme.typography.titleMedium, color = tint) }
        }
        trailing()
    }
}
