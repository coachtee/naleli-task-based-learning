package com.naleli.tbl.ui.screens.certificate

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.Button
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.collectAsState
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.ErrorRed
import com.naleli.tbl.ui.theme.SuccessGreen
import com.naleli.tbl.util.shareIntent
import com.naleli.tbl.util.viewIntent
import java.io.File

@Composable
fun CertificateScreen() {
    val container = rememberAppContainer()
    val viewModel: CertificateViewModel = viewModel(factory = viewModelFactory { initializer { CertificateViewModel(container) } })
    val state by viewModel.state.collectAsState()
    val context = LocalContext.current

    if (state.isLoading) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(20.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Text("Certificate", style = MaterialTheme.typography.headlineSmall)
        Text(
            "Naleli Innovators Business School — ${state.course?.programmeName ?: ""}",
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
        )

        state.latestCertificate?.let { cert ->
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("Certificate issued", style = MaterialTheme.typography.titleMedium, color = SuccessGreen)
                Text("Certificate number: ${cert.certificateNumber}", style = MaterialTheme.typography.bodyMedium)
                Spacer(Modifier.height(8.dp))
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(onClick = { context.startActivity(viewIntent(context, File(cert.filePath), "application/pdf")) }) { Text("Open PDF") }
                    OutlinedButton(onClick = { context.startActivity(shareIntent(context, File(cert.filePath), "application/pdf")) }) { Text("Share") }
                }
            }
        }

        NaleliCard(modifier = Modifier.fillMaxWidth()) {
            Text("Eligibility", style = MaterialTheme.typography.titleMedium)
            Text(
                "This checklist comes directly from the programme's configured requirements — it doesn't assume anything not yet satisfied.",
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
            Spacer(Modifier.height(8.dp))
            state.eligibility?.rules?.forEach { rule ->
                Row(verticalAlignment = Alignment.CenterVertically) {
                    Icon(
                        imageVector = if (rule.satisfied) Icons.Filled.Check else Icons.Filled.Close,
                        contentDescription = null,
                        tint = if (rule.satisfied) SuccessGreen else ErrorRed,
                    )
                    Spacer(Modifier.height(0.dp))
                    Text(rule.label, style = MaterialTheme.typography.bodyMedium, modifier = Modifier.padding(start = 8.dp))
                }
                Spacer(Modifier.height(6.dp))
            }
        }

        Button(
            onClick = viewModel::generate,
            modifier = Modifier.fillMaxWidth(),
            enabled = state.eligibility?.isEligible == true && !state.isGenerating,
        ) {
            Text("Generate Certificate")
        }
    }
}
