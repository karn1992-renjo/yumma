package com.adgraph.yamma_delivery

import android.Manifest
import android.app.AlarmManager
import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import android.os.SystemClock
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat

class OrderAlertForegroundService : Service() {
    private var currentStatus = DEFAULT_STATUS
    private var currentTrackLocation = false

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        currentStatus = intent?.getStringExtra(EXTRA_STATUS) ?: currentStatus
        val fullScreen = intent?.getBooleanExtra(EXTRA_FULL_SCREEN, false) ?: false
        currentTrackLocation = intent?.getBooleanExtra(EXTRA_TRACK_LOCATION, currentTrackLocation)
            ?: currentTrackLocation
        ensureChannel()
        val notification = buildNotification(currentStatus, fullScreen)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            val locationServiceType = if (currentTrackLocation && hasLocationPermission()) {
                ServiceInfo.FOREGROUND_SERVICE_TYPE_LOCATION
            } else {
                0
            }
            val serviceType = ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC or locationServiceType
            startForeground(
                NOTIFICATION_ID,
                notification,
                serviceType
            )
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }
        return START_STICKY
    }

    override fun onTaskRemoved(rootIntent: Intent?) {
        val restartIntent = Intent(applicationContext, OrderAlertForegroundService::class.java)
            .putExtra(EXTRA_STATUS, currentStatus)
            .putExtra(EXTRA_FULL_SCREEN, false)
            .putExtra(EXTRA_TRACK_LOCATION, currentTrackLocation)
        val restartPendingIntent = PendingIntent.getService(
            applicationContext,
            RESTART_REQUEST_CODE,
            restartIntent,
            PendingIntent.FLAG_ONE_SHOT or PendingIntent.FLAG_IMMUTABLE
        )
        val alarmManager = getSystemService(Context.ALARM_SERVICE) as AlarmManager
        alarmManager.set(
            AlarmManager.ELAPSED_REALTIME_WAKEUP,
            SystemClock.elapsedRealtime() + 1000L,
            restartPendingIntent
        )
        super.onTaskRemoved(rootIntent)
    }

    private fun hasLocationPermission(): Boolean {
        return ContextCompat.checkSelfPermission(
            this,
            Manifest.permission.ACCESS_FINE_LOCATION
        ) == PackageManager.PERMISSION_GRANTED ||
            ContextCompat.checkSelfPermission(
                this,
                Manifest.permission.ACCESS_COARSE_LOCATION
            ) == PackageManager.PERMISSION_GRANTED
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
            .setContentTitle("Yumma! Go is active")
            .setContentText(status)
            .setPriority(if (fullScreen) NotificationCompat.PRIORITY_HIGH else NotificationCompat.PRIORITY_LOW)
            .setCategory(if (fullScreen) NotificationCompat.CATEGORY_CALL else NotificationCompat.CATEGORY_SERVICE)
            .setOnlyAlertOnce(true)
            .setShowWhen(false)
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
            NotificationManager.IMPORTANCE_LOW
        ).apply {
            description = "Keeps driver order alerts responsive"
            setSound(null, null)
        }
        getSystemService(NotificationManager::class.java)
            .createNotificationChannel(channel)
    }

    companion object {
        const val CHANNEL_ID = "swaad_order_listener"
        const val NOTIFICATION_ID = 4101
        const val RESTART_REQUEST_CODE = 4102
        const val DEFAULT_STATUS = "Logged in and ready for delivery orders"
        const val EXTRA_STATUS = "status"
        const val EXTRA_FULL_SCREEN = "full_screen"
        const val EXTRA_TRACK_LOCATION = "track_location"
    }
}