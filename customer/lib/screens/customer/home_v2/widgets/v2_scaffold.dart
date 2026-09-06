// Every V2 screen is wrapped in this: it binds the live [v2ModeNotifier] to a
// [V2Theme] scope, paints the aurora background, and (optionally) a lightweight
// glass back bar. Content is drawn on a transparent Scaffold on top.

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

import '../theme/v2_theme.dart';
import 'v2_background.dart';
import 'v2_glass.dart';

class V2Scaffold extends StatelessWidget {
  const V2Scaffold({
    super.key,
    required this.body,
    this.title,
    this.showBack = false,
    this.actions,
    this.floating,
    this.bottom,
    this.extendBody = true,
    this.safeTop = true,
  });

  final Widget body;
  final String? title;
  final bool showBack;
  final List<Widget>? actions;
  final Widget? floating;
  final Widget? bottom;
  final bool extendBody;

  /// When false, the body extends behind the status bar (the screen draws its
  /// own full-bleed hero and manages the top inset).
  final bool safeTop;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<V2Mode>(
      valueListenable: v2ModeNotifier,
      builder: (context, mode, _) {
        final palette = V2Palette.of(mode);
        return V2Theme(
          palette: palette,
          child: DefaultTextStyle.merge(
            style: GoogleFonts.nunitoSans(color: palette.ink),
            child: Scaffold(
              backgroundColor: palette.bgTop,
              extendBody: extendBody,
              extendBodyBehindAppBar: true,
              body: SizedBox.expand(
                child: Stack(
                fit: StackFit.expand,
                children: [
                  Positioned.fill(child: V2Background(palette: palette)),
                  Positioned.fill(
                    child: SafeArea(
                      top: safeTop,
                      bottom: false,
                      child: Column(
                        children: [
                          if (title != null || showBack)
                            _V2TopBar(
                              title: title,
                              showBack: showBack,
                              actions: actions,
                            ),
                          Expanded(child: body),
                        ],
                      ),
                    ),
                  ),
                  if (floating != null) floating!,
                  if (bottom != null)
                    Positioned(left: 0, right: 0, bottom: 0, child: bottom!),
                ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}

class _V2TopBar extends StatelessWidget {
  const _V2TopBar({this.title, this.showBack = false, this.actions});

  final String? title;
  final bool showBack;
  final List<Widget>? actions;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
      child: Row(
        children: [
          if (showBack)
            GlassIconButton(
              icon: Icons.arrow_back_rounded,
              onTap: () => Navigator.of(context).maybePop(),
            ),
          if (showBack) const SizedBox(width: 12),
          Expanded(
            child: Text(
              title ?? '',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: p.ink,
                fontSize: 19,
                fontWeight: FontWeight.w800,
                letterSpacing: -0.3,
              ),
            ),
          ),
          if (actions != null)
            for (final a in actions!) ...[const SizedBox(width: 8), a],
        ],
      ),
    );
  }
}
