package com.naleli.tbl.ui.screens.settings

import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import kotlinx.coroutines.launch
import java.io.File
import java.time.LocalDate

@Composable
fun BackupScreen(onBack: () -> Unit) {
    val container = rememberAppContainer()
    val context = LocalContext.current
    val scope = rememberCoroutineScope()

    var includeEvidenceFiles by rememberSaveable { mutableStateOf(true) }
    var statusMessage by rememberSaveable { mutableStateOf<String?>(null) }
    var showRestoreWarning by rememberSaveable { mutableStateOf(false) }
    var pendingRestoreFile by rememberSaveable { mutableStateOf<File?>(null) }

    val exportLauncher = rememberLauncherForActivityResult(ActivityResultContracts.CreateDocument("application/zip")) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        scope.launch {
            val tempFile = File(context.cacheDir, "naleli_backup_temp.zip")
            container.backupRepository.exportTo(tempFile, includeEvidenceFiles)
            context.contentResolver.openOutputStream(uri)?.use { out -> tempFile.inputStream().use { it.copyTo(out) } }
            tempFile.delete()
            statusMessage = "Backup exported successfully."
        }
    }

    val restoreLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri ->
        if (uri == null) return@rememberLauncherForActivityResult
        scope.launch {
            val tempFile = File(context.cacheDir, "naleli_restore_temp.zip")
            context.contentResolver.openInputStream(uri)?.use { input -> tempFile.outputStream().use { input.copyTo(it) } }
            pendingRestoreFile = tempFile
            showRestoreWarning = true
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        BackHeader(title = "Backup / Export", onBack = onBack)

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("Backup My Learning", style = MaterialTheme.typography.titleMedium)
            Text(
                "Exports your profile, progress, evidence metadata, portfolio and certificates to a ZIP file you choose the location for.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(8.dp))
            Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(checked = includeEvidenceFiles, onCheckedChange = { includeEvidenceFiles = it })
                Text("Include evidence files (recommended)", style = MaterialTheme.typography.bodyMedium)
            }
            Spacer(Modifier.height(8.dp))
            Button(onClick = { exportLauncher.launch("Naleli_Backup_${LocalDate.now()}.zip") }, modifier = Modifier.fillMaxWidth()) {
                Text("Backup My Learning")
            }
        }

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("Restore My Learning", style = MaterialTheme.typography.titleMedium)
            Text(
                "Restoring will overwrite your current learner data on this device. This cannot be undone.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.error,
            )
            Spacer(Modifier.height(8.dp))
            OutlinedButton(onClick = { restoreLauncher.launch(arrayOf("application/zip")) }, modifier = Modifier.fillMaxWidth()) {
                Text("Restore My Learning")
            }
        }

        statusMessage?.let { Text(it, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.primary) }
    }

    if (showRestoreWarning && pendingRestoreFile != null) {
        AlertDialog(
            onDismissRequest = { showRestoreWarning = false },
            title = { Text("Overwrite current data?") },
            text = { Text("Restoring will replace your profile, progress, evidence and portfolio on this device with the backup's contents. This cannot be undone.") },
            confirmButton = {
                Button(onClick = {
                    val file = pendingRestoreFile!!
                    scope.launch {
                        container.backupRepository.restoreFrom(file)
                        file.delete()
                        statusMessage = "Restore complete."
                        showRestoreWarning = false
                    }
                }) { Text("Overwrite and Restore") }
            },
            dismissButton = {
                OutlinedButton(onClick = { showRestoreWarning = false }) { Text("Cancel") }
            },
        )
    }
}
