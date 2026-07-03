package com.adgraph.yumma_vendor

import android.Manifest
import android.bluetooth.BluetoothAdapter
import android.bluetooth.BluetoothClass
import android.bluetooth.BluetoothDevice
import android.bluetooth.BluetoothManager
import android.app.NotificationManager
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.media.AudioDeviceInfo
import android.media.AudioManager
import android.net.Uri
import android.net.wifi.WifiManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.os.PowerManager
import android.provider.Settings
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.net.Inet4Address
import java.net.NetworkInterface
import java.net.Socket
import java.util.Collections
import java.util.Locale
import java.util.concurrent.CountDownLatch
import java.util.concurrent.Executors
import java.util.concurrent.TimeUnit

class MainActivity : FlutterActivity() {
    private val audioChannelName = "com.adgraph.yumma_vendor/order_audio"
    private val alertChannelName = "com.adgraph.yumma_vendor/order_alerts"
    private val configChannelName = "com.adgraph.yumma_vendor/app_config"
    private val printerDiscoveryChannelName =
        "com.adgraph.yumma_vendor/printer_discovery"
    private val bluetoothPermissionRequestCode = 7041
    private var previousRingerMode: Int? = null
    private var previousAudioMode: Int? = null
    private var previousSpeakerphone: Boolean? = null
    private var previousAlarmVolume: Int? = null
    private var previousNotificationVolume: Int? = null
    private var previousMusicVolume: Int? = null
    private var pendingBluetoothPermissionResult: MethodChannel.Result? = null

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
                    startOrderAlertService(status, fullScreen)
                    result.success(true)
                }
                "updateServiceNotification" -> {
                    val status = call.argument<String>("status")
                        ?: "Online and listening for orders"
                    val fullScreen = call.argument<Boolean>("fullScreen") ?: false
                    startOrderAlertService(status, fullScreen)
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

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            printerDiscoveryChannelName
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "requestBluetoothPermissions" -> requestBluetoothPermissions(result)
                "discoverBluetoothPrinters" -> {
                    if (!hasBluetoothPermissions()) {
                        result.error(
                            "permissions_denied",
                            "Bluetooth permission is required to search paired printers.",
                            null
                        )
                        return@setMethodCallHandler
                    }
                    result.success(discoverBluetoothPrinters())
                }
                "discoverNetworkPrinters" -> runAsync(result) {
                    discoverNetworkPrinters()
                }
                "discoverAllPrinters" -> runAsync(result) {
                    mapOf(
                        "bluetooth" to discoverBluetoothPrinters(),
                        "network" to discoverNetworkPrinters()
                    )
                }
                else -> result.notImplemented()
            }
        }
    }

    override fun onRequestPermissionsResult(
        requestCode: Int,
        permissions: Array<out String>,
        grantResults: IntArray
    ) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode != bluetoothPermissionRequestCode) return

        val granted = grantResults.isNotEmpty() && grantResults.all { it == PackageManager.PERMISSION_GRANTED }
        pendingBluetoothPermissionResult?.success(granted)
        pendingBluetoothPermissionResult = null
    }

    private fun startOrderAlertService(status: String, fullScreen: Boolean = false) {
        val intent = Intent(this, OrderAlertForegroundService::class.java)
            .putExtra(OrderAlertForegroundService.EXTRA_STATUS, status)
            .putExtra(OrderAlertForegroundService.EXTRA_FULL_SCREEN, fullScreen)
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

    private fun requestBluetoothPermissions(result: MethodChannel.Result) {
        if (hasBluetoothPermissions()) {
            result.success(true)
            return
        }

        pendingBluetoothPermissionResult = result
        ActivityCompat.requestPermissions(
            this,
            requiredBluetoothPermissions(),
            bluetoothPermissionRequestCode
        )
    }

    private fun hasBluetoothPermissions(): Boolean {
        return requiredBluetoothPermissions().all { permission ->
            ContextCompat.checkSelfPermission(this, permission) == PackageManager.PERMISSION_GRANTED
        }
    }

    private fun requiredBluetoothPermissions(): Array<String> {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            arrayOf(
                Manifest.permission.BLUETOOTH_SCAN,
                Manifest.permission.BLUETOOTH_CONNECT
            )
        } else {
            arrayOf(
                Manifest.permission.BLUETOOTH,
                Manifest.permission.BLUETOOTH_ADMIN,
                Manifest.permission.ACCESS_FINE_LOCATION
            )
        }
    }

    private fun discoverBluetoothPrinters(): List<Map<String, Any?>> {
        val manager = getSystemService(Context.BLUETOOTH_SERVICE) as? BluetoothManager
        val adapter = manager?.adapter ?: BluetoothAdapter.getDefaultAdapter() ?: return emptyList()
        if (!adapter.isEnabled) {
            return emptyList()
        }

        return adapter.bondedDevices
            ?.filter { isLikelyPrinter(it) }
            ?.sortedBy { it.name?.lowercase(Locale.getDefault()) ?: "" }
            ?.map { device ->
                mapOf(
                    "name" to (device.name ?: "Bluetooth printer"),
                    "mac" to device.address,
                    "type" to "bluetooth",
                    "paired" to true,
                    "status" to "paired"
                )
            }
            ?: emptyList()
    }

    private fun isLikelyPrinter(device: BluetoothDevice): Boolean {
        val name = (device.name ?: "").lowercase(Locale.getDefault())
        val printerKeywords = listOf(
            "printer",
            "pos",
            "thermal",
            "epson",
            "star",
            "xp-",
            "tm-t",
            "tsp",
            "btp"
        )
        val nameLooksLikePrinter = printerKeywords.any { name.contains(it) }
        val classLooksLikePrinter = device.bluetoothClass?.majorDeviceClass == BluetoothClass.Device.Major.IMAGING
        return nameLooksLikePrinter || classLooksLikePrinter
    }

    private fun discoverNetworkPrinters(): List<Map<String, Any?>> {
        val localIp = getLocalIpv4Address() ?: return emptyList()
        val parts = localIp.split(".")
        if (parts.size != 4) return emptyList()

        val subnet = "${parts[0]}.${parts[1]}.${parts[2]}"
        val discovered = Collections.synchronizedList(mutableListOf<Map<String, Any?>>())
        val executor = Executors.newFixedThreadPool(24)
        val latch = CountDownLatch(254)

        for (host in 1..254) {
            executor.execute {
                try {
                    val ip = "$subnet.$host"
                    if (ip == localIp) {
                        return@execute
                    }

                    val openPort = findOpenPrinterPort(ip)
                    if (openPort != null) {
                        discovered.add(
                            mapOf(
                                "name" to "Network Printer $host",
                                "ip" to ip,
                                "port" to openPort,
                                "type" to "network",
                                "status" to "available"
                            )
                        )
                    }
                } finally {
                    latch.countDown()
                }
            }
        }

        latch.await(12, TimeUnit.SECONDS)
        executor.shutdownNow()

        return discovered.sortedBy { (it["ip"] as? String) ?: "" }
    }

    private fun findOpenPrinterPort(ip: String): Int? {
        val ports = listOf(9100, 515, 631)
        for (port in ports) {
            try {
                Socket().use { socket ->
                    socket.connect(java.net.InetSocketAddress(ip, port), 120)
                    return port
                }
            } catch (_: Exception) {
            }
        }
        return null
    }

    private fun getLocalIpv4Address(): String? {
        val wifiManager = applicationContext.getSystemService(Context.WIFI_SERVICE) as? WifiManager
        val wifiIp = wifiManager?.connectionInfo?.ipAddress ?: 0
        if (wifiIp != 0) {
            return String.format(
                Locale.US,
                "%d.%d.%d.%d",
                wifiIp and 0xff,
                wifiIp shr 8 and 0xff,
                wifiIp shr 16 and 0xff,
                wifiIp shr 24 and 0xff
            )
        }

        return try {
            Collections.list(NetworkInterface.getNetworkInterfaces())
                .flatMap { Collections.list(it.inetAddresses) }
                .firstOrNull { address ->
                    !address.isLoopbackAddress && address is Inet4Address
                }
                ?.hostAddress
        } catch (_: Exception) {
            null
        }
    }

    private fun <T> runAsync(result: MethodChannel.Result, block: () -> T) {
        val handler = Handler(Looper.getMainLooper())
        Executors.newSingleThreadExecutor().execute {
            try {
                val data = block()
                handler.post { result.success(data) }
            } catch (exception: Exception) {
                handler.post {
                    result.error("printer_discovery_failed", exception.message, null)
                }
            }
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
