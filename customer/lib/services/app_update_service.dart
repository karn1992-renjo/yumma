import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_constants.dart';
import '../config/app_config.dart';
import '../theme/foodflow_theme.dart';
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
        barrierColor: Colors.black.withOpacity(0.34),
        barrierDismissible: !mandatory,
        builder: (dialogContext) {
          return PopScope(
            canPop: !mandatory,
            child: _UpdateReleaseDialog(
              mandatory: mandatory,
              latestVersion: latestVersion,
              latestBuild: latestBuild,
              releaseNotes: _stringValue(release['release_notes']),
              onLater: mandatory
                  ? null
                  : () async {
                      await prefs.setBool(dismissKey, true);
                      if (dialogContext.mounted) {
                        Navigator.of(dialogContext).pop();
                      }
                    },
              onUpdate: () async {
                await launchUrl(
                  Uri.parse(url),
                  mode: LaunchMode.externalApplication,
                );
                if (!mandatory && dialogContext.mounted) {
                  Navigator.of(dialogContext).pop();
                }
              },
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

class _UpdateReleaseDialog extends StatelessWidget {
  const _UpdateReleaseDialog({
    required this.mandatory,
    required this.latestVersion,
    required this.latestBuild,
    required this.releaseNotes,
    required this.onUpdate,
    this.onLater,
  });

  final bool mandatory;
  final String latestVersion;
  final int latestBuild;
  final String releaseNotes;
  final Future<void> Function() onUpdate;
  final Future<void> Function()? onLater;

  @override
  Widget build(BuildContext context) {
    final versionLabel = latestVersion.isNotEmpty
        ? latestVersion
        : latestBuild > 0
            ? 'Build $latestBuild'
            : 'Latest version';

    return Dialog(
      elevation: 0,
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 24),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(28),
            border: Border.all(color: FoodFlowTheme.line),
            boxShadow: [
              BoxShadow(
                color: FoodFlowTheme.primaryColor.withOpacity(0.18),
                blurRadius: 30,
                offset: const Offset(0, 16),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _UpdateHeader(
                  mandatory: mandatory,
                  versionLabel: versionLabel,
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(22, 20, 22, 22),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        mandatory ? 'Update required' : 'Update available',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          height: 1.12,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        mandatory
                            ? 'Install the latest ${AppConfig.appName} version to keep ordering without interruption.'
                            : 'A fresher ${AppConfig.appName} experience is ready for you.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 13,
                          height: 1.38,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (releaseNotes.isNotEmpty) ...[
                        const SizedBox(height: 18),
                        _ReleaseNotesBox(notes: releaseNotes),
                      ],
                      const SizedBox(height: 22),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton.icon(
                          onPressed: onUpdate,
                          icon: const Icon(Icons.download_rounded, size: 19),
                          label: const Text('Update now'),
                          style: FilledButton.styleFrom(
                            backgroundColor: FoodFlowTheme.primaryColor,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                            textStyle: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                      if (onLater != null) ...[
                        const SizedBox(height: 10),
                        SizedBox(
                          width: double.infinity,
                          child: TextButton(
                            onPressed: onLater,
                            style: TextButton.styleFrom(
                              foregroundColor: FoodFlowTheme.muted,
                              padding: const EdgeInsets.symmetric(vertical: 13),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            child: const Text('Maybe later'),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _UpdateHeader extends StatelessWidget {
  const _UpdateHeader({
    required this.mandatory,
    required this.versionLabel,
  });

  final bool mandatory;
  final String versionLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(22, 22, 22, 20),
      decoration: const BoxDecoration(
        gradient: FoodFlowTheme.brandGradient,
      ),
      child: Column(
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.16),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: Colors.white.withOpacity(0.18)),
            ),
            child: const Icon(
              Icons.system_update_alt_rounded,
              color: Colors.white,
              size: 34,
            ),
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.16),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: Colors.white.withOpacity(0.14)),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  mandatory
                      ? Icons.priority_high_rounded
                      : Icons.auto_awesome_rounded,
                  color: Colors.white,
                  size: 15,
                ),
                const SizedBox(width: 6),
                Text(
                  versionLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
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

class _ReleaseNotesBox extends StatelessWidget {
  const _ReleaseNotesBox({required this.notes});

  final String notes;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      constraints: const BoxConstraints(maxHeight: 132),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8FA),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: SingleChildScrollView(
        physics: const ClampingScrollPhysics(),
        child: Text(
          notes,
          style: const TextStyle(
            color: FoodFlowTheme.inkSoft,
            fontSize: 12,
            height: 1.42,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
    );
  }
}
