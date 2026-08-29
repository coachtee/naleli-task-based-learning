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
import androidx.compose.material.icons.filled.PriorityHigh
import androidx.compose.material.icons.filled.RadioButtonUnchecked
import androidx.compose.material.icons.filled.Schedule
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
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import androidx.lifecycle.viewmodel.initializer
import androidx.lifecycle.viewmodel.viewModelFactory
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.domain.AssessmentEngine
import com.naleli.tbl.ui.components.BackHeader
import com.naleli.tbl.ui.components.NaleliCard
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.AssessmentPurple
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.SuccessGreen
import com.naleli.tbl.ui.theme.SurfaceWhite
import com.naleli.tbl.ui.theme.WarningOrange

/**
 * The submission review experience: one of three states, never ambiguous.
 *
 * Submitted for Review — the work is banked and waiting, and there is
 * nothing for the learner to do. Competent — the evidence was approved and
 * the skill is now theirs. Needs Changes — exactly what to improve, with
 * the work still intact behind a CONTINUE WORK button; nobody starts over.
 *
 * This is also the one screen that keeps progress and competence visibly
 * separate: a task can be fully worked through and submitted and still show
 * as awaiting a result, because completion is never competence.
 */
@Composable
fun AssessmentScreen(
    taskId: String,
    onBack: () -> Unit,
    onOpenPortfolio: () -> Unit,
    onContinueWork: (taskId: String) -> Unit,
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
    val result = assessment.result
    val unmet = state.checks.filterNot { it.met }

    LazyColumn(
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(20.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        item {
            BackHeader(onBack = onBack)
            Spacer(Modifier.height(8.dp))
            Text(task.title, style = MaterialTheme.typography.headlineSmall)
            Text("Your submission", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
        }

        // The status panel is the screen. Everything else on it is
        // supporting detail, so it comes first and reads in one glance.
        item {
            when (result) {
                CompetenceResult.NOT_YET_ASSESSED -> StatusPanel(
                    icon = Icons.Filled.Schedule,
                    color = AssessmentPurple,
                    heading = "Submitted for Review",
                    body = "Your work has been saved to your portfolio and is waiting for review.",
                )
                CompetenceResult.COMPETENT -> StatusPanel(
                    icon = Icons.Filled.CheckCircle,
                    color = SuccessGreen,
                    heading = "Competent",
                    body = "Your evidence has been approved. This skill has been added to your demonstrated competencies.",
                    footnote = task.skillDeveloped,
                )
                CompetenceResult.REQUIRES_IMPROVEMENT -> StatusPanel(
                    icon = Icons.Filled.PriorityHigh,
                    color = WarningOrange,
                    heading = "Needs Changes",
                    body = "Your work is saved. Fix the points below and submit again — you do not start over.",
                )
            }
        }

        // Needs Changes names the specific gaps and what to do about each.
        // Restating every criterion here would bury them; only the ones
        // that failed are the learner's business right now.
        if (result == CompetenceResult.REQUIRES_IMPROVEMENT && unmet.isNotEmpty()) {
            item {
                NaleliCard(modifier = Modifier.fillMaxWidth()) {
                    Text("WHAT NEEDS IMPROVEMENT", style = MaterialTheme.typography.labelLarge, color = WarningOrange)
                    Spacer(Modifier.height(8.dp))
                    unmet.forEachIndexed { index, check ->
                        if (index > 0) Spacer(Modifier.height(12.dp))
                        Text(check.label, style = MaterialTheme.typography.titleSmall)
                        if (check.fix.isNotBlank()) {
                            Spacer(Modifier.height(2.dp))
                            Text(
                                check.fix,
                                style = MaterialTheme.typography.bodyMedium,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text("WHAT YOU SUBMITTED", style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.primary)
                Spacer(Modifier.height(6.dp))
                Text(
                    if (state.evidenceCount == 0) "No evidence attached" else "${state.evidenceCount} item(s) in your portfolio",
                    style = MaterialTheme.typography.bodyMedium,
                )
            }
        }

        item {
            NaleliCard(modifier = Modifier.fillMaxWidth()) {
                Text(
                    if (result == CompetenceResult.NOT_YET_ASSESSED) "WHAT IT WILL BE CHECKED AGAINST" else "ASSESSMENT CRITERIA",
                    style = MaterialTheme.typography.labelLarge,
                    color = MaterialTheme.colorScheme.primary,
                )
                Spacer(Modifier.height(8.dp))
                state.checks.forEach { check -> CriterionRow(check) }
            }
        }

        // One dominant action per state, and it is always the thing the
        // learner should do next — never a Back button standing in for one.
        item {
            Spacer(Modifier.height(4.dp))
            when (result) {
                CompetenceResult.COMPETENT -> Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
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
                CompetenceResult.REQUIRES_IMPROVEMENT -> Button(
                    onClick = { onContinueWork(taskId) },
                    modifier = Modifier.fillMaxWidth(),
                    colors = ButtonDefaults.buttonColors(containerColor = NibsOrange, contentColor = SurfaceWhite),
                ) { Text("CONTINUE WORK") }
                CompetenceResult.NOT_YET_ASSESSED -> OutlinedButton(
                    onClick = onBack,
                    modifier = Modifier.fillMaxWidth(),
                ) { Text("BACK TO MY WORK") }
            }
        }
    }
}

/**
 * The result, stated once and unmistakably: a mark, a heading, a sentence.
 *
 * Green is reserved for the one state that earns it. Waiting is purple, the
 * same colour the Submitted badge uses elsewhere, and anything needing the
 * learner's hand is orange — so the panel agrees with the badge that
 * brought them here.
 */
@Composable
private fun StatusPanel(
    icon: ImageVector,
    color: Color,
    heading: String,
    body: String,
    footnote: String? = null,
) {
    Column(
        modifier = Modifier
            .fillMaxWidth()
            .clip(RoundedCornerShape(20.dp))
            .background(color.copy(alpha = 0.10f))
            .border(1.dp, color.copy(alpha = 0.45f), RoundedCornerShape(20.dp))
            .padding(horizontal = 20.dp, vertical = 22.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Icon(icon, contentDescription = null, tint = color, modifier = Modifier.size(24.dp))
            Text(heading, style = MaterialTheme.typography.headlineSmall, color = color)
        }
        Spacer(Modifier.height(10.dp))
        Text(
            body,
            style = MaterialTheme.typography.bodyMedium,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
        )
        if (!footnote.isNullOrBlank()) {
            Spacer(Modifier.height(8.dp))
            Text(footnote, style = MaterialTheme.typography.titleMedium, textAlign = TextAlign.Center)
        }
    }
}

@Composable
private fun CriterionRow(check: AssessmentEngine.CriterionCheck) {
    Row(modifier = Modifier.fillMaxWidth().padding(vertical = 5.dp), verticalAlignment = Alignment.Top) {
        Icon(
            if (check.met) Icons.Filled.Check else Icons.Filled.RadioButtonUnchecked,
            contentDescription = null,
            tint = if (check.met) SuccessGreen else MaterialTheme.colorScheme.onSurfaceVariant,
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
