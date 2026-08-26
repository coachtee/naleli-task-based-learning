package com.naleli.tbl.domain

import com.naleli.tbl.data.content.Course
import com.naleli.tbl.data.db.entity.DayStatus
import com.naleli.tbl.data.db.entity.DayProgressEntity

data class EligibilityRuleResult(val label: String, val satisfied: Boolean)

data class CertificateEligibilityResult(
    val rules: List<EligibilityRuleResult>,
) {
    val isEligible: Boolean get() = rules.all { it.satisfied }
}

/**
 * Evaluates certificate eligibility purely from course.json's configured
 * rules (brief §18) — never hard-codes "must finish 90 days" in a button.
 * With V1 shipping only Days 1-7 of content, requireAllDaysComplete can
 * never be satisfied yet; that is the correct, honest behaviour.
 */
object CertificateEligibilityEvaluator {

    fun evaluate(
        course: Course,
        dayProgress: List<DayProgressEntity>,
        portfolioItemCount: Int,
    ): CertificateEligibilityResult {
        val rules = mutableListOf<EligibilityRuleResult>()
        val config = course.certificateEligibility

        if (config.requireAllDaysComplete) {
            val allComplete = course.totalDays > 0 &&
                (1..course.totalDays).all { day ->
                    dayProgress.firstOrNull { it.dayNumber == day }?.status == DayStatus.COMPLETE
                }
            rules += EligibilityRuleResult("All ${course.totalDays} days complete", allComplete)
        }

        if (config.requireCapstoneComplete) {
            val capstoneStage = course.stages.firstOrNull { it.stageId == "stage-4" }
            val capstoneComplete = capstoneStage != null && (capstoneStage.dayStart..capstoneStage.dayEnd).all { day ->
                dayProgress.firstOrNull { it.dayNumber == day }?.status == DayStatus.COMPLETE
            }
            rules += EligibilityRuleResult("Capstone & Portfolio stage complete", capstoneComplete)
        }

        if (config.requireFinalAssessmentComplete) {
            // V1 has no assessor flow yet — this rule is included so the
            // checklist is honest about what final certification requires,
            // and stays unsatisfied until that flow exists (see docs/ROADMAP.md).
            rules += EligibilityRuleResult("Final assessment marked competent", false)
        }

        if (config.minimumPortfolioItems > 0) {
            rules += EligibilityRuleResult(
                "At least ${config.minimumPortfolioItems} portfolio item(s)",
                portfolioItemCount >= config.minimumPortfolioItems,
            )
        }

        return CertificateEligibilityResult(rules)
    }
}
