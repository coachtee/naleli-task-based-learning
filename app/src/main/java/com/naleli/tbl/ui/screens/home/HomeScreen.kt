package com.naleli.tbl.ui.screens.home

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
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
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.rememberAppContainer

@Composable
fun HomeScreen(
    onStartTodaysTask: (dayNumber: Int) -> Unit,
    onOpenDay: (dayNumber: Int) -> Unit,
    onOpenPortfolio: () -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: HomeViewModel = viewModel(
        factory = viewModelFactory { initializer { HomeViewModel(container) } },
    )
    val state by viewModel.state.collectAsState()

    if (state.isLoading) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    val profile = state.profile ?: return
    val course = state.course ?: return
    val progress = state.progress ?: return

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text(
            text = "Hi ${profile.firstName},",
            style = MaterialTheme.typography.headlineSmall,
        )
        Text(
            text = course.programmeName,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text(
                text = "DAY ${progress.currentDayNumber} OF ${progress.totalDays}",
                style = MaterialTheme.typography.titleLarge,
            )
            progress.currentStageName?.let {
                Text(it.uppercase(), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            }
            Spacer(Modifier.height(12.dp))
            NaleliProgressBar(progressFraction = progress.overallPercent / 100f)
            Spacer(Modifier.height(4.dp))
            Text(
                "${progress.overallPercent}% complete · ${progress.daysCompleted}/${progress.totalDays} days",
                style = MaterialTheme.typography.labelSmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        state.currentDay?.let { day ->
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("TODAY'S MISSION", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(4.dp))
                Text(day.title, style = MaterialTheme.typography.titleLarge)
                Spacer(Modifier.height(4.dp))
                Text(
                    "${state.incompleteTaskCount} task(s) remaining today",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(12.dp))
                Button(onClick = { onStartTodaysTask(day.dayNumber) }, modifier = Modifier.fillMaxWidth()) {
                    Text("Start Today's Task")
                }
            }
        } ?: NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text(
                "Day ${progress.currentDayNumber} content isn't available in this V1 build yet — Days 1–7 are ready.",
                style = MaterialTheme.typography.bodyMedium,
            )
        }

        state.upcomingDay?.let { upcoming ->
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("UPCOMING", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(4.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    Column {
                        Text("Day ${upcoming.dayNumber}", style = MaterialTheme.typography.titleMedium)
                        Text(upcoming.title, style = MaterialTheme.typography.bodyMedium)
                    }
                    Button(onClick = { onOpenDay(upcoming.dayNumber) }) { Text("Preview") }
                }
            }
        }

        state.recentPortfolioItem?.let { item ->
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("RECENT PORTFOLIO ITEM", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(4.dp))
                Text(item.title, style = MaterialTheme.typography.titleMedium)
                Text(item.skillDemonstrated, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Spacer(Modifier.height(8.dp))
                Button(onClick = onOpenPortfolio) { Text("View Portfolio") }
            }
        }
    }
}
