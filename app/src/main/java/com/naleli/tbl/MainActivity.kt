package com.naleli.tbl

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.naleli.tbl.ui.navigation.NaleliDestinations
import com.naleli.tbl.ui.navigation.NaleliNavHost
import com.naleli.tbl.ui.theme.NaleliTheme

/**
 * One splash experience only (V1.5.1 §1): the OS-level SplashScreen API
 * (icon on navy background — see themes.xml Theme.Naleli.Splash) is held
 * on screen via setKeepOnScreenCondition for exactly as long as the
 * profile-existence check takes, then dismisses straight into Welcome or
 * Home. There is no second, separate Compose splash screen/route.
 */
class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        val splashScreen = installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        var startDestination by mutableStateOf<String?>(null)
        splashScreen.setKeepOnScreenCondition { startDestination == null }

        setContent {
            val container = (application as NaleliApplication).container

            LaunchedEffect(Unit) {
                startDestination = when {
                    !container.profileRepository.hasProfile() -> NaleliDestinations.WELCOME
                    !container.workspacePreferences.portfolioSetupComplete -> NaleliDestinations.PORTFOLIO_SETUP
                    else -> NaleliDestinations.HOME
                }
            }

            NaleliTheme(themeMode = container.themePreferences.mode) {
                startDestination?.let { destination ->
                    NaleliNavHost(startDestination = destination)
                }
            }
        }
    }
}
