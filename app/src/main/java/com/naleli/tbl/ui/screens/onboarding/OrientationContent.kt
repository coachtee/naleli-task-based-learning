package com.naleli.tbl.ui.screens.onboarding

/**
 * The orientation a learner sees once, between creating their profile and
 * opening Day 1.
 *
 * It exists because Naleli Workspace is not a course you watch. A learner
 * who starts at Lesson 1 without this has no way to know why they are being
 * asked to produce evidence, or what the portfolio at the end is for — and
 * the first thing that would teach them is the assessment, which is far too
 * late.
 *
 * Kept as data rather than six hand-built screens: the sequence is a list,
 * so adding, cutting or reordering a step is an edit here, not a change to
 * the pager, the progress dots, or the navigation.
 */

/** How a step's body is laid out. The flow diagram and the principle are
 * genuinely different shapes, not paragraphs with different text. */
enum class OrientationLayout { PROSE, FLOW, PRINCIPLE }

data class OrientationStep(
    val eyebrow: String,
    val title: String,
    val body: String,
    val layout: OrientationLayout = OrientationLayout.PROSE,
    /** Bullets for PROSE, the sequence for FLOW, the three lines for
     * PRINCIPLE. */
    val points: List<String> = emptyList(),
    val closing: String = "",
)

object OrientationContent {

    /** The sequence the brief specifies, in order. The last step's button
     * reads "Begin My Learning Journey"; every other step continues. */
    val steps: List<OrientationStep> = listOf(
        OrientationStep(
            eyebrow = "WELCOME",
            title = "You are not here to finish a course",
            body = "Naleli Workspace is a place of work, not a playlist. Over the next 90 days " +
                "you will build three things at once — what you know, what you can do, and the " +
                "evidence that proves it.",
            points = listOf(
                "Knowledge you can explain in your own words",
                "Practical skill you have actually performed",
                "Evidence a real employer can look at",
            ),
            closing = "Finishing is not the goal. Being able to do the work is.",
        ),
        OrientationStep(
            eyebrow = "YOUR FIELD",
            title = "What is Digital Operations?",
            body = "Every organisation runs on digital tools, information and processes — files " +
                "that must be found, data that must be right, systems that must stay secure, " +
                "messages that must reach the right person.",
            points = listOf(
                "Tools — the software people work in every day",
                "Systems — the devices and networks that connect them",
                "Information — the files, records and data an organisation depends on",
                "Processes — the steps that keep work consistent and repeatable",
            ),
            closing = "Digital Operations is the work of keeping all four running well. " +
                "It is a profession, and it is what you are training for.",
        ),
        OrientationStep(
            eyebrow = "THE JOURNEY",
            title = "What happens during and after",
            body = "Each day moves through the same sequence. Nothing is skipped, and each " +
                "stage produces something the next one needs.",
            layout = OrientationLayout.FLOW,
            points = listOf(
                "Learn",
                "Practise",
                "Complete tasks",
                "Build evidence",
                "Demonstrate competence",
                "Build professional portfolio",
            ),
            closing = "By Day 90 the portfolio is not a certificate. It is a body of work.",
        ),
        OrientationStep(
            eyebrow = "THE METHOD",
            title = "What is Naleli Task-Based Learning?",
            body = "You will not only consume content. Every day, you:",
            points = listOf(
                "Understand a concept",
                "Practise the skill",
                "Complete a realistic workplace-style task",
                "Submit evidence of what you produced",
                "Demonstrate competence against clear criteria",
            ),
            closing = "The task is not a test at the end of the learning. The task is the learning.",
        ),
        OrientationStep(
            eyebrow = "THE REASON",
            title = "Why we learn this way",
            body = "Watching a video does not make you able to do something. Passing a quiz " +
                "does not either. Both can be true while the actual work is still beyond you.",
            layout = OrientationLayout.PRINCIPLE,
            points = listOf(
                "Knowledge tells you what.",
                "Practice helps you understand how.",
                "Tasks allow you to demonstrate that you can do it.",
            ),
            closing = "So we assess what you produced, not what you watched.",
        ),
        OrientationStep(
            eyebrow = "YOUR EVIDENCE",
            title = "Your portfolio builds itself",
            body = "Every task you complete and submit becomes part of your professional " +
                "record. Nothing is thrown away when a day ends.",
            points = listOf(
                "Your files stay on your device, under your control",
                "Assessed work is recorded against the skill it demonstrates",
                "Your portfolio grows from real work, never from a score",
            ),
            closing = "When someone asks what you can do, you will be able to show them.",
        ),
    )

    const val FINAL_BUTTON = "BEGIN MY LEARNING JOURNEY"
    const val CONTINUE_BUTTON = "CONTINUE"
}
