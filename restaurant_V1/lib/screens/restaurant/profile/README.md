# Restaurant Profile Screens - Implementation Guide

## Overview
Created modular profile screens for the restaurant app with separate screens for each function, replacing the monolithic settings screen.

## New Screen Structure

### 1. **Restaurant Profile Screen** (`restaurant_profile_screen.dart`)
Main dashboard showing:
- Restaurant logo/icon with name and rating
- 5 navigation tiles for different sections
- Clean, modern card-based UI

**Navigation Routes:**
- `/restaurant/profile` - Main profile screen
- `/restaurant/profile/edit` - Edit profile
- `/restaurant/profile/bank` - Bank details
- `/restaurant/profile/location` - Location update
- `/restaurant/profile/help` - Help & Support
- `/restaurant/profile/legal` - Legal documents

### 2. **Edit Profile Screen** (`restaurant_profile_edit_screen.dart`)
Features:
- Logo/Image upload with camera picker
- Edit restaurant name, description, email, phone
- Minimum order amount configuration
- Form validation
- Success feedback and navigation back

### 3. **Bank Details Screen** (`restaurant_bank_details_screen.dart`)
Features:
- Bank name, account holder name, account number
- Confirm account number (validation)
- IFSC code
- UPI ID (optional)
- Secure data handling with info banner
- Form validation with matching confirmation

### 4. **Location Screen** (`restaurant_location_screen.dart`)
**Key Features:**
- Google Maps integration with pinning
- Current location detection using Geolocator
- Tap on map to pin exact location
- Address, city, pincode fields
- Auto-updated latitude/longitude from map taps
- "Use Current Location" button for quick setup
- Restaurant name marker on map

**Permissions Required:**
- Location access (Geolocator)
- Maps API key in AndroidManifest.xml

### 5. **Help & Support Screen** (`restaurant_help_support_screen.dart`)
Features:
- Direct contact methods (Phone, Email, Live Chat)
- FAQ section with common questions
- Submit query form for custom issues
- URL launcher for phone and email

### 6. **Legal Screen** (`restaurant_legal_screen.dart`)
Features:
- Privacy Policy
- Terms & Conditions
- Refund Policy
- Restaurant Agreement
- Modal dialogs for viewing full content
- Email contact for legal team
- Version information

## Integration Steps

### 1. Add to Main Routes (`main.dart`)
```dart
routes: {
  '/restaurant/profile': (context) => const RestaurantProfileScreen(),
  '/restaurant/profile/edit': (context) => const RestaurantProfileEditScreen(),
  '/restaurant/profile/bank': (context) => const RestaurantBankDetailsScreen(),
  '/restaurant/profile/location': (context) => const RestaurantLocationScreen(),
  '/restaurant/profile/help': (context) => const RestaurantHelpSupportScreen(),
  '/restaurant/profile/legal': (context) => const RestaurantLegalScreen(),
}
```

### 2. Update API Constants (`config/api_constants.dart`)
Add these endpoints:
```dart
// Profile endpoints
static const restaurantProfile = '/api/restaurant/profile';
static const updateRestaurantProfile = '/api/restaurant/profile/update';
static const restaurantBankDetails = '/api/restaurant/bank-details';
static const updateBankDetails = '/api/restaurant/bank-details/update';
static const restaurantLocation = '/api/restaurant/location';
static const updateLocation = '/api/restaurant/location/update';
```

### 3. Add Permissions (`AndroidManifest.xml`)
```xml
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
```

### 4. Update Navigation in Dashboard/Settings
Replace the old settings link with:
```dart
Navigator.pushNamed(context, '/restaurant/profile')
```

## Import Usage
```dart
import 'screens/restaurant/profile/index.dart';
// Now all screens are available
```

## Design System
- **Primary Color:** `Color(0xFFFC8019)` (Orange)
- **Card Shadows:** `BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 8)`
- **Border Radius:** 12px for cards, 16px for containers
- **Spacing:** 16px horizontal padding, consistent vertical spacing

## Features Implemented

✅ Separate modular screens for each function
✅ Google Maps integration with location pinning
✅ Image picker for logo upload
✅ Form validation across all screens
✅ Responsive design
✅ Error handling and user feedback
✅ Secure data handling for sensitive info
✅ Modern UI with gradients and shadows
✅ Loading states and error messages
✅ Help & support with FAQ
✅ Legal documents viewer
✅ Location auto-detection

## Future Enhancements
- Add photo gallery integration
- Implement real-time location tracking
- Add document upload for FSSAI license
- Enable bulk edit for menu items
- Add analytics dashboard
- Implement two-factor authentication for sensitive changes
