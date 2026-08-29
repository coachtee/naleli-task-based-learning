package com.naleli.tbl.ui.screens.profile

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material.icons.Icons
import androidx.compose.foundation.layout.RowScope
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.NibsNavy
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft
import com.naleli.tbl.ui.theme.SuccessGreen
import com.naleli.tbl.ui.theme.SurfaceWhite
import com.naleli.tbl.ui.theme.ThemeMode
import kotlinx.coroutines.launch

/**
 * The bottom-nav "Me" tab: the learner's professional profile first —
 * who they are, where they are in the programme, and what they can now
 * claim to be able to do — with the settings menu below it.
 *
 * It used to open on a name and a list of menu rows, which made the one
 * screen about the learner the one screen that said nothing about them.
 * The competencies listed here are the same assessment rows the portfolio
 * reads, so this cannot flatter what the portfolio shows.
 *
 * The old Progress and Certificate screens are intentionally not linked
 * here: they still read the pre-Workspace day/task entities that this
 * rebuild stopped writing to, so they'd show stale or empty data. Rather
 * than rebuild their eligibility/PDF logic against the new Task/Assessment
 * model in this pass, they're left in the codebase (like the QR scanner)
 * but unreachable from the UI until they're rebuilt for real.
 */
@Composable
fun ProfileHubScreen(
    onEditProfile: () -> Unit,
    onOpenPortfolio: () -> Unit,
    onOpenBackup: () -> Unit,
    onOpenHelp: () -> Unit,
    onOpenPrivacy: () -> Unit,
    onDataDeleted: () -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: ProfileHubViewModel = viewModel(factory = viewModelFactory { initializer { ProfileHubViewModel(container) } })
    val state by viewModel.state.collectAsState()
    val scope = rememberCoroutineScope()
    var showDeleteWarning by rememberSaveable { mutableStateOf(false) }
    var showThemePicker by rememberSaveable { mutableStateOf(false) }

    val currentProfile = state.profile
    if (currentProfile == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        // The identity card carries the navy of the Home hero, so the two
        // screens that say "this is you and this is your programme" look
        // like the same system.
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(20.dp))
                .background(NibsNavy)
                .padding(20.dp),
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                Box(
                    modifier = Modifier
                        .size(56.dp)
                        .background(SurfaceWhite.copy(alpha = 0.14f), CircleShape),
                    contentAlignment = Alignment.Center,
                ) {
                    Text(
                        "${currentProfile.firstName.firstOrNull() ?: ' '}${currentProfile.surname.firstOrNull() ?: ' '}",
                        style = MaterialTheme.typography.titleLarge,
                        color = OnHeroSurface,
                    )
                }
                Column(modifier = Modifier.padding(start = 14.dp).weight(1f)) {
                    Text(
                        "${currentProfile.firstName} ${currentProfile.surname}",
                        style = MaterialTheme.typography.titleLarge,
                        color = OnHeroSurface,
                    )
                    Text(
                        state.programmeName.ifBlank { currentProfile.learnerCode },
                        style = MaterialTheme.typography.bodySmall,
                        color = OnHeroSurfaceSoft,
                    )
                    Text(
                        currentProfile.learnerCode,
                        style = MaterialTheme.typography.labelSmall,
                        color = OnHeroSurfaceSoft,
                    )
                }
                OutlinedButton(
                    onClick = onEditProfile,
                    colors = ButtonDefaults.outlinedButtonColors(contentColor = OnHeroSurface),
                ) { Text("Edit") }
            }
            if (state.totalDays > 0) {
                Spacer(Modifier.height(16.dp))
                Text(
                    "Day ${state.dayNumber} of ${state.totalDays}",
                    style = MaterialTheme.typography.labelSmall,
                    color = OnHeroSurfaceSoft,
                )
                Spacer(Modifier.height(6.dp))
                NaleliProgressBar(
                    progressFraction = state.dayNumber / state.totalDays.toFloat(),
                    trackColor = SurfaceWhite.copy(alpha = 0.22f),
                )
            }
        }

        // Three counts, all from real rows — the same three Home shows, so
        // the learner never has to reconcile two versions of their own
        // record.
        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            ProfileStat("COMPETENT", "${state.daysCompetent}", "of ${state.daysTotal} days")
            ProfileStat("SKILLS", "${state.competencies.size}", "of ${state.skillsTotal} demonstrated")
            ProfileStat("EVIDENCE", "${state.evidenceCount}", "items banked")
        }

        SectionLabel("Demonstrated competencies")
        if (state.competencies.isEmpty()) {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("Nothing assessed yet", style = MaterialTheme.typography.titleMedium)
                Spacer(Modifier.height(2.dp))
                Text(
                    "A skill appears here once your evidence for it has been approved — completing a lesson is not enough.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        } else {
            NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onOpenPortfolio)) {
                // Capped at four: this is the summary, and the portfolio is
                // the full record one tap away.
                state.competencies.take(4).forEachIndexed { index, skill ->
                    if (index > 0) Spacer(Modifier.height(8.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Filled.CheckCircle,
                            contentDescription = null,
                            tint = SuccessGreen,
                            modifier = Modifier.size(18.dp),
                        )
                        Text(
                            skill,
                            style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.padding(start = 10.dp),
                        )
                    }
                }
                if (state.competencies.size > 4) {
                    Spacer(Modifier.height(8.dp))
                    Text(
                        "+ ${state.competencies.size - 4} more in your portfolio",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.primary,
                    )
                }
            }
        }

        SectionLabel("Learning")
        MenuRow("My Portfolio", onOpenPortfolio)
        MenuRow("Backup & Export", onOpenBackup)

        SectionLabel("Preferences")
        MenuRow("Appearance · ${container.themePreferences.mode.label()}") { showThemePicker = true }

        SectionLabel("Support")
        MenuRow("Help & Support", onOpenHelp)
        MenuRow("Privacy Notice", onOpenPrivacy)

        SectionLabel("Data", color = MaterialTheme.colorScheme.error)
        NaleliCard(modifier = Modifier.fillMaxWidth().clickable { showDeleteWarning = true }) {
            Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Filled.Delete, contentDescription = null, tint = MaterialTheme.colorScheme.error)
                    Text("Delete My Learning Data", style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.error, modifier = Modifier.padding(start = 12.dp))
                }
            }
        }
    }

    if (showDeleteWarning) {
        AlertDialog(
            onDismissRequest = { showDeleteWarning = false },
            title = { Text("Delete all learner data?") },
            text = { Text("This permanently deletes your profile, progress, evidence, and portfolio from this device. This cannot be undone.") },
            confirmButton = {
                Button(onClick = {
                    scope.launch {
                        container.profileRepository.deleteProfile()
                        container.progressRepository.deleteAll()
                        container.evidenceRepository.deleteAll()
                        container.portfolioRepository.deleteAll()
                        container.certificateRepository.deleteAll()
                        container.workspaceRepository.deleteAll()
                        container.workspacePreferences.resetForNewLearner()
                        showDeleteWarning = false
                        onDataDeleted()
                    }
                }) { Text("Delete Everything") }
            },
            dismissButton = { OutlinedButton(onClick = { showDeleteWarning = false }) { Text("Cancel") } },
        )
    }

    if (showThemePicker) {
        AlertDialog(
            onDismissRequest = { showThemePicker = false },
            title = { Text("Appearance") },
            text = {
                Column {
                    ThemeOption("Light", ThemeMode.LIGHT, container.themePreferences.mode) {
                        container.themePreferences.updateMode(it)
                        showThemePicker = false
                    }
                    ThemeOption("Dark", ThemeMode.DARK, container.themePreferences.mode) {
                        container.themePreferences.updateMode(it)
                        showThemePicker = false
                    }
                    ThemeOption("Match system", ThemeMode.SYSTEM, container.themePreferences.mode) {
                        container.themePreferences.updateMode(it)
                        showThemePicker = false
                    }
                }
            },
            confirmButton = { TextButton(onClick = { showThemePicker = false }) { Text("Close") } },
        )
    }
}

/** One figure of the learner's record. Deliberately the same shape as
 * Home's stat tiles — one visual language for "what you have banked". */
@Composable
private fun RowScope.ProfileStat(label: String, value: String, caption: String) {
    NaleliCard(modifier = Modifier.weight(1f)) {
        Text(label, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.primary)
        Spacer(Modifier.height(2.dp))
        Text(value, style = MaterialTheme.typography.headlineSmall)
        Text(
            caption,
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 2,
        )
    }
}

@Composable
private fun ThemeOption(label: String, mode: ThemeMode, selectedMode: ThemeMode, onSelect: (ThemeMode) -> Unit) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .clickable { onSelect(mode) },
        verticalAlignment = Alignment.CenterVertically,
    ) {
        RadioButton(selected = selectedMode == mode, onClick = { onSelect(mode) })
        Text(label, style = MaterialTheme.typography.bodyMedium)
    }
}

private fun ThemeMode.label(): String = when (this) {
    ThemeMode.LIGHT -> "Light"
    ThemeMode.DARK -> "Dark"
    ThemeMode.SYSTEM -> "System"
}

@Composable
private fun SectionLabel(title: String, color: androidx.compose.ui.graphics.Color = MaterialTheme.colorScheme.primary) {
    Text(
        title.uppercase(),
        style = MaterialTheme.typography.labelLarge,
        color = color,
        modifier = Modifier.padding(top = 10.dp, bottom = 2.dp),
    )
}

@Composable
private fun MenuRow(title: String, onClick: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
