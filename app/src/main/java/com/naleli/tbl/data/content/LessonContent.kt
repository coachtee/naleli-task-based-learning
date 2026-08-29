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

/**
 * The five stages of a Naleli Task-Based Learning day. A lesson's reading
 * covers the first two; the last three are the work.
 *
 * There is deliberately no CHECK or quiz stage. A quiz can appear inside a
 * lesson where it earns its place, but competence is demonstrated by
 * producing evidence, not by answering questions about it.
 */
enum class LessonStage(val label: String, val ordinal1: Int) {
    UNDERSTAND("Understand", 1),
    SEE("See", 2),
    TRY("Try", 3),
    APPLY("Apply", 4),
    SHOW("Show", 5);

    companion object {
        fun from(raw: String): LessonStage =
            entries.firstOrNull { it.name.equals(raw, ignoreCase = true) } ?: UNDERSTAND
    }
}

/**
 * One screen of a lesson — a single idea, sized to be read without
 * scrolling a wall of text. The reader pages through these rather than
 * presenting the whole lesson as one document.
 */
@Serializable
data class LessonPage(
    val pageId: String,
    /** "understand" or "see" — the reading stages. */
    val stage: String = "understand",
    val title: String = "",
    val blocks: List<ContentBlock> = emptyList(),
) {
    val lessonStage: LessonStage get() = LessonStage.from(stage)
}

/** The Try stage: a small guided exercise with numbered steps and one
 * worked answer, so "practise it" is never left to the learner to invent. */
@Serializable
data class LessonPractice(
    val goal: String = "",
    val steps: List<String> = emptyList(),
    val exampleAnswer: String = "",
)

/**
 * The Apply stage: a workplace assignment with a situation, a numbered
 * task, a stated target, and an explicit deliverable.
 *
 * [submit] deliberately omits the day's own deliverable — that lives on the
 * workbook task and is prepended by the screen, so the mission can never
 * disagree with what the curriculum actually asks for.
 */
@Serializable
data class LessonMission(
    val situation: String = "",
    val steps: List<String> = emptyList(),
    val successCriteria: List<String> = emptyList(),
    val submit: List<String> = emptyList(),
)

@Serializable
data class Lesson(
    val lessonCode: String,
    val moduleNumber: Int = 0,
    val moduleTitle: String = "",
    val title: String,
    /** One sentence naming what the learner will be able to do. Shown on
     * the lesson's landing page in place of the old Brief card. */
    val summary: String = "",
    val sourcePages: List<Int> = emptyList(),
    val pages: List<LessonPage> = emptyList(),
    val practice: LessonPractice? = null,
    val mission: LessonMission? = null,
)

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
