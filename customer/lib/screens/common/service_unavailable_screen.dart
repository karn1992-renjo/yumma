import 'package:flutter/material.dart';

import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';

/// Shown when a selected/detected location isn't inside any configured
/// delivery zone. The illustration below is a simple placeholder built from
/// Flutter widgets -- swap [_illustration] for a real asset (Lottie/SVG/PNG)
/// later without touching anything else on this screen.
class ServiceUnavailableScreen extends StatelessWidget {
  const ServiceUnavailableScreen({
    super.key,
    required this.onTryDifferentLocation,
  });

  final VoidCallback onTryDifferentLocation;

  Widget _illustration() {
    return Container(
      width: 160,
      height: 160,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(32),
        border: Border.all(color: FoodFlowTheme.line),
        boxShadow: [
          BoxShadow(
            color: FoodFlowTheme.success.withOpacity(0.10),
            blurRadius: 28,
            offset: const Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: FoodFlowTheme.success.withOpacity(0.08),
              shape: BoxShape.circle,
            ),
          ),
          const Icon(
            Icons.storefront_rounded,
            size: 56,
            color: FoodFlowTheme.success,
          ),
          Positioned(
            bottom: 18,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: FoodFlowTheme.ink,
                borderRadius: BorderRadius.circular(999),
              ),
              child: const Text(
                'Coming soon',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      ),
    );
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
                _illustration(),
                const SizedBox(height: 26),
                const Text(
                  'Service Unavailable!',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  '${AppConfig.appName} is not available at this location, please come back later',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 14,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 24),
                SizedBox(
                  width: 220,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: onTryDifferentLocation,
                    style: FoodFlowTheme.zomatoPrimaryButton(
                      color: FoodFlowTheme.success,
                      radius: 8,
                    ),
                    child: const Text('Try Different Location'),
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
