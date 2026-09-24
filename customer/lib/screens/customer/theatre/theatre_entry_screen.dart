import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../providers/auth_provider.dart';
import '../../../services/location_service.dart';
import 'theatre_api_service.dart';
import 'theatre_models.dart';
import 'theatre_scan_screen.dart';
import 'theatre_screen_picker_screen.dart';
import 'theatre_theme.dart';

/// swaad-only: the entry point for in-seat theatre ordering.
/// Styled as a clean, authentic BookMyShow Light UI with cinema search,
/// experience format chips, and live geofence verification.
class TheatreEntryScreen extends StatefulWidget {
  const TheatreEntryScreen({super.key});

  @override
  State<TheatreEntryScreen> createState() => _TheatreEntryScreenState();
}

enum _LoadState { loading, locationDenied, loaded, error, needsLogin }

class _TheatreEntryScreenState extends State<TheatreEntryScreen> {
  _LoadState _state = _LoadState.loading;
  List<TheatreSummary> _theatres = const [];
  String _searchQuery = '';
  double? _lat;
  double? _lng;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final authProvider = context.read<AuthProvider>();
    if (!authProvider.isAuthenticated || !authProvider.canUseCurrentApp) {
      setState(() => _state = _LoadState.needsLogin);
      return;
    }

    setState(() => _state = _LoadState.loading);
    final position = await LocationService().getCurrentLocation();
    if (position == null) {
      setState(() => _state = _LoadState.locationDenied);
      return;
    }

    _lat = position.latitude;
    _lng = position.longitude;

    try {
      final theatres = await TheatreApiService.instance.nearby(
        lat: position.latitude,
        lng: position.longitude,
      );
      // Show ONLY theatres that are within radius (withinGeofence)
      final inRadiusTheatres =
          theatres.where((t) => t.withinGeofence).toList();
      setState(() {
        _theatres = inRadiusTheatres;
        _state = _LoadState.loaded;
      });
    } catch (e) {
      final message = e.toString().toLowerCase();
      if (message.contains('401') ||
          message.contains('unauthenticated') ||
          message.contains('session expired')) {
        setState(() => _state = _LoadState.needsLogin);
        return;
      }
      setState(() => _state = _LoadState.error);
    }
  }

  void _openTheatre(TheatreSummary theatre) {
    if (!theatre.withinGeofence) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFF1E293B),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
          content: Row(
            children: [
              const Icon(Icons.location_off_rounded,
                  color: BmsTheme.gold, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  'You need to be inside ${theatre.name} to order to a seat there.',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => TheatreScreenPickerScreen(
          theatre: theatre,
          lat: _lat!,
          lng: _lng!,
        ),
      ),
    );
  }

  List<TheatreSummary> get _filteredTheatres {
    if (_searchQuery.trim().isEmpty) return _theatres;
    final query = _searchQuery.toLowerCase();
    return _theatres.where((t) {
      final nameMatches = t.name.toLowerCase().contains(query);
      final cityMatches = (t.city ?? '').toLowerCase().contains(query);
      final addressMatches = (t.address ?? '').toLowerCase().contains(query);
      return nameMatches || cityMatches || addressMatches;
    }).toList();
  }

  void _openScanner() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const TheatreScanScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F5FA), // BookMyShow light canvas
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF111827),
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(
            Icons.arrow_back_rounded,
            size: 22,
            color: Color(0xFF111827),
          ),
          onPressed: () => Navigator.of(context).pop(),
        ),
        titleSpacing: 0,
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Cinemas Near You',
              style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w800,
                color: Color(0xFF111827),
              ),
            ),
            SizedBox(height: 2),
            Text(
              'Order snacks & food delivered to your seat',
              style: TextStyle(
                fontSize: 11.5,
                color: Color(0xFF6B7280),
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        actions: [
          // The short path: a scanned armrest sticker already knows the
          // cinema, the screen and the seat, so it skips this whole list
          // (and the location permission behind it).
          IconButton(
            tooltip: 'Scan the QR on your seat',
            icon: const Icon(
              Icons.qr_code_scanner_rounded,
              size: 22,
              color: Color(0xFF111827),
            ),
            onPressed: _openScanner,
          ),
        ],
      ),
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    switch (_state) {
      case _LoadState.loading:
        return const Center(
          child: CircularProgressIndicator(color: BmsTheme.primary),
        );
      case _LoadState.needsLogin:
        return _buildMessage(
          icon: Icons.lock_outline_rounded,
          title: 'Login Required',
          message:
              'Sign in to your account to order food and concessions straight to your seat.',
          actionLabel: 'Sign In',
          onAction: () => Navigator.pushReplacementNamed(context, '/login'),
        );
      case _LoadState.locationDenied:
        return _buildMessage(
          icon: Icons.location_off_rounded,
          title: 'Location Access Needed',
          message:
              'Turn on location so we can detect whether you\'re seated inside a cinema hall.',
          actionLabel: 'Enable Location',
          onAction: _load,
        );
      case _LoadState.error:
        return _buildMessage(
          icon: Icons.wifi_off_rounded,
          title: 'Could Not Load Cinemas',
          message:
              'Unable to connect to the cinema service. Check your connection.',
          actionLabel: 'Retry',
          onAction: _load,
        );
      case _LoadState.loaded:
        if (_theatres.isEmpty) {
          return _buildMessage(
            icon: Icons.theaters_outlined,
            title: 'No Cinemas in Your Delivery Radius',
            message:
                'In-seat ordering is active only when you are seated inside a participating cinema hall.',
            actionLabel: 'Refresh Location',
            onAction: _load,
          );
        }

        final filtered = _filteredTheatres;

        return RefreshIndicator(
          color: BmsTheme.primary,
          backgroundColor: Colors.white,
          onRefresh: _load,
          child: ListView(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            children: [
              // BookMyShow Style Light Search Bar
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.03),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: TextField(
                  style: const TextStyle(
                    color: Color(0xFF111827),
                    fontSize: 14,
                  ),
                  cursorColor: BmsTheme.primary,
                  decoration: InputDecoration(
                    icon: const Icon(
                      Icons.search_rounded,
                      color: Color(0xFF9CA3AF),
                      size: 20,
                    ),
                    hintText: 'Search cinemas, chains, or locations...',
                    hintStyle: const TextStyle(
                      color: Color(0xFF9CA3AF),
                      fontSize: 13.5,
                    ),
                    border: InputBorder.none,
                    suffixIcon: _searchQuery.isNotEmpty
                        ? IconButton(
                            icon: const Icon(
                              Icons.close_rounded,
                              color: Color(0xFF9CA3AF),
                              size: 18,
                            ),
                            onPressed: () => setState(() => _searchQuery = ''),
                          )
                        : null,
                  ),
                  onChanged: (q) => setState(() => _searchQuery = q),
                ),
              ),

              const SizedBox(height: 14),

              // Filter info chips
              Row(
                children: [
                  Text(
                    '${filtered.length} CINEMAS AVAILABLE',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF6B7280),
                      letterSpacing: 0.8,
                    ),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: const Color(0xFFECFDF5),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: const Color(0xFFA7F3D0)),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.check_circle_rounded,
                            size: 12, color: Color(0xFF059669)),
                        SizedBox(width: 4),
                        Text(
                          'IN-SEAT LIVE',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF059669),
                            letterSpacing: 0.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              // Cinema Cards
              ...filtered.map((theatre) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _BmsLightTheatreCard(
                    theatre: theatre,
                    onTap: () => _openTheatre(theatre),
                  ),
                );
              }),
            ],
          ),
        );
    }
  }

  Widget _buildMessage({
    required IconData icon,
    required String title,
    required String message,
    required String actionLabel,
    required VoidCallback onAction,
  }) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Container(
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: const Color(0xFFE5E7EB)),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: const Color(0xFFFEE2E2),
                  shape: BoxShape.circle,
                  border: Border.all(color: const Color(0xFFFECACA)),
                ),
                child: Icon(icon, size: 28, color: BmsTheme.primary),
              ),
              const SizedBox(height: 16),
              Text(
                title,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF111827),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Color(0xFF6B7280),
                  fontSize: 13,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 20),
              ElevatedButton(
                onPressed: onAction,
                style: ElevatedButton.styleFrom(
                  backgroundColor: BmsTheme.primary,
                  foregroundColor: Colors.white,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: Text(
                  actionLabel,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BmsLightTheatreCard extends StatelessWidget {
  const _BmsLightTheatreCard({
    required this.theatre,
    required this.onTap,
  });

  final TheatreSummary theatre;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final distanceLabel = theatre.distanceMeters < 1000
        ? '${theatre.distanceMeters.round()} m'
        : '${(theatre.distanceMeters / 1000).toStringAsFixed(1)} km';

    final isInside = theatre.withinGeofence;

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(15),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: isInside
                  ? const Color(0xFF059669)
                  : const Color(0xFFE5E7EB),
              width: isInside ? 1.5 : 1.0,
            ),
            boxShadow: isInside
                ? [
                    BoxShadow(
                      color: const Color(0xFF059669).withOpacity(0.12),
                      blurRadius: 10,
                      offset: const Offset(0, 3),
                    ),
                  ]
                : [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.04),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Cinema Icon Badge
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: isInside
                          ? const Color(0xFFECFDF5)
                          : const Color(0xFFFFF1F3),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isInside
                            ? const Color(0xFFA7F3D0)
                            : const Color(0xFFFFD4DC),
                      ),
                    ),
                    child: Center(
                      child: Icon(
                        Icons.theaters_rounded,
                        color: isInside
                            ? const Color(0xFF059669)
                            : BmsTheme.primary,
                        size: 24,
                      ),
                    ),
                  ),

                  const SizedBox(width: 12),

                  // Theatre details
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          theatre.name,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF111827),
                          ),
                        ),
                        const SizedBox(height: 3),
                        if (theatre.address != null &&
                            theatre.address!.isNotEmpty)
                          Text(
                            theatre.address!,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF6B7280),
                            ),
                          ),
                        const SizedBox(height: 6),
                        Row(
                          children: [
                            Icon(
                              Icons.near_me_outlined,
                              size: 13,
                              color: isInside
                                  ? const Color(0xFF059669)
                                  : const Color(0xFF4B5563),
                            ),
                            const SizedBox(width: 4),
                            Text(
                              isInside
                                  ? 'You\'re inside cinema · $distanceLabel away'
                                  : '$distanceLabel away',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: isInside
                                    ? FontWeight.w700
                                    : FontWeight.w500,
                                color: isInside
                                    ? const Color(0xFF059669)
                                    : const Color(0xFF4B5563),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  // Chevron
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: Color(0xFF9CA3AF),
                  ),
                ],
              ),

              const SizedBox(height: 12),
              const Divider(color: Color(0xFFF3F4F6), height: 1),
              const SizedBox(height: 10),

              // Badges & Action (Dynamic tags from backend)
              Row(
                children: [
                  Expanded(
                    child: Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      children: [
                        ...(theatre.tags.isNotEmpty
                                ? theatre.tags.take(3)
                                : const ['M-Ticket', 'In-Seat F&B'])
                            .map((tag) => _buildLightBadge(
                                  tag,
                                  tag.toLowerCase().contains('dolby') ||
                                          tag.toLowerCase().contains('laser') ||
                                          tag.toLowerCase().contains('imax')
                                      ? const Color(0xFF0284C7)
                                      : const Color(0xFF374151),
                                  tag.toLowerCase().contains('dolby') ||
                                          tag.toLowerCase().contains('laser') ||
                                          tag.toLowerCase().contains('imax')
                                      ? const Color(0xFFE0F2FE)
                                      : const Color(0xFFF3F4F6),
                                )),
                        if (theatre.aisleHandoffOnly)
                          _buildLightBadge('Aisle Pickup', const Color(0xFFD97706), const Color(0xFFFEF3C7))
                        else
                          _buildLightBadge('Seat Delivery', const Color(0xFF059669), const Color(0xFFECFDF5)),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  // CTA button
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: isInside ? BmsTheme.primary : Colors.transparent,
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(
                        color: BmsTheme.primary,
                        width: 1.2,
                      ),
                    ),
                    child: Text(
                      isInside ? 'ORDER NOW' : 'VIEW SCREENS',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        color: isInside ? Colors.white : BmsTheme.primary,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLightBadge(String text, Color textColor, Color bgColor) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2.5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        text.toUpperCase(),
        style: TextStyle(
          fontSize: 9.5,
          fontWeight: FontWeight.w700,
          color: textColor,
          letterSpacing: 0.3,
        ),
      ),
    );
  }
}
