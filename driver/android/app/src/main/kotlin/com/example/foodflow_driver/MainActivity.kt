package com.adgraph.delivery

import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioDeviceInfo
import android.media.AudioManager
import android.net.Uri
import android.os.Build
import android.os.PowerManager
import android.provider.Settings
import androidx.core.content.ContextCompat
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val audioChannelName = "com.adgraph.delivery/order_audio"
    private val alertChannelName = "com.adgraph.delivery/order_alerts"
    private val configChannelName = "com.adgraph.delivery/app_config"
    private var previousRingerMode: Int? = null
    private var previousAudioMode: Int? = null
    private var previousSpeakerphone: Boolean? = null
    private var previousAlarmVolume: Int? = null
    private var previousNotificationVolume: Int? = null
    private var previousMusicVolume: Int? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            audioChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "prepareUrgentOrderAudio" -> {
                    prepareUrgentOrderAudio()
                    result.success(true)
                }
                "restoreNormalAudio" -> {
                    restoreNormalAudio()
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            alertChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "startForegroundService" -> {
                    val status = call.argument<String>("status")
                        ?: "Online and listening for orders"
                    val fullScreen = call.argument<Boolean>("fullScreen") ?: false
                    val trackLocation = call.argument<Boolean>("trackLocation") ?: false
                    startOrderAlertService(status, fullScreen, trackLocation)
                    result.success(true)
                }
                "updateServiceNotification" -> {
                    val status = call.argument<String>("status")
                        ?: "Online and listening for orders"
                    val fullScreen = call.argument<Boolean>("fullScreen") ?: false
                    val trackLocation = call.argument<Boolean>("trackLocation") ?: false
                    startOrderAlertService(status, fullScreen, trackLocation)
                    result.success(true)
                }
                "stopForegroundService" -> {
                    stopService(Intent(this, OrderAlertForegroundService::class.java))
                    result.success(true)
                }
                "bringAppToFront" -> {
                    bringAppToFront()
                    result.success(true)
                }
                "canDrawOverlays" -> {
                    result.success(canDrawOverlays())
                }
                "requestOverlayPermission" -> {
                    openOverlayPermissionSettings()
                    result.success(true)
                }
                "requestBatteryOptimizationExemption" -> {
                    requestBatteryOptimizationExemption()
                    result.success(true)
                }
                "openAppNotificationSettings" -> {
                    openAppNotificationSettings()
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            configChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "getGoogleMapsApiKey" -> result.success(getGoogleMapsApiKey())
                else -> result.notImplemented()
            }
        }
    }

    private fun startOrderAlertService(
        status: String,
        fullScreen: Boolean = false,
        trackLocation: Boolean = false
    ) {
        val intent = Intent(this, OrderAlertForegroundService::class.java)
            .putExtra(OrderAlertForegroundService.EXTRA_STATUS, status)
            .putExtra(OrderAlertForegroundService.EXTRA_FULL_SCREEN, fullScreen)
            .putExtra(OrderAlertForegroundService.EXTRA_TRACK_LOCATION, trackLocation)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            ContextCompat.startForegroundService(this, intent)
        } else {
            startService(intent)
        }
    }

    private fun canDrawOverlays(): Boolean {
        return Build.VERSION.SDK_INT < Build.VERSION_CODES.M || Settings.canDrawOverlays(this)
    }

    private fun openOverlayPermissionSettings() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M || canDrawOverlays()) return
        val intent = Intent(
            Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
            Uri.parse("package:$packageName")
        ).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        startActivity(intent)
    }

    private fun requestBatteryOptimizationExemption() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return
        val powerManager = getSystemService(Context.POWER_SERVICE) as PowerManager
        if (powerManager.isIgnoringBatteryOptimizations(packageName)) return
        val intent = Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS)
            .setData(Uri.parse("package:$packageName"))
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        startActivity(intent)
    }

    private fun openAppNotificationSettings() {
        val intent = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                .putExtra(Settings.EXTRA_APP_PACKAGE, packageName)
        } else {
            Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS)
                .setData(Uri.parse("package:$packageName"))
        }.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        startActivity(intent)
    }

    private fun bringAppToFront() {
        val intent = Intent(this, MainActivity::class.java)
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        startActivity(intent)
    }

    private fun getGoogleMapsApiKey(): String {
        return try {
            val appInfo = packageManager.getApplicationInfo(
                packageName,
                PackageManager.GET_META_DATA
            )
            appInfo.metaData?.getString("com.google.android.geo.API_KEY") ?: ""
        } catch (_: Exception) {
            ""
        }
    }

    private fun prepareUrgentOrderAudio() {
        val audioManager = getSystemService(Context.AUDIO_SERVICE) as AudioManager

        if (previousAudioMode == null) {
            previousAudioMode = audioManager.mode
            previousSpeakerphone = audioManager.isSpeakerphoneOn
            previousAlarmVolume = audioManager.getStreamVolume(AudioManager.STREAM_ALARM)
            previousNotificationVolume =
                audioManager.getStreamVolume(AudioManager.STREAM_NOTIFICATION)
            previousMusicVolume = audioManager.getStreamVolume(AudioManager.STREAM_MUSIC)
            previousRingerMode = audioManager.ringerMode
        }

        setMaxVolume(audioManager, AudioManager.STREAM_ALARM)
        setMaxVolume(audioManager, AudioManager.STREAM_NOTIFICATION)
        setMaxVolume(audioManager, AudioManager.STREAM_MUSIC)
        setRingerNormalIfAllowed(audioManager)

        audioManager.mode = AudioManager.MODE_IN_COMMUNICATION

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            val speaker = audioManager.availableCommunicationDevices.firstOrNull {
                it.type == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER
            }
            if (speaker != null) {
                audioManager.setCommunicationDevice(speaker)
            }
        }

        @Suppress("DEPRECATION")
        audioManager.isSpeakerphoneOn = true
    }

    private fun restoreNormalAudio() {
        val audioManager = getSystemService(Context.AUDIO_SERVICE) as AudioManager

        previousAlarmVolume?.let {
            audioManager.setStreamVolume(AudioManager.STREAM_ALARM, it, 0)
        }
        previousNotificationVolume?.let {
            audioManager.setStreamVolume(AudioManager.STREAM_NOTIFICATION, it, 0)
        }
        previousMusicVolume?.let {
            audioManager.setStreamVolume(AudioManager.STREAM_MUSIC, it, 0)
        }

        previousRingerMode?.let { mode ->
            try {
                if (canChangeNotificationPolicy()) {
                    audioManager.ringerMode = mode
                }
            } catch (_: SecurityException) {
            }
        }

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            audioManager.clearCommunicationDevice()
        }

        previousSpeakerphone?.let {
            @Suppress("DEPRECATION")
            audioManager.isSpeakerphoneOn = it
        }
        previousAudioMode?.let { audioManager.mode = it }

        previousRingerMode = null
        previousAudioMode = null
        previousSpeakerphone = null
        previousAlarmVolume = null
        previousNotificationVolume = null
        previousMusicVolume = null
    }

    private fun setMaxVolume(audioManager: AudioManager, stream: Int) {
        val maxVolume = audioManager.getStreamMaxVolume(stream)
        audioManager.setStreamVolume(stream, maxVolume, 0)
    }

    private fun setRingerNormalIfAllowed(audioManager: AudioManager) {
        try {
            if (canChangeNotificationPolicy()) {
                audioManager.ringerMode = AudioManager.RINGER_MODE_NORMAL
            }
        } catch (_: SecurityException) {
        }
    }

    private fun canChangeNotificationPolicy(): Boolean {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.M) return true
        val notificationManager =
            getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        return notificationManager.isNotificationPolicyAccessGranted
    }
}
