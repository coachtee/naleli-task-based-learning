package com.naleli.tbl.ui.screens.mylearning

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Circle
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.RadioButtonUnchecked
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.FilterChip
import androidx.compose.material3.FilterChipDefaults
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.ui.components.NaleliCard
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

    var selectedFilter by remember { mutableIntStateOf(0) }
    val filterLabels = listOf("All Days") + state.sections.map { it.stage.name }
    val visibleSections = if (selectedFilter == 0) state.sections else state.sections.filter { it.stage.name == filterLabels[selectedFilter] }

    Column(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.padding(20.dp)) {
            Text("My Learning", style = MaterialTheme.typography.headlineSmall)
            Text(
                "The full 90-day Digital Foundation journey",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(12.dp))
            LazyRow(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                items(filterLabels.size) { index ->
                    FilterChip(
                        selected = selectedFilter == index,
                        onClick = { selectedFilter = index },
                        label = { Text(filterLabels[index]) },
                        colors = FilterChipDefaults.filterChipColors(
                            selectedContainerColor = MaterialTheme.colorScheme.primary,
                            selectedLabelColor = MaterialTheme.colorScheme.onPrimary,
                        ),
                    )
                }
            }
        }

        LazyColumn(
            modifier = Modifier.fillMaxSize(),
            contentPadding = PaddingValues(horizontal = 20.dp, vertical = 4.dp),
            verticalArrangement = Arrangement.spacedBy(6.dp),
        ) {
            visibleSections.forEach { section ->
                item {
                    Spacer(Modifier.height(10.dp))
                    Text(
                        "${section.stage.name.uppercase()} · DAYS ${section.stage.dayStart}–${section.stage.dayEnd}",
                        style = MaterialTheme.typography.labelLarge,
                        color = MaterialTheme.colorScheme.primary,
                    )
                    Spacer(Modifier.height(4.dp))
                }
                items(section.days) { dayItem ->
                    DayRow(dayItem = dayItem, onClick = { if (dayItem.isContentAvailable && !dayItem.isLocked) onOpenDay(dayItem.dayNumber) })
                }
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
        contentPadding = androidx.compose.foundation.layout.PaddingValues(horizontal = 14.dp, vertical = 10.dp),
    ) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            StatusIcon(dayItem)
            Column(modifier = Modifier.weight(1f).padding(horizontal = 12.dp)) {
                Text("Day ${dayItem.dayNumber} · ${dayItem.title ?: "Coming soon"}", style = MaterialTheme.typography.titleMedium)
                if (dayItem.isContentAvailable && dayItem.taskCompleteFraction > 0f) {
                    Text(
                        "${(dayItem.taskCompleteFraction * 100).toInt()}% of tasks complete",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                }
            }
            if (dayItem.isLocked) {
                Icon(Icons.Filled.Lock, contentDescription = "Locked", modifier = Modifier.size(18.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun StatusIcon(dayItem: DayListItem) {
    val (icon, tint) = when {
        !dayItem.isContentAvailable -> Icons.Filled.RadioButtonUnchecked to MaterialTheme.colorScheme.onSurfaceVariant
        dayItem.status == DayStatus.COMPLETE -> Icons.Filled.CheckCircle to MaterialTheme.colorScheme.primary
        dayItem.status == DayStatus.IN_PROGRESS -> Icons.Filled.Circle to MaterialTheme.colorScheme.primary
        dayItem.status == DayStatus.NEEDS_REVIEW -> Icons.Filled.Circle to MaterialTheme.colorScheme.error
        else -> Icons.Filled.RadioButtonUnchecked to MaterialTheme.colorScheme.onSurfaceVariant
    }
    Icon(icon, contentDescription = dayItem.status.name, tint = tint, modifier = Modifier.size(22.dp))
}
