package com.adgraph.yumma_vendor

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Intent
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat

class OrderAlertForegroundService : Service() {
    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val status = intent?.getStringExtra(EXTRA_STATUS) ?: "Online and listening for orders"
        val fullScreen = intent?.getBooleanExtra(EXTRA_FULL_SCREEN, false) ?: false
        ensureChannel()
        startForeground(NOTIFICATION_ID, buildNotification(status, fullScreen))
        return START_STICKY
    }

    private fun buildNotification(status: String, fullScreen: Boolean): Notification {
        val launchIntent = packageManager.getLaunchIntentForPackage(packageName)
            ?: Intent(this, MainActivity::class.java)
        val pendingIntent = PendingIntent.getActivity(
            this,
            0,
            launchIntent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )

        val builder = NotificationCompat.Builder(this, CHANNEL_ID)
            .setSmallIcon(applicationInfo.icon)
            .setContentTitle("Yumma! Resto is online")
            .setContentText(status)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(NotificationCompat.CATEGORY_CALL)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setOngoing(true)
            .setContentIntent(pendingIntent)

        if (fullScreen) {
            val fullScreenIntent = Intent(this, MainActivity::class.java).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)
            }
            val fullScreenPendingIntent = PendingIntent.getActivity(
                this,
                1,
                fullScreenIntent,
                PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
            )
            builder.setFullScreenIntent(fullScreenPendingIntent, true)
        }

        return builder.build()
    }

    private fun ensureChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val channel = NotificationChannel(
            CHANNEL_ID,
            "Online Order Listener",
            NotificationManager.IMPORTANCE_HIGH
        ).apply {
            description = "Keeps restaurant and driver order alerts responsive"
            setSound(null, null)
        }
        getSystemService(NotificationManager::class.java)
            .createNotificationChannel(channel)
    }

    companion object {
        const val CHANNEL_ID = "yumma_order_listener"
        const val NOTIFICATION_ID = 4101
        const val EXTRA_STATUS = "status"
        const val EXTRA_FULL_SCREEN = "full_screen"
    }
}
