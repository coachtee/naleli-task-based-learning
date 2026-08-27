package com.naleli.tbl.util

import android.Manifest
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat

/**
 * Camera evidence capture must request the CAMERA runtime permission
 * before ever launching a camera intent/use-case — skipping this is what
 * was crashing "Take Photo" (V1.5.1 §2). Returns a function that checks
 * current permission state and either proceeds immediately or requests
 * it first, calling [onGranted]/[onDenied] exactly once either way.
 */
@Composable
fun rememberCameraPermissionAction(
    onGranted: () -> Unit,
    onDenied: () -> Unit = {},
): () -> Unit {
    val context = LocalContext.current
    val requestPermissionLauncher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission(),
    ) { granted -> if (granted) onGranted() else onDenied() }

    return {
        val alreadyGranted = ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.CAMERA,
        ) == PackageManager.PERMISSION_GRANTED
        if (alreadyGranted) {
            onGranted()
        } else {
            requestPermissionLauncher.launch(Manifest.permission.CAMERA)
        }
    }
}
