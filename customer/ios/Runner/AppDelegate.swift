import UIKit
import Flutter
import FirebaseCore
import FirebaseAuth
import GoogleMaps
import UserNotifications

@main
@objc class AppDelegate: FlutterAppDelegate {

    override func application(
        _ application: UIApplication,
        didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
    ) -> Bool {

        // Configure Firebase
        if FirebaseApp.app() == nil {
            FirebaseApp.configure()
        }

        // Configure Google Maps
        GMSServices.provideAPIKey("AIzaSyDhZCVKvFQmun_wXebEFKgaP6zzjQn-c4I")

        // Register Flutter plugins
        GeneratedPluginRegistrant.register(with: self)

        UNUserNotificationCenter.current().delegate = self
        application.registerForRemoteNotifications()

        return super.application(
            application,
            didFinishLaunchingWithOptions: launchOptions
        )
    }

    override func application(
        _ app: UIApplication,
        open url: URL,
        options: [UIApplication.OpenURLOptionsKey: Any] = [:]
    ) -> Bool {
        if Auth.auth().canHandle(url) {
            return true
        }

        return super.application(app, open: url, options: options)
    }
}
