// lib/widgets/stories/story_layout.dart
//
// The look of a Story, shared by the restaurant app (composer + preview) and the customer app (viewer).
// Four layouts: classic, spotlight, split, poster. When a menu item is linked, every layout shows the dish
// with a call-to-action button. Keep this file identical in both apps.
import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';

import '../../utils/currency_utils.dart';

const Color kStoryAccent = Color(0xFFFF6A2B);

/// Layouts a restaurant can choose from, in display order.
const List<({String key, String label, IconData icon})> kStoryLayouts = [
  (key: 'classic', label: 'Classic', icon: Icons.crop_portrait_rounded),
  (key: 'spotlight', label: 'Spotlight', icon: Icons.view_agenda_rounded),
  (key: 'split', label: 'Split', icon: Icons.splitscreen_rounded),
  (key: 'poster', label: 'Poster', icon: Icons.format_size_rounded),
];

/// Quick call-to-action wordings.
const List<String> kStoryCtaOptions = ['Order now', 'Add to cart', 'Try now', 'View dish'];

class StoryLayoutView extends StatelessWidget {
  const StoryLayoutView({
    super.key,
    required this.media,
    this.layout = 'classic',
    this.caption,
    this.menuItem,
    this.ctaLabel,
    this.onCta,
    this.topInset = 0,
    this.compact = false,
  });

  /// The picture / video, drawn to fill its space.
  final Widget media;
  final String layout;
  final String? caption;

  /// {id, name, price, image_url, is_veg} of the linked dish, or null.
  final Map<String, dynamic>? menuItem;
  final String? ctaLabel;

  /// Tapped CTA. Null shows the button without an action (a preview).
  final VoidCallback? onCta;

  /// Space kept free at the top (the viewer's progress bar and close button).
  final double topInset;

  /// Smaller type and paddings, for the composer's phone-sized preview.
  final bool compact;

  String get _cta => (ctaLabel == null || ctaLabel!.trim().isEmpty) ? 'Order now' : ctaLabel!.trim();
  bool get _hasDish => menuItem != null;
  bool get _hasCaption => caption != null && caption!.trim().isNotEmpty;

  @override
  Widget build(BuildContext context) {
    switch (layout) {
      case 'spotlight':
        return _spotlight(context);
      case 'split':
        return _split(context);
      case 'poster':
        return _poster(context);
      default:
        return _classic(context);
    }
  }

  double get _s => compact ? 0.72 : 1.0;

  // ---- classic: full picture, caption and a slim dish bar at the bottom ----
  Widget _classic(BuildContext context) {
    return Stack(fit: StackFit.expand, children: [
      media,
      Positioned.fill(child: _gradient(top: 0.0, bottom: 0.62)),
      Positioned(
        left: 18 * _s,
        right: 18 * _s,
        bottom: 26 * _s,
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (_hasCaption) _captionText(size: 17, weight: FontWeight.w700, lines: 4),
          if (_hasDish) ...[
            SizedBox(height: 12 * _s),
            _dishBar(context),
          ],
        ]),
      ),
    ]);
  }

  // ---- spotlight: full picture with a frosted dish card ----
  Widget _spotlight(BuildContext context) {
    return Stack(fit: StackFit.expand, children: [
      media,
      Positioned.fill(child: _gradient(top: 0.28, bottom: 0.7)),
      Positioned(
        left: 16 * _s,
        right: 16 * _s,
        bottom: 22 * _s,
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (_hasCaption) ...[
            _captionText(size: 18, weight: FontWeight.w800, lines: 3),
            SizedBox(height: 12 * _s),
          ],
          if (_hasDish)
            ClipRRect(
              borderRadius: BorderRadius.circular(22 * _s),
              child: BackdropFilter(
                filter: ImageFilter.blur(sigmaX: 14, sigmaY: 14),
                child: Container(
                  padding: EdgeInsets.all(12 * _s),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.16),
                    borderRadius: BorderRadius.circular(22 * _s),
                    border: Border.all(color: Colors.white.withOpacity(0.28)),
                  ),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Row(children: [
                      _dishThumb(64 * _s),
                      SizedBox(width: 12 * _s),
                      Expanded(child: _dishText(context, big: true)),
                    ]),
                    SizedBox(height: 12 * _s),
                    _ctaButton(expand: true),
                  ]),
                ),
              ),
            ),
        ]),
      ),
    ]);
  }

  // ---- split: framed picture on a dark canvas, details underneath ----
  Widget _split(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF1E1B2E), Color(0xFF0E0D14)]),
      ),
      padding: EdgeInsets.fromLTRB(14 * _s, (topInset > 0 ? topInset : 14) * _s, 14 * _s, 18 * _s),
      child: Column(children: [
        Expanded(
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(26 * _s),
              boxShadow: [BoxShadow(color: kStoryAccent.withOpacity(0.25), blurRadius: 28, offset: const Offset(0, 10))],
            ),
            child: ClipRRect(borderRadius: BorderRadius.circular(26 * _s), child: SizedBox.expand(child: media)),
          ),
        ),
        SizedBox(height: 14 * _s),
        if (_hasCaption) _captionText(size: 16, weight: FontWeight.w700, lines: 2, shadow: false),
        if (_hasDish) ...[
          SizedBox(height: 10 * _s),
          Row(children: [
            _dishThumb(46 * _s),
            SizedBox(width: 10 * _s),
            Expanded(child: _dishText(context)),
          ]),
          SizedBox(height: 12 * _s),
          _ctaButton(expand: true),
        ],
      ]),
    );
  }

  // ---- poster: bold headline, price sticker, centred button ----
  Widget _poster(BuildContext context) {
    final price = _hasDish ? (menuItem!['price'] as num?)?.toDouble() : null;
    return Stack(fit: StackFit.expand, children: [
      media,
      Positioned.fill(child: Container(color: Colors.black.withOpacity(0.38))),
      Positioned.fill(child: _gradient(top: 0.2, bottom: 0.55)),
      if (price != null)
        Positioned(
          top: (topInset + 12) * (compact ? 0.72 : 1),
          right: 18 * _s,
          child: Transform.rotate(
            angle: -0.12,
            child: Container(
              padding: EdgeInsets.symmetric(horizontal: 14 * _s, vertical: 10 * _s),
              decoration: BoxDecoration(
                color: kStoryAccent,
                shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.35), blurRadius: 14, offset: const Offset(0, 6))],
              ),
              child: Text(
                formatCurrencyValue(context, price),
                style: TextStyle(color: Colors.white, fontSize: 15 * _s, fontWeight: FontWeight.w900),
              ),
            ),
          ),
        ),
      Positioned(
        left: 22 * _s,
        right: 22 * _s,
        bottom: 30 * _s,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 44 * _s, height: 5 * _s, decoration: BoxDecoration(color: kStoryAccent, borderRadius: BorderRadius.circular(3))),
          SizedBox(height: 14 * _s),
          if (_hasCaption)
            Text(
              caption!.trim().toUpperCase(),
              textAlign: TextAlign.center,
              maxLines: 4,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: Colors.white, fontSize: 28 * _s, height: 1.1, fontWeight: FontWeight.w900, letterSpacing: 0.5, shadows: const [Shadow(color: Colors.black54, blurRadius: 12)]),
            )
          else if (_hasDish)
            Text(
              (menuItem!['name'] ?? '').toString().toUpperCase(),
              textAlign: TextAlign.center,
              maxLines: 3,
              style: TextStyle(color: Colors.white, fontSize: 28 * _s, height: 1.1, fontWeight: FontWeight.w900, shadows: const [Shadow(color: Colors.black54, blurRadius: 12)]),
            ),
          if (_hasDish) ...[
            if (_hasCaption) ...[
              SizedBox(height: 8 * _s),
              Text((menuItem!['name'] ?? '').toString(), style: TextStyle(color: Colors.white.withOpacity(0.9), fontSize: 14 * _s, fontWeight: FontWeight.w700)),
            ],
            SizedBox(height: 16 * _s),
            _ctaButton(expand: false),
          ],
        ]),
      ),
    ]);
  }

  // ---- pieces ----
  Widget _gradient({required double top, required double bottom}) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Colors.black.withOpacity(top), Colors.transparent, Colors.black.withOpacity(bottom)],
          stops: const [0.0, 0.45, 1.0],
        ),
      ),
    );
  }

  Widget _captionText({required double size, required FontWeight weight, required int lines, bool shadow = true}) {
    return Text(
      caption!.trim(),
      maxLines: lines,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: Colors.white,
        fontSize: size * _s,
        height: 1.25,
        fontWeight: weight,
        shadows: shadow ? const [Shadow(color: Colors.black54, blurRadius: 10)] : null,
      ),
    );
  }

  Widget _dishThumb(double size) {
    final url = (menuItem?['image_url'] ?? '').toString();
    return ClipRRect(
      borderRadius: BorderRadius.circular(size * 0.26),
      child: SizedBox(
        width: size,
        height: size,
        child: url.isNotEmpty
            ? Image.network(url, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _thumbFallback())
            : _thumbFallback(),
      ),
    );
  }

  Widget _thumbFallback() => Container(color: Colors.white24, child: const Icon(Icons.restaurant_rounded, color: Colors.white70));

  Widget _dishText(BuildContext context, {bool big = false}) {
    final price = (menuItem!['price'] as num?)?.toDouble() ?? 0;
    final veg = menuItem!['is_veg'] == true;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
      Row(children: [
        Container(
          width: 12 * _s,
          height: 12 * _s,
          decoration: BoxDecoration(border: Border.all(color: veg ? const Color(0xFF22C55E) : const Color(0xFFEF4444), width: 1.5), borderRadius: BorderRadius.circular(3)),
          child: Center(child: Container(width: 5.5 * _s, height: 5.5 * _s, decoration: BoxDecoration(color: veg ? const Color(0xFF22C55E) : const Color(0xFFEF4444), shape: BoxShape.circle))),
        ),
        SizedBox(width: 6 * _s),
        Expanded(
          child: Text(
            (menuItem!['name'] ?? '').toString(),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: Colors.white, fontSize: (big ? 16 : 14) * _s, fontWeight: FontWeight.w800),
          ),
        ),
      ]),
      SizedBox(height: 3 * _s),
      Text(formatCurrencyValue(context, price), style: TextStyle(color: Colors.white.withOpacity(0.92), fontSize: (big ? 15 : 13) * _s, fontWeight: FontWeight.w700)),
    ]);
  }

  Widget _dishBar(BuildContext context) {
    return Container(
      padding: EdgeInsets.all(8 * _s),
      decoration: BoxDecoration(
        color: Colors.black.withOpacity(0.42),
        borderRadius: BorderRadius.circular(18 * _s),
        border: Border.all(color: Colors.white.withOpacity(0.18)),
      ),
      child: Row(children: [
        _dishThumb(46 * _s),
        SizedBox(width: 10 * _s),
        Expanded(child: _dishText(context)),
        SizedBox(width: 8 * _s),
        _ctaButton(expand: false),
      ]),
    );
  }

  Widget _ctaButton({required bool expand}) {
    final button = Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onCta,
        borderRadius: BorderRadius.circular(999),
        child: Ink(
          padding: EdgeInsets.symmetric(horizontal: 22 * _s, vertical: 12 * _s),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(999),
            gradient: const LinearGradient(colors: [Color(0xFFFF8A3D), kStoryAccent]),
            boxShadow: [BoxShadow(color: kStoryAccent.withOpacity(0.45), blurRadius: 14, offset: const Offset(0, 6))],
          ),
          child: Row(mainAxisSize: MainAxisSize.min, mainAxisAlignment: MainAxisAlignment.center, children: [
            Text(_cta, style: TextStyle(color: Colors.white, fontSize: 14 * _s, fontWeight: FontWeight.w900)),
            SizedBox(width: 6 * _s),
            Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 17 * _s),
          ]),
        ),
      ),
    );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
