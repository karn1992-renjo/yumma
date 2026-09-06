import 'dart:ui';

import 'package:flutter/material.dart';

import '../../theme/aurora_theme.dart';
import '../../theme/foodflow_theme.dart';

/// Fade + slide-up entrance animation. Wrap cards / list items; pass an
/// increasing [delay] (e.g. `index * 60ms`) to stagger a list.
class AuroraEntrance extends StatefulWidget {
  const AuroraEntrance({
    super.key,
    required this.child,
    this.delay = Duration.zero,
    this.offset = 16,
    this.duration = const Duration(milliseconds: 380),
  });

  final Widget child;
  final Duration delay;
  final double offset;
  final Duration duration;

  @override
  State<AuroraEntrance> createState() => _AuroraEntranceState();
}

class _AuroraEntranceState extends State<AuroraEntrance>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller =
      AnimationController(vsync: this, duration: widget.duration);
  late final Animation<double> _anim =
      CurvedAnimation(parent: _controller, curve: Curves.easeOutCubic);

  @override
  void initState() {
    super.initState();
    if (widget.delay == Duration.zero) {
      _controller.forward();
    } else {
      Future<void>.delayed(widget.delay, () {
        if (mounted) _controller.forward();
      });
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _anim,
      builder: (context, child) => Opacity(
        opacity: _anim.value,
        child: Transform.translate(
          offset: Offset(0, (1 - _anim.value) * widget.offset),
          child: child,
        ),
      ),
      child: widget.child,
    );
  }
}

/// Scaffold with the aurora backdrop (soft colour blobs) painted behind an
/// optional frosted layer. Screens migrating to the redesign wrap their body
/// in this instead of a bare [Scaffold].
class AuroraScaffold extends StatelessWidget {
  const AuroraScaffold({
    super.key,
    required this.body,
    this.appBar,
    this.bottomNavigationBar,
    this.floatingActionButton,
    this.floatingActionButtonLocation,
    this.extendBodyBehindAppBar = false,
    this.padding = EdgeInsets.zero,
    this.safeArea = true,
  });

  final Widget body;
  final PreferredSizeWidget? appBar;
  final Widget? bottomNavigationBar;
  final Widget? floatingActionButton;
  final FloatingActionButtonLocation? floatingActionButtonLocation;
  final bool extendBodyBehindAppBar;
  final EdgeInsetsGeometry padding;
  final bool safeArea;

  @override
  Widget build(BuildContext context) {
    Widget content = Padding(padding: padding, child: body);
    if (safeArea) {
      content = SafeArea(bottom: bottomNavigationBar == null, child: content);
    }

    return Scaffold(
      backgroundColor: foodflow.canvas,
      appBar: appBar,
      extendBodyBehindAppBar: extendBodyBehindAppBar,
      bottomNavigationBar: bottomNavigationBar,
      floatingActionButton: floatingActionButton,
      floatingActionButtonLocation: floatingActionButtonLocation,
      body: Stack(
        children: [
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(color: foodflow.canvas),
              child: Stack(children: AuroraTheme.auroraBlobs()),
            ),
          ),
          Positioned.fill(child: content),
        ],
      ),
    );
  }
}

/// A frosted-glass panel. The default look is a translucent surface with a
/// hairline border and a soft blur; set [solid] for opaque cards that still
/// need the rounded aurora styling.
class GlassCard extends StatelessWidget {
  const GlassCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.margin = EdgeInsets.zero,
    this.radius = 18,
    this.onTap,
    this.blur = 18,
    this.solid = false,
    this.border = true,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final double radius;
  final VoidCallback? onTap;
  final double blur;
  final bool solid;
  final bool border;

  @override
  Widget build(BuildContext context) {
    final borderRadius = BorderRadius.circular(radius);
    final surface = solid ? foodflow.surfaceColor : foodflow.glassSurface;

    Widget panel = DecoratedBox(
      decoration: BoxDecoration(
        color: surface,
        borderRadius: borderRadius,
        border:
            border ? Border.all(color: foodflow.glassBorder, width: 1) : null,
        boxShadow: [
          BoxShadow(
            color: foodflow.isDark
                ? Colors.black.withOpacity(0.35)
                : Colors.black.withOpacity(0.06),
            blurRadius: 22,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Padding(padding: padding, child: child),
    );

    if (!solid) {
      panel = BackdropFilter(
        filter: ImageFilter.blur(sigmaX: blur, sigmaY: blur),
        child: panel,
      );
    }

    panel = ClipRRect(borderRadius: borderRadius, child: panel);

    if (onTap == null) return Padding(padding: margin, child: panel);

    return Padding(
      padding: margin,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: borderRadius,
          child: panel,
        ),
      ),
    );
  }
}

/// Frosted app bar for aurora screens.
class GlassAppBar extends StatelessWidget implements PreferredSizeWidget {
  const GlassAppBar({
    super.key,
    this.title,
    this.actions,
    this.leading,
    this.centerTitle = false,
    this.bottom,
    this.toolbarHeight = 60,
  });

  final Widget? title;
  final List<Widget>? actions;
  final Widget? leading;
  final bool centerTitle;
  final PreferredSizeWidget? bottom;
  final double toolbarHeight;

  @override
  Size get preferredSize =>
      Size.fromHeight(toolbarHeight + (bottom?.preferredSize.height ?? 0));

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: Colors.transparent,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: centerTitle,
      toolbarHeight: toolbarHeight,
      titleSpacing: 20,
      title: title,
      actions: actions,
      leading: leading,
      bottom: bottom,
      flexibleSpace: ClipRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: foodflow.canvas.withOpacity(0.65),
              border: Border(
                bottom: BorderSide(color: foodflow.glassBorder),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Frosted bottom bar container — wrap a Row of items or a NavigationBar.
class GlassBottomBar extends StatelessWidget {
  const GlassBottomBar({super.key, required this.child, this.height});

  final Widget child;
  final double? height;

  @override
  Widget build(BuildContext context) {
    return ClipRect(
      child: BackdropFilter(
        filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
        child: Container(
          height: height,
          decoration: BoxDecoration(
            color: foodflow.canvas.withOpacity(0.72),
            border: Border(top: BorderSide(color: foodflow.glassBorder)),
          ),
          child: SafeArea(top: false, child: child),
        ),
      ),
    );
  }
}

/// Compact KPI tile used on dashboards and the earnings screen.
class AuroraStatCard extends StatelessWidget {
  const AuroraStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    this.accent,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color? accent;

  @override
  Widget build(BuildContext context) {
    final tint = accent ?? foodflow.orange;
    return GlassCard(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: tint.withOpacity(0.14),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 18, color: tint),
          ),
          const SizedBox(height: 10),
          Text(
            value,
            style: TextStyle(
              color: foodflow.ink,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(color: foodflow.muted, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

/// Primary pill button with the brand gradient.
class GlassButton extends StatelessWidget {
  const GlassButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.loading = false,
    this.expand = true,
    this.compact = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool loading;
  final bool expand;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final disabled = onPressed == null || loading;
    final height = compact ? 42.0 : 54.0;
    final button = Opacity(
      opacity: disabled ? 0.6 : 1,
      child: Container(
        height: height,
        alignment: Alignment.center,
        padding: EdgeInsets.symmetric(horizontal: compact ? 16 : 20),
        decoration: BoxDecoration(
          gradient: foodflow.brandGradient,
          borderRadius: BorderRadius.circular(compact ? 12 : 16),
          boxShadow: [
            BoxShadow(
              color: foodflow.orange.withOpacity(0.30),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: loading
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              )
            : Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (icon != null) ...[
                    Icon(icon, size: 18, color: Colors.white),
                    const SizedBox(width: 8),
                  ],
                  Text(
                    label,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: compact ? 14 : 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
      ),
    );

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: disabled ? null : onPressed,
        borderRadius: BorderRadius.circular(compact ? 12 : 16),
        child: expand ? SizedBox(width: double.infinity, child: button) : button,
      ),
    );
  }
}
