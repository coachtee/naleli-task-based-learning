package com.naleli.tbl.ui.screens.splash

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.width
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.naleli.tbl.R
import com.naleli.tbl.ui.rememberAppContainer
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft

/**
 * Custom in-app splash, shown right after the OS-level splash (see
 * Theme.Naleli.Splash). Full NIBS academic mark + institutional wordmark
 * sequence, per brief V1.5 §2/§24 — elegant and minimal, no animation
 * beyond what's needed to route once we know if a profile exists.
 */
@Composable
fun SplashScreen(onRouteDecided: (hasProfile: Boolean) -> Unit) {
    val container = rememberAppContainer()

    LaunchedEffect(Unit) {
        val hasProfile = container.profileRepository.hasProfile()
        onRouteDecided(hasProfile)
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(HeroSurface),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.Center,
    ) {
        Image(
            painter = painterResource(R.drawable.nibs_mark),
            contentDescription = "NIBS academic mark",
            modifier = Modifier
                .width(96.dp)
                .height(160.dp),
        )
        Spacer(Modifier.height(28.dp))
        Text(
            text = "NALELI INNOVATORS BUSINESS SCHOOL",
            color = OnHeroSurface,
            fontWeight = FontWeight.SemiBold,
            fontSize = 14.sp,
            letterSpacing = 1.2.sp,
            textAlign = TextAlign.Center,
            modifier = Modifier.width(260.dp),
        )
        Spacer(Modifier.height(10.dp))
        Text(
            text = "Naleli Task-Based Learning",
            style = MaterialTheme.typography.titleMedium,
            color = OnHeroSurfaceSoft,
            textAlign = TextAlign.Center,
        )
    }
}
