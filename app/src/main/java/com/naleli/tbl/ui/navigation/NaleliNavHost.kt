package com.naleli.tbl.ui.navigation

import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Assignment
import androidx.compose.material.icons.filled.Explore
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
import com.naleli.tbl.ui.screens.assessment.AssessmentScreen
import com.naleli.tbl.ui.screens.evidence.AddEvidenceScreen
import com.naleli.tbl.ui.screens.help.HelpScreen
import com.naleli.tbl.ui.screens.home.HomeScreen
import com.naleli.tbl.ui.screens.journey.JourneyScreen
import com.naleli.tbl.ui.screens.mywork.MyWorkScreen
import com.naleli.tbl.ui.screens.onboarding.PortfolioSetupScreen
import com.naleli.tbl.ui.screens.portfolio.PortfolioScreen
import com.naleli.tbl.ui.screens.profile.ProfileHubScreen
import com.naleli.tbl.ui.screens.profile.ProfileScreen
import com.naleli.tbl.ui.screens.settings.BackupScreen
import com.naleli.tbl.ui.screens.settings.PrivacyScreen
import com.naleli.tbl.ui.screens.welcome.WelcomeScreen
import com.naleli.tbl.ui.screens.workspace.TaskWorkspaceScreen
import com.naleli.tbl.ui.theme.HeroSurface
import com.naleli.tbl.ui.theme.NibsOrange
import com.naleli.tbl.ui.theme.OnHeroSurface
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft

/**
 * Bottom-nav for Naleli Workspace: Home / My Work / Journey / Portfolio /
 * Me — never "Profile" (brief: navigation).
 */
private data class BottomNavItem(val route: String, val label: String, val icon: androidx.compose.ui.graphics.vector.ImageVector)

private val bottomNavItems = listOf(
    BottomNavItem(NaleliDestinations.HOME, "Home", Icons.Filled.Home),
    BottomNavItem(NaleliDestinations.MY_WORK, "My Work", Icons.Filled.Assignment),
    BottomNavItem(NaleliDestinations.JOURNEY, "Journey", Icons.Filled.Explore),
    BottomNavItem(NaleliDestinations.PORTFOLIO, "Portfolio", Icons.Filled.WorkspacePremium),
    BottomNavItem(NaleliDestinations.PROFILE, "Me", Icons.Filled.Person),
)

@Composable
fun NaleliNavHost(
    startDestination: String,
    navController: NavHostController = rememberNavController(),
) {
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
                                // Orange marks the active destination, the
                                // same accent the hero CTA uses.
                                indicatorColor = NibsOrange,
                            ),
                        )
                    }
                }
            }
        },
    ) { padding ->
        NavHost(
            navController = navController,
            startDestination = startDestination,
            modifier = Modifier.padding(padding),
        ) {
            composable(NaleliDestinations.WELCOME) {
                WelcomeScreen(onCreateProfile = { navController.navigate(NaleliDestinations.CREATE_PROFILE) })
            }
            composable(NaleliDestinations.CREATE_PROFILE) {
                ProfileScreen(isEditMode = false) {
                    navController.navigate(NaleliDestinations.PORTFOLIO_SETUP) {
                        popUpTo(navController.graph.findStartDestination().id) { inclusive = true }
                    }
                }
            }
            composable(NaleliDestinations.PORTFOLIO_SETUP) {
                PortfolioSetupScreen(
                    onDone = {
                        navController.navigate(NaleliDestinations.HOME) {
                            popUpTo(navController.graph.findStartDestination().id) { inclusive = true }
                        }
                    },
                )
            }
            composable(NaleliDestinations.EDIT_PROFILE) {
                ProfileScreen(isEditMode = true, onBack = { navController.popBackStack() }) { navController.popBackStack() }
            }

            composable(NaleliDestinations.HOME) {
                HomeScreen(
                    onOpenTask = { taskId -> navController.navigate(NaleliDestinations.taskWorkspace(taskId)) },
                    onOpenPortfolio = { navController.navigate(NaleliDestinations.PORTFOLIO) },
                )
            }
            composable(NaleliDestinations.MY_WORK) {
                MyWorkScreen(onOpenTask = { taskId -> navController.navigate(NaleliDestinations.taskWorkspace(taskId)) })
            }
            composable(NaleliDestinations.JOURNEY) {
                JourneyScreen(onOpenTask = { taskId -> navController.navigate(NaleliDestinations.taskWorkspace(taskId)) })
            }
            composable(NaleliDestinations.PORTFOLIO) { PortfolioScreen() }
            composable(NaleliDestinations.PROFILE) {
                ProfileHubScreen(
                    onEditProfile = { navController.navigate(NaleliDestinations.EDIT_PROFILE) },
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
                route = NaleliDestinations.TASK_WORKSPACE_PATTERN,
                arguments = listOf(navArgument("taskId") { type = androidx.navigation.NavType.StringType }),
            ) { backStackEntry ->
                val taskId = backStackEntry.arguments?.getString("taskId") ?: ""
                TaskWorkspaceScreen(
                    taskId = taskId,
                    onBack = { navController.popBackStack() },
                    onAddEvidence = { navController.navigate(NaleliDestinations.addEvidence(taskId)) },
                    onSubmitted = { navController.navigate(NaleliDestinations.assessment(taskId)) },
                )
            }

            composable(
                route = NaleliDestinations.ASSESSMENT_PATTERN,
                arguments = listOf(navArgument("taskId") { type = androidx.navigation.NavType.StringType }),
            ) { backStackEntry ->
                val taskId = backStackEntry.arguments?.getString("taskId") ?: ""
                AssessmentScreen(
                    taskId = taskId,
                    onBack = { navController.popBackStack() },
                    onOpenPortfolio = { navController.navigate(NaleliDestinations.PORTFOLIO) },
                    // Continuing the journey clears the finished task's
                    // workspace and assessment off the back stack: Back
                    // from the next task belongs at Home, not inside work
                    // the learner has already been assessed competent on.
                    onContinueJourney = { nextTaskId ->
                        val destination = nextTaskId?.let { NaleliDestinations.taskWorkspace(it) }
                            ?: NaleliDestinations.JOURNEY
                        navController.navigate(destination) {
                            popUpTo(navController.graph.findStartDestination().id) { saveState = true }
                            launchSingleTop = true
                        }
                    },
                )
            }

            composable(
                route = NaleliDestinations.ADD_EVIDENCE_PATTERN,
                arguments = listOf(navArgument("taskId") { type = androidx.navigation.NavType.StringType }),
            ) { backStackEntry ->
                val taskId = backStackEntry.arguments?.getString("taskId") ?: ""
                val taskTitle = com.naleli.tbl.data.content.WorkspaceCurriculum.taskById(taskId)?.title ?: "Task"
                AddEvidenceScreen(
                    taskId = taskId,
                    taskTitle = taskTitle,
                    onBack = { navController.popBackStack() },
                    onDone = { navController.popBackStack() },
                )
            }

            composable(NaleliDestinations.HELP) { HelpScreen(onBack = { navController.popBackStack() }) }
            composable(NaleliDestinations.BACKUP) { BackupScreen(onBack = { navController.popBackStack() }) }
            composable(NaleliDestinations.PRIVACY) { PrivacyScreen(onBack = { navController.popBackStack() }) }
        }
    }
}
