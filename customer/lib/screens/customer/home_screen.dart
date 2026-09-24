import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/location_service.dart';
import '../../services/tracking_authorization_service.dart';
import 'home_experience.dart';
import 'home_screen_production.dart';
import 'home_v2/home_screen_v2.dart';
import 'theatre/theatre_api_service.dart';
import 'theatre/theatre_entry_screen.dart';
import 'theatre/theatre_models.dart';
import 'theatre/theatre_screen_picker_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      TrackingAuthorizationService.instance.ensureRequested();
    });

    return Stack(
      children: [
        ValueListenableBuilder<bool>(
          valueListenable: homeV2Enabled,
          builder: (context, useV2, _) {
            return useV2
                ? const CustomerHomeScreenV2()
                : const CustomerHomeScreenProduction();
          },
        ),
        // swaad-only: movie-theatre in-seat ordering floating bubble.
        // Appears ONLY when user is detected within a theatre's geofence radius.
        const _TheatreEntryBubble(),
      ],
    );
  }
}

/// Floating BookMyShow-style cinema bubble that appears ONLY when the user
/// is physically inside or in the immediate radius of an enabled theatre.
class _TheatreEntryBubble extends StatefulWidget {
  const _TheatreEntryBubble();

  @override
  State<_TheatreEntryBubble> createState() => _TheatreEntryBubbleState();
}

class _TheatreEntryBubbleState extends State<_TheatreEntryBubble>
    with SingleTickerProviderStateMixin {
  bool _isInTheatreRadius = false;
  TheatreSummary? _activeTheatre;
  double? _lat;
  double? _lng;
  late AnimationController _pulseController;
  late Animation<double> _pulseAnimation;

  @override
  void initState() {
    super.initState();
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);

    _pulseAnimation = Tween<double>(begin: 1.0, end: 1.06).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _checkTheatreRadius();
    });
  }

  @override
  void dispose() {
    _pulseController.dispose();
    super.dispose();
  }

  Future<void> _checkTheatreRadius() async {
    if (!mounted) return;
    final authProvider = context.read<AuthProvider>();
    if (!authProvider.isAuthenticated || !authProvider.canUseCurrentApp) {
      if (mounted) setState(() => _isInTheatreRadius = false);
      return;
    }

    try {
      final position = await LocationService().getCurrentLocation();
      if (position == null) {
        if (mounted) setState(() => _isInTheatreRadius = false);
        return;
      }

      _lat = position.latitude;
      _lng = position.longitude;

      final theatres = await TheatreApiService.instance.nearby(
        lat: position.latitude,
        lng: position.longitude,
      );

      final inside = theatres.where((t) => t.withinGeofence).toList();
      if (inside.isNotEmpty && mounted) {
        setState(() {
          _activeTheatre = inside.first;
          _isInTheatreRadius = true;
        });
      } else if (mounted) {
        setState(() {
          _isInTheatreRadius = false;
          _activeTheatre = null;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isInTheatreRadius = false);
    }
  }

  void _open() {
    if (_activeTheatre == null) {
      Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const TheatreEntryScreen()),
      );
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TheatreScreenPickerScreen(
          theatre: _activeTheatre!,
          lat: _lat ?? 0,
          lng: _lng ?? 0,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // Hidden whenever user is outside theatre radius
    if (!_isInTheatreRadius || _activeTheatre == null) {
      return const SizedBox.shrink();
    }

    return Positioned(
      right: 16,
      bottom: 92,
      child: SafeArea(
        top: false,
        child: ScaleTransition(
          scale: _pulseAnimation,
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: _open,
              borderRadius: BorderRadius.circular(30),
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFFF84464), Color(0xFFDC2626)],
                  ),
                  borderRadius: BorderRadius.circular(30),
                  boxShadow: [
                    BoxShadow(
                      color: const Color(0xFFF84464).withOpacity(0.45),
                      blurRadius: 16,
                      offset: const Offset(0, 4),
                    ),
                    BoxShadow(
                      color: Colors.black.withOpacity(0.2),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                  border: Border.all(color: Colors.white, width: 1.5),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 28,
                      height: 28,
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        shape: BoxShape.circle,
                      ),
                      child: const Center(
                        child: Icon(
                          Icons.theaters_rounded,
                          color: Color(0xFFF84464),
                          size: 16,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: const BoxDecoration(
                                color: Color(0xFF22C55E),
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 4),
                            ConstrainedBox(
                              constraints: const BoxConstraints(maxWidth: 130),
                              child: Text(
                                _activeTheatre!.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const Text(
                          'Order to Seat',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 12.5,
                            fontWeight: FontWeight.w900,
                            letterSpacing: 0.3,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(width: 6),
                    const Icon(
                      Icons.arrow_forward_ios_rounded,
                      color: Colors.white,
                      size: 12,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
