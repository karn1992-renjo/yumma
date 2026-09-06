import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../config/app_config.dart';
import '../../services/native_config_service.dart';
import '../../theme/foodflow_theme.dart';

class BackgroundLocationDisclosureScreen extends StatefulWidget {
  const BackgroundLocationDisclosureScreen({super.key});

  static const acceptedPreferenceKey =
      'driver_background_location_disclosure_accepted_v4';

  static Future<bool> ensureAccepted(
    BuildContext context, {
    bool forceDisclosure = false,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    if (!forceDisclosure && prefs.getBool(acceptedPreferenceKey) == true) {
      return true;
    }

    if (!context.mounted) return false;
    final accepted = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (_) => const _BackgroundLocationDisclosureDialog(),
    );

    if (accepted == true) {
      await prefs.setBool(acceptedPreferenceKey, true);
      return true;
    }

    return false;
  }

  @override
  State<BackgroundLocationDisclosureScreen> createState() =>
      _BackgroundLocationDisclosureScreenState();
}

class _BackgroundLocationDisclosureDialog extends StatelessWidget {
  const _BackgroundLocationDisclosureDialog();

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Background location access'),
      content: const SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'This app collects location data to enable live delivery assignment, route tracking, ETA updates, and pickup/drop-off verification even when the app is closed or not in use.',
              style: TextStyle(fontWeight: FontWeight.w800, height: 1.4),
            ),
            SizedBox(height: 12),
            Text(
              'Yumma! Rider transmits and stores precise location data on Yumma! servers and shares live delivery location with assigned restaurants and assigned customers while you are online for active delivery work. Going offline stops live delivery tracking. We do not sell this data or use it for advertising.',
              style: TextStyle(height: 1.4),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('I do not agree'),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop(true),
          child: const Text('Agree'),
        ),
      ],
    );
  }
}

class _BackgroundLocationDisclosureScreenState
    extends State<BackgroundLocationDisclosureScreen> {
  String _appName = AppConfig.appName;

  @override
  void initState() {
    super.initState();
    _loadAppName();
  }

  Future<void> _loadAppName() async {
    final nativeAppName = await NativeConfigService.getAppName();
    if (mounted && nativeAppName.trim().isNotEmpty) {
      setState(() => _appName = nativeAppName.trim());
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: foodflow.canvas,
        appBar: AppBar(
          automaticallyImplyLeading: false,
          backgroundColor: foodflow.surfaceColor,
          foregroundColor: foodflow.ink,
          elevation: 0,
          title: const Text('Background location access'),
        ),
        body: SafeArea(
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
                  children: [
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: foodflow.line),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: foodflow.orange.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(
                              Icons.location_on_outlined,
                              color: foodflow.orange,
                              size: 26,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '$_appName collects precise location data',
                                  style: GoogleFonts.plusJakartaSans(
                                    color: foodflow.ink,
                                    fontSize: 19,
                                    fontWeight: FontWeight.w900,
                                    height: 1.2,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  '$_appName collects, transmits, stores, and shares your precise location with $_appName servers, assigned restaurants, and assigned customers to enable live delivery assignment, route tracking, ETA updates, and pickup/drop-off verification when you are online, including when the app is closed or not in use.',
                                  style: GoogleFonts.plusJakartaSans(
                                    color: foodflow.inkSoft,
                                    fontSize: 13,
                                    height: 1.45,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    _DisclosureItem(
                      icon: Icons.delivery_dining_outlined,
                      title: 'How it is used',
                      body:
                          'Your location helps assign nearby orders, show live delivery progress, calculate ETAs, and verify pickup and drop-off status.',
                    ),
                    _DisclosureItem(
                      icon: Icons.groups_2_outlined,
                      title: 'Who can see it',
                      body:
                          'Location updates are sent to $_appName servers and may be shared with the restaurant and customer for your assigned delivery. We do not sell this data or use it for advertising.',
                    ),
                    _DisclosureItem(
                      icon: Icons.toggle_off_outlined,
                      title: 'Your control',
                      body:
                          'Tracking is used only for delivery work. Going offline stops live delivery tracking, and you can change location permission from Android settings.',
                    ),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: foodflow.warmCanvas,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFFFDFD4)),
                      ),
                      child: Text(
                        'After you agree, Android will ask for location permission. To go online for deliveries, allow location access and choose "Allow all the time" if Android opens the app location settings.',
                        style: GoogleFonts.plusJakartaSans(
                          color: foodflow.inkSoft,
                          fontSize: 13,
                          height: 1.4,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
                decoration:  BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: foodflow.line)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ElevatedButton(
                      onPressed: () => Navigator.of(context).pop(true),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: foodflow.orange,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                      child: const Text('Agree to background location'),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(false),
                      child: const Text('I do not agree'),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DisclosureItem extends StatelessWidget {
  const _DisclosureItem({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: foodflow.softSurface(radius: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: foodflow.orange, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.plusJakartaSans(
                    color: foodflow.ink,
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  body,
                  style: GoogleFonts.plusJakartaSans(
                    color: foodflow.muted,
                    fontSize: 13,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
