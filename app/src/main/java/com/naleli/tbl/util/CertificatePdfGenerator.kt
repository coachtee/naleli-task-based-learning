package com.naleli.tbl.util

import android.content.Context
import android.graphics.Paint
import android.graphics.Typeface
import android.graphics.pdf.PdfDocument
import com.naleli.tbl.data.content.CredentialInfo
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import java.io.File
import java.time.format.DateTimeFormatter
import java.time.LocalDate

/**
 * Generates the local certificate PDF using Android's built-in
 * android.graphics.pdf.PdfDocument — no third-party PDF dependency (brief
 * §18, docs/ARCHITECTURE.md). Only called once
 * CertificateEligibilityEvaluator confirms every configured rule passes.
 */
object CertificatePdfGenerator {

    private const val PAGE_WIDTH = 842 // A4 landscape @ 72dpi
    private const val PAGE_HEIGHT = 595

    fun generate(
        context: Context,
        profile: LearnerProfileEntity,
        credential: CredentialInfo,
        programmeName: String,
        certificateNumber: String,
        completionDate: LocalDate = LocalDate.now(),
    ): File {
        val document = PdfDocument()
        val pageInfo = PdfDocument.PageInfo.Builder(PAGE_WIDTH, PAGE_HEIGHT, 1).create()
        val page = document.startPage(pageInfo)
        val canvas = page.canvas

        val navy = android.graphics.Color.rgb(0x10, 0x18, 0x28)
        val purple = android.graphics.Color.rgb(0x5B, 0x2A, 0x86)
        val grey = android.graphics.Color.rgb(0x47, 0x54, 0x67)

        val borderPaint = Paint().apply { color = purple; style = Paint.Style.STROKE; strokeWidth = 6f }
        canvas.drawRect(24f, 24f, (PAGE_WIDTH - 24).toFloat(), (PAGE_HEIGHT - 24).toFloat(), borderPaint)

        fun text(value: String, y: Float, size: Float, color: Int, bold: Boolean = false, centered: Boolean = true) {
            val paint = Paint().apply {
                this.color = color
                textSize = size
                isAntiAlias = true
                typeface = Typeface.create(Typeface.DEFAULT, if (bold) Typeface.BOLD else Typeface.NORMAL)
                textAlign = if (centered) Paint.Align.CENTER else Paint.Align.LEFT
            }
            val x = if (centered) PAGE_WIDTH / 2f else 80f
            canvas.drawText(value, x, y, paint)
        }

        text(credential.issuingBody, 100f, 22f, purple, bold = true)
        text(credential.campus, 128f, 13f, grey)
        text("CERTIFICATE OF COMPLETION", 190f, 28f, navy, bold = true)
        text(programmeName, 224f, 18f, grey)

        text("This certifies that", 270f, 14f, grey)
        text("${profile.firstName} ${profile.surname}", 310f, 26f, navy, bold = true)
        text("Student Number: ${profile.studentNumber ?: profile.learnerCode}", 336f, 12f, grey)

        text(
            "has successfully completed the ${credential.programmeTitle} programme",
            372f, 14f, navy,
        )

        val dateFormatter = DateTimeFormatter.ofPattern("d MMMM yyyy")
        text("Completion date: ${completionDate.format(dateFormatter)}", 420f, 12f, grey)
        text("Certificate number: $certificateNumber", 440f, 12f, grey)

        document.finishPage(page)

        val dir = File(context.filesDir, "certificates").apply { mkdirs() }
        val file = File(dir, "$certificateNumber.pdf")
        file.outputStream().use { document.writeTo(it) }
        document.close()
        return file
    }
}
