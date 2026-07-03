# FoodFlow Customer App

This Flutter module is the customer app for the FoodFlow platform. It uses Firebase for authentication and messaging, and connects to the Laravel backend through API_BASE_URL.

> For the full project installation guide, see ../README.md.

## Setup

1. Install Flutter dependencies:

`ash
cd customer
flutter pub get
`

2. Configure Firebase:
- Place google-services.json in customer/android/app/
- Place GoogleService-Info.plist in customer/ios/Runner/ if building for iOS
- Update lib/firebase_options.dart with your Firebase project values

3. Set runtime build values using --dart-define.

## Runtime configuration

Required build flags:
- APP_ROLE=customer
- API_BASE_URL=https://your-api.example.com/api
- FIREBASE_PROJECT_ID=your-firebase-project-id
- PUSHER_APP_KEY=your-pusher-key
- PUSHER_APP_CLUSTER=your-pusher-cluster
- SUPPORT_PHONE=your-support-phone
- SUPPORT_EMAIL=support@example.com

Customer builds should also include:
- RAZORPAY_KEY_ID=your-razorpay-key
- STRIPE_PUBLISHABLE_KEY=your-stripe-key

## Example build command

`ash
flutter run \
  --dart-define=APP_ROLE=customer \
  --dart-define=API_BASE_URL=https://your-api.example.com/api \
  --dart-define=FIREBASE_PROJECT_ID=your-firebase-project-id \
  --dart-define=PUSHER_APP_KEY=your-pusher-key \
  --dart-define=PUSHER_APP_CLUSTER=your-pusher-cluster \
  --dart-define=SUPPORT_PHONE=your-support-phone \
  --dart-define=SUPPORT_EMAIL=support@example.com
`

## Android Maps

Add GOOGLE_MAPS_API_KEY to ndroid/gradle.properties, CI environment, or build environment.

Example:

`properties
GOOGLE_MAPS_API_KEY=YOUR_ANDROID_MAPS_KEY
`

## Version

The current application version is 1.1.0+1. Update this value in pubspec.yaml before publishing.

## Release

For Android:

`ash
flutter build apk --release --dart-define=APP_ROLE=customer --dart-define=API_BASE_URL=https://your-api.example.com/api
`

For iOS, install CocoaPods and build via Xcode:

`ash
cd customer/ios
pod install
`

Repeat for each app as needed.
