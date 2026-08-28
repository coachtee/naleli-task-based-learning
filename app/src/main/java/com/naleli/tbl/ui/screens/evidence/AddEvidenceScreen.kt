package com.naleli.tbl.ui.screens.evidence

import android.content.ActivityNotFoundException
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CameraAlt
import androidx.compose.material.icons.filled.CloudUpload
import androidx.compose.material.icons.filled.Delete
import androidx.compose.material.icons.filled.DocumentScanner
import androidx.compose.material.icons.filled.Image
import androidx.compose.material.icons.filled.InsertDriveFile
import androidx.compose.material.icons.filled.Laptop
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.util.createCameraCaptureUri
import com.naleli.tbl.util.rememberCameraPermissionAction

/**
 * "Prove Your Work" — evidence sources presented as clear, purposeful
 * choices, not one plain file picker button. Generic over any taskId, so
 * both Naleli Workspace's Task Workspace and (previously) the day-based
 * task engine can reuse it.
 *
 * Both camera-backed sources request runtime CAMERA permission first and
 * catch ActivityNotFoundException — this is what was crashing "Take Photo"
 * before (V1.5.1 §2): the intent was launched without ever checking it.
 */
@Composable
fun AddEvidenceScreen(taskId: String, taskTitle: String, onBack: () -> Unit, onDone: () -> Unit) {
    val container = rememberAppContainer()
    val viewModel: AddEvidenceViewModel = viewModel(
        factory = viewModelFactory { initializer { AddEvidenceViewModel(container, taskId) } },
    )
    val evidence by viewModel.evidence.collectAsState()
    val context = LocalContext.current
    // Saveable, not just remember: a rotation between launching the camera
    // intent and its result coming back would otherwise drop the URI the
    // callback needs, silently losing the photo.
    var pendingCameraUri by rememberSaveable { mutableStateOf<Uri?>(null) }
    var showComputerStub by rememberSaveable { mutableStateOf(false) }
    var errorMessage by rememberSaveable { mutableStateOf<String?>(null) }

    val openDocumentLauncher = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri: Uri? ->
        if (uri != null) viewModel.attach(uri, null)
    }
    val pickImageLauncher = rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri: Uri? ->
        if (uri != null) viewModel.attach(uri, null)
    }
    val takePictureLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success: Boolean ->
        val uri = pendingCameraUri
        if (success && uri != null) viewModel.attach(uri, null)
        if (!success) errorMessage = "No photo was captured."
        pendingCameraUri = null
    }
    val scanWorksheetLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success: Boolean ->
        val uri = pendingCameraUri
        if (success && uri != null) viewModel.attach(uri, "Worksheet (scanned)")
        if (!success) errorMessage = "No worksheet photo was captured."
        pendingCameraUri = null
    }

    fun launchCamera(taggedAs: String?) {
        try {
            val uri = createCameraCaptureUri(context)
            pendingCameraUri = uri
            errorMessage = null
            if (taggedAs == null) takePictureLauncher.launch(uri) else scanWorksheetLauncher.launch(uri)
        } catch (e: ActivityNotFoundException) {
            errorMessage = "No camera app is available on this device."
        } catch (e: IllegalArgumentException) {
            errorMessage = "Couldn't prepare a location for the photo. Please try again."
        }
    }

    val requestCameraForPhoto = rememberCameraPermissionAction(
        onGranted = { launchCamera(taggedAs = null) },
        onDenied = { errorMessage = "Camera permission is needed to take a photo." },
    )
    val requestCameraForScan = rememberCameraPermissionAction(
        onGranted = { launchCamera(taggedAs = "Worksheet (scanned)") },
        onDenied = { errorMessage = "Camera permission is needed to scan a worksheet." },
    )

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            Text("Prove Your Work", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Add evidence for: $taskTitle",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            errorMessage?.let {
                Spacer(Modifier.height(8.dp))
                Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
            }
        }

        item {
            EvidenceSourceRow(Icons.Filled.CameraAlt, "Take Photo", "Capture your work with the camera") {
                requestCameraForPhoto()
            }
        }
        item {
            EvidenceSourceRow(Icons.Filled.Image, "Choose from Gallery", "Select an existing photo") {
                pickImageLauncher.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
            }
        }
        item {
            EvidenceSourceRow(Icons.Filled.InsertDriveFile, "Upload File", "Any document, spreadsheet, or file") {
                openDocumentLauncher.launch(arrayOf("*/*"))
            }
        }
        item {
            EvidenceSourceRow(Icons.Filled.DocumentScanner, "Scan Worksheet", "Photograph a completed printed worksheet") {
                requestCameraForScan()
            }
        }
        item {
            EvidenceSourceRow(Icons.Filled.Laptop, "From Computer", "Pair with a computer to send a file") {
                showComputerStub = true
            }
        }

        if (evidence.isNotEmpty()) {
            item {
                Spacer(Modifier.height(8.dp))
                Text("UPLOADED EVIDENCE", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            }
            items(evidence) { item ->
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.InsertDriveFile, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
                        Column(modifier = Modifier.weight(1f).padding(start = 12.dp)) {
                            Text(item.fileName, style = MaterialTheme.typography.bodyMedium)
                            item.description?.let {
                                Text(it, style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                            }
                        }
                        IconButton(onClick = { viewModel.delete(item) }) {
                            Icon(Icons.Filled.Delete, contentDescription = "Remove evidence", tint = MaterialTheme.colorScheme.error)
                        }
                    }
                }
            }
        }

        item {
            Spacer(Modifier.height(8.dp))
            Button(onClick = onDone, modifier = Modifier.fillMaxWidth(), enabled = evidence.isNotEmpty()) {
                Text("Save Evidence")
            }
        }
    }

    if (showComputerStub) {
        AlertDialog(
            onDismissRequest = { showComputerStub = false },
            title = { Text("From Computer") },
            text = {
                Text(
                    "Pairing with a computer to send files directly isn't available in this build yet. " +
                        "For now, save your file to your phone (e.g. via cloud storage or a cable) and use " +
                        "\"Upload File\" instead. See docs/ROADMAP.md for what's planned here.",
                )
            },
            confirmButton = { Button(onClick = { showComputerStub = false }) { Text("Got it") } },
        )
    }
}

@Composable
private fun EvidenceSourceRow(icon: ImageVector, title: String, subtitle: String, onClick: () -> Unit) {
    NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Icon(
                icon,
                contentDescription = null,
                tint = MaterialTheme.colorScheme.primary,
                modifier = Modifier.size(28.dp),
            )
            Column(modifier = Modifier.weight(1f).padding(start = 16.dp)) {
                Text(title, style = MaterialTheme.typography.titleMedium)
                Text(subtitle, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Icon(Icons.Filled.CloudUpload, contentDescription = null, tint = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
