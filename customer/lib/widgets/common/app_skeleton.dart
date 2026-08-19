import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

class AppSkeletonBox extends StatelessWidget {
  const AppSkeletonBox({
    super.key,
    required this.height,
    this.width,
    this.radius = 8,
  });

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(radius),
      ),
    );
  }
}

class AppSkeletonLine extends StatelessWidget {
  const AppSkeletonLine({
    super.key,
    this.width,
    this.height = 12,
  });

  final double? width;
  final double height;

  @override
  Widget build(BuildContext context) {
    return AppSkeletonBox(width: width, height: height, radius: 4);
  }
}

class AppSkeletonColumn extends StatelessWidget {
  const AppSkeletonColumn({
    super.key,
    this.itemCount = 4,
    this.itemHeight = 92,
    this.spacing = 12,
  });

  final int itemCount;
  final double itemHeight;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    return _SkeletonShimmer(
      child: Column(
        children: List.generate(
          itemCount,
          (index) => Padding(
            padding: EdgeInsets.only(
              bottom: index == itemCount - 1 ? 0 : spacing,
            ),
            child: _SkeletonListCard(height: itemHeight, index: index),
          ),
        ),
      ),
    );
  }
}

class AppSkeletonListView extends StatelessWidget {
  const AppSkeletonListView({
    super.key,
    this.itemCount = 5,
    this.itemHeight = 92,
    this.padding = const EdgeInsets.fromLTRB(16, 12, 16, 28),
  });

  final int itemCount;
  final double itemHeight;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return _SkeletonShimmer(
      child: ListView.separated(
        physics: const NeverScrollableScrollPhysics(),
        padding: padding,
        itemCount: itemCount,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (_, index) =>
            _SkeletonListCard(height: itemHeight, index: index),
      ),
    );
  }
}

class AppSkeletonSliverList extends StatelessWidget {
  const AppSkeletonSliverList({
    super.key,
    this.itemCount = 5,
    this.itemHeight = 92,
    this.padding = const EdgeInsets.fromLTRB(16, 8, 16, 32),
  });

  final int itemCount;
  final double itemHeight;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return SliverPadding(
      padding: padding,
      sliver: SliverToBoxAdapter(
        child: AppSkeletonColumn(
          itemCount: itemCount,
          itemHeight: itemHeight,
        ),
      ),
    );
  }
}

class AppDetailSkeleton extends StatelessWidget {
  const AppDetailSkeleton({
    super.key,
    this.showHero = true,
    this.cardCount = 3,
  });

  final bool showHero;
  final int cardCount;

  @override
  Widget build(BuildContext context) {
    return _SkeletonShimmer(
      child: ListView(
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 32),
        children: [
          Row(
            children: const [
              AppSkeletonBox(width: 40, height: 40, radius: 8),
              SizedBox(width: 12),
              Expanded(child: AppSkeletonLine(height: 18)),
              SizedBox(width: 48),
            ],
          ),
          if (showHero) ...[
            const SizedBox(height: 20),
            const AppSkeletonBox(height: 180),
          ],
          const SizedBox(height: 16),
          const AppSkeletonLine(width: 150, height: 18),
          const SizedBox(height: 14),
          ...List.generate(
            cardCount,
            (index) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _SkeletonListCard(
                height: index == 0 ? 138 : 108,
                index: index,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SkeletonListCard extends StatelessWidget {
  const _SkeletonListCard({
    required this.height,
    required this.index,
  });

  final double height;
  final int index;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: height,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AppSkeletonBox(
            width: height > 110 ? 82 : 58,
            height: height > 110 ? 82 : 58,
            radius: 8,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AppSkeletonLine(
                  width: index.isEven ? 168 : 132,
                  height: 14,
                ),
                const SizedBox(height: 10),
                const AppSkeletonLine(),
                const SizedBox(height: 8),
                const AppSkeletonLine(width: 110, height: 10),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _SkeletonShimmer extends StatelessWidget {
  const _SkeletonShimmer({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return IgnorePointer(
      child: ExcludeSemantics(
        child: Shimmer.fromColors(
          baseColor: isDark ? const Color(0xFF25282D) : const Color(0xFFE8EAED),
          highlightColor:
              isDark ? const Color(0xFF373B42) : const Color(0xFFF7F8FA),
          period: const Duration(milliseconds: 1150),
          child: child,
        ),
      ),
    );
  }
}
