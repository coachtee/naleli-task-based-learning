package com.naleli.tbl.ui.screens.profile

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer

/**
 * The bottom-nav "Profile" tab: profile summary plus the secondary
 * destinations the brief lists alongside the five main tabs — Progress,
 * Certificate, Help, Settings, Backup/Export (brief §6).
 */
@Composable
fun ProfileHubScreen(
    onEditProfile: () -> Unit,
    onOpenProgress: () -> Unit,
    onOpenCertificate: () -> Unit,
    onOpenSettings: () -> Unit,
    onOpenHelp: () -> Unit,
    onOpenBackup: () -> Unit,
) {
    val container = rememberAppContainer()
    val profile by container.profileRepository.observeProfile().collectAsState(initial = null)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Text("Profile", style = MaterialTheme.typography.headlineSmall)

        val currentProfile = profile
        if (currentProfile == null) {
            CircularProgressIndicator()
        } else {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("${currentProfile.firstName} ${currentProfile.surname}", style = MaterialTheme.typography.titleLarge)
                Text(currentProfile.learnerCode, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                currentProfile.studentNumber?.let { Text("Student number: $it", style = MaterialTheme.typography.bodyMedium) }
                currentProfile.email?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
                androidx.compose.foundation.layout.Spacer(Modifier.padding(4.dp))
                OutlinedButton(onClick = onEditProfile) { Text("Edit Profile") }
            }
        }

        MenuRow("Progress", "Your real completion progress", onOpenProgress)
        MenuRow("Certificate", "Generate your certificate once eligible", onOpenCertificate)
        MenuRow("Backup / Export", "Back up or restore your learner data", onOpenBackup)
        MenuRow("Help", "About this app", onOpenHelp)
        MenuRow("Settings", "Privacy notice and data controls", onOpenSettings)
    }
}

@Composable
private fun MenuRow(title: String, subtitle: String, onClick: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
