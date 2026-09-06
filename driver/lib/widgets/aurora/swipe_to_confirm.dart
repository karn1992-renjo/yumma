import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../theme/foodflow_theme.dart';

/// Slide-to-confirm control styled like a classic "slide to unlock" pill:
/// a white track, a coloured circular knob with a chevron on the left, a
/// centred label, and a gradient that fills in behind the knob as it travels.
/// On confirm the knob snaps to the end, turns into a white check and the
/// whole pill takes the gradient with the success label.
class SwipeToConfirm extends StatefulWidget {
  const SwipeToConfirm({
    super.key,
    required this.label,
    required this.onConfirmed,
    this.confirmedLabel = 'Done',
    this.loading = false,
    this.enabled = true,
    this.accent,
    this.icon = Icons.chevron_right_rounded,
    this.height = 60,
  });

  final String label;
  final String confirmedLabel;
  final Future<void> Function() onConfirmed;
  final bool loading;
  final bool enabled;
  final Color? accent;
  final IconData icon;
  final double height;

  @override
  State<SwipeToConfirm> createState() => _SwipeToConfirmState();
}

class _SwipeToConfirmState extends State<SwipeToConfirm>
    with TickerProviderStateMixin {
  double _progress = 0;
  bool _armed = false;
  bool _confirmed = false;
  bool _dragging = false;

  late final AnimationController _settle = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 360),
  )..addListener(() => setState(() => _progress = _settleAnim.value));
  Animation<double> _settleAnim = const AlwaysStoppedAnimation<double>(0);

  late final AnimationController _shine = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1700),
  )..repeat();

  Color get _c1 => widget.accent ?? foodflow.success;
  Color get _c2 {
    // A brighter, slightly hue-shifted partner colour for the gradient.
    final h = HSLColor.fromColor(_c1);
    return h
        .withHue((h.hue + 12) % 360)
        .withLightness((h.lightness + 0.12).clamp(0.0, 1.0))
        .withSaturation((h.saturation + 0.05).clamp(0.0, 1.0))
        .toColor();
  }

  Gradient get _gradient => LinearGradient(
        begin: Alignment.centerLeft,
        end: Alignment.centerRight,
        colors: [_c1, _c2],
      );

  bool get _interactive => widget.enabled && !widget.loading && !_confirmed;

  @override
  void dispose() {
    _settle.dispose();
    _shine.dispose();
    super.dispose();
  }

  void _settleTo(double target, {Curve curve = Curves.easeOutCubic}) {
    _settleAnim = Tween<double>(begin: _progress, end: target)
        .animate(CurvedAnimation(parent: _settle, curve: curve));
    _settle.forward(from: 0);
  }

  Future<void> _fire() async {
    setState(() => _confirmed = true);
    _settleTo(1, curve: Curves.easeOutBack);
    HapticFeedback.mediumImpact();
    try {
      await widget.onConfirmed();
    } finally {
      if (mounted) {
        setState(() {
          _confirmed = false;
          _armed = false;
        });
        _settleTo(0);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final h = widget.height;
    final pad = 5.0;
    final knob = h - pad * 2;

    return LayoutBuilder(
      builder: (context, c) {
        final maxTravel =
            (c.maxWidth - knob - pad * 2).clamp(1.0, double.infinity);
        final x = pad + maxTravel * _progress;
        final ready = _progress > 0.9;
        // gradient reveal width follows the knob's right edge
        final revealW = x + knob + pad;
        final labelFade = (1 - _progress * 2.4).clamp(0.0, 1.0);

        final trackColor = _confirmed ? Colors.transparent : Colors.white;

        return GestureDetector(
          onHorizontalDragStart:
              _interactive ? (_) => setState(() => _dragging = true) : null,
          onHorizontalDragUpdate: _interactive
              ? (d) {
                  _settle.stop();
                  setState(() {
                    _progress = (_progress + (d.primaryDelta ?? 0) / maxTravel)
                        .clamp(0.0, 1.0);
                  });
                  final a = _progress > 0.9;
                  if (a != _armed) {
                    _armed = a;
                    HapticFeedback.selectionClick();
                  }
                }
              : null,
          onHorizontalDragEnd: _interactive
              ? (_) {
                  setState(() => _dragging = false);
                  ready ? _fire() : _settleTo(0);
                }
              : null,
          child: Container(
            height: h,
            decoration: BoxDecoration(
              color: trackColor,
              borderRadius: BorderRadius.circular(h / 2),
              gradient: _confirmed ? _gradient : null,
              boxShadow: [
                BoxShadow(
                  color: _c1.withOpacity(_confirmed ? 0.35 : 0.16),
                  blurRadius: 18,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(h / 2),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // gradient that fills in from the left behind the knob
                  if (!_confirmed)
                    Positioned(
                      left: 0,
                      top: 0,
                      bottom: 0,
                      width: revealW,
                      child: DecoratedBox(
                        decoration: BoxDecoration(gradient: _gradient),
                      ),
                    ),

                  // label
                  Padding(
                    padding: EdgeInsets.symmetric(horizontal: knob + 12),
                    child: _confirmed
                        ? Text(
                            widget.confirmedLabel,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 15.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.3,
                            ),
                          )
                        : Opacity(
                            opacity: labelFade,
                            child: Text(
                              widget.loading
                                  ? 'Please wait…'
                                  : ready
                                      ? 'Release'
                                      : widget.label,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: foodflow.ink,
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.2,
                              ),
                            ),
                          ),
                  ),

                  // travelling shine over the un-revealed part of the label
                  if (_interactive && _progress < 0.06)
                    AnimatedBuilder(
                      animation: _shine,
                      builder: (context, _) {
                        return Align(
                          alignment: Alignment(-1.2 + 2.4 * _shine.value, 0),
                          child: Container(
                            width: 70,
                            height: h,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                colors: [
                                  Colors.white.withOpacity(0),
                                  Colors.white.withOpacity(0.35),
                                  Colors.white.withOpacity(0),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),

                  // knob
                  AnimatedPositioned(
                    duration: _dragging
                        ? Duration.zero
                        : const Duration(milliseconds: 40),
                    left: x,
                    top: pad,
                    child: Container(
                      width: knob,
                      height: knob,
                      decoration: BoxDecoration(
                        color: _confirmed ? Colors.white : null,
                        gradient: _confirmed ? null : _gradient,
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withOpacity(0.18),
                            blurRadius: 10,
                            offset: const Offset(0, 3),
                          ),
                        ],
                      ),
                      alignment: Alignment.center,
                      child: widget.loading
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.4,
                                valueColor:
                                    AlwaysStoppedAnimation(Colors.white),
                              ),
                            )
                          : Icon(
                              _confirmed
                                  ? Icons.check_rounded
                                  : widget.icon,
                              color: _confirmed ? _c1 : Colors.white,
                              size: 24,
                            ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}
