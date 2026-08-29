package com.naleli.tbl.ui.screens.assessment

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Check
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.RadioButtonUnchecked
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
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
import androidx.compose.ui.draw.clip
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.domain.AssessmentEngine
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.components.color
import com.naleli.tbl.ui.components.label
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.SurfaceWhite

/**
 * The one screen in Naleli Workspace that exists purely to keep progress
 * and competence separate: a task can be fully worked through and
 * submitted, and this screen still shows NOT_YET_ASSESSED until the rubric
 * has actually run — never an automatic "done = competent".
 */
@Composable
fun AssessmentScreen(
    taskId: String,
    onBack: () -> Unit,
    onOpenPortfolio: () -> Unit,
    onContinueJourney: (nextTaskId: String?) -> Unit,
) {
    val container = rememberAppContainer()
    val viewModel: AssessmentViewModel = viewModel(factory = viewModelFactory { initializer { AssessmentViewModel(container, taskId) } })
    val state by viewModel.state.collectAsState()

    if (state.isLoading || state.task == null || state.assessment == null) {
        Column(Modifier.fillMaxSize(), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.Center) {
            CircularProgressIndicator()
        }
        return
    }
    val task = state.task!!
    val assessment = state.assessment!!

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            Text(task.title, style = MaterialTheme.typography.headlineSmall)
            Text("Assessment", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("EVIDENCE", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(6.dp))
                Text(
                    if (state.evidenceCount == 0) "No evidence attached" else "${state.evidenceCount} file(s) attached",
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("ASSESSMENT CRITERIA", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(8.dp))
                state.checks.forEach { check -> CriterionRow(check) }
            }
        }

        item {
            Spacer(Modifier.height(8.dp))
            val isCompetent = assessment.result == CompetenceResult.COMPETENT
            val resultColor = assessment.result.color()
            // The competence result gets its own bordered, tinted surface —
            // deliberately distinct from the criteria checklist above, so
            // "assessed competent" never reads as just another tick.
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .clip(RoundedCornerShape(20.dp))
                    .background(resultColor.copy(alpha = 0.10f))
                    .border(1.dp, resultColor.copy(alpha = 0.45f), RoundedCornerShape(20.dp))
                    .padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
            ) {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    if (isCompetent) {
                        Icon(Icons.Filled.CheckCircle, contentDescription = null, tint = resultColor, modifier = Modifier.size(26.dp))
                    }
                    Text(assessment.result.label().uppercase(), style = MaterialTheme.typography.headlineSmall, color = resultColor)
                }
                Spacer(Modifier.height(10.dp))
                if (isCompetent) {
                    Text(
                        "You demonstrated the ability to",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        textAlign = TextAlign.Center,
                    )
                    Text(
                        task.skillDeveloped,
                        style = MaterialTheme.typography.titleMedium,
                        textAlign = TextAlign.Center,
                    )
                } else {
                    Text(
                        "Review the criteria above, then go back and resubmit.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        textAlign = TextAlign.Center,
                    )
                }
            }
        }

        item {
            Spacer(Modifier.height(4.dp))
            if (assessment.result == CompetenceResult.COMPETENT) {
                // Emerald success banner (above) paired with an orange
                // primary button that actually moves the learner on. It
                // previously called onBack, which returned them to the
                // workspace of the task they had just finished — the one
                // place in the flow they are done with.
                Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    // Naming the destination makes the button's promise
                    // checkable: the learner sees where it goes before
                    // pressing it.
                    Text(
                        state.nextTaskTitle?.let { "Next: $it" }
                            ?: "That is everything unlocked so far — back to your Journey.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = MaterialTheme.colorScheme.onSurfaceVariant,
                        textAlign = TextAlign.Center,
                        modifier = Modifier.fillMaxWidth(),
                    )
                    OutlinedButton(onClick = onOpenPortfolio, modifier = Modifier.fillMaxWidth()) { Text("VIEW PORTFOLIO") }
                    Button(
                        onClick = { onContinueJourney(state.nextTaskId) },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = NibsOrange, contentColor = SurfaceWhite),
                    ) { Text("CONTINUE JOURNEY") }
                }
            } else {
                Button(onClick = onBack, modifier = Modifier.fillMaxWidth()) { Text("Back to Task") }
            }
        }
    }
}

@Composable
private fun CriterionRow(check: AssessmentEngine.CriterionCheck) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.CenterVertically) {
        Icon(
            if (check.met) Icons.Filled.Check else Icons.Filled.RadioButtonUnchecked,
            contentDescription = null,
            tint = if (check.met) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.size(18.dp),
        )
        Text(
            check.label,
            style = MaterialTheme.typography.bodyMedium,
            color = if (check.met) MaterialTheme.colorScheme.onSurface else MaterialTheme.colorScheme.onSurfaceVariant,
            modifier = Modifier.padding(start = 10.dp),
        )
    }
}
