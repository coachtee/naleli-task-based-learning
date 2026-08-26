package com.naleli.tbl

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.core.splashscreen.SplashScreen.Companion.installSplashScreen
import com.naleli.tbl.ui.navigation.NaleliNavHost
import com.naleli.tbl.ui.theme.NaleliTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        installSplashScreen()
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            NaleliTheme {
                NaleliNavHost()
            }
        }
    }
}
