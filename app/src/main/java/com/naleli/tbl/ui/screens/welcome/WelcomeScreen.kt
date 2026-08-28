package com.naleli.tbl.ui.screens.welcome

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.luminance
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.naleli.tbl.R
import com.naleli.tbl.ui.theme.NaleliLightBlue
import com.naleli.tbl.ui.theme.NibsWordmarkNavy

/**
 * The one place the full NIBS academic mark + institutional wordmark
 * ("NALELI INNOVATORS BUSINESS SCHOOL") appears — first-launch only. The
 * app no longer shows a separate branded splash screen (V1.5.1 §1): the
 * OS-level splash (icon only) dismisses straight into this screen for a
 * new learner, or straight into Home for a returning one.
 */
@Composable
fun WelcomeScreen(onCreateProfile: () -> Unit) {
    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Image(
            painter = painterResource(R.drawable.nibs_mark),
            contentDescription = "NIBS academic mark",
            modifier = Modifier
                .width(56.dp)
                .height(94.dp),
        )
        Spacer(Modifier.height(16.dp))
        // The wordmark's real ink colour (sampled from the logo asset) has
        // almost no contrast against the dark-theme background, so dark
        // mode gets a legible light-blue instead — never the interactive
        // primary blue, so this reads as a brand mark, not a tappable one.
        val isDarkSurface = MaterialTheme.colorScheme.background.luminance() < 0.5f
        Text(
            text = "NALELI INNOVATORS\nBUSINESS SCHOOL",
            style = MaterialTheme.typography.labelLarge,
            fontWeight = FontWeight.SemiBold,
            letterSpacing = 1.sp,
            color = if (isDarkSurface) NaleliLightBlue else NibsWordmarkNavy,
            textAlign = TextAlign.Center,
        )
        Spacer(Modifier.height(28.dp))
        Text(
            text = stringResource(R.string.welcome_title),
            style = MaterialTheme.typography.headlineSmall,
            textAlign = TextAlign.Center,
        )
        Text(
            text = stringResource(R.string.welcome_body),
            style = MaterialTheme.typography.bodyLarge,
            color = MaterialTheme.colorScheme.onSurfaceVariant,
            textAlign = TextAlign.Center,
            modifier = Modifier.padding(top = 12.dp, bottom = 32.dp),
        )
        Button(onClick = onCreateProfile, modifier = Modifier.fillMaxWidth()) {
            Text(stringResource(R.string.welcome_cta))
        }
    }
}
