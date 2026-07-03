import java.io.File
import java.io.FileInputStream
import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("com.google.gms.google-services")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val keystoreProperties = Properties()
val keystorePropertiesFile = file("D:/karn/swaad/cust_key/key.properties")
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(FileInputStream(keystorePropertiesFile))
}

android {
    namespace = "com.adgraph.yumma"
    compileSdk = flutter.compileSdkVersion
    // NDK r28+ produces 16 KB ELF-aligned native libraries by default.
    ndkVersion = "28.2.13676358"

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.adgraph.yumma"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
                // Provide the Google Maps API key placeholder used in AndroidManifest.xml.
        // Replace "YOUR_GOOGLE_MAPS_API_KEY" with your actual API key or inject via CI.
        manifestPlaceholders["GOOGLE_MAPS_API_KEY"] = "AIzaSyB8-QxsMReKTKEZQZ58_BCDMOeiTHo4d2Q"
        manifestPlaceholders["APPSFLYER_ONELINK_DOMAIN"] =
            (project.findProperty("APPSFLYER_ONELINK_DOMAIN") as String?)
                ?: System.getenv("APPSFLYER_ONELINK_DOMAIN")
                ?: "yumma.onelink.me"
    }

    signingConfigs {
        create("release") {
            keyAlias = keystoreProperties["keyAlias"] as String?
            keyPassword = keystoreProperties["keyPassword"] as String?
            storeFile = (keystoreProperties["storeFile"] as String?)?.trim()?.let { rawPath ->
                val normalizedPath = rawPath.replace('\\', '/')
                val candidate = File(normalizedPath)
                if (candidate.isAbsolute) file(normalizedPath) else File(keystorePropertiesFile.parentFile, normalizedPath)
            }
            storePassword = keystoreProperties["storePassword"] as String?
        }
    }

    buildTypes {
        debug {
            signingConfig = signingConfigs.getByName("release")
        }

        getByName("profile") {
            signingConfig = signingConfigs.getByName("release")
        }

        release {
            signingConfig = signingConfigs.getByName("release")
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}

flutter {
    source = "../.."
}
