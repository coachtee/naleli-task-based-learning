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
import com.naleli.tbl.domain.ProjectHealth
import com.naleli.tbl.domain.TaskProgressState
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NaleliGradients
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import java.text.DateFormat
import java.util.Date

/**
 * Home answers one question: what do I need to do next in my project? Not a
 * dashboard of stats — a project-health strip, today's workspace snapshot,
 * and exactly one priority task (brief: "What am I working on today?", not
 * "what lesson am I watching?").
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
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                Column {
                    Text("Good ${greetingWord()}, ${profile.firstName}", style = MaterialTheme.typography.headlineSmall, color = OnHeroSurface)
                    Text("${course.programmeName} · ${course.credential.issuingBody}", style = MaterialTheme.typography.bodyMedium, color = OnHeroSurfaceSoft)
                }
                Icon(Icons.Filled.NotificationsNone, contentDescription = "Notifications", tint = OnHeroSurface)
            }

            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("CURRENT PROJECT", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Text(course.projectTitle, style = MaterialTheme.typography.titleLarge)
                NaleliProgressBar(progressFraction = state.progressPercent / 100f)
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("Day ${state.dayNumber} of ${course.totalDays}", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    Text("${state.progressPercent}%", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Spacer(Modifier.height(11.dp))
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                    HealthPill(state.health)
                    Text("${state.daysRemaining} days left", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Spacer(Modifier.height(2.dp))
                Text(
                    "Next milestone: ${state.nextMilestoneTitle}",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }

            Text("YOUR WORKSPACE TODAY", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                WorkspaceStat("To Do", state.statBoard.toDo, Modifier.weight(1f))
                WorkspaceStat("In Progress", state.statBoard.inProgress, Modifier.weight(1f))
                WorkspaceStat("Submitted", state.statBoard.submitted, Modifier.weight(1f))
                WorkspaceStat("Competent", state.statBoard.competent, Modifier.weight(1f))
            }

            state.priorityTask?.let { task ->
                Box(
                    modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(20.dp)).background(NaleliGradients.missionCard).padding(20.dp),
                ) {
                    Column {
                        Text("TODAY'S PRIORITY", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
                        Spacer(Modifier.height(4.dp))
                        Text(task.title, style = MaterialTheme.typography.titleLarge, color = OnHeroSurface)
                        Spacer(Modifier.height(10.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                            MissionStat("Type", task.tier.name.lowercase().replaceFirstChar { it.uppercase() })
                            MissionStat("Time", "~${task.estimatedMinutes} min")
                            MissionStat("Status", task.priorityStatusLabel(state.priorityTaskState))
                        }
                        Spacer(Modifier.height(16.dp))
                        Button(
                            onClick = { onOpenTask(task.taskId) },
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(containerColor = OnHeroSurface, contentColor = MaterialTheme.colorScheme.primary),
                        ) {
                            Text(if (state.priorityTaskState == TaskProgressState.NOT_STARTED) "Open Task" else "Continue Task")
                        }
                    }
                }
            } ?: NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("You're caught up on everything available right now.", style = MaterialTheme.typography.bodyMedium)
            }

            if (state.recentActivity.isNotEmpty()) {
                Text("RECENT ACTIVITY", style = MaterialTheme.typography.labelLarge, color = OnHeroSurfaceSoft)
                state.recentActivity.forEach { event ->
                    NaleliCard(modifier = Modifier.fillMaxWidth()) {
                        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) {
                            Column {
                                Text(event.title, style = MaterialTheme.typography.titleMedium)
                                Text(event.subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                            Text(
                                DateFormat.getDateInstance(DateFormat.SHORT).format(Date(event.timestamp)),
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
                Button(onClick = onOpenPortfolio, modifier = Modifier.fillMaxWidth()) { Text("View Portfolio") }
            }
            Spacer(Modifier.height(8.dp))
        }
    }
}

@Composable
private fun HealthPill(health: ProjectHealth) {
    val (label, color) = when (health) {
        ProjectHealth.ON_TRACK -> "On Track" to MaterialTheme.colorScheme.primary
        ProjectHealth.ATTENTION_REQUIRED -> "Attention Required" to com.naleli.tbl.ui.theme.WarningOrange
        ProjectHealth.BEHIND_SCHEDULE -> "Behind Schedule" to MaterialTheme.colorScheme.error
    }
    Text(label, style = MaterialTheme.typography.labelMedium, color = color)
}

@Composable
private fun WorkspaceStat(label: String, count: Int, modifier: Modifier = Modifier) {
    NaleliCard(modifier = modifier, contentPadding = androidx.compose.foundation.layout.PaddingValues(10.dp)) {
        Column(horizontalAlignment = Alignment.CenterHorizontally, modifier = Modifier.fillMaxWidth()) {
            Text(count.toString(), style = MaterialTheme.typography.titleLarge)
            Text(label.uppercase(), style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
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

private fun com.naleli.tbl.data.content.WorkTask.priorityStatusLabel(state: TaskProgressState): String = when (state) {
    TaskProgressState.NOT_STARTED -> "Ready"
    TaskProgressState.IN_PROGRESS -> "In Progress"
    TaskProgressState.SUBMITTED -> "Submitted"
    TaskProgressState.NEEDS_REVISION -> "Needs Revision"
    TaskProgressState.COMPETENT -> "Competent"
}

private fun greetingWord(): String {
    val hour = java.time.LocalTime.now().hour
    return when {
        hour < 12 -> "morning"
        hour < 17 -> "afternoon"
        else -> "evening"
    }
}
