// lib/widgets/stories/stories_rail.dart
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../screens/customer/story_viewer_screen.dart';

/// Instagram-style horizontal rail of restaurant story bubbles. Reusable by
/// both home surfaces (V1 and V2) -- each surface wraps this with its own
/// section-header widget, matching how shop_by_brand already does it.
class StoriesRailList extends StatelessWidget {
  const StoriesRailList({super.key, required this.items});

  final List<Map<String, dynamic>> items;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 104,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 14),
        itemBuilder: (context, index) => StoryBubble(item: items[index]),
      ),
    );
  }
}

class StoryBubble extends StatelessWidget {
  const StoryBubble({super.key, required this.item});

  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final restaurantId = (item['restaurant_id'] as num?)?.toInt();
    final name = item['restaurant_name']?.toString() ?? 'Restaurant';
    final avatar = item['avatar']?.toString();
    final hasUnseen = item['has_unseen'] == true;

    return GestureDetector(
      onTap: restaurantId == null
          ? null
          : () {
              Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => StoryViewerScreen(
                    restaurantId: restaurantId,
                    restaurantName: name,
                    restaurantAvatar: avatar,
                  ),
                  fullscreenDialog: true,
                ),
              );
            },
      child: SizedBox(
        width: 72,
        child: Column(
          children: [
            Container(
              width: 68,
              height: 68,
              padding: const EdgeInsets.all(2.4),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: hasUnseen
                    ? const LinearGradient(
                        colors: [Color(0xFFF58529), Color(0xFFDD2A7B), Color(0xFF8134AF)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      )
                    : null,
                color: hasUnseen ? null : const Color(0xFFD1D5DB),
              ),
              child: Container(
                padding: const EdgeInsets.all(2),
                decoration: const BoxDecoration(shape: BoxShape.circle, color: Colors.white),
                child: ClipOval(
                  child: avatar != null && avatar.isNotEmpty
                      ? CachedNetworkImage(
                          imageUrl: avatar,
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) => _fallback(name),
                          placeholder: (_, __) => _fallback(name),
                        )
                      : _fallback(name),
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF111827)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _fallback(String name) {
    return Container(
      color: const Color(0xFFF3F4F6),
      alignment: Alignment.center,
      child: Text(
        name.isNotEmpty ? name[0].toUpperCase() : '?',
        style: const TextStyle(fontWeight: FontWeight.w800, color: Color(0xFF6B7280)),
      ),
    );
  }
}
