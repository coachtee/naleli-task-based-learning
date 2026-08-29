package com.naleli.tbl.data.content

import android.content.Context
import kotlinx.serialization.json.Json

/**
 * The lesson reading content, loaded once for the process — the same
 * contract as [WorkspaceCurriculum], which holds the 90-day task
 * curriculum.
 *
 * The two are separate on purpose. WorkspaceCurriculum is what the learner
 * has to *do*; this is what they *read* first. They join by lesson code: a
 * workbook day carries `lessonCodes` (["1A"], or ["1D", "2A"] on the ten
 * days that cover two lessons), and [lessonsForTask] resolves them.
 *
 * This asset is larger than the curriculum (~1.4 MB), so unlike
 * WorkspaceCurriculum it is loaded lazily on first use rather than in
 * AppContainer's init: nothing on Home, My Work, Journey or Portfolio
 * needs it, so paying for it during app start would slow every launch for
 * a screen the learner may not open.
 */
object LessonLibrary {

    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
    }

    @Volatile
    private var loaded: LessonPackage? = null

    /** Parses on first call, then serves the cached package. Safe to call
     * from any thread; a lost race just parses twice and keeps one result. */
    fun content(context: Context, programmeId: String = "digital-foundation"): LessonPackage {
        loaded?.let { return it }
        val parsed = runCatching {
            val text = context.assets
                .open("$programmeId/lessons.json")
                .bufferedReader()
                .use { it.readText() }
            json.decodeFromString(LessonPackage.serializer(), text)
        }.getOrElse {
            // A missing or malformed lesson asset must not crash the app —
            // the reading screen renders "no lesson content" and the task
            // itself, which is the part the learner is assessed on, is
            // unaffected.
            LessonPackage.EMPTY
        }
        loaded = parsed
        return parsed
    }

    fun lesson(context: Context, lessonCode: String): Lesson? =
        content(context).byCode(lessonCode)

    /** Every lesson a curriculum day is built on, in the day's own order. */
    fun lessonsForTask(context: Context, taskId: String): List<Lesson> {
        val codes = WorkspaceCurriculum.taskById(taskId)?.lessonCodes.orEmpty()
        val package_ = content(context)
        return codes.mapNotNull { package_.byCode(it) }
    }
}
