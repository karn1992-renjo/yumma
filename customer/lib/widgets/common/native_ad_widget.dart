// lib/widgets/common/native_ad_widget.dart
//
// A reusable AdMob Native Advanced ad, styled via a native-side template
// (android/.../ListTileNativeAdFactory.kt + res/layout/native_ad_card.xml;
// iOS: ListTileNativeAdFactory.swift) so it reads as part of the app's own
// UI rather than a generic ad box. Drop it into any scrollable screen's
// child list; it reserves zero height until the ad actually loads and
// collapses back to zero height if the load fails or errors out later, so a
// no-fill/network-error never leaves a broken grey box in the layout.
//
// Native Advanced requires its own ad-unit format in the AdMob console --
// an ad unit created as "Banner" will not serve here. Create (or confirm)
// the order-tracking/checkout ad units as "Native advanced" format before
// pointing AdConfig at them.
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import '../../config/ad_config.dart';

class NativeAdWidget extends StatefulWidget {
  const NativeAdWidget({
    super.key,
    required this.adUnitId,
  });

  /// Which AdMob ad unit to load. Pass one of the placement constants from
  /// `AdConfig` (e.g. `AdConfig.nativeAdUnitIdOrderTracking`) -- never a
  /// literal id -- so every placement stays swappable from one file.
  final String adUnitId;

  @override
  State<NativeAdWidget> createState() => _NativeAdWidgetState();
}

class _NativeAdWidgetState extends State<NativeAdWidget> {
  NativeAd? _nativeAd;
  bool _isLoaded = false;

  @override
  void initState() {
    super.initState();
    // Deferred to a post-frame callback for the same reason as
    // BannerAdWidget: anything that reads an InheritedWidget (none needed
    // here today, but AdConfig.adsEnabled's branding lookup may grow one)
    // must not run synchronously inside initState().
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) unawaited(_loadAd());
    });
  }

  @override
  void didUpdateWidget(NativeAdWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.adUnitId != widget.adUnitId) {
      _disposeAd();
      unawaited(_loadAd());
    }
  }

  Future<void> _loadAd() async {
    if (!AdConfig.adsEnabled) return;

    final ad = NativeAd(
      adUnitId: widget.adUnitId,
      // Must match the factory id MainActivity.kt /
      // AppDelegate.swift registers for GoogleMobileAdsPlugin.
      factoryId: 'listTileNativeAd',
      request: const AdRequest(),
      listener: NativeAdListener(
        onAdLoaded: (ad) {
          if (!mounted) {
            ad.dispose();
            return;
          }
          setState(() {
            _nativeAd = ad as NativeAd;
            _isLoaded = true;
          });
        },
        onAdFailedToLoad: (ad, error) {
          debugPrint('NativeAdWidget: failed to load ($error)');
          ad.dispose();
          if (!mounted) return;
          setState(() {
            _isLoaded = false;
            _nativeAd = null;
          });
        },
      ),
    );

    await ad.load();
  }

  void _disposeAd() {
    _nativeAd?.dispose();
    _nativeAd = null;
    _isLoaded = false;
  }

  @override
  void dispose() {
    _disposeAd();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ad = _nativeAd;
    if (!_isLoaded || ad == null) {
      // No ad yet (still loading, no fill, or a load error) -- collapse to
      // zero height instead of showing a broken placeholder box.
      return const SizedBox.shrink();
    }
    // Native ads don't report a fixed intrinsic size back to Flutter the
    // way an adaptive banner does -- this height is sized to match
    // native_ad_card.xml's own content (icon/headline row + media + body +
    // CTA button + padding). Keep both in sync if the template changes.
    return SizedBox(
      height: 400,
      child: AdWidget(ad: ad),
    );
  }
}
