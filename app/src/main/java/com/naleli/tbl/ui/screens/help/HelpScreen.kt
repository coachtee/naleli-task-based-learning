package com.naleli.tbl.ui.screens.help

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
fun HelpScreen(onBack: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        BackHeader(title = "Help", onBack = onBack)

        Text("About Naleli Task-Based Learning", style = MaterialTheme.typography.titleMedium)
        Text(
            "A practical, task-based learning application where you learn a professional role by completing daily workplace-style tasks, producing evidence, building a portfolio, and completing a final capstone. Every day follows Learn → Do → Check → Evidence → Reflect.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("Naleli Innovators Business School (NIBS)", style = MaterialTheme.typography.titleMedium)
        Text(
            "NIBS is the professional programme and credential brand behind this course.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("Katlehong Computer School (KCS)", style = MaterialTheme.typography.titleMedium)
        Text(
            "KCS is the campus / training environment where this programme is delivered.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("Working offline", style = MaterialTheme.typography.titleMedium)
        Text(
            "Everything in this app works without an internet connection — your lessons, tasks, evidence, progress, portfolio, and backups all stay on this device.",
            style = MaterialTheme.typography.bodyMedium,
        )

        Text("This is an early build", style = MaterialTheme.typography.titleMedium)
        Text(
            "This version includes Days 1–7 of the 90-day Digital Foundation programme, to prove the full learning experience before the rest of the course is added.",
            style = MaterialTheme.typography.bodyMedium,
        )
    }
}
