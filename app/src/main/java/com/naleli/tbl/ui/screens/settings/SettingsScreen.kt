package com.naleli.tbl.ui.screens.settings

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import kotlinx.coroutines.launch

@Composable
fun SettingsScreen(
    onOpenBackup: () -> Unit,
    onOpenPrivacy: () -> Unit,
    onOpenHelp: () -> Unit,
    onDataDeleted: () -> Unit,
) {
    val container = rememberAppContainer()
    val scope = rememberCoroutineScope()
    var showDeleteWarning by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Settings", style = MaterialTheme.typography.headlineSmall)

        SettingsRow("Backup / Export", "Backup or restore your learner data", onOpenBackup)
        SettingsRow("Privacy Notice", "What we store and why", onOpenPrivacy)
        SettingsRow("Help", "About Naleli Task-Based Learning", onOpenHelp)

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("Delete My Learning Data", style = MaterialTheme.typography.titleMedium, color = MaterialTheme.colorScheme.error)
            Text(
                "Permanently deletes your profile, progress, evidence and portfolio from this device. This cannot be undone.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            androidx.compose.foundation.layout.Spacer(Modifier.padding(4.dp))
            OutlinedButton(onClick = { showDeleteWarning = true }) { Text("Delete My Learning Data") }
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
                        showDeleteWarning = false
                        onDataDeleted()
                    }
                }) { Text("Delete Everything") }
            },
            dismissButton = { OutlinedButton(onClick = { showDeleteWarning = false }) { Text("Cancel") } },
        )
    }
}

@Composable
private fun SettingsRow(title: String, subtitle: String, onClick: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Text(title, style = MaterialTheme.typography.titleMedium)
        Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}
