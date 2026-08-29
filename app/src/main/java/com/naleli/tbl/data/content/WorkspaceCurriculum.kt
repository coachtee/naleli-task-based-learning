package com.naleli.tbl.data.content

import android.content.Context
import kotlinx.serialization.json.Json

/**
 * The loaded 90-day curriculum, held once for the process.
 *
 * Curriculum content is a fixed, read-only asset — it never changes while
 * the app runs — so a single loaded copy is legitimate shared state rather
 * than mutable app state. Holding it here (instead of threading a package
 * parameter through every calculator and ViewModel) keeps the pure
 * functions in domain/WorkspaceCalculators.kt callable without a content
 * argument, which is what the whole UI already assumes.
 *
 * [load] is called once from AppContainer, which NaleliApplication builds in
 * onCreate — so the content is parsed before any screen composes. The parse
 * is synchronous: it is a single ~320 KB asset, and doing it up front avoids
 * every reader having to handle a not-loaded-yet state. If start-up cost
 * ever matters, this is the one place to make it async.
 */
object WorkspaceCurriculum {

    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
    }

    @Volatile
    private var loaded: WorkspaceContentPackage = WorkspaceContentPackage.EMPTY

    /** The whole package, for callers that need stage-level queries. */
    val content: WorkspaceContentPackage get() = loaded

    fun load(context: Context, programmeId: String = "digital-foundation") {
        loaded = runCatching {
            val text = context.assets
                .open("$programmeId/workspace-content.json")
                .bufferedReader()
                .use { it.readText() }
            json.decodeFromString(WorkspaceContentPackage.serializer(), text)
        }.getOrElse {
            // A missing or malformed content asset must not crash the app on
            // launch — every screen already renders an empty curriculum as
            // "nothing available yet".
            WorkspaceContentPackage.EMPTY
        }
    }

    fun allTasks(): List<WorkTask> = loaded.allTasks

    fun taskById(taskId: String): WorkTask? = loaded.taskById(taskId)

    fun workstreamFor(taskId: String): Workstream? = loaded.workstreamFor(taskId)

    fun workstreamsForStage(stageId: String): List<Workstream> = loaded.workstreamsForStage(stageId)

    fun tasksForStage(stageId: String): List<WorkTask> = loaded.tasksForStage(stageId)

    /** The task that must be COMPETENT before [taskId] unlocks, or null if
     * it is the first day. Sequential, per course.json's SEQUENTIAL_UNLOCK. */
    fun prerequisiteFor(taskId: String): String? = loaded.prerequisiteFor(taskId)

    /** The stage a task belongs to, via its workstream. */
    fun stageIdFor(taskId: String): String? = loaded.workstreamFor(taskId)?.stageId
}
