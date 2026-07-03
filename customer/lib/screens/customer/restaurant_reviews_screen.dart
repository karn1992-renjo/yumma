import 'package:flutter/material.dart';

import '../../models/restaurant.dart';
import '../../theme/foodflow_theme.dart';

class RestaurantReviewsScreen extends StatelessWidget {
  final Restaurant restaurant;

  const RestaurantReviewsScreen({
    super.key,
    required this.restaurant,
  });

  @override
  Widget build(BuildContext context) {
    final rating = restaurant.visibleRating ?? restaurant.rating;
    final reviews = restaurant.reviewHighlights;

    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Ratings & Reviews'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          _RatingSummaryCard(
            restaurantName: restaurant.name,
            rating: rating,
            reviewCount: restaurant.reviewCount,
            commentCount: restaurant.reviewCommentCount,
            reviews: reviews,
          ),
          const SizedBox(height: 16),
          Text(
            reviews.isEmpty ? 'Customer comments' : 'Recent reviews',
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 12),
          if (reviews.isEmpty)
            FoodFlowTheme.emptyState(
              icon: Icons.rate_review_outlined,
              title: 'No written reviews yet',
              subtitle: restaurant.reviewCount > 0
                  ? 'Customers have rated this restaurant, but no comments are visible yet.'
                  : 'Ratings and comments will appear here after completed orders.',
            )
          else
            ...reviews.map((review) => _ReviewTile(review: review)),
        ],
      ),
    );
  }
}

class _RatingSummaryCard extends StatelessWidget {
  final String restaurantName;
  final double rating;
  final int reviewCount;
  final int commentCount;
  final List<Map<String, dynamic>> reviews;

  const _RatingSummaryCard({
    required this.restaurantName,
    required this.rating,
    required this.reviewCount,
    required this.commentCount,
    required this.reviews,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: FoodFlowTheme.elevatedCard(radius: 22),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            restaurantName,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: FoodFlowTheme.success,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      rating > 0 ? rating.toStringAsFixed(1) : 'New',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(width: 5),
                    const Icon(Icons.star_rounded, color: Colors.white, size: 22),
                  ],
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      reviewCount > 0
                          ? 'Based on $reviewCount rating${reviewCount == 1 ? '' : 's'}'
                          : 'No ratings yet',
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      commentCount > 0
                          ? '$commentCount written comment${commentCount == 1 ? '' : 's'}'
                          : 'No comments yet',
                      style: const TextStyle(
                        color: FoodFlowTheme.muted,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (reviews.isNotEmpty) ...[
            const SizedBox(height: 18),
            ...List.generate(5, (index) {
              final star = 5 - index;
              final count = reviews.where((review) {
                final value = review['rating'];
                if (value is num) return value.round() == star;
                return int.tryParse(value?.toString() ?? '') == star;
              }).length;
              final fraction = reviews.isEmpty ? 0.0 : count / reviews.length;
              return Padding(
                padding: const EdgeInsets.only(bottom: 7),
                child: Row(
                  children: [
                    SizedBox(
                      width: 34,
                      child: Text(
                        '$star star',
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: FoodFlowTheme.muted,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(999),
                        child: LinearProgressIndicator(
                          value: fraction,
                          minHeight: 7,
                          backgroundColor: const Color(0xFFE8EEF5),
                          valueColor: const AlwaysStoppedAnimation<Color>(
                            FoodFlowTheme.success,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              );
            }),
          ],
        ],
      ),
    );
  }
}

class _ReviewTile extends StatelessWidget {
  final Map<String, dynamic> review;

  const _ReviewTile({required this.review});

  @override
  Widget build(BuildContext context) {
    final rating = review['rating']?.toString() ?? '0';
    final name = review['user_name']?.toString().trim();
    final comment = review['comment']?.toString().trim() ?? '';
    final createdAt = DateTime.tryParse(review['created_at']?.toString() ?? '');

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(15),
      decoration: FoodFlowTheme.surface(radius: 18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: const Color(0xFFEAF8EF),
                child: Text(
                  (name?.isNotEmpty == true ? name![0] : 'C').toUpperCase(),
                  style: const TextStyle(
                    color: FoodFlowTheme.success,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name?.isNotEmpty == true ? name! : 'Customer',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    if (createdAt != null)
                      Text(
                        _dateLabel(createdAt),
                        style: const TextStyle(
                          color: FoodFlowTheme.muted,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: FoodFlowTheme.success,
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      rating,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(width: 3),
                    const Icon(Icons.star_rounded, color: Colors.white, size: 12),
                  ],
                ),
              ),
            ],
          ),
          if (comment.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              comment,
              style: const TextStyle(
                color: FoodFlowTheme.inkSoft,
                height: 1.45,
                fontSize: 13,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _dateLabel(DateTime date) {
    final now = DateTime.now();
    final difference = now.difference(date);
    if (difference.inDays <= 0) return 'Today';
    if (difference.inDays == 1) return 'Yesterday';
    if (difference.inDays < 30) return '${difference.inDays} days ago';
    final months = (difference.inDays / 30).floor();
    if (months < 12) return '$months month${months == 1 ? '' : 's'} ago';
    final years = (difference.inDays / 365).floor();
    return '$years year${years == 1 ? '' : 's'} ago';
  }
}
