// lib/widgets/customer/category_card.dart
import 'package:flutter/material.dart';
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
    for (final key in keys) {
      final value = item[key];
      if (value is String && value.isNotEmpty) {
        if (value.startsWith('http')) return value;
        if (value.startsWith('/')) return '${AppConfig.apiBaseUrl}$value';
        return value;
      }
    }
    return '';
  }

  @override
  Widget build(BuildContext context) {
    final name = category['name']?.toString() ?? '';
    final imageUrl = _resolveImageUrl(category, [
      'image_url',
      'icon_url',
      'image',
      'icon',
      'thumb',
    ]);

    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 88,
        margin: const EdgeInsets.symmetric(horizontal: 5),
        child: Column(
          children: [
            Container(
              width: 76,
              height: 76,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: const Color(0xFFFFF3E8),
                border: Border.all(color: const Color(0xFFFFD7B3), width: 1),
                boxShadow: [
                  BoxShadow(
                    color: FoodFlowTheme.orange.withOpacity(0.08),
                    blurRadius: 12,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: ClipOval(
                child: imageUrl.isNotEmpty
                    ? Image.network(
                        imageUrl,
                        width: 76,
                        height: 76,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) {
                          return Container(
                            color: Colors.grey.shade100,
                            child: const Icon(Icons.restaurant_menu,
                                size: 32, color: AppConfig.primaryColor),
                          );
                        },
                      )
                    : Container(
                        color: Colors.grey.shade100,
                        child: const Icon(Icons.restaurant_menu,
                            size: 32, color: AppConfig.primaryColor),
                      ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              name,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w800,
                color: FoodFlowTheme.ink,
              ),
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}
