// lib/widgets/customer/category_card.dart
import 'package:flutter/material.dart';
import '../common/app_cached_image.dart';
import '../../config/app_config.dart';
import '../../theme/foodflow_theme.dart';

class CategoryCard extends StatelessWidget {
  final dynamic category;
  final VoidCallback onTap;

  const CategoryCard({
    Key? key,
    required this.category,
    required this.onTap,
  }) : super(key: key);

  String _resolveImageUrl(dynamic item, List<String> keys) {
    if (item == null) return '';

    String resolve(dynamic value) {
      if (value is String && value.trim().isNotEmpty) {
        return _resolveStoredImageUrl(value);
      }
      if (value is Map) {
        for (final key in const <String>[
          'url',
          'image_url',
          'path',
          'file',
          'src',
        ]) {
          final resolved = resolve(value[key]);
          if (resolved.isNotEmpty) return resolved;
        }
      }
      if (value is List) {
        for (final child in value) {
          final resolved = resolve(child);
          if (resolved.isNotEmpty) return resolved;
        }
      }
      return '';
    }

    for (final key in keys) {
      final resolved = resolve(item[key]);
      if (resolved.isNotEmpty) return resolved;
    }
    return '';
  }

  String _resolveStoredImageUrl(String rawValue) {
    final value = rawValue.trim();
    if (value.isEmpty || value == 'null') return '';
    if (value.startsWith('http://') || value.startsWith('https://')) {
      return value;
    }

    final apiUri = Uri.parse(AppConfig.apiBaseUrl);
    final port = apiUri.hasPort ? ':${apiUri.port}' : '';
    final origin = '${apiUri.scheme}://${apiUri.host}$port';
    final normalized = value.startsWith('/') ? value.substring(1) : value;

    if (normalized.startsWith('storage/')) return '$origin/$normalized';
    if (normalized.startsWith('uploads/') ||
        normalized.startsWith('banners/') ||
        normalized.startsWith('menu_items/') ||
        normalized.startsWith('restaurants/') ||
        normalized.startsWith('categories/') ||
        normalized.startsWith('global-categories/')) {
      return '$origin/storage/$normalized';
    }
    if (value.startsWith('/')) return '$origin/$normalized';
    return '$origin/storage/$normalized';
  }

  @override
  Widget build(BuildContext context) {
    final name = category['name']?.toString() ?? '';
    final imageUrl = _resolveImageUrl(category, [
      'image_url',
      'icon_url',
      'thumbnail_url',
      'thumb_url',
      'photo_url',
      'asset_url',
      'media_url',
      'image',
      'icon',
      'thumbnail',
      'thumb',
      'photo',
      'url',
      'images',
      'media',
    ]);

    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 64,
        margin: const EdgeInsets.only(right: 18),
        child: Column(
          children: [
            Container(
              width: 58,
              height: 58,
              decoration: BoxDecoration(
                color: _accentColor(name).withOpacity(0.22),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.04),
                    blurRadius: 12,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(999),
                child: imageUrl.isNotEmpty
                    ? AppCachedImage(
                        imageUrl: imageUrl,
                        width: 64,
                        height: 64,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) {
                          return Container(
                            color: _accentColor(name).withOpacity(0.22),
                            child: Icon(Icons.restaurant_menu,
                                size: 24, color: _accentColor(name)),
                          );
                        },
                      )
                    : Container(
                        color: _accentColor(name).withOpacity(0.22),
                        child: Icon(Icons.restaurant_menu,
                            size: 24, color: _accentColor(name)),
                      ),
              ),
            ),
            const SizedBox(height: 10),
            Expanded(
              child: Text(
                name,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: FoodFlowTheme.ink,
                  height: 1.15,
                ),
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _accentColor(String seed) {
    const palette = <Color>[
      Color(0xFFFF7A00),
      Color(0xFFFFB703),
      Color(0xFF8FD14F),
      Color(0xFFB388FF),
      Color(0xFFFF8FAB),
      Color(0xFF6CCFF6),
    ];

    if (seed.isEmpty) return palette.first;
    return palette[seed.codeUnits.fold<int>(0, (sum, code) => sum + code) %
        palette.length];
  }
}
