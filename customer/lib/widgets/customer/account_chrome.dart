import 'package:flutter/material.dart';

const accountCanvas = Color(0xFFFAFAFA);
const accountBorder = Color(0xFFE5E7EB);
const accountMuted = Color(0xFF6B7280);
const accountText = Color(0xFF111827);

class AccountHeroCard extends StatelessWidget {
  const AccountHeroCard({
    super.key,
    required this.title,
    required this.subtitle,
    required this.icon,
    this.badge,
    this.trailing,
    this.margin = const EdgeInsets.fromLTRB(16, 8, 16, 16),
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final String? badge;
  final Widget? trailing;
  final EdgeInsets margin;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Container(
      margin: margin,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            scheme.primary,
            scheme.secondary,
          ],
        ),
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: scheme.primary.withOpacity(0.18),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (badge != null && badge!.trim().isNotEmpty) ...[
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 7,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.16),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(color: Colors.white.withOpacity(0.14)),
                    ),
                    child: Text(
                      badge!,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.2,
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                ],
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    height: 1.05,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.92),
                    fontSize: 13,
                    fontWeight: FontWeight.w500,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 14),
          trailing ??
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(color: Colors.white.withOpacity(0.12)),
                ),
                child: Icon(icon, color: Colors.white, size: 34),
              ),
        ],
      ),
    );
  }
}

class AccountSurfaceCard extends StatelessWidget {
  const AccountSurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.radius = 24,
    this.margin,
  });

  final Widget child;
  final EdgeInsets padding;
  final double radius;
  final EdgeInsets? margin;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: accountBorder),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );
  }
}

class AccountSectionTitle extends StatelessWidget {
  const AccountSectionTitle({
    super.key,
    required this.title,
    this.actionLabel,
    this.onTap,
  });

  final String title;
  final String? actionLabel;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: accountMuted,
            ),
          ),
        ),
        if (actionLabel != null && onTap != null)
          TextButton(
            onPressed: onTap,
            child: Text(actionLabel!),
          ),
      ],
    );
  }
}
