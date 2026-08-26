# Naleli Task-Based Learning — release ProGuard/R8 rules.
# kotlinx.serialization needs its generated serializers kept.
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.AnnotationsKt

-keepclassmembers class kotlinx.serialization.json.** {
    *** Companion;
}
-keepclasseswithmembers class kotlinx.serialization.json.** {
    kotlinx.serialization.KSerializer serializer(...);
}

-keep,includedescriptorclasses class com.naleli.tbl.**$$serializer { *; }
-keepclassmembers class com.naleli.tbl.** {
    *** Companion;
}
-keepclasseswithmembers class com.naleli.tbl.** {
    kotlinx.serialization.KSerializer serializer(...);
}
