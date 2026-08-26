package com.naleli.tbl.domain

/**
 * Printed-worksheet identifier scheme (brief V1.5 §11): a short code like
 * `DF-D24-T02` printed (as text and/or a future QR code) on a physical
 * worksheet, which the app resolves straight to a task without the
 * student hunting through the day list.
 *
 * `DF-D24-T02` = programme `DF` (Digital Foundation), day 24, task 2.
 *
 * V1.5 ships the identifier scheme, the parser, and a manual-entry lookup
 * screen (fully working today, no new dependency). Camera-based QR
 * auto-scan is architected for but not implemented in this pass — it
 * needs a barcode-decoding library (e.g. ML Kit), which is a deliberate
 * follow-up rather than something to add mid this build-fix pass (see
 * docs/ROADMAP.md).
 */
data class WorksheetCode(
    val programmeCode: String,
    val dayNumber: Int,
    val taskNumber: Int,
) {
    val taskId: String
        get() = "day-%02d-task-%d".format(dayNumber, taskNumber)

    fun format(): String = "$programmeCode-D%02d-T%02d".format(dayNumber, taskNumber)

    companion object {
        private val PATTERN = Regex("""^([A-Za-z]{2,4})-D(\d{1,2})-T(\d{1,2})$""")

        fun parse(raw: String): WorksheetCode? {
            val match = PATTERN.matchEntire(raw.trim().uppercase()) ?: return null
            val (programme, day, task) = match.destructured
            val dayNumber = day.toIntOrNull() ?: return null
            val taskNumber = task.toIntOrNull() ?: return null
            return WorksheetCode(programme, dayNumber, taskNumber)
        }

        fun forTask(programmeCode: String, dayNumber: Int, taskId: String): WorksheetCode? {
            val taskNumber = taskId.substringAfterLast("-task-").toIntOrNull() ?: return null
            return WorksheetCode(programmeCode, dayNumber, taskNumber)
        }
    }
}
