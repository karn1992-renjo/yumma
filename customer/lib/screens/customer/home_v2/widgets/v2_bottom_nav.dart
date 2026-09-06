import 'package:flutter/material.dart';

import '../theme/v2_theme.dart';
import 'v2_anim.dart';

class V2BottomNav extends StatelessWidget {
  const V2BottomNav({
    super.key,
    required this.currentIndex,
    required this.onTap,
  });

  final int currentIndex;
  final ValueChanged<int> onTap;

  static const _items = <(IconData, String)>[
    (Icons.home_rounded, 'Home'),
    (Icons.search_rounded, 'Search'),
    (Icons.receipt_long_rounded, 'Orders'),
    (Icons.person_rounded, 'Profile'),
  ];

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final bottomInset = MediaQuery.of(context).padding.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 10 + bottomInset),
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [p.glassStrongTop, p.glassStrongBottom],
          ),
          border: Border.all(color: p.glassBorder),
          boxShadow: [
            BoxShadow(color: p.shadow, blurRadius: 24, offset: const Offset(0, 12)),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
          child: Row(
            children: [
              for (var i = 0; i < _items.length; i++)
                Expanded(
                  child: V2Tappable(
                    onTap: () => onTap(i),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        color: currentIndex == i
                            ? p.accent.withOpacity(p.isDark ? 0.24 : 0.14)
                            : Colors.transparent,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: currentIndex == i
                              ? p.accent.withOpacity(0.5)
                              : Colors.transparent,
                        ),
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            _items[i].$1,
                            size: 21,
                            color: currentIndex == i ? p.ink : p.inkFaint,
                          ),
                          const SizedBox(height: 3),
                          Text(
                            _items[i].$2,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: currentIndex == i
                                  ? FontWeight.w800
                                  : FontWeight.w600,
                              color: currentIndex == i ? p.ink : p.inkFaint,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
