package com.naleli.tbl.ui.screens.mylearning

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.colors
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.components.StatusChip
import com.naleli.tbl.ui.rememberAppContainer

@Composable
fun MyLearningScreen(onOpenDay: (dayNumber: Int) -> Unit) {
    val container = rememberAppContainer()
    val viewModel: MyLearningViewModel = viewModel(
        factory = viewModelFactory { initializer { MyLearningViewModel(container) } },
    )
    val state by viewModel.state.collectAsState()

    if (state.isLoading) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = androidx.compose.foundation.layout.PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Text("My Learning", style = MaterialTheme.typography.headlineSmall)
            Spacer(Modifier.height(4.dp))
            Text(
                "The full 90-day Digital Foundation journey",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        state.sections.forEach { section ->
            item {
                Spacer(Modifier.height(8.dp))
                Text(
                    "STAGE ${section.stage.stageNumber} · ${section.stage.name.uppercase()}",
                    style = MaterialTheme.typography.labelLarge,
                    color = MaterialTheme.colorScheme.primary,
                )
                Text(
                    "Days ${section.stage.dayStart}–${section.stage.dayEnd}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
            items(section.days) { dayItem ->
                DayRow(dayItem = dayItem, onClick = { if (dayItem.isContentAvailable && !dayItem.isLocked) onOpenDay(dayItem.dayNumber) })
            }
        }
    }
}

@Composable
private fun DayRow(dayItem: DayListItem, onClick: () -> Unit) {
    NaleliCard(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(enabled = dayItem.isContentAvailable && !dayItem.isLocked, onClick = onClick),
    ) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Column(modifier = Modifier.weight(1f)) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Text("Day ${dayItem.dayNumber}", style = MaterialTheme.typography.titleMedium)
                    if (dayItem.isLocked) {
                        Spacer(Modifier.width(6.dp))
                        Icon(Icons.Filled.Lock, contentDescription = "Locked", modifier = Modifier.size(14.dp))
                    }
                }
                Text(
                    text = dayItem.title ?: "Content coming in a future update",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                if (dayItem.isContentAvailable && dayItem.taskCompleteFraction > 0f) {
                    Text(
                        "${(dayItem.taskCompleteFraction * 100).toInt()}% of tasks complete",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            if (dayItem.isContentAvailable) {
                StatusChip(text = dayItem.status.label(), colors = dayItem.status.colors())
            } else {
                StatusChip(text = "COMING SOON", colors = dayItem.status.colors())
            }
        }
    }
}
