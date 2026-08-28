package com.naleli.tbl.ui.screens.onboarding

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.unit.dp
import com.naleli.tbl.data.preferences.StorageChoice
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer

/**
 * Day 1-2 onboarding (brief §7): where should evidence live. This Device is
 * the real, working default — the app is fully offline-first without a
 * cloud account. Google Drive / OneDrive are an upgrade a learner can
 * connect later, never a requirement to get started.
 */
@Composable
fun PortfolioSetupScreen(onDone: () -> Unit) {
    val container = rememberAppContainer()
    var selected by remember { mutableStateOf(StorageChoice.THIS_DEVICE) }

    Column(
        modifier = Modifier.fillMaxSize().padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Text("Set Up Your Workspace", style = MaterialTheme.typography.headlineSmall)
        Text(
            "Everything you do here builds your professional evidence.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        Text("Where would you like to keep your professional evidence?", style = MaterialTheme.typography.titleSmall)

        StorageOption(
            title = "This Device",
            subtitle = "Recommended to start — save and organise files on this device. Connect cloud storage later if you want to.",
            selected = selected == StorageChoice.THIS_DEVICE,
        ) { selected = StorageChoice.THIS_DEVICE }
        StorageOption(
            title = "Google Drive",
            subtitle = "Connect an existing Google account.",
            selected = selected == StorageChoice.GOOGLE_DRIVE,
        ) { selected = StorageChoice.GOOGLE_DRIVE }
        StorageOption(
            title = "Microsoft OneDrive",
            subtitle = "Connect an existing Microsoft account.",
            selected = selected == StorageChoice.ONEDRIVE,
        ) { selected = StorageChoice.ONEDRIVE }

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("YOUR STRUCTURE", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            Spacer(Modifier.height(6.dp))
            Text(
                "NTBL Portfolio\n" +
                    "├── 01 Digital Foundations\n" +
                    "├── 02 Computer Skills\n" +
                    "├── 03 Microsoft Word\n" +
                    "├── 04 Microsoft Excel\n" +
                    "├── 05 Microsoft PowerPoint\n" +
                    "├── 06 Communication\n" +
                    "├── 07 Workplace Tasks\n" +
                    "└── 08 Final Project",
                style = MaterialTheme.typography.bodySmall.copy(fontFamily = FontFamily.Monospace),
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        Spacer(Modifier.weight(1f))
        Button(
            onClick = {
                container.workspacePreferences.setStorageChoice(selected)
                container.workspacePreferences.markPortfolioSetupComplete()
                onDone()
            },
            modifier = Modifier.fillMaxWidth(),
        ) { Text("Create My Workspace") }
    }
}

@Composable
private fun StorageOption(title: String, subtitle: String, selected: Boolean, onSelect: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onSelect)) {
        Row(verticalAlignment = Alignment.Top) {
            RadioButton(selected = selected, onClick = onSelect)
            Column(modifier = Modifier.padding(top = 12.dp)) {
                Text(title, style = MaterialTheme.typography.titleMedium)
                Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}
