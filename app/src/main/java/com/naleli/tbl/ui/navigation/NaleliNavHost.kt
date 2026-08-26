package com.naleli.tbl.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Assignment
import androidx.compose.material.icons.filled.CalendarViewMonth
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Person
import androidx.compose.material.icons.filled.WorkspacePremium
import androidx.compose.material3.Icon
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.NavigationBarItemDefaults
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
import com.naleli.tbl.ui.screens.day.TaskDetailScreen
import com.naleli.tbl.ui.screens.evidence.AddEvidenceScreen
import com.naleli.tbl.ui.screens.evidence.EvidenceScreen
import com.naleli.tbl.ui.screens.help.HelpScreen
import com.naleli.tbl.ui.screens.home.HomeScreen
import com.naleli.tbl.ui.screens.mylearning.MyLearningScreen
import com.naleli.tbl.ui.screens.portfolio.PortfolioScreen
import com.naleli.tbl.ui.screens.profile.ProfileHubScreen
import com.naleli.tbl.ui.screens.profile.ProfileScreen
import com.naleli.tbl.ui.screens.progress.ProgressScreen
import com.naleli.tbl.ui.screens.qrlookup.QrLookupScreen
import com.naleli.tbl.ui.screens.settings.BackupScreen
import com.naleli.tbl.ui.screens.settings.PrivacyScreen
import com.naleli.tbl.ui.screens.splash.SplashScreen
import com.naleli.tbl.ui.screens.welcome.WelcomeScreen
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft

/**
 * Bottom-nav labels follow the V1.5 information architecture (brief §5):
 * Home / Learn / Work / Portfolio / Profile. Route names underneath stay
 * as MY_LEARNING/EVIDENCE — see NaleliDestinations for why.
 */
private data class BottomNavItem(val route: String, val label: String, val icon: androidx.compose.ui.graphics.vector.ImageVector)

private val bottomNavItems = listOf(
    BottomNavItem(NaleliDestinations.HOME, "Home", Icons.Filled.Home),
    BottomNavItem(NaleliDestinations.MY_LEARNING, "Learn", Icons.Filled.CalendarViewMonth),
    BottomNavItem(NaleliDestinations.EVIDENCE, "Work", Icons.Filled.Assignment),
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
                NavigationBar(containerColor = HeroSurface) {
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
                            colors = NavigationBarItemDefaults.colors(
                                selectedIconColor = OnHeroSurface,
                                selectedTextColor = OnHeroSurface,
                                unselectedIconColor = OnHeroSurfaceSoft,
                                unselectedTextColor = OnHeroSurfaceSoft,
                                indicatorColor = androidx.compose.ui.graphics.Color.White.copy(alpha = 0.12f),
                            ),
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
                ProfileScreen(isEditMode = true, onBack = { navController.popBackStack() }) { navController.popBackStack() }
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
            composable(NaleliDestinations.EVIDENCE) {
                EvidenceScreen(onScanWorksheetCode = { navController.navigate(NaleliDestinations.QR_LOOKUP) })
            }
            composable(NaleliDestinations.QR_LOOKUP) {
                QrLookupScreen(
                    onFound = { dayNumber, taskId ->
                        navController.popBackStack()
                        navController.navigate(NaleliDestinations.taskDetail(dayNumber, taskId))
                    },
                )
            }
            composable(NaleliDestinations.PORTFOLIO) { PortfolioScreen() }
            composable(NaleliDestinations.PROFILE) {
                ProfileHubScreen(
                    onEditProfile = { navController.navigate(NaleliDestinations.EDIT_PROFILE) },
                    onOpenProgress = { navController.navigate(NaleliDestinations.PROGRESS) },
                    onOpenCertificate = { navController.navigate(NaleliDestinations.CERTIFICATE) },
                    onOpenPortfolio = { navController.navigate(NaleliDestinations.PORTFOLIO) },
                    onOpenBackup = { navController.navigate(NaleliDestinations.BACKUP) },
                    onOpenHelp = { navController.navigate(NaleliDestinations.HELP) },
                    onOpenPrivacy = { navController.navigate(NaleliDestinations.PRIVACY) },
                    onDataDeleted = {
                        navController.navigate(NaleliDestinations.WELCOME) { popUpTo(0) { inclusive = true } }
                    },
                )
            }

            composable(
                route = NaleliDestinations.DAY_DETAIL_PATTERN,
                arguments = listOf(navArgument("dayNumber") { type = androidx.navigation.NavType.IntType }),
            ) { backStackEntry ->
                val dayNumber = backStackEntry.arguments?.getInt("dayNumber") ?: 1
                DayDetailScreen(
                    dayNumber = dayNumber,
                    onBack = { navController.popBackStack() },
                    onOpenTask = { taskId -> navController.navigate(NaleliDestinations.taskDetail(dayNumber, taskId)) },
                    onDayCompleted = { navController.popBackStack() },
                )
            }

            composable(
                route = NaleliDestinations.TASK_DETAIL_PATTERN,
                arguments = listOf(
                    navArgument("dayNumber") { type = androidx.navigation.NavType.IntType },
                    navArgument("taskId") { type = androidx.navigation.NavType.StringType },
                ),
            ) { backStackEntry ->
                val dayNumber = backStackEntry.arguments?.getInt("dayNumber") ?: 1
                val taskId = backStackEntry.arguments?.getString("taskId") ?: ""
                TaskDetailScreen(
                    dayNumber = dayNumber,
                    taskId = taskId,
                    onBack = { navController.popBackStack() },
                    onAddEvidence = { navController.navigate(NaleliDestinations.addEvidence(dayNumber, taskId)) },
                    onTaskCompleted = { navController.popBackStack() },
                )
            }

            composable(
                route = NaleliDestinations.ADD_EVIDENCE_PATTERN,
                arguments = listOf(
                    navArgument("dayNumber") { type = androidx.navigation.NavType.IntType },
                    navArgument("taskId") { type = androidx.navigation.NavType.StringType },
                ),
            ) { backStackEntry ->
                val dayNumber = backStackEntry.arguments?.getInt("dayNumber") ?: 1
                val taskId = backStackEntry.arguments?.getString("taskId") ?: ""
                AddEvidenceScreen(
                    dayNumber = dayNumber,
                    taskId = taskId,
                    onBack = { navController.popBackStack() },
                    onDone = { navController.popBackStack() },
                )
            }

            composable(NaleliDestinations.PROGRESS) { ProgressScreen(onBack = { navController.popBackStack() }) }
            composable(NaleliDestinations.CERTIFICATE) { CertificateScreen(onBack = { navController.popBackStack() }) }
            composable(NaleliDestinations.HELP) { HelpScreen(onBack = { navController.popBackStack() }) }
            composable(NaleliDestinations.BACKUP) { BackupScreen(onBack = { navController.popBackStack() }) }
            composable(NaleliDestinations.PRIVACY) { PrivacyScreen(onBack = { navController.popBackStack() }) }
        }
    }
}
