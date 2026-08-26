package com.naleli.tbl.domain

import java.time.LocalDate

/** Programme codes used inside the local learner identifier, e.g. NAL-DF-2026-0001. */
object ProgrammeCodes {
    fun codeFor(programmeId: String): String = when (programmeId) {
        "digital-foundation" -> "DF"
        else -> programmeId.take(2).uppercase()
    }
}

/**
 * Generates a local learner identifier (brief §5): NAL-<programme>-<year>-<seq>.
 * Sequence is always 1 in V1 since only one active learner profile exists
 * per device — the format still reserves room for multiple local profiles
 * later without changing the scheme.
 */
object LearnerCodeGenerator {
    fun generate(programmeId: String, year: Int = LocalDate.now().year, sequence: Int = 1): String {
        val programmeCode = ProgrammeCodes.codeFor(programmeId)
        return "NAL-$programmeCode-$year-${sequence.toString().padStart(4, '0')}"
    }
}
