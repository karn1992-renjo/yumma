import 'package:flutter/material.dart';

import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

/// Shown when the customer picks a location outside every active delivery area.
class NoDeliveryZoneV2 extends StatelessWidget {
  const NoDeliveryZoneV2({
    super.key,
    required this.locationLabel,
    required this.onChangeLocation,
  });

  final String locationLabel;
  final VoidCallback onChangeLocation;

  @override
  Widget build(BuildContext context) {
    return V2Scaffold(
      showBack: true,
      title: 'Not available here',
      body: Builder(
        builder: (context) {
          final p = V2Theme.of(context);
          return Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(28),
              child: V2Entrance(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 108,
                      height: 108,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: p.warning.withOpacity(0.14),
                      ),
                      child: Icon(Icons.location_off_rounded,
                          size: 52, color: p.warning),
                    ),
                    const SizedBox(height: 22),
                    Text('We are not in your area yet',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 20,
                            fontWeight: FontWeight.w900)),
                    const SizedBox(height: 8),
                    if (locationLabel.trim().isNotEmpty)
                      Text(locationLabel,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                              color: p.inkSoft,
                              fontSize: 13,
                              fontWeight: FontWeight.w700)),
                    const SizedBox(height: 6),
                    Text(
                      "No restaurants deliver to this location right now. "
                      "We're expanding fast — try a different address, or "
                      'check back soon.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          color: p.inkFaint, fontSize: 12.5, height: 1.5),
                    ),
                    const SizedBox(height: 24),
                    V2Tappable(
                      onTap: onChangeLocation,
                      child: Container(
                        height: 50,
                        padding: const EdgeInsets.symmetric(horizontal: 30),
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: p.accent,
                          borderRadius: BorderRadius.circular(15),
                        ),
                        child: const Text('Change location',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 15)),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
