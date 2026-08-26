package com.naleli.tbl.ui.screens.splash

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.rememberAppContainer

/**
 * Routes straight to Welcome (no profile yet) or Home (profile exists).
 * No network call, no artificial delay — the only thing worth waiting for
 * here is one local database read (brief: everything works offline).
 */
@Composable
fun SplashScreen(onRouteDecided: (hasProfile: Boolean) -> Unit) {
    val container = rememberAppContainer()

    LaunchedEffect(Unit) {
        val hasProfile = container.profileRepository.hasProfile()
        onRouteDecided(hasProfile)
    }

    Column(
        modifier = Modifier.fillMaxSize(),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        CircularProgressIndicator()
        Spacer(modifier = Modifier.height(16.dp))
        Text(
            text = "Naleli Task-Based Learning",
            style = MaterialTheme.typography.titleMedium,
        )
    }
}
