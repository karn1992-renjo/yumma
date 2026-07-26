import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_constants.dart';
import '../config/app_config.dart';
import 'navigation_service.dart';

class AppUpdateService {
  static bool _isChecking = false;

  static Future<void> checkForLatestRelease({required String appKey}) async {
    if (_isChecking || kIsWeb) return;
    _isChecking = true;

    try {
      final release = await _fetchRelease(appKey);
      if (release == null) return;

      final packageInfo = await PackageInfo.fromPlatform();
      final currentBuild = int.tryParse(packageInfo.buildNumber.trim()) ?? 0;
      final latestBuild = _intValue(release['latest_build_number']);
      final minBuild = _intValue(release['min_supported_build_number']);
      final latestVersion = _stringValue(release['latest_version']);

      final hasBuildUpdate = latestBuild > 0 && latestBuild > currentBuild;
      final hasVersionUpdate = latestBuild <= 0 &&
          _compareVersions(latestVersion, packageInfo.version) > 0;
      final belowMinimum = minBuild > 0 && currentBuild < minBuild;
      if (!hasBuildUpdate && !hasVersionUpdate && !belowMinimum) return;

      final mandatory = _boolValue(release['force_update']) || belowMinimum;
      final url = _updateUrl(release);
      if (url.isEmpty) return;

      final prefs = await SharedPreferences.getInstance();
      final dismissKey =
          'dismissed_update_${appKey}_${latestBuild}_$latestVersion';
      if (!mandatory && prefs.getBool(dismissKey) == true) return;

      final context = await _waitForContext();
      if (context == null) return;

      await showDialog<void>(
        context: context,
        barrierDismissible: !mandatory,
        builder: (dialogContext) {
          return WillPopScope(
            onWillPop: () async => !mandatory,
            child: AlertDialog(
              title: Text(mandatory ? 'Update required' : 'Update available'),
              content: Text(_message(
                latestVersion: latestVersion,
                latestBuild: latestBuild,
                releaseNotes: _stringValue(release['release_notes']),
                mandatory: mandatory,
              )),
              actions: [
                if (!mandatory)
                  TextButton(
                    onPressed: () async {
                      await prefs.setBool(dismissKey, true);
                      if (dialogContext.mounted) {
                        Navigator.of(dialogContext).pop();
                      }
                    },
                    child: const Text('Later'),
                  ),
                FilledButton(
                  onPressed: () async {
                    await launchUrl(
                      Uri.parse(url),
                      mode: LaunchMode.externalApplication,
                    );
                    if (!mandatory && dialogContext.mounted) {
                      Navigator.of(dialogContext).pop();
                    }
                  },
                  child: const Text('Update'),
                ),
              ],
            ),
          );
        },
      );
    } catch (e, stackTrace) {
      debugPrint('App update check skipped: $e');
      debugPrintStack(stackTrace: stackTrace);
    } finally {
      _isChecking = false;
    }
  }

  static Future<Map<String, dynamic>?> _fetchRelease(String appKey) async {
    final uri = Uri.parse('${AppConfig.apiBaseUrl}${ApiConstants.appBranding}');
    final response = await http.get(uri, headers: {
      'Accept': 'application/json'
    }).timeout(const Duration(seconds: 8));
    if (response.statusCode < 200 || response.statusCode >= 300) return null;

    final decoded = jsonDecode(response.body);
    if (decoded is! Map || decoded['data'] is! Map) return null;
    final data = Map<String, dynamic>.from(decoded['data'] as Map);
    if (data['app_releases'] is! Map) return null;
    final releases = Map<String, dynamic>.from(data['app_releases'] as Map);
    if (releases[appKey] is! Map) return null;
    return Map<String, dynamic>.from(releases[appKey] as Map);
  }

  static String _updateUrl(Map<String, dynamic> release) {
    final platformUrl = switch (defaultTargetPlatform) {
      TargetPlatform.iOS => _stringValue(release['ios_update_url']),
      TargetPlatform.android => _stringValue(release['android_update_url']),
      _ => '',
    };
    if (platformUrl.isNotEmpty) return platformUrl;
    return _stringValue(release['update_url']);
  }

  static Future<BuildContext?> _waitForContext() async {
    for (var attempt = 0; attempt < 12; attempt++) {
      final context = appNavigatorKey.currentContext ??
          appNavigatorKey.currentState?.overlay?.context;
      if (context != null) return context;
      await Future<void>.delayed(const Duration(milliseconds: 250));
    }
    return null;
  }

  static String _message({
    required String latestVersion,
    required int latestBuild,
    required String releaseNotes,
    required bool mandatory,
  }) {
    final versionLabel = latestVersion.isNotEmpty
        ? latestVersion
        : latestBuild > 0
            ? 'build $latestBuild'
            : 'the latest version';
    final buffer = StringBuffer(
      '${AppConfig.appName} $versionLabel is available.',
    );
    if (mandatory) {
      buffer.write(' Please update to continue.');
    }
    if (releaseNotes.isNotEmpty) {
      buffer.write('\n\n$releaseNotes');
    }
    return buffer.toString();
  }

  static int _compareVersions(String latest, String current) {
    final latestParts = _versionParts(latest);
    final currentParts = _versionParts(current);
    final length = latestParts.length > currentParts.length
        ? latestParts.length
        : currentParts.length;
    for (var index = 0; index < length; index++) {
      final a = index < latestParts.length ? latestParts[index] : 0;
      final b = index < currentParts.length ? currentParts[index] : 0;
      if (a != b) return a.compareTo(b);
    }
    return 0;
  }

  static List<int> _versionParts(String value) {
    return value
        .split(RegExp(r'[^0-9]+'))
        .where((part) => part.isNotEmpty)
        .map((part) => int.tryParse(part) ?? 0)
        .toList();
  }

  static String _stringValue(dynamic value) => value?.toString().trim() ?? '';

  static int _intValue(dynamic value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString().trim() ?? '') ?? 0;
  }

  static bool _boolValue(dynamic value) {
    final normalized = value?.toString().trim().toLowerCase() ?? '';
    return normalized == '1' || normalized == 'true' || normalized == 'yes';
  }
}
