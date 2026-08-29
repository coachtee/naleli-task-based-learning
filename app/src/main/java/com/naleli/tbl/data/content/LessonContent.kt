package com.naleli.tbl.data.content

import kotlinx.serialization.Serializable

/**
 * Structured lesson content: a lesson is an ordered list of typed blocks,
 * and the reading screen knows how to lay out each type. No lesson text
 * lives in Kotlin — it is authored in the course export and converted by
 * source/build_lesson_content.py into
 * content/digital-foundation/lessons.json, which ships as an Android asset.
 *
 * [ContentBlock] is one flat type with a `type` discriminator rather than a
 * sealed hierarchy. That is deliberate: content is authored outside the app
 * and will grow new block types before the app knows about them. A flat
 * block with optional fields lets an unknown type be skipped by the
 * renderer, where a sealed hierarchy would fail the whole parse and leave
 * the learner with an empty lesson.
 */

/** Every block type the reading screen can render. Anything else in the
 * content file is ignored rather than treated as an error — see above. */
object BlockType {
    const val HEADING = "heading"
    const val PARAGRAPH = "paragraph"
    const val LIST = "list"
    const val LEARNING_OUTCOMES = "learningOutcomes"
    const val KEY_CONCEPT = "keyConcept"
    const val EXAMPLE = "example"
    const val IMAGE = "image"
    const val VIDEO = "video"
    const val REFLECTION = "reflection"
    const val PRACTICE = "practice"
    const val TASK = "task"
    const val RESOURCE = "resource"
}

@Serializable
data class ContentBlock(
    val type: String,
    val text: String = "",
    val title: String = "",
    val items: List<String> = emptyList(),
    val url: String = "",
    val caption: String = "",
)

@Serializable
data class Lesson(
    val lessonCode: String,
    val moduleNumber: Int = 0,
    val moduleTitle: String = "",
    val title: String,
    val sourcePages: List<Int> = emptyList(),
    val blocks: List<ContentBlock> = emptyList(),
) {
    /** Reading blocks only — the count the progress indicator divides by. */
    val readingBlockCount: Int get() = blocks.count { it.type != BlockType.LEARNING_OUTCOMES }
}

@Serializable
data class LessonPackage(
    val programmeId: String = "digital-foundation",
    val courseTitle: String = "",
    val provider: String = "",
    val lessons: List<Lesson> = emptyList(),
) {
    fun byCode(lessonCode: String): Lesson? = lessons.firstOrNull { it.lessonCode == lessonCode }

    companion object {
        val EMPTY = LessonPackage(lessons = emptyList())
    }
}
