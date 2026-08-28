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
import androidx.compose.material.icons.filled.ChevronRight
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
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
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.NaleliPurpleLight
import com.naleli.tbl.ui.theme.ThemeMode
import kotlinx.coroutines.launch

/**
 * The bottom-nav "Profile" tab: a compact learner identity header plus a
 * flat menu list (brief V1.5 §16) — Progress, Certificate, My Portfolio,
 * Backup & Export, Help, Privacy, Delete My Data — instead of a stack of
 * heavy cards.
 */
@Composable
fun ProfileHubScreen(
    onEditProfile: () -> Unit,
    onOpenProgress: () -> Unit,
    onOpenCertificate: () -> Unit,
    onOpenPortfolio: () -> Unit,
    onOpenBackup: () -> Unit,
    onOpenHelp: () -> Unit,
    onOpenPrivacy: () -> Unit,
    onDataDeleted: () -> Unit,
) {
    val container = rememberAppContainer()
    val profile by container.profileRepository.observeProfile().collectAsState(initial = null)
    val scope = rememberCoroutineScope()
    var showDeleteWarning by remember { mutableStateOf(false) }
    var showThemePicker by remember { mutableStateOf(false) }

    val currentProfile = profile
    if (currentProfile == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                modifier = Modifier
                    .size(56.dp)
                    .background(NaleliPurpleLight, CircleShape),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    "${currentProfile.firstName.firstOrNull() ?: ' '}${currentProfile.surname.firstOrNull() ?: ' '}",
                    style = MaterialTheme.typography.titleLarge,
                    color = MaterialTheme.colorScheme.primary,
                )
            }
            Column(modifier = Modifier.padding(start = 14.dp).weight(1f)) {
                Text("${currentProfile.firstName} ${currentProfile.surname}", style = MaterialTheme.typography.titleLarge)
                Text(currentProfile.learnerCode, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            OutlinedButton(onClick = onEditProfile) { Text("Edit") }
        }
        Spacer(Modifier.height(4.dp))

        MenuRow("My Progress", onOpenProgress)
        MenuRow("Certificate", onOpenCertificate)
        MenuRow("My Portfolio", onOpenPortfolio)
        MenuRow("Appearance · ${container.themePreferences.mode.label()}") { showThemePicker = true }
        MenuRow("Backup & Export", onOpenBackup)
        MenuRow("Help & Support", onOpenHelp)
        MenuRow("Privacy Notice", onOpenPrivacy)

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
private fun MenuRow(title: String, onClick: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            Icon(Icons.Filled.ChevronRight, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
