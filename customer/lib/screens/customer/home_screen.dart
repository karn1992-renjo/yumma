import 'package:flutter/material.dart';

import '../../services/tracking_authorization_service.dart';
import 'home_experience.dart';
import 'home_screen_production.dart';
import 'home_v2/home_screen_v2.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      TrackingAuthorizationService.instance.ensureRequested();
    });

    return ValueListenableBuilder<bool>(
      valueListenable: homeV2Enabled,
      builder: (context, useV2, _) {
        return useV2
            ? const CustomerHomeScreenV2()
            : const CustomerHomeScreenProduction();
      },
    );
  }
}
