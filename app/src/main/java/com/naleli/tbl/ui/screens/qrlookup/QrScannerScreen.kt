package com.naleli.tbl.ui.screens.qrlookup

import android.Manifest
import android.content.pm.PackageManager
import android.util.Log
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.ImageProxy
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import com.naleli.tbl.domain.WorksheetCode
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import kotlinx.coroutines.launch
import java.util.concurrent.ExecutorService
import java.util.concurrent.Executors

/**
 * Live camera QR scanner for worksheet codes (V1.5.1 §3) — a real
 * CameraX preview with an ML Kit on-device barcode analyzer, not a stub.
 * A decoded code is validated against the loaded course content (same
 * check as manual entry) before navigating; anything else shows a
 * friendly, non-crashing message and keeps scanning.
 */
@Composable
fun QrScannerScreen(
    onBack: () -> Unit,
    onFound: (dayNumber: Int, taskId: String) -> Unit,
    onEnterManually: () -> Unit = {},
) {
    val context = LocalContext.current
    val container = rememberAppContainer()
    val scope = rememberCoroutineScope()

    var hasCameraPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED,
        )
    }
    var statusMessage by remember { mutableStateOf<String?>(null) }
    var isNavigating by remember { mutableStateOf(false) }

    val permissionLauncher = androidx.activity.compose.rememberLauncherForActivityResult(
        androidx.activity.result.contract.ActivityResultContracts.RequestPermission(),
    ) { granted -> hasCameraPermission = granted }

    LaunchedEffect(Unit) {
        if (!hasCameraPermission) permissionLauncher.launch(Manifest.permission.CAMERA)
    }

    val onFoundState = rememberUpdatedState(onFound)

    Column(modifier = Modifier.fillMaxSize()) {
        Column(modifier = Modifier.padding(20.dp)) {
            BackHeader(title = "Scan Worksheet Code", onBack = onBack)
        }

        if (!hasCameraPermission) {
            Column(
                modifier = Modifier.fillMaxSize().padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.Center,
            ) {
                Text(
                    "Camera permission is needed to scan a worksheet code.",
                    style = MaterialTheme.typography.bodyMedium,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
                Spacer(Modifier.height(12.dp))
                Button(onClick = { permissionLauncher.launch(Manifest.permission.CAMERA) }) {
                    Text("Grant Camera Permission")
                }
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = onEnterManually) {
                    Text("Enter code manually instead")
                }
            }
            return
        }

        Box(modifier = Modifier.fillMaxWidth().weight(1f)) {
            CameraPreview(
                onBarcodeDetected = { rawValue ->
                    if (isNavigating) return@CameraPreview
                    val code = WorksheetCode.parse(rawValue)
                    if (code == null) {
                        statusMessage = "That QR code isn't a Naleli worksheet code."
                        return@CameraPreview
                    }
                    isNavigating = true
                    scope.launch {
                        val profile = container.profileRepository.getProfile()
                        val course = profile?.let { container.contentRepository.getCourse(it.programmeId) }
                        val day = course?.let { container.contentRepository.getDay(it.programmeId, code.dayNumber) }
                        val task = day?.tasks?.firstOrNull { it.taskId == code.taskId }
                        if (day == null || task == null) {
                            statusMessage = "Day ${code.dayNumber}, Task ${code.taskNumber} isn't available in this build yet."
                            isNavigating = false
                        } else {
                            onFoundState.value(code.dayNumber, code.taskId)
                        }
                    }
                },
            )
        }

        NaleliCard(modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp)) {
            Text(
                statusMessage ?: "Point the camera at a worksheet's QR code.",
                style = MaterialTheme.typography.bodyMedium,
                color = if (statusMessage != null) MaterialTheme.colorScheme.error else MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
        TextButton(onClick = onEnterManually, modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp)) {
            Text("Enter code manually instead")
        }
    }
}

@Composable
private fun CameraPreview(onBarcodeDetected: (String) -> Unit) {
    val context = LocalContext.current
    val lifecycleOwner = LocalLifecycleOwner.current
    val onBarcodeDetectedState = rememberUpdatedState(onBarcodeDetected)

    val analysisExecutor: ExecutorService = remember { Executors.newSingleThreadExecutor() }
    DisposableEffect(Unit) {
        onDispose { analysisExecutor.shutdown() }
    }

    AndroidView(
        modifier = Modifier.fillMaxSize(),
        factory = { ctx ->
            val previewView = PreviewView(ctx)
            val cameraProviderFuture = ProcessCameraProvider.getInstance(ctx)
            cameraProviderFuture.addListener(
                {
                    val cameraProvider = cameraProviderFuture.get()

                    val preview = Preview.Builder().build().also {
                        it.setSurfaceProvider(previewView.surfaceProvider)
                    }

                    val barcodeScanner = BarcodeScanning.getClient(
                        BarcodeScannerOptions.Builder()
                            .setBarcodeFormats(Barcode.FORMAT_QR_CODE)
                            .build(),
                    )

                    val imageAnalysis = ImageAnalysis.Builder()
                        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                        .build()
                    imageAnalysis.setAnalyzer(analysisExecutor) { imageProxy: ImageProxy ->
                        processImageProxy(barcodeScanner, imageProxy, onBarcodeDetectedState.value)
                    }

                    try {
                        cameraProvider.unbindAll()
                        cameraProvider.bindToLifecycle(
                            lifecycleOwner,
                            CameraSelector.DEFAULT_BACK_CAMERA,
                            preview,
                            imageAnalysis,
                        )
                    } catch (e: Exception) {
                        Log.e("QrScannerScreen", "Failed to bind camera use cases", e)
                    }
                },
                ContextCompat.getMainExecutor(ctx),
            )
            previewView
        },
    )
}

private fun processImageProxy(
    scanner: com.google.mlkit.vision.barcode.BarcodeScanner,
    imageProxy: ImageProxy,
    onBarcodeDetected: (String) -> Unit,
) {
    val mediaImage = imageProxy.image
    if (mediaImage == null) {
        imageProxy.close()
        return
    }
    val inputImage = InputImage.fromMediaImage(mediaImage, imageProxy.imageInfo.rotationDegrees)
    scanner.process(inputImage)
        .addOnSuccessListener { barcodes ->
            barcodes.firstOrNull()?.rawValue?.let { onBarcodeDetected(it) }
        }
        .addOnFailureListener { e -> Log.e("QrScannerScreen", "Barcode scan failed", e) }
        .addOnCompleteListener { imageProxy.close() }
}
