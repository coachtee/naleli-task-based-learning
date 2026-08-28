package com.naleli.tbl.data.db

import androidx.room.TypeConverter
import com.naleli.tbl.data.db.entity.AssessmentStatus
import com.naleli.tbl.data.db.entity.CompetenceResult
import com.naleli.tbl.data.db.entity.DayStatus

class Converters {
    @TypeConverter
    fun fromDayStatus(value: DayStatus): String = value.name

    @TypeConverter
    fun toDayStatus(value: String): DayStatus = DayStatus.valueOf(value)

    @TypeConverter
    fun fromAssessmentStatus(value: AssessmentStatus): String = value.name

    @TypeConverter
    fun toAssessmentStatus(value: String): AssessmentStatus = AssessmentStatus.valueOf(value)

    @TypeConverter
    fun fromCompetenceResult(value: CompetenceResult): String = value.name

    @TypeConverter
    fun toCompetenceResult(value: String): CompetenceResult = CompetenceResult.valueOf(value)
}
