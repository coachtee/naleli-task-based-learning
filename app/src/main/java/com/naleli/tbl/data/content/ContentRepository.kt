package com.naleli.tbl.data.content

import android.content.Context
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import java.io.IOException

/**
 * Loads the content-driven course package from Android assets.
 * content/<programmeId>/course.json and content/<programmeId>/days/day-NN.json
 * are bundled directly from the repository-root /content directory (see
 * app/build.gradle.kts sourceSets.assets.srcDirs) — this class never talks
 * to the network and never hard-codes course text.
 */
class ContentRepository(private val context: Context) {

    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
    }

    private val courseCache = mutableMapOf<String, Course>()
    private val dayCache = mutableMapOf<String, CourseDay?>()

    suspend fun getCourse(programmeId: String): Course = withContext(Dispatchers.IO) {
        courseCache.getOrPut(programmeId) {
            val text = context.assets.open("$programmeId/course.json").bufferedReader().use { it.readText() }
            json.decodeFromString(Course.serializer(), text)
        }
    }

    suspend fun getDay(programmeId: String, dayNumber: Int): CourseDay? = withContext(Dispatchers.IO) {
        val cacheKey = "$programmeId:$dayNumber"
        if (dayCache.containsKey(cacheKey)) return@withContext dayCache[cacheKey]

        val dayFileName = "day-%02d.json".format(dayNumber)
        val day = try {
            val text = context.assets.open("$programmeId/days/$dayFileName").bufferedReader().use { it.readText() }
            json.decodeFromString(CourseDay.serializer(), text)
        } catch (e: IOException) {
            null // Content not yet authored for this day — handled by the UI as "not available yet".
        }
        dayCache[cacheKey] = day
        day
    }

    /** Opens a read-only course resource/download file for streaming. */
    fun openSupportContent(programmeId: String, ref: SupportContentRef) =
        context.assets.open("$programmeId/${ref.location}/${ref.fileName}")

    /**
     * Course resources are read-only (brief §13) — the student always works
     * from their own copy. This copies the bundled asset into app-private
     * storage (overwriting any previous copy) and returns that real file,
     * which the caller can then open/share/edit like any other file.
     */
    suspend fun copyResourceToDevice(
        programmeId: String,
        ref: SupportContentRef,
        destinationDir: java.io.File,
    ): java.io.File = withContext(Dispatchers.IO) {
        destinationDir.mkdirs()
        val destFile = java.io.File(destinationDir, ref.fileName)
        openSupportContent(programmeId, ref).use { input ->
            destFile.outputStream().use { output -> input.copyTo(output) }
        }
        destFile
    }
}
