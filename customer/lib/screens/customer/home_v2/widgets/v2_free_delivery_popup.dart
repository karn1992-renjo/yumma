import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

import '../theme/v2_theme.dart';
import 'v2_anim.dart';
import 'v2_glass.dart';

/// "Celebrate once per cart when the free-delivery threshold is crossed" —
/// mirrors [FreeDeliveryMilestoneTracker] semantics without importing V1.
class V2FreeDeliveryTracker {
  V2FreeDeliveryTracker._();

  static bool _celebrated = false;

  static bool shouldCelebrate({required bool eligible, required bool achieved}) {
    if (!eligible || !achieved) {
      _celebrated = false;
      return false;
    }
    if (_celebrated) return false;
    _celebrated = true;
    return true;
  }
}

Future<void> showV2FreeDeliverySuccess(BuildContext context) {
  final p = V2Palette.of(v2ModeNotifier.value);
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withOpacity(0.35),
    builder: (dialogContext) => V2Theme(
      palette: p,
      child: Dialog(
        elevation: 0,
        backgroundColor: Colors.transparent,
        insetPadding: const EdgeInsets.symmetric(horizontal: 34),
        child: GlassPanel(
          radius: 26,
          strong: true,
          padding: const EdgeInsets.fromLTRB(22, 18, 22, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              SizedBox(
                width: 160,
                height: 160,
                child: Lottie.asset(
                  'assets/animations/success.json',
                  repeat: false,
                  errorBuilder: (_, __, ___) => Icon(
                    Icons.local_shipping_rounded,
                    size: 88,
                    color: p.positive,
                  ),
                ),
              ),
              const SizedBox(height: 6),
              Text('Free delivery unlocked!',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                      color: p.ink,
                      fontSize: 19,
                      fontWeight: FontWeight.w900)),
              const SizedBox(height: 6),
              Text(
                'Your delivery fee is on us for this order. Enjoy! 🎉',
                textAlign: TextAlign.center,
                style: TextStyle(color: p.inkSoft, fontSize: 13),
              ),
              const SizedBox(height: 18),
              V2Tappable(
                onTap: () => Navigator.of(dialogContext).pop(),
                child: Container(
                  height: 48,
                  width: double.infinity,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: p.accent,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Text('Nice!',
                      style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900)),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
