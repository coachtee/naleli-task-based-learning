package com.naleli.tbl.ui

import android.content.Context
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalContext
import com.naleli.tbl.AppContainer
import com.naleli.tbl.NaleliApplication

fun Context.naleliContainer(): AppContainer = (applicationContext as NaleliApplication).container

@Composable
fun rememberAppContainer(): AppContainer {
    val context = LocalContext.current
    return context.naleliContainer()
}
