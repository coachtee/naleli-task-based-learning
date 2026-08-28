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
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.content.WorkspaceMockContent
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NaleliGradients
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft

/**
 * Home answers exactly one question — what should I do right now? One
 * dominant Current Focus card (the task, not a stats dashboard), then a
 * short, real Today list. No dead ends: even "caught up" points somewhere.
 */
@Composable
fun HomeScreen(onOpenTask: (taskId: String) -> Unit, onOpenPortfolio: () -> Unit) {
    val container = rememberAppContainer()
    val viewModel: HomeViewModel = viewModel(factory = viewModelFactory { initializer { HomeViewModel(container) } })
    val state by viewModel.state.collectAsState()

    Box(modifier = Modifier.fillMaxSize().background(HeroSurface)) {
        if (state.isLoading) {
            Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
                CircularProgressIndicator(color = OnHeroSurface)
            }
            return@Box
        }

        val profile = state.profile ?: return@Box
        val course = state.course ?: return@Box

        Column(
            modifier = Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp, vertical = 16.dp),
            verticalArrangement = Arrangement.spacedBy(20.dp),
        ) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column(modifier = Modifier.weight(1f)) {
                    Text(
                        "Good ${greetingWord()}, ${profile.firstName}",
                        style = MaterialTheme.typography.headlineSmall,
                        color = OnHeroSurface,
                        maxLines = 1,
                        overflow = TextOverflow.Ellipsis,
                    )
                    Text(
                        "Day ${state.dayNumber} of ${course.totalDays}",
                        style = MaterialTheme.typography.bodyMedium,
                        color = OnHeroSurfaceSoft,
                    )
                }
                Icon(Icons.Filled.NotificationsNone, contentDescription = "Notifications", tint = OnHeroSurface)
            }

            state.priorityTask?.let { task ->
                val workstreamName = WorkspaceMockContent.workstreamFor(task.taskId)?.name ?: course.programmeName
                Box(
                    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(NaleliGradients.missionCard).padding(20.dp),
                ) {
                    Column {
                        Text("CURRENT FOCUS", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
                        Spacer(Modifier.height(2.dp))
                        Text(workstreamName, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurfaceSoft, maxLines = 1, overflow = TextOverflow.Ellipsis)
                        Text(task.title, style = MaterialTheme.typography.titleLarge, color = OnHeroSurface, maxLines = 2, overflow = TextOverflow.Ellipsis)
                        Spacer(Modifier.height(10.dp))
                        Text(stepsLabel(task.tier.name, state.priorityStepsDone, state.priorityStepsTotal), style = MaterialTheme.typography.labelSmall, color = OnHeroSurfaceSoft)
                        Spacer(Modifier.height(6.dp))
                        NaleliProgressBar(progressFraction = if (state.priorityStepsTotal == 0) 0f else state.priorityStepsDone / state.priorityStepsTotal.toFloat())
                        Spacer(Modifier.height(16.dp))
                        Button(
                            onClick = { onOpenTask(task.taskId) },
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(containerColor = OnHeroSurface, contentColor = MaterialTheme.colorScheme.primary),
                        ) {
                            Text(if (state.priorityTaskState == TaskProgressState.NOT_STARTED) "Open Task" else "Continue Work")
                        }
                    }
                }
            } ?: NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("You're caught up", style = MaterialTheme.typography.titleLarge)
                Spacer(Modifier.height(4.dp))
                Text(
                    "Everything unlocked so far is done. Check your Portfolio to see what you've built, or come back once new work unlocks.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(12.dp))
                Button(onClick = onOpenPortfolio, modifier = Modifier.fillMaxWidth()) { Text("View Portfolio") }
            }

            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text("TODAY", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)

                state.priorityHint?.let { hint ->
                    TodayRow(label = "Priority", body = hint)
                }
                TodayRow(label = "Upcoming milestone", body = state.nextMilestoneTitle)
                state.recentAchievement?.let { achievement ->
                    TodayRow(label = "Recent achievement", body = achievement.title, icon = true)
                }
            }
            Spacer(Modifier.height(4.dp))
        }
    }
}

@Composable
private fun TodayRow(label: String, body: String, icon: Boolean = false) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 10.dp), verticalAlignment = Alignment.Top) {
        if (icon) {
            Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = com.naleli.tbl.ui.theme.SuccessGreen, modifier = Modifier.padding(top = 2.dp))
            Spacer(Modifier.width(10.dp))
        }
        Column(modifier = Modifier.weight(1f)) {
            Text(label.uppercase(), style = MaterialTheme.typography.labelSmall, color = OnHeroSurfaceSoft)
            Text(body, style = MaterialTheme.typography.bodyMedium, color = OnHeroSurface, maxLines = 2, overflow = TextOverflow.Ellipsis)
        }
    }
}

private fun stepsLabel(tierName: String, done: Int, total: Int): String {
    val tier = tierName.lowercase().replaceFirstChar { it.uppercase() }
    return if (total == 0) tier else "$tier · Step $done of $total complete"
}

private fun greetingWord(): String {
    val hour = java.time.LocalTime.now().hour
    return when {
        hour < 12 -> "morning"
        hour < 17 -> "afternoon"
        else -> "evening"
    }
}
