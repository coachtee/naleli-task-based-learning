package com.naleli.tbl.data.db

import android.content.Context
import androidx.room.Database
import androidx.room.Room
import androidx.room.RoomDatabase
import androidx.room.TypeConverters
import com.naleli.tbl.data.db.dao.CertificateDao
import com.naleli.tbl.data.db.dao.DayProgressDao
import com.naleli.tbl.data.db.dao.EvidenceDao
import com.naleli.tbl.data.db.dao.LearnerProfileDao
import com.naleli.tbl.data.db.dao.PortfolioDao
import com.naleli.tbl.data.db.dao.TaskStatusDao
import com.naleli.tbl.data.db.entity.CertificateEntity
import com.naleli.tbl.data.db.entity.DayProgressEntity
import com.naleli.tbl.data.db.entity.EvidenceEntity
import com.naleli.tbl.data.db.entity.LearnerProfileEntity
import com.naleli.tbl.data.db.entity.PortfolioItemEntity
import com.naleli.tbl.data.db.entity.TaskStatusEntity

@Database(
    entities = [
        LearnerProfileEntity::class,
        DayProgressEntity::class,
        TaskStatusEntity::class,
        EvidenceEntity::class,
        PortfolioItemEntity::class,
        CertificateEntity::class,
    ],
    version = 1,
    exportSchema = true,
)
@TypeConverters(Converters::class)
abstract class NaleliDatabase : RoomDatabase() {
    abstract fun learnerProfileDao(): LearnerProfileDao
    abstract fun dayProgressDao(): DayProgressDao
    abstract fun taskStatusDao(): TaskStatusDao
    abstract fun evidenceDao(): EvidenceDao
    abstract fun portfolioDao(): PortfolioDao
    abstract fun certificateDao(): CertificateDao

    companion object {
        @Volatile private var instance: NaleliDatabase? = null

        fun getInstance(context: Context): NaleliDatabase =
            instance ?: synchronized(this) {
                instance ?: Room.databaseBuilder(
                    context.applicationContext,
                    NaleliDatabase::class.java,
                    "naleli.db",
                ).build().also { instance = it }
            }
    }
}
