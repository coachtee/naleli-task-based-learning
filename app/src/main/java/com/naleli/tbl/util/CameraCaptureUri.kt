package com.naleli.tbl.util

import android.content.Context
import android.net.Uri
import androidx.core.content.FileProvider
import java.io.File

/** Creates a content:// Uri (via FileProvider) for ActivityResultContracts.TakePicture()
 * to write a full-resolution camera capture into, for evidence attachment. */
fun createCameraCaptureUri(context: Context): Uri {
    val dir = File(context.cacheDir, "camera").apply { mkdirs() }
    val file = File(dir, "capture-${System.currentTimeMillis()}.jpg")
    return FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
}
