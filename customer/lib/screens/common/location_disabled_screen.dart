import 'package:flutter/material.dart';

import '../../services/location_service.dart';
import '../../theme/foodflow_theme.dart';

/// Shown when the device's location service (GPS) is turned off. The Enable
/// button opens the phone's real location-settings page
/// (`LocationService.openLocationSettings`); when the app resumes, this
/// screen re-checks automatically and calls [onEnabled] without requiring an
/// extra tap if the user turned GPS on while in Settings.
class LocationDisabledScreen extends StatefulWidget {
  const LocationDisabledScreen({super.key, required this.onEnabled});

  final VoidCallback onEnabled;

  @override
  State<LocationDisabledScreen> createState() =>
      _LocationDisabledScreenState();
}

class _LocationDisabledScreenState extends State<LocationDisabledScreen>
    with WidgetsBindingObserver, SingleTickerProviderStateMixin {
  final LocationService _locationService = LocationService();
  late final AnimationController _controller;
  late final Animation<double> _float;
  bool _isOpeningSettings = false;
  bool _resolved = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1300),
    )..repeat(reverse: true);
    _float = CurvedAnimation(parent: _controller, curve: Curves.easeInOut);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _controller.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkIfEnabled();
    }
  }

  Future<void> _checkIfEnabled() async {
    if (_resolved) return;
    final enabled = await _locationService.isLocationServiceEnabled();
    if (!mounted || _resolved || !enabled) return;
    _resolved = true;
    widget.onEnabled();
  }

  Future<void> _onEnablePressed() async {
    if (_isOpeningSettings) return;
    setState(() => _isOpeningSettings = true);
    try {
      await _locationService.openLocationSettings();
    } finally {
      if (mounted) setState(() => _isOpeningSettings = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                AnimatedBuilder(
                  animation: _float,
                  builder: (context, child) {
                    return Transform.translate(
                      offset: Offset(0, -8 * _float.value),
                      child: child,
                    );
                  },
                  child: Container(
                    width: 132,
                    height: 132,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(28),
                      border: Border.all(color: FoodFlowTheme.line),
                      boxShadow: [
                        BoxShadow(
                          color: FoodFlowTheme.primaryColor.withOpacity(0.10),
                          blurRadius: 28,
                          offset: const Offset(0, 14),
                        ),
                      ],
                    ),
                    child: Stack(
                      alignment: Alignment.center,
                      children: [
                        Container(
                          width: 72,
                          height: 72,
                          decoration: BoxDecoration(
                            color: FoodFlowTheme.primaryColor.withOpacity(0.08),
                            shape: BoxShape.circle,
                          ),
                        ),
                        const Icon(
                          Icons.location_off_rounded,
                          size: 48,
                          color: FoodFlowTheme.primaryColor,
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 26),
                const Text(
                  'Turn on device location',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Location is turned off on this device. Turn it on to find restaurants near you.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 14,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 24),
                SizedBox(
                  width: 180,
                  height: 48,
                  child: ElevatedButton.icon(
                    onPressed: _isOpeningSettings ? null : _onEnablePressed,
                    style: FoodFlowTheme.zomatoPrimaryButton(
                      color: FoodFlowTheme.primaryColor,
                      radius: 8,
                    ),
                    icon: _isOpeningSettings
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.location_on_rounded),
                    label: Text(_isOpeningSettings ? 'Opening...' : 'Enable'),
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
