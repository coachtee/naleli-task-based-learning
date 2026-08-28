package com.naleli.tbl.data.content

/**
 * Naleli Workspace content (mock-data-first pass, per the Workspace build
 * brief): Phase → Workstream → Task → Sub-step. This deliberately does not
 * reuse the day-based CourseDay/CourseTask model above — that model is
 * "Day 1..90, 3-4 tasks each"; this one is "a phase opens into workstreams,
 * each workstream holds a handful of real tasks." Kotlin-defined rather than
 * JSON for now, matching the brief's "build using realistic mock data
 * first" — trivial to move to a JSON asset once the full 90-day curriculum
 * for this model is authored, following the same pattern as ContentRepository.
 */

enum class TaskTier { REQUIRED, SUPPORTING, ASSESSMENT }

data class WorkSubStep(
    val subStepId: String,
    val title: String,
    val estimatedMinutes: Int,
)

data class WorkTask(
    val taskId: String,
    val title: String,
    val tier: TaskTier,
    val estimatedMinutes: Int,
    val whatYoureDoing: String,
    val whyItMatters: String,
    val skillDeveloped: String,
    val understandText: String,
    val watchLabel: String?,
    val practiseText: String,
    val assignmentText: String,
    val deliverableLabel: String,
    val subSteps: List<WorkSubStep>,
    val assessmentCriteria: List<String>,
)

data class Workstream(
    val workstreamId: String,
    val name: String,
    val tasks: List<WorkTask>,
)

object WorkspaceMockContent {

    /** Which CourseStage (course.json) this workstream set belongs to. */
    const val PHASE_1_STAGE_ID = "stage-1"

    /** Full curriculum scope for Phase 1 isn't authored yet — this is the
     * planned total used for the "X of Y tasks" display, honestly larger
     * than what's actually modelled below. */
    const val PHASE_1_PLANNED_TASK_COUNT = 24

    /** The one task left fresh for a real, end-to-end interactive walk-through. */
    const val LIVE_TASK_ID = "task-word-letter"

    /** Pre-seeded as already-completed-and-competent, so a fresh install
     * shows a lived-in workspace rather than an empty Day 1 (per the brief's
     * "mid-journey state rather than an empty Day 1 screen"). */
    val SEED_COMPLETE_TASK_IDS = listOf(
        "task-digital-workplace-1",
        "task-communication-1",
        "task-file-management",
    )

    /** A locked task's single prerequisite — the task that must be COMPETENT
     * before it unlocks. A small, real dependency graph, not a hardcoded flag. */
    val UNLOCK_REQUIRES: Map<String, String> = mapOf(
        "task-word-checkpoint" to "task-word-letter",
        "task-first-portfolio-entry" to "task-file-management",
    )

    val phase1Workstreams: List<Workstream> = listOf(
        Workstream(
            workstreamId = "ws-digital-workplace",
            name = "Digital Workplace Foundations",
            tasks = listOf(
                WorkTask(
                    taskId = "task-digital-workplace-1",
                    title = "Understand Your Digital Workplace",
                    tier = TaskTier.REQUIRED,
                    estimatedMinutes = 15,
                    whatYoureDoing = "Getting oriented in the tools and spaces you'll work in every day.",
                    whyItMatters = "Every digital role starts with knowing what you're looking at before you're expected to be productive in it.",
                    skillDeveloped = "Digital Workplace Literacy",
                    understandText = "A short read on what a digital workplace is and the tools inside it.",
                    watchLabel = "What Is a Digital Workplace?",
                    practiseText = "Explore a sample digital workspace and identify its key tools.",
                    assignmentText = "Write down three tools you think you'll use in a digital workplace, and one question you still have.",
                    deliverableLabel = "A short written response (2-3 sentences)",
                    subSteps = listOf(
                        WorkSubStep("sub-dw-1-1", "Read the orientation brief", 5),
                        WorkSubStep("sub-dw-1-2", "List your three tools", 5),
                        WorkSubStep("sub-dw-1-3", "Submit your response", 5),
                    ),
                    assessmentCriteria = listOf(
                        "Names at least three real tools",
                        "Question is specific, not generic",
                        "Response is submitted in full",
                    ),
                ),
            ),
        ),
        Workstream(
            workstreamId = "ws-communication",
            name = "Professional Communication",
            tasks = listOf(
                WorkTask(
                    taskId = "task-communication-1",
                    title = "Write a Professional Email",
                    tier = TaskTier.REQUIRED,
                    estimatedMinutes = 20,
                    whatYoureDoing = "Drafting a real work email to a colleague about a shared task.",
                    whyItMatters = "Clear, professional email is one of the most-used skills in any digital role.",
                    skillDeveloped = "Professional Communication",
                    understandText = "The structure of a professional email: greeting, purpose, action, close.",
                    watchLabel = "Anatomy of a Professional Email",
                    practiseText = "Rewrite a casual message into a professional one.",
                    assignmentText = "Write an email to a colleague asking them to review a document by Friday.",
                    deliverableLabel = "One email (subject line and body)",
                    subSteps = listOf(
                        WorkSubStep("sub-comm-1-1", "Review the email structure", 5),
                        WorkSubStep("sub-comm-1-2", "Draft your email", 10),
                        WorkSubStep("sub-comm-1-3", "Proofread and submit", 5),
                    ),
                    assessmentCriteria = listOf(
                        "Clear subject line",
                        "Polite, direct tone",
                        "States the action needed and the deadline",
                    ),
                ),
            ),
        ),
        Workstream(
            workstreamId = "ws-word-foundations",
            name = "Microsoft Word Foundations",
            tasks = listOf(
                WorkTask(
                    taskId = "task-word-letter",
                    title = "Prepare a Professional Business Letter",
                    tier = TaskTier.REQUIRED,
                    estimatedMinutes = 25,
                    whatYoureDoing = "Formatting a real business letter responding to a client brief.",
                    whyItMatters = "Correctly formatted documents are one of the most visible signs of workplace professionalism.",
                    skillDeveloped = "Digital Documents",
                    understandText = "The standard layout of a professional business letter: sender details, date, recipient, body, sign-off.",
                    watchLabel = "Formatting a Business Letter in Word",
                    practiseText = "Format a short sample paragraph using the standard letter layout.",
                    assignmentText = "Write a professional business letter responding to the client brief provided, using the correct layout and tone.",
                    deliverableLabel = "Professional Business Letter (.docx)",
                    subSteps = listOf(
                        WorkSubStep("sub-word-letter-1", "Review document requirements", 10),
                        WorkSubStep("sub-word-letter-2", "Create and format the letter", 25),
                        WorkSubStep("sub-word-letter-3", "Submit for assessment", 5),
                    ),
                    assessmentCriteria = listOf(
                        "Correct structure",
                        "Required information included",
                        "Professional formatting",
                        "Correct file naming",
                    ),
                ),
                WorkTask(
                    taskId = "task-word-checkpoint",
                    title = "Word Foundations Checkpoint",
                    tier = TaskTier.ASSESSMENT,
                    estimatedMinutes = 20,
                    whatYoureDoing = "A short assessed exercise covering everything in this workstream.",
                    whyItMatters = "Checkpoints confirm a skill is solid before the next workstream builds on it.",
                    skillDeveloped = "Digital Documents",
                    understandText = "A recap of Word Foundations: layout, formatting, and document structure.",
                    watchLabel = null,
                    practiseText = "Skim your completed letter for anything you'd do differently now.",
                    assignmentText = "Format a one-page memo using everything covered in this workstream.",
                    deliverableLabel = "One-page memo (.docx)",
                    subSteps = listOf(
                        WorkSubStep("sub-word-cp-1", "Review your prior work", 5),
                        WorkSubStep("sub-word-cp-2", "Format the memo", 10),
                        WorkSubStep("sub-word-cp-3", "Submit for assessment", 5),
                    ),
                    assessmentCriteria = listOf(
                        "Correct memo structure",
                        "Consistent formatting",
                        "No layout errors",
                    ),
                ),
            ),
        ),
        Workstream(
            workstreamId = "ws-productivity",
            name = "Digital Productivity",
            tasks = listOf(
                WorkTask(
                    taskId = "task-file-management",
                    title = "Organise Your Digital Files",
                    tier = TaskTier.SUPPORTING,
                    estimatedMinutes = 15,
                    whatYoureDoing = "Setting up a simple, sensible folder structure for your own work.",
                    whyItMatters = "Being able to find your own files quickly is a basic expectation in any digital role.",
                    skillDeveloped = "Digital Productivity",
                    understandText = "A short guide to naming and organising files so anyone can find them.",
                    watchLabel = null,
                    practiseText = "Rename a set of badly-named sample files.",
                    assignmentText = "Set up a folder structure for your own NTBL work, and rename your existing files to match.",
                    deliverableLabel = "A screenshot of your folder structure",
                    subSteps = listOf(
                        WorkSubStep("sub-files-1", "Plan your folder structure", 5),
                        WorkSubStep("sub-files-2", "Create the folders", 5),
                        WorkSubStep("sub-files-3", "Submit a screenshot", 5),
                    ),
                    assessmentCriteria = listOf(
                        "Folders are clearly named",
                        "Structure is easy to navigate",
                    ),
                ),
            ),
        ),
        Workstream(
            workstreamId = "ws-evidence-assessment",
            name = "Workplace Evidence and Assessment",
            tasks = listOf(
                WorkTask(
                    taskId = "task-first-portfolio-entry",
                    title = "Build Your First Portfolio Entry",
                    tier = TaskTier.ASSESSMENT,
                    estimatedMinutes = 20,
                    whatYoureDoing = "Turning one piece of completed work into a portfolio-ready entry.",
                    whyItMatters = "A portfolio only works if you can clearly explain what a piece of evidence demonstrates.",
                    skillDeveloped = "Workplace Evidence",
                    understandText = "What makes evidence portfolio-ready: the work itself, plus a short note on what it shows.",
                    watchLabel = null,
                    practiseText = "Read two example portfolio entries and note what makes them clear.",
                    assignmentText = "Choose one piece of completed work and write a short note explaining what skill it demonstrates.",
                    deliverableLabel = "Evidence file plus a short written note",
                    subSteps = listOf(
                        WorkSubStep("sub-portfolio-1", "Choose your evidence", 5),
                        WorkSubStep("sub-portfolio-2", "Write your note", 10),
                        WorkSubStep("sub-portfolio-3", "Submit for assessment", 5),
                    ),
                    assessmentCriteria = listOf(
                        "Evidence is genuinely the learner's own work",
                        "Note clearly explains the skill shown",
                    ),
                ),
            ),
        ),
    )

    fun allTasks(): List<WorkTask> = phase1Workstreams.flatMap { it.tasks }
    fun taskById(taskId: String): WorkTask? = allTasks().firstOrNull { it.taskId == taskId }
    fun workstreamFor(taskId: String): Workstream? = phase1Workstreams.firstOrNull { ws -> ws.tasks.any { it.taskId == taskId } }
}
