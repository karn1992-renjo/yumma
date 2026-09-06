import 'package:flutter/material.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../../../utils/currency_utils.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

/// The re-priced delivery details handed back after the user edits the
/// location from the review screen.
class PreOrderReview {
  const PreOrderReview({
    required this.address,
    required this.lat,
    required this.lng,
    required this.total,
  });
  final String address;
  final double? lat;
  final double? lng;
  final double total;
}

/// "Review before you order" — a map of the delivery pin plus the amount,
/// shown between the checkout screen and the actual order call.
class PreOrderConfirmV2 extends StatefulWidget {
  const PreOrderConfirmV2({
    super.key,
    required this.address,
    required this.lat,
    required this.lng,
    required this.total,
    required this.paymentLabel,
    required this.onConfirm,
    this.onEditLocation,
  });

  final String address;
  final double? lat;
  final double? lng;
  final double total;
  final String paymentLabel;

  /// Runs the real order placement. Returns true on success (the screen then
  /// pops itself); false leaves the screen up so the user can retry.
  final Future<bool> Function() onConfirm;

  /// Opens the location picker, saves + re-prices, and returns the updated
  /// details. Null when the user backs out. When omitted the address row is
  /// not editable.
  final Future<PreOrderReview?> Function()? onEditLocation;

  @override
  State<PreOrderConfirmV2> createState() => _PreOrderConfirmV2State();
}

class _PreOrderConfirmV2State extends State<PreOrderConfirmV2> {
  bool _busy = false;
  bool _editingLocation = false;

  late String _address = widget.address;
  late double? _lat = widget.lat;
  late double? _lng = widget.lng;
  late double _total = widget.total;

  Future<void> _confirm() async {
    setState(() => _busy = true);
    final ok = await widget.onConfirm();
    if (!mounted) return;
    if (ok) {
      Navigator.of(context).pop();
    } else {
      setState(() => _busy = false);
    }
  }

  Future<void> _editLocation() async {
    final cb = widget.onEditLocation;
    if (cb == null || _editingLocation) return;
    setState(() => _editingLocation = true);
    final res = await cb();
    if (!mounted) return;
    setState(() {
      _editingLocation = false;
      if (res != null) {
        _address = res.address;
        _lat = res.lat;
        _lng = res.lng;
        _total = res.total;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final hasPin = _lat != null && _lng != null;
    final canEdit = widget.onEditLocation != null;
    return V2Scaffold(
      showBack: true,
      title: 'Review your order',
      body: Builder(
        builder: (context) {
          final p = V2Theme.of(context);
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
                  children: [
                    if (hasPin)
                      ClipRRect(
                        borderRadius: BorderRadius.circular(20),
                        child: SizedBox(
                          height: 220,
                          child: GoogleMap(
                            key: ValueKey('map_${_lat}_$_lng'),
                            initialCameraPosition: CameraPosition(
                              target: LatLng(_lat!, _lng!),
                              zoom: 16,
                            ),
                            markers: {
                              Marker(
                                markerId: const MarkerId('drop'),
                                position: LatLng(_lat!, _lng!),
                                icon:
                                    BitmapDescriptor.defaultMarkerWithHue(
                                        BitmapDescriptor.hueGreen),
                              ),
                            },
                            myLocationButtonEnabled: false,
                            zoomControlsEnabled: false,
                            liteModeEnabled: true,
                          ),
                        ),
                      )
                    else
                      GlassPanel(
                        radius: 18,
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          children: [
                            Icon(Icons.info_outline_rounded,
                                size: 18, color: p.warning),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                  'No map pin for this address — the delivery '
                                  'partner will use the text address below.',
                                  style: TextStyle(
                                      color: p.inkSoft, fontSize: 12.5)),
                            ),
                          ],
                        ),
                      ),
                    const SizedBox(height: 14),
                    GlassPanel(
                      radius: 18,
                      onTap: canEdit ? _editLocation : null,
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          Icon(Icons.location_on_rounded,
                              size: 18, color: p.accent),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Delivering to',
                                    style: TextStyle(
                                        color: p.inkFaint, fontSize: 11)),
                                const SizedBox(height: 2),
                                Text(
                                  _address.trim().isEmpty
                                      ? 'Address not set'
                                      : _address,
                                  style: TextStyle(
                                      color: p.ink,
                                      fontSize: 13.5,
                                      fontWeight: FontWeight.w700),
                                ),
                              ],
                            ),
                          ),
                          if (canEdit) ...[
                            const SizedBox(width: 8),
                            _editingLocation
                                ? SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2, color: p.accent),
                                  )
                                : Text('Change',
                                    style: TextStyle(
                                        color: p.accent,
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w900)),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    GlassPanel(
                      radius: 18,
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        children: [
                          _row(context, 'Payment', widget.paymentLabel),
                          const SizedBox(height: 8),
                          _row(context, 'Amount to pay',
                              formatCurrency(context, _total),
                              bold: true),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: EdgeInsets.fromLTRB(16, 12, 16,
                    MediaQuery.of(context).padding.bottom + 14),
                decoration: BoxDecoration(
                  color: p.isDark ? const Color(0xF2121720) : Colors.white,
                  border: Border(top: BorderSide(color: p.glassBorder)),
                ),
                child: V2Tappable(
                  onTap: _busy ? null : _confirm,
                  child: Container(
                    height: 52,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: _busy ? p.inkFaint : p.accent,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: _busy
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white),
                          )
                        : const Text('Confirm & place order',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 15)),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _row(BuildContext context, String label, String value,
      {bool bold = false}) {
    final p = V2Theme.of(context);
    return Row(
      children: [
        Expanded(
          child: Text(label,
              style: TextStyle(
                  color: bold ? p.ink : p.inkSoft,
                  fontSize: bold ? 14.5 : 13,
                  fontWeight: bold ? FontWeight.w900 : FontWeight.w600)),
        ),
        Text(value,
            style: TextStyle(
                color: p.ink,
                fontSize: bold ? 15 : 13,
                fontWeight: bold ? FontWeight.w900 : FontWeight.w700)),
      ],
    );
  }
}
