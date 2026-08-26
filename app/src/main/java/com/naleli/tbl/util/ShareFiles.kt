package com.naleli.tbl.util

import android.content.Context
import android.content.Intent
import android.net.Uri
import androidx.core.content.FileProvider
import java.io.File

fun fileProviderUri(context: Context, file: File): Uri =
    FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)

fun viewIntent(context: Context, file: File, mimeType: String?): Intent {
    val uri = fileProviderUri(context, file)
    return Intent(Intent.ACTION_VIEW).apply {
        setDataAndType(uri, mimeType ?: "*/*")
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
        addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
    }
}

fun shareIntent(context: Context, file: File, mimeType: String?): Intent {
    val uri = fileProviderUri(context, file)
    val send = Intent(Intent.ACTION_SEND).apply {
        type = mimeType ?: "*/*"
        putExtra(Intent.EXTRA_STREAM, uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }
    return Intent.createChooser(send, null).apply { addFlags(Intent.FLAG_ACTIVITY_NEW_TASK) }
}
