package com.naleli.tbl.ui.components

import android.graphics.BitmapFactory
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.naleli.tbl.ui.theme.OnHeroSurfaceSoft

/**
 * A lesson illustration, diagram or screenshot, loaded from the app's own
 * assets.
 *
 * Deliberately not a network image loader. Naleli Workspace is offline-first
 * for learners who may have no data at all, and the app holds no INTERNET
 * permission — an illustration that only appears on Wi-Fi is worse than no
 * illustration, because the lesson silently loses the thing that was meant
 * to explain it.
 *
 * Stock photography (Unsplash, Pexels) and diagrams therefore ride into the
 * app at content-build time: the converter fetches and downsamples them into
 * content/<programme>/images/, and the content file references the asset
 * path. See docs/CONTENT-MODEL.md.
 *
 * A missing or unreadable image renders as nothing rather than a broken
 * placeholder: the lesson text still reads correctly without it.
 */
@Composable
fun LessonImage(assetPath: String, caption: String, modifier: Modifier = Modifier) {
    if (assetPath.isBlank()) return
    val context = LocalContext.current

    val bitmap: ImageBitmap? = remember(assetPath) {
        runCatching {
            context.assets.open(assetPath).use { BitmapFactory.decodeStream(it) }?.asImageBitmap()
        }.getOrNull()
    }
    if (bitmap == null) return

    Column(modifier.fillMaxWidth().padding(vertical = 10.dp)) {
        Image(
            bitmap = bitmap,
            contentDescription = caption.ifBlank { null },
            modifier = Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)),
            contentScale = ContentScale.FillWidth,
        )
        if (caption.isNotBlank()) {
            Spacer(Modifier.height(8.dp))
            Text(
                caption,
                style = MaterialTheme.typography.labelSmall,
                color = OnHeroSurfaceSoft,
            )
        }
    }
}
