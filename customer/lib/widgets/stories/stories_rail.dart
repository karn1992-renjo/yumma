// lib/widgets/stories/stories_rail.dart
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../screens/customer/story_viewer_screen.dart';
import '../../services/story_prefetcher.dart';

/// Instagram-style horizontal rail of restaurant story bubbles. Reusable by
/// both home surfaces (V1 and V2) -- each surface wraps this with its own
/// section-header widget, matching how shop_by_brand already does it.
class StoriesRailList extends StatefulWidget {
  const StoriesRailList({super.key, required this.items});

  final List<Map<String, dynamic>> items;

  @override
  State<StoriesRailList> createState() => _StoriesRailListState();
}

class _StoriesRailListState extends State<StoriesRailList> {
  List<Map<String, dynamic>> get items => widget.items;

  @override
  void initState() {
    super.initState();
    // Download the first reels now so a tap opens instantly.
    StoryPrefetcher.instance.warmRail(items);
  }

  @override
  void didUpdateWidget(covariant StoriesRailList oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.items.length != items.length) {
      StoryPrefetcher.instance.warmRail(items);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 168,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: items.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) => StoryBubble(item: items[index]),
      ),
    );
  }
}

class StoryBubble extends StatelessWidget {
  const StoryBubble({super.key, required this.item});

  final Map<String, dynamic> item;

  static const double cardWidth = 104;

  @override
  Widget build(BuildContext context) {
    final restaurantId = (item['restaurant_id'] as num?)?.toInt();
    final name = item['restaurant_name']?.toString() ?? 'Restaurant';
    final avatar = item['avatar']?.toString();
    final preview = item['preview_url']?.toString();
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
      // A tall portrait card: the newest story's picture, the restaurant's avatar ringed at the top, its name below.
      child: Container(
        width: cardWidth,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          color: const Color(0xFF1F2937),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.12), blurRadius: 8, offset: const Offset(0, 3))],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(18),
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (preview != null && preview.isNotEmpty)
                CachedNetworkImage(
                  imageUrl: preview,
                  fit: BoxFit.cover,
                  errorWidget: (_, __, ___) => _backdrop(name),
                  placeholder: (_, __) => _backdrop(name),
                )
              else
                _backdrop(name),
              // Darkens the top and bottom so the avatar and the name stay readable on any picture.
              const DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Color(0x66000000), Color(0x00000000), Color(0x00000000), Color(0xB3000000)],
                    stops: [0.0, 0.3, 0.6, 1.0],
                  ),
                ),
              ),
              Positioned(
                top: 8,
                left: 8,
                child: Container(
                  width: 38,
                  height: 38,
                  padding: const EdgeInsets.all(2),
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: hasUnseen ? const Color(0xFF22C55E) : Colors.white54, width: 2.4),
                    color: const Color(0xFF111827),
                  ),
                  child: ClipOval(
                    child: avatar != null && avatar.isNotEmpty
                        ? CachedNetworkImage(
                            imageUrl: avatar,
                            fit: BoxFit.cover,
                            errorWidget: (_, __, ___) => _avatarFallback(),
                            placeholder: (_, __) => _avatarFallback(),
                          )
                        : _avatarFallback(),
                  ),
                ),
              ),
              Positioned(
                left: 8,
                right: 8,
                bottom: 9,
                child: Text(
                  name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 12,
                    height: 1.15,
                    fontWeight: FontWeight.w700,
                    color: Colors.white,
                    shadows: [Shadow(color: Colors.black54, blurRadius: 6)],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _backdrop(String name) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [Color(0xFF374151), Color(0xFF111827)]),
      ),
      alignment: Alignment.center,
      child: Text(
        name.isNotEmpty ? name[0].toUpperCase() : '?',
        style: const TextStyle(fontSize: 34, fontWeight: FontWeight.w800, color: Colors.white24),
      ),
    );
  }

  Widget _avatarFallback() {
    return const ColoredBox(
      color: Color(0xFF1F2937),
      child: Center(child: Icon(Icons.person_rounded, size: 20, color: Color(0xFF22C55E))),
    );
  }
}
