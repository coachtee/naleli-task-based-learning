package com.naleli.tbl.ui.screens.qrlookup

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.Button
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.unit.dp
import com.naleli.tbl.domain.WorksheetCode
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import kotlinx.coroutines.launch

/**
 * Manual worksheet-code lookup (brief V1.5 §11) — the fallback path when a
 * learner can't or doesn't want to use the camera. The live camera scanner
 * ([QrScannerScreen], V1.5.1 §3) is the primary entry point from Work; this
 * screen offers a "Scan with Camera" shortcut into it, and is itself the
 * "enter manually instead" fallback the scanner links back to.
 */
@Composable
fun QrLookupScreen(onFound: (dayNumber: Int, taskId: String) -> Unit, onScanWithCamera: () -> Unit = {}) {
    val container = rememberAppContainer()
    val scope = rememberCoroutineScope()
    var input by remember { mutableStateOf("") }
    var error by remember { mutableStateOf<String?>(null) }
    var isChecking by remember { mutableStateOf(false) }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Find a Worksheet Task", style = MaterialTheme.typography.headlineSmall)
        Text(
            "Enter the code printed on your worksheet (e.g. DF-D24-T02) to jump straight to that task.",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        NaleliCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onScanWithCamera)) {
            Icon(Icons.Filled.QrCodeScanner, contentDescription = null, tint = MaterialTheme.colorScheme.primary)
            Text(
                "Scan with Camera",
                style = MaterialTheme.typography.titleMedium,
            )
            Text(
                "Point your camera at the QR square on your worksheet.",
                style = MaterialTheme.typography.bodySmall,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        Text(
            "Or type the code shown under the QR square:",
            style = MaterialTheme.typography.labelLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        OutlinedTextField(
            value = input,
            onValueChange = { input = it; error = null },
            label = { Text("Worksheet code") },
            placeholder = { Text("DF-D24-T02") },
            keyboardOptions = androidx.compose.foundation.text.KeyboardOptions(capitalization = KeyboardCapitalization.Characters),
            modifier = Modifier.fillMaxWidth(),
            singleLine = true,
            isError = error != null,
        )
        error?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall) }

        Button(
            onClick = {
                val code = WorksheetCode.parse(input)
                if (code == null) {
                    error = "That doesn't look like a worksheet code. Expected format: DF-D24-T02."
                    return@Button
                }
                isChecking = true
                scope.launch {
                    val course = container.profileRepository.getProfile()?.let { container.contentRepository.getCourse(it.programmeId) }
                    val day = course?.let { container.contentRepository.getDay(it.programmeId, code.dayNumber) }
                    val task = day?.tasks?.firstOrNull { it.taskId == code.taskId }
                    isChecking = false
                    if (day == null || task == null) {
                        error = "Day ${code.dayNumber}, Task ${code.taskNumber} isn't available in this build yet."
                    } else {
                        onFound(code.dayNumber, code.taskId)
                    }
                }
            },
            modifier = Modifier.fillMaxWidth(),
            enabled = input.isNotBlank() && !isChecking,
        ) {
            Text("Find Task")
        }
    }
}
