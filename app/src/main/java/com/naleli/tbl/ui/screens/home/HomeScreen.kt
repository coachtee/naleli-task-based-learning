package com.naleli.tbl.ui.screens.home

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.NotificationsNone
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.CourseDay
import com.naleli.tbl.ui.components.CircularProgressLabel
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NaleliGradients
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft

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

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(HeroSurface),
    ) {
        if (state.isLoading) {
            Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
                CircularProgressIndicator(color = OnHeroSurface)
            }
            return@Box
        }

        val profile = state.profile ?: return@Box
        val course = state.course ?: return@Box
        val progress = state.progress ?: return@Box

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(horizontal = 20.dp, vertical = 16.dp),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text("Hello, ${profile.firstName}", style = MaterialTheme.typography.headlineSmall, color = OnHeroSurface)
                    Text(course.programmeName, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurfaceSoft)
                }
                Icon(Icons.Filled.NotificationsNone, contentDescription = "Notifications", tint = OnHeroSurface)
            }

            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    Column {
                        Text("YOUR PROGRESS", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                        Text("Day ${progress.currentDayNumber} of ${progress.totalDays}", style = MaterialTheme.typography.titleLarge)
                        progress.currentStageName?.let {
                            Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                    }
                    CircularProgressLabel(percent = progress.overallPercent, size = 64.dp)
                }
            }

            state.currentDay?.let { day ->
                TodaysMissionCard(day = day, incompleteTaskCount = state.incompleteTaskCount, onStart = { onStartTodaysTask(day.dayNumber) })
            } ?: NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(
                    "Day ${progress.currentDayNumber} content isn't available in this build yet — Days 1–7 are ready.",
                    style = MaterialTheme.typography.bodyMedium,
                )
            }

            state.upcomingDay?.let { upcoming ->
                Text("UP NEXT", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                        Column {
                            Text("Day ${upcoming.dayNumber}", style = MaterialTheme.typography.titleMedium)
                            Text(upcoming.title, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        }
                        Text("Preview", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                    }
                }
            }

            state.recentPortfolioItem?.let { item ->
                Text("RECENT WORK", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text(item.title, style = MaterialTheme.typography.titleMedium)
                    Text(item.skillDemonstrated, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Spacer(Modifier.height(8.dp))
                    Button(onClick = onOpenPortfolio) { Text("View Portfolio") }
                }
            }
            Spacer(Modifier.height(8.dp))
        }
    }
}

@Composable
private fun TodaysMissionCard(day: CourseDay, incompleteTaskCount: Int, onStart: () -> Unit) {
    val totalMinutes = day.tasks.sumOf { task ->
        Regex("""\d+""").find(task.estimatedTime)?.value?.toIntOrNull() ?: 0
    }
    val evidenceRequiredCount = day.tasks.count { it.evidenceRequired }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(NaleliGradients.missionCard)
            .padding(20.dp),
    ) {
        Column {
            Text("TODAY'S MISSION", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
            Spacer(Modifier.height(4.dp))
            Text(day.title, style = MaterialTheme.typography.titleLarge, color = OnHeroSurface)
            Spacer(Modifier.height(10.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                MissionStat(label = "Estimated time", value = if (totalMinutes > 0) "~$totalMinutes min" else day.tasks.firstOrNull()?.estimatedTime.orEmpty())
                MissionStat(label = "Tasks", value = day.tasks.size.toString())
                MissionStat(label = "Evidence", value = "$evidenceRequiredCount required")
            }
            Spacer(Modifier.height(16.dp))
            Button(
                onClick = onStart,
                modifier = Modifier.fillMaxWidth(),
                colors = ButtonDefaults.buttonColors(containerColor = OnHeroSurface, contentColor = MaterialTheme.colorScheme.primary),
            ) {
                Text(if (incompleteTaskCount > 0) "Start Today's Task" else "Review Today's Work")
            }
        }
    }
}

@Composable
private fun MissionStat(label: String, value: String) {
    Column {
        Text(value, style = MaterialTheme.typography.titleMedium, color = OnHeroSurface)
        Text(label, style = MaterialTheme.typography.labelSmall, color = OnHeroSurfaceSoft)
    }
}
