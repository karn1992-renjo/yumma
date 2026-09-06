// Static glass primitives shared by every V2 screen.

import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../theme/v2_theme.dart';
import 'v2_anim.dart';

/// A translucent gradient panel with a hairline border + soft shadow.
/// No BackdropFilter — safe to use dozens per screen.
class GlassPanel extends StatelessWidget {
  const GlassPanel({
    super.key,
    required this.child,
    this.radius = 22,
    this.padding = EdgeInsets.zero,
    this.strong = false,
    this.onTap,
    this.border = true,
  });

  final Widget child;
  final double radius;
  final EdgeInsetsGeometry padding;
  final bool strong;
  final VoidCallback? onTap;
  final bool border;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final decorated = DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: strong
              ? [p.glassStrongTop, p.glassStrongBottom]
              : [p.glassTop, p.glassBottom],
        ),
        borderRadius: BorderRadius.circular(radius),
        border: border ? Border.all(color: p.glassBorder) : null,
        boxShadow: [
          BoxShadow(
            color: p.shadow,
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Padding(padding: padding, child: child),
    );
    if (onTap == null) {
      return ClipRRect(borderRadius: BorderRadius.circular(radius), child: decorated);
    }
    return V2Tappable(
      onTap: onTap,
      borderRadius: BorderRadius.circular(radius),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: decorated,
      ),
    );
  }
}

/// The ONE place a real blur is used: the pinned home header.
class FrostedBar extends StatelessWidget {
  const FrostedBar({super.key, required this.child, this.sigma = 18});

  final Widget child;
  final double sigma;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return ClipRect(
      child: BackdropFilter(
        filter: ui.ImageFilter.blur(sigmaX: sigma, sigmaY: sigma),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: p.headerScrim,
            border: Border(bottom: BorderSide(color: p.glassBorder)),
          ),
          child: child,
        ),
      ),
    );
  }
}

class GlassIconButton extends StatelessWidget {
  const GlassIconButton({
    super.key,
    required this.icon,
    required this.onTap,
    this.badge = 0,
    this.size = 20,
  });

  final IconData icon;
  final VoidCallback onTap;
  final int badge;
  final double size;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 14,
      strong: true,
      onTap: onTap,
      padding: const EdgeInsets.all(9),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Icon(icon, size: size, color: p.ink),
          if (badge > 0)
            Positioned(
              right: -7,
              top: -7,
              child: Container(
                padding: const EdgeInsets.all(3),
                constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                decoration: BoxDecoration(
                  color: p.danger,
                  shape: BoxShape.circle,
                  border: Border.all(color: p.bgTop, width: 1.5),
                ),
                child: Text(
                  badge > 9 ? '9+' : '$badge',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    height: 1,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class GlassChip extends StatelessWidget {
  const GlassChip({
    super.key,
    required this.label,
    required this.onTap,
    this.selected = false,
    this.icon,
  });

  final String label;
  final VoidCallback onTap;
  final bool selected;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return V2Tappable(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? p.accent : p.glassTop,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: selected ? p.accent : p.glassBorder,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon,
                  size: 14,
                  color: selected ? Colors.white : p.inkSoft),
              const SizedBox(width: 5),
            ],
            Text(
              label,
              style: TextStyle(
                color: selected ? Colors.white : p.inkSoft,
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Veg / non-veg square marker.
class VegDot extends StatelessWidget {
  const VegDot({super.key, required this.isVeg, this.size = 12});

  final bool isVeg;
  final double size;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final color = isVeg ? p.positive : p.danger;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * 0.25),
        border: Border.all(color: color, width: 1.5),
      ),
      child: Center(
        child: Container(
          width: size * 0.42,
          height: size * 0.42,
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(size * 0.14),
          ),
        ),
      ),
    );
  }
}

class RatingPill extends StatelessWidget {
  const RatingPill({super.key, required this.rating});

  final double rating;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
      decoration: BoxDecoration(
        color: p.positive.withOpacity(p.isDark ? 0.20 : 0.14),
        borderRadius: BorderRadius.circular(7),
        border: Border.all(color: p.positive.withOpacity(0.55)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            rating.toStringAsFixed(1),
            style: TextStyle(
              color: p.ink,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(width: 2),
          Icon(Icons.star_rounded, size: 12, color: p.positive),
        ],
      ),
    );
  }
}

/// Section title + optional subtitle + optional trailing action.
class V2SectionHeader extends StatelessWidget {
  const V2SectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: p.ink,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                  ),
                ),
                if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    style: TextStyle(
                      color: p.inkFaint,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (actionLabel != null && onAction != null)
            V2Tappable(
              onTap: onAction,
              child: Padding(
                padding: const EdgeInsets.only(left: 10, top: 2),
                child: Text(
                  actionLabel!,
                  style: TextStyle(
                    color: p.accent,
                    fontSize: 12.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Full-bleed empty / error state.
class V2EmptyState extends StatelessWidget {
  const V2EmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.onRetry,
  });

  final IconData icon;
  final String title;
  final String? message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 46, color: p.inkFaint),
            const SizedBox(height: 12),
            Text(
              title,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: p.ink,
                fontSize: 16,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (message != null) ...[
              const SizedBox(height: 6),
              Text(
                message!,
                textAlign: TextAlign.center,
                style: TextStyle(color: p.inkFaint, fontSize: 12.5),
              ),
            ],
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              V2Tappable(
                onTap: onRetry,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 11),
                  decoration: BoxDecoration(
                    color: p.accent,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Text(
                    'Retry',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Shimmering skeleton block for loading states.
class V2Skeleton extends StatefulWidget {
  const V2Skeleton({
    super.key,
    this.width,
    this.height = 14,
    this.radius = 8,
  });

  final double? width;
  final double height;
  final double radius;

  @override
  State<V2Skeleton> createState() => _V2SkeletonState();
}

class _V2SkeletonState extends State<V2Skeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return AnimatedBuilder(
      animation: _c,
      builder: (context, _) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          color: Color.lerp(p.glassTop, p.glassStrongTop, _c.value),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}
