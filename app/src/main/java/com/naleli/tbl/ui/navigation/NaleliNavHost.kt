package com.naleli.tbl.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CalendarViewMonth
import androidx.compose.material.icons.filled.Description
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.WorkspacePremium
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.navigation.NavDestination.Companion.hierarchy
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.naleli.tbl.ui.screens.certificate.CertificateScreen
import com.naleli.tbl.ui.screens.day.DayDetailScreen
import com.naleli.tbl.ui.screens.evidence.EvidenceScreen
import com.naleli.tbl.ui.screens.help.HelpScreen
import com.naleli.tbl.ui.screens.home.HomeScreen
import com.naleli.tbl.ui.screens.mylearning.MyLearningScreen
import com.naleli.tbl.ui.screens.portfolio.PortfolioScreen
import com.naleli.tbl.ui.screens.profile.ProfileHubScreen
import com.naleli.tbl.ui.screens.profile.ProfileScreen
import com.naleli.tbl.ui.screens.progress.ProgressScreen
import com.naleli.tbl.ui.screens.settings.BackupScreen
import com.naleli.tbl.ui.screens.settings.PrivacyScreen
import com.naleli.tbl.ui.screens.settings.SettingsScreen
import com.naleli.tbl.ui.screens.splash.SplashScreen
import com.naleli.tbl.ui.screens.welcome.WelcomeScreen

private data class BottomNavItem(val route: String, val label: String, val icon: androidx.compose.ui.graphics.vector.ImageVector)

private val bottomNavItems = listOf(
    BottomNavItem(NaleliDestinations.HOME, "Home", Icons.Filled.Home),
    BottomNavItem(NaleliDestinations.MY_LEARNING, "My Learning", Icons.Filled.CalendarViewMonth),
    BottomNavItem(NaleliDestinations.EVIDENCE, "Evidence", Icons.Filled.Description),
    BottomNavItem(NaleliDestinations.PORTFOLIO, "Portfolio", Icons.Filled.WorkspacePremium),
    BottomNavItem(NaleliDestinations.PROFILE, "Profile", Icons.Filled.Person),
)

@Composable
fun NaleliNavHost(navController: NavHostController = rememberNavController()) {
    val backStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = backStackEntry?.destination?.route
    val showBottomBar = currentRoute in NaleliDestinations.BOTTOM_NAV_ROUTES

    Scaffold(
        bottomBar = {
            if (showBottomBar) {
                NavigationBar {
                    bottomNavItems.forEach { item ->
                        NavigationBarItem(
                            selected = backStackEntry?.destination?.hierarchy?.any { it.route == item.route } == true,
                            onClick = {
                                navController.navigate(item.route) {
                                    popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                                    launchSingleTop = true
                                    restoreState = true
                                }
                            },
                            icon = { Icon(item.icon, contentDescription = item.label) },
                            label = { Text(item.label) },
                        )
                    }
                }
            }
        },
    ) { padding ->
        NavHost(
            navController = navController,
            startDestination = NaleliDestinations.SPLASH,
            modifier = Modifier.padding(padding),
        ) {
            composable(NaleliDestinations.SPLASH) {
                SplashScreen { hasProfile ->
                    val destination = if (hasProfile) NaleliDestinations.HOME else NaleliDestinations.WELCOME
                    navController.navigate(destination) { popUpTo(NaleliDestinations.SPLASH) { inclusive = true } }
                }
            }
            composable(NaleliDestinations.WELCOME) {
                WelcomeScreen(onCreateProfile = { navController.navigate(NaleliDestinations.CREATE_PROFILE) })
            }
            composable(NaleliDestinations.CREATE_PROFILE) {
                ProfileScreen(isEditMode = false) {
                    navController.navigate(NaleliDestinations.HOME) { popUpTo(NaleliDestinations.SPLASH) { inclusive = true } }
                }
            }
            composable(NaleliDestinations.EDIT_PROFILE) {
                ProfileScreen(isEditMode = true) { navController.popBackStack() }
            }

            composable(NaleliDestinations.HOME) {
                HomeScreen(
                    onStartTodaysTask = { dayNumber -> navController.navigate(NaleliDestinations.dayDetail(dayNumber)) },
                    onOpenDay = { dayNumber -> navController.navigate(NaleliDestinations.dayDetail(dayNumber)) },
                    onOpenPortfolio = { navController.navigate(NaleliDestinations.PORTFOLIO) },
                )
            }
            composable(NaleliDestinations.MY_LEARNING) {
                MyLearningScreen(onOpenDay = { dayNumber -> navController.navigate(NaleliDestinations.dayDetail(dayNumber)) })
            }
            composable(NaleliDestinations.EVIDENCE) { EvidenceScreen() }
            composable(NaleliDestinations.PORTFOLIO) { PortfolioScreen() }
            composable(NaleliDestinations.PROFILE) {
                ProfileHubScreen(
                    onEditProfile = { navController.navigate(NaleliDestinations.EDIT_PROFILE) },
                    onOpenProgress = { navController.navigate(NaleliDestinations.PROGRESS) },
                    onOpenCertificate = { navController.navigate(NaleliDestinations.CERTIFICATE) },
                    onOpenSettings = { navController.navigate(NaleliDestinations.SETTINGS) },
                    onOpenHelp = { navController.navigate(NaleliDestinations.HELP) },
                    onOpenBackup = { navController.navigate(NaleliDestinations.BACKUP) },
                )
            }

            composable(
                route = NaleliDestinations.DAY_DETAIL_PATTERN,
                arguments = listOf(navArgument("dayNumber") { type = androidx.navigation.NavType.IntType }),
            ) { backStackEntry ->
                val dayNumber = backStackEntry.arguments?.getInt("dayNumber") ?: 1
                DayDetailScreen(dayNumber = dayNumber, onDayCompleted = { navController.popBackStack() })
            }

            composable(NaleliDestinations.PROGRESS) { ProgressScreen() }
            composable(NaleliDestinations.CERTIFICATE) { CertificateScreen() }
            composable(NaleliDestinations.SETTINGS) {
                SettingsScreen(
                    onOpenBackup = { navController.navigate(NaleliDestinations.BACKUP) },
                    onOpenPrivacy = { navController.navigate(NaleliDestinations.PRIVACY) },
                    onOpenHelp = { navController.navigate(NaleliDestinations.HELP) },
                    onDataDeleted = {
                        navController.navigate(NaleliDestinations.WELCOME) { popUpTo(0) { inclusive = true } }
                    },
                )
            }
            composable(NaleliDestinations.HELP) { HelpScreen() }
            composable(NaleliDestinations.BACKUP) { BackupScreen() }
            composable(NaleliDestinations.PRIVACY) { PrivacyScreen() }
        }
    }
}
