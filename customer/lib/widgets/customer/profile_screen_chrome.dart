import 'package:flutter/material.dart';
import 'package:flutter_lucide/flutter_lucide.dart';

import '../../theme/foodflow_theme.dart';

Color profileCanvasColor(BuildContext context) =>
    Theme.of(context).colorScheme.surface;

Color profileAccentColor(BuildContext context) =>
    FoodFlowTheme.brandPrimary(context);

Color profileButtonColor(BuildContext context) =>
    FoodFlowTheme.brandSecondary(context);

Color profileOnButtonColor(BuildContext context) =>
    Theme.of(context).colorScheme.onSecondary;

Color profileButtonSoftColor(BuildContext context, [double amount = 0.92]) {
  final scheme = Theme.of(context).colorScheme;
  return Color.lerp(profileButtonColor(context), scheme.surface, amount) ??
      scheme.surface;
}

Color profileTextColor(BuildContext context) =>
    Theme.of(context).colorScheme.onSurface;

Color profileMutedColor(BuildContext context) =>
    Theme.of(context).colorScheme.onSurfaceVariant;

Color profileLineColor(BuildContext context) =>
    Theme.of(context).colorScheme.outlineVariant;

Color profileSoftColor(BuildContext context, [double amount = 0.9]) {
  final scheme = Theme.of(context).colorScheme;
  return Color.lerp(profileAccentColor(context), scheme.surface, amount) ??
      scheme.surface;
}

BoxDecoration profileSurfaceDecoration(
  BuildContext context, {
  double radius = 20,
}) {
  final scheme = Theme.of(context).colorScheme;
  return BoxDecoration(
    color: scheme.surface,
    borderRadius: BorderRadius.circular(radius),
    border: Border.all(color: profileLineColor(context)),
    boxShadow: [
      BoxShadow(
        color: scheme.shadow.withOpacity(0.025),
        blurRadius: 12,
        offset: const Offset(0, 6),
      ),
    ],
  );
}

class ProfilePageTopBar extends StatelessWidget {
  const ProfilePageTopBar({
    super.key,
    required this.title,
    required this.subtitle,
    this.actions = const <Widget>[],
  });

  final String title;
  final String subtitle;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ProfileRoundButton(
          icon: LucideIcons.arrow_left,
          onTap: () => Navigator.of(context).maybePop(),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: profileTextColor(context),
                  fontSize: 20,
                  height: 1.05,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: profileMutedColor(context),
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
        ),
        ...actions,
      ],
    );
  }
}

class ProfileRoundButton extends StatelessWidget {
  const ProfileRoundButton({
    super.key,
    required this.icon,
    required this.onTap,
    this.color,
    this.showDot = false,
  });

  final IconData icon;
  final VoidCallback? onTap;
  final Color? color;
  final bool showDot;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        width: 44,
        height: 44,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Icon(icon, size: 29, color: color ?? profileTextColor(context)),
            if (showDot)
              Positioned(
                top: 8,
                right: 9,
                child: Container(
                  width: 10,
                  height: 10,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.error,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Theme.of(context).colorScheme.surface,
                      width: 1.4,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class ProfileSurfaceCard extends StatelessWidget {
  const ProfileSurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.margin = EdgeInsets.zero,
    this.radius = 20,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: profileSurfaceDecoration(context, radius: radius),
      child: child,
    );
  }
}

class ProfileSectionLabel extends StatelessWidget {
  const ProfileSectionLabel({
    super.key,
    required this.title,
  });

  final String title;

  @override
  Widget build(BuildContext context) {
    return Text(
      title.toUpperCase(),
      style: TextStyle(
        color: profileMutedColor(context),
        fontSize: 11,
        letterSpacing: 0,
        fontWeight: FontWeight.w800,
      ),
    );
  }
}

class ProfileAccentIcon extends StatelessWidget {
  const ProfileAccentIcon({
    super.key,
    required this.icon,
    this.iconColor,
    this.backgroundColor,
    this.size = 48,
    this.iconSize = 22,
    this.radius = 14,
  });

  final IconData icon;
  final Color? iconColor;
  final Color? backgroundColor;
  final double size;
  final double iconSize;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: backgroundColor ?? profileSoftColor(context),
        borderRadius: BorderRadius.circular(radius),
      ),
      child: Icon(
        icon,
        color: iconColor ?? profileAccentColor(context),
        size: iconSize,
      ),
    );
  }
}
