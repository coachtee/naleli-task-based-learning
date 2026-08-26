package com.naleli.tbl.ui.screens.settings

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.components.BackHeader

@Composable
fun PrivacyScreen(onBack: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        BackHeader(title = "Privacy Notice", onBack = onBack)

        Text("What we collect", style = MaterialTheme.typography.titleMedium)
        Text(
            "Your first name, surname, and optionally a student number, email, and phone number, to create your local learner profile. Nothing more is collected.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("Where it's stored", style = MaterialTheme.typography.titleMedium)
        Text(
            "Entirely on this device, in a local database and local files. Naleli Task-Based Learning has no server and no online account — your data is never sent anywhere by this app.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("No accounts, no passwords", style = MaterialTheme.typography.titleMedium)
        Text(
            "There is no login and no password to remember, because there is nowhere else your data goes.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("No analytics", style = MaterialTheme.typography.titleMedium)
        Text(
            "This app includes no analytics or tracking SDKs of any kind.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("Your control", style = MaterialTheme.typography.titleMedium)
        Text(
            "You can back up your data to a file you control at any time, and permanently delete all of it from this device from Profile → Delete My Learning Data.",
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}
