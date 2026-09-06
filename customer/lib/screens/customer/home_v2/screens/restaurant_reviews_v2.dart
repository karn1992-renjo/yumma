import 'package:flutter/material.dart';

import '../../../../utils/currency_utils.dart' show formatCurrency;
import '../data/v2_util.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

/// Reviews for a restaurant, built from the `review_highlights` embedded in the
/// restaurant-detail payload (same source the production reviews screen uses).
class RestaurantReviewsV2 extends StatelessWidget {
  const RestaurantReviewsV2({super.key, required this.restaurant});

  final Map<String, dynamic> restaurant;

  @override
  Widget build(BuildContext context) {
    final name = (restaurant['name'] ?? 'Restaurant').toString();
    final rating = v2Double(restaurant['rating'] ?? restaurant['avg_rating']);
    final total = v2Int(restaurant['total_ratings'] ??
        restaurant['ratings_count'] ??
        restaurant['review_count']);
    final reviews = v2MapList(restaurant['review_highlights'] ??
        restaurant['reviews'] ??
        restaurant['recent_reviews']);

    return V2Scaffold(
      title: 'Reviews',
      showBack: true,
      body: Builder(
        builder: (context) {
          final p = V2Theme.of(context);
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 40),
            children: [
              V2Entrance(
                child: GlassPanel(
                  radius: 22,
                  strong: true,
                  padding: const EdgeInsets.all(18),
                  child: Row(
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.baseline,
                            textBaseline: TextBaseline.alphabetic,
                            children: [
                              Text(
                                rating > 0
                                    ? rating.toStringAsFixed(1)
                                    : '—',
                                style: TextStyle(
                                    color: p.ink,
                                    fontSize: 34,
                                    fontWeight: FontWeight.w900),
                              ),
                              const SizedBox(width: 4),
                              Icon(Icons.star_rounded,
                                  color: p.positive, size: 24),
                            ],
                          ),
                          Text(
                            total > 0
                                ? '$total rating${total == 1 ? '' : 's'}'
                                : 'No ratings yet',
                            style: TextStyle(
                                color: p.inkSoft, fontSize: 12.5),
                          ),
                        ],
                      ),
                      const Spacer(),
                      Expanded(
                        child: Text(
                          name,
                          textAlign: TextAlign.right,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                              color: p.inkFaint,
                              fontSize: 13,
                              fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),
              if (reviews.isEmpty)
                const Padding(
                  padding: EdgeInsets.only(top: 30),
                  child: V2EmptyState(
                    icon: Icons.rate_review_outlined,
                    title: 'No written reviews yet',
                    message: 'Be the first to review after your order.',
                  ),
                )
              else
                for (final r in reviews) ...[
                  _ReviewTile(review: r),
                  const SizedBox(height: 10),
                ],
            ],
          );
        },
      ),
    );
  }
}

const _months = [
  'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
  'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
];

/// Turn a raw ISO timestamp into "6 Aug 2026"; leave friendly strings alone.
String _prettyWhen(String raw) {
  final s = raw.trim();
  if (s.isEmpty) return '';
  final dt = DateTime.tryParse(s);
  if (dt == null) return s;
  final local = dt.toLocal();
  final now = DateTime.now();
  final diff = now.difference(local);
  if (diff.inMinutes < 1) return 'Just now';
  if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
  if (diff.inHours < 24) return '${diff.inHours} hr ago';
  if (diff.inDays < 7) return '${diff.inDays} day${diff.inDays == 1 ? '' : 's'} ago';
  return '${local.day} ${_months[local.month - 1]} ${local.year}';
}

class _ReviewTile extends StatelessWidget {
  const _ReviewTile({required this.review});

  final Map<String, dynamic> review;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    final name = (review['customer_name'] ??
            review['user_name'] ??
            review['name'] ??
            'Customer')
        .toString();
    final comment = (review['comment'] ??
            review['review'] ??
            review['text'] ??
            review['feedback'] ??
            '')
        .toString()
        .trim();
    final stars = v2Double(review['rating'] ?? review['stars']);
    final when = _prettyWhen((review['created_at_label'] ??
            review['time_ago'] ??
            review['created_at'] ??
            '')
        .toString());
    final orderTotal = v2DoubleOrNull(review['order_total']);

    return GlassPanel(
      radius: 18,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 15,
                backgroundColor: p.accent.withOpacity(0.14),
                child: Text(
                  name.isNotEmpty ? name[0].toUpperCase() : '?',
                  style: TextStyle(
                      color: p.accent,
                      fontWeight: FontWeight.w900,
                      fontSize: 13),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: p.ink,
                            fontSize: 13,
                            fontWeight: FontWeight.w800)),
                    if (when.isNotEmpty)
                      Text(when,
                          style: TextStyle(
                              color: p.inkFaint, fontSize: 10.5)),
                  ],
                ),
              ),
              if (stars > 0)
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 6, vertical: 3),
                  decoration: BoxDecoration(
                    color: p.positive.withOpacity(0.14),
                    borderRadius: BorderRadius.circular(7),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(stars.toStringAsFixed(1),
                          style: TextStyle(
                              color: p.ink,
                              fontSize: 11,
                              fontWeight: FontWeight.w800)),
                      const SizedBox(width: 2),
                      Icon(Icons.star_rounded,
                          size: 11, color: p.positive),
                    ],
                  ),
                ),
            ],
          ),
          if (comment.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(comment,
                style: TextStyle(
                    color: p.inkSoft, fontSize: 12.5, height: 1.4)),
          ],
          if (orderTotal != null && orderTotal > 0) ...[
            const SizedBox(height: 6),
            Text('Order · ${formatCurrency(context, orderTotal)}',
                style: TextStyle(color: p.inkFaint, fontSize: 10.5)),
          ],
        ],
      ),
    );
  }
}
