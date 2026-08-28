package com.naleli.tbl.ui.screens.portfolio

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.preferences.StorageChoice
import com.naleli.tbl.domain.PortfolioSkill
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.NaleliProgressBar
import com.naleli.tbl.ui.components.color
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer

/** The portfolio as centrepiece (brief §6): a strength score, per-skill
 * competence + evidence count + a confidence tag that never substitutes
 * for the assessment result, and a My Files card that points at — rather
 * than owns — where the learner's evidence actually lives. */
@Composable
fun PortfolioScreen() {
    val container = rememberAppContainer()
    val viewModel: PortfolioViewModel = viewModel(factory = viewModelFactory { initializer { PortfolioViewModel(container) } })
    val state by viewModel.state.collectAsState()
    val storageChoice = container.workspacePreferences.storageChoice
    var showConnectStub by remember { mutableStateOf(false) }

    if (state.isLoading) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            Text("My Portfolio", style = MaterialTheme.typography.headlineSmall)
            Text(
                "Evidence of what you can do",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("PORTFOLIO STRENGTH", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(8.dp))
                NaleliProgressBar(progressFraction = state.strengthPercent / 100f)
                Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(
                        "${state.skills.count { it.result == CompetenceResult.COMPETENT }} skills competent",
                        style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                    )
                    Text("${state.strengthPercent}%", style = MaterialTheme.typography.labelSmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Spacer(Modifier.height(4.dp))
                Text(
                    "Grows as you demonstrate competence and add evidence — not just from marking work done.",
                    style = MaterialTheme.typography.labelSmall,
                    color = MaterialTheme.colorScheme.onSurfaceVariant,
                )
            }
        }

        item { Text("SKILLS DEMONSTRATED", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary) }
        items(state.skills) { skill -> SkillCard(skill) }

        item {
            Spacer(Modifier.height(4.dp))
            Text("MY FILES", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(
                    when (storageChoice) {
                        StorageChoice.THIS_DEVICE -> "Your work is saved on this device, inside the app's own NTBL Portfolio folder."
                        StorageChoice.GOOGLE_DRIVE -> "Your work is set to save to your Google Drive NTBL Portfolio folder."
                        StorageChoice.ONEDRIVE -> "Your work is set to save to your Microsoft OneDrive NTBL Portfolio folder."
                    },
                    style = MaterialTheme.typography.bodyMedium,
                )
                Spacer(Modifier.height(10.dp))
                if (storageChoice == StorageChoice.THIS_DEVICE) {
                    OutlinedButton(onClick = { showConnectStub = true }, modifier = Modifier.fillMaxWidth()) {
                        Text("Connect Cloud Storage")
                    }
                } else {
                    OutlinedButton(onClick = { showConnectStub = true }, modifier = Modifier.fillMaxWidth()) {
                        Text("Open Folder")
                    }
                }
            }
        }
        item { Spacer(Modifier.height(8.dp)) }
    }

    if (showConnectStub) {
        AlertDialog(
            onDismissRequest = { showConnectStub = false },
            title = { Text("Not connected yet") },
            text = {
                Text(
                    "Cloud storage isn't wired up to a real account in this build yet. Your evidence stays safely on " +
                        "this device either way — you can connect Google Drive or OneDrive from a future update.",
                )
            },
            confirmButton = { Button(onClick = { showConnectStub = false }) { Text("Got it") } },
        )
    }
}

@Composable
private fun SkillCard(skill: PortfolioSkill) {
    NaleliCard(modifier = Modifier.fillMaxWidth()) {
        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Text(
                skill.skillName,
                style = MaterialTheme.typography.titleMedium,
                modifier = Modifier.weight(1f).padding(end = 8.dp),
                maxLines = 1,
                overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
            )
            Text(skill.result.label(), style = MaterialTheme.typography.labelMedium, color = skill.result.color(), maxLines = 1)
        }
        Text(
            "${if (skill.evidenceCount == 0) "No evidence yet" else "${skill.evidenceCount} evidence item(s)"} · Confidence: ${skill.confidenceLabel}",
            style = MaterialTheme.typography.labelSmall,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            maxLines = 1,
            overflow = androidx.compose.ui.text.style.TextOverflow.Ellipsis,
        )
    }
}
