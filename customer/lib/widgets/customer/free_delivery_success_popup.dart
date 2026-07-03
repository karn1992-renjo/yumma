import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

import '../../theme/foodflow_theme.dart';

class FreeDeliveryMilestoneTracker {
  FreeDeliveryMilestoneTracker._();

  static bool _celebrated = false;

  static bool shouldCelebrate({
    required bool eligible,
    required bool achieved,
  }) {
    if (!eligible || !achieved) {
      _celebrated = false;
      return false;
    }
    if (_celebrated) return false;
    _celebrated = true;
    return true;
  }
}

Future<void> showFreeDeliverySuccessPopup(BuildContext context) {
  return showDialog<void>(
    context: context,
    barrierColor: Colors.black.withOpacity(0.28),
    builder: (dialogContext) => Dialog(
      elevation: 0,
      backgroundColor: Colors.transparent,
      insetPadding: const EdgeInsets.symmetric(horizontal: 34),
      child: Container(
        padding: const EdgeInsets.fromLTRB(22, 18, 22, 22),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Colors.white, Color(0xFFF0FFF5)],
          ),
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: const Color(0xFFD9F5E3)),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: FoodFlowTheme.primaryColor.withOpacity(0.18),
              blurRadius: 30,
              offset: const Offset(0, 14),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Lottie.asset(
              'assets/animations/success.json',
              width: 195,
              height: 195,
              repeat: false,
              fit: BoxFit.contain,
            ),
            const Text(
              'Free delivery unlocked!',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: FoodFlowTheme.ink,
                fontSize: 21,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Nice one—your delivery charge is now on us.',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: FoodFlowTheme.muted,
                fontSize: 13,
                height: 1.35,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                style: FilledButton.styleFrom(
                  backgroundColor: FoodFlowTheme.primaryColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: const Text(
                  'Great!',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
