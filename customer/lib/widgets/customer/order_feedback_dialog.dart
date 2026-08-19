import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/order.dart';
import '../../providers/order_provider.dart';
import '../common/app_cached_image.dart';

const _accent = Color(0xFFFF6B00);
const _text = Color(0xFF111827);
const _muted = Color(0xFF6B7280);
const _green = Color(0xFF16A34A);
const _line = Color(0xFFE5E7EB);

const _ratingLabels = <String>['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

const _foodReasons = <String>[
  'Food was cold',
  'Portion was small',
  'Wrong item',
  'Quality issue',
  'Item(s) missing',
];

const _deliveryReasons = <String>[
  'Rider was late',
  'Rider was rude',
  'Order was damaged',
  'Handed to wrong address',
];

Future<bool> showOrderFeedbackDialog(
  BuildContext context,
  Order order,
) async {
  final existingPrefs = await SharedPreferences.getInstance();
  if (existingPrefs.getInt('dismissed_feedback_order_id') == order.id) {
    return false;
  }

  int? foodRating;
  int? deliveryRating;
  final foodReasons = <String>{};
  final deliveryReasons = <String>{};
  final itemStars = <int, int>{};
  var showComment = false;
  var submitting = false;
  var submitted = false;
  final commentController = TextEditingController();
  final canRateDriver = !order.isTakeaway && order.driver != null;
  final ratedItems =
      order.items.where((item) => item.menuItemId != null).toList();

  Future<void> dismiss(BuildContext dialogContext) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt('dismissed_feedback_order_id', order.id);
    if (dialogContext.mounted) Navigator.of(dialogContext).pop();
  }

  await showModalBottomSheet<void>(
    context: context,
    isDismissible: false,
    enableDrag: false,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, setSheetState) {
        final canSubmit =
            foodRating != null && (!canRateDriver || deliveryRating != null);

        String composeFeedback(Set<String> reasons) {
          if (reasons.isEmpty) return '';
          return reasons.join(', ');
        }

        List<Map<String, int>> composeItemRatings() {
          return itemStars.entries
              .map((entry) => {'menu_item_id': entry.key, 'rating': entry.value})
              .toList();
        }

        Future<void> submit() async {
          if (submitting || !canSubmit) return;
          setSheetState(() => submitting = true);

          final comment = commentController.text.trim();
          final restaurantFeedbackParts = [
            composeFeedback(foodReasons),
            if (comment.isNotEmpty) comment,
          ].where((part) => part.isNotEmpty).join('. ');

          final success = await context.read<OrderProvider>().submitFeedback(
                orderId: order.id,
                restaurantRating: foodRating!,
                driverRating: canRateDriver ? deliveryRating : null,
                itemRating: foodRating,
                restaurantFeedback:
                    restaurantFeedbackParts.isEmpty ? null : restaurantFeedbackParts,
                driverFeedback: canRateDriver
                    ? composeFeedback(deliveryReasons)
                    : null,
                itemRatings: composeItemRatings(),
              );

          if (!dialogContext.mounted) return;
          if (success) {
            submitted = true;
            final prefs = await SharedPreferences.getInstance();
            await prefs.setInt('dismissed_feedback_order_id', order.id);
            if (dialogContext.mounted) Navigator.of(dialogContext).pop();
            return;
          }

          setSheetState(() => submitting = false);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not submit feedback.')),
          );
        }

        return SafeArea(
          top: false,
          child: Container(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.sizeOf(context).height * 0.88,
            ),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const SizedBox(height: 10),
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: _line,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 14, 12, 0),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Rate your order',
                          style: const TextStyle(
                            color: _text,
                            fontSize: 19,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      IconButton(
                        onPressed:
                            submitting ? null : () => dismiss(dialogContext),
                        icon: const Icon(Icons.close_rounded, color: _muted),
                      ),
                    ],
                  ),
                ),
                Flexible(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(20, 0, 20, 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'From ${order.restaurant?.name ?? 'the restaurant'}',
                          style: const TextStyle(
                            color: _muted,
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 20),
                        _RatingBlock(
                          title: 'Food',
                          rating: foodRating,
                          onChanged: (value) =>
                              setSheetState(() => foodRating = value),
                        ),
                        if (foodRating != null && ratedItems.isNotEmpty) ...[
                          const SizedBox(height: 14),
                          const Text(
                            'Rate items you ordered',
                            style: TextStyle(
                              color: _text,
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 10),
                          SizedBox(
                            height: 128,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: ratedItems.length,
                              separatorBuilder: (_, __) =>
                                  const SizedBox(width: 10),
                              itemBuilder: (context, index) {
                                final item = ratedItems[index];
                                final stars = itemStars[item.menuItemId!];
                                return _ItemRatingCard(
                                  name: item.name,
                                  imageUrl: item.imageUrl,
                                  rating: stars,
                                  onChanged: (value) => setSheetState(() {
                                    if (itemStars[item.menuItemId!] == value) {
                                      itemStars.remove(item.menuItemId!);
                                    } else {
                                      itemStars[item.menuItemId!] = value;
                                    }
                                  }),
                                );
                              },
                            ),
                          ),
                        ],
                        if (foodRating != null && foodRating! <= 3) ...[
                          const SizedBox(height: 14),
                          _ReasonChips(
                            options: _foodReasons,
                            selected: foodReasons,
                            onToggle: (reason) => setSheetState(() {
                              if (!foodReasons.remove(reason)) {
                                foodReasons.add(reason);
                              }
                            }),
                          ),
                        ],
                        if (canRateDriver) ...[
                          const SizedBox(height: 22),
                          const Divider(color: _line, height: 1),
                          const SizedBox(height: 18),
                          _RatingBlock(
                            title: 'Delivery · ${order.driver!.name}',
                            rating: deliveryRating,
                            onChanged: (value) =>
                                setSheetState(() => deliveryRating = value),
                          ),
                          if (deliveryRating != null &&
                              deliveryRating! <= 3) ...[
                            const SizedBox(height: 14),
                            _ReasonChips(
                              options: _deliveryReasons,
                              selected: deliveryReasons,
                              onToggle: (reason) => setSheetState(() {
                                if (!deliveryReasons.remove(reason)) {
                                  deliveryReasons.add(reason);
                                }
                              }),
                            ),
                          ],
                        ],
                        const SizedBox(height: 18),
                        if (!showComment)
                          TextButton.icon(
                            onPressed: () =>
                                setSheetState(() => showComment = true),
                            style: TextButton.styleFrom(
                              padding: EdgeInsets.zero,
                              foregroundColor: _muted,
                            ),
                            icon: const Icon(Icons.edit_note_rounded,
                                size: 20),
                            label: const Text(
                              'Add a comment',
                              style: TextStyle(fontWeight: FontWeight.w700),
                            ),
                          )
                        else
                          TextField(
                            controller: commentController,
                            maxLines: 3,
                            autofocus: true,
                            decoration: InputDecoration(
                              hintText: 'Tell us more (optional)',
                              filled: true,
                              fillColor: const Color(0xFFF7F8FA),
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(14),
                                borderSide: BorderSide.none,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                Container(
                  padding: EdgeInsets.fromLTRB(
                    20,
                    12,
                    20,
                    12 + MediaQuery.paddingOf(context).bottom,
                  ),
                  decoration: const BoxDecoration(
                    border: Border(top: BorderSide(color: _line)),
                  ),
                  child: SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton(
                      onPressed: submitting || !canSubmit ? null : submit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _accent,
                        disabledBackgroundColor: _accent.withOpacity(0.4),
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                      child: submitting
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.2,
                                color: Colors.white,
                              ),
                            )
                          : const Text(
                              'Submit rating',
                              style: TextStyle(fontWeight: FontWeight.w800),
                            ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    ),
  );

  // showModalBottomSheet completes as soon as the route is popped, while its
  // reverse transition (and the TextFields) can remain mounted for a few
  // frames. Keep their controllers alive until that transition has finished.
  await Future<void>.delayed(const Duration(milliseconds: 350));
  commentController.dispose();
  return submitted;
}

class _RatingBlock extends StatelessWidget {
  const _RatingBlock({
    required this.title,
    required this.rating,
    required this.onChanged,
  });

  final String title;
  final int? rating;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            color: _text,
            fontSize: 15,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            ...List.generate(5, (index) {
              final value = index + 1;
              final filled = rating != null && value <= rating!;
              return InkWell(
                onTap: () => onChanged(value),
                borderRadius: BorderRadius.circular(999),
                child: Padding(
                  padding: const EdgeInsets.only(right: 4),
                  child: Icon(
                    filled ? Icons.star_rounded : Icons.star_border_rounded,
                    color: _green,
                    size: 38,
                  ),
                ),
              );
            }),
            if (rating != null) ...[
              const SizedBox(width: 8),
              Text(
                _ratingLabels[rating!],
                style: const TextStyle(
                  color: _green,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ],
        ),
      ],
    );
  }
}

class _ReasonChips extends StatelessWidget {
  const _ReasonChips({
    required this.options,
    required this.selected,
    required this.onToggle,
  });

  final List<String> options;
  final Set<String> selected;
  final ValueChanged<String> onToggle;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: options.map((reason) {
        final isSelected = selected.contains(reason);
        return InkWell(
          onTap: () => onToggle(reason),
          borderRadius: BorderRadius.circular(999),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: isSelected ? const Color(0xFFFFF0E6) : Colors.white,
              borderRadius: BorderRadius.circular(999),
              border: Border.all(
                color: isSelected ? _accent : _line,
              ),
            ),
            child: Text(
              reason,
              style: TextStyle(
                color: isSelected ? _accent : _text,
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        );
      }).toList(),
    );
  }
}

class _ItemRatingCard extends StatelessWidget {
  const _ItemRatingCard({
    required this.name,
    required this.imageUrl,
    required this.rating,
    required this.onChanged,
  });

  final String name;
  final String imageUrl;
  final int? rating;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 96,
      child: Column(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: SizedBox(
              width: 96,
              height: 56,
              child: imageUrl.trim().isNotEmpty
                  ? AppCachedImage(
                      imageUrl: imageUrl,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        color: const Color(0xFFF2F3F5),
                        child: const Icon(Icons.restaurant_rounded,
                            color: _muted, size: 20),
                      ),
                    )
                  : Container(
                      color: const Color(0xFFF2F3F5),
                      child: const Icon(Icons.restaurant_rounded,
                          color: _muted, size: 20),
                    ),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
              color: _text,
            ),
          ),
          const SizedBox(height: 3),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              final value = index + 1;
              final filled = rating != null && value <= rating!;
              return InkWell(
                onTap: () => onChanged(value),
                borderRadius: BorderRadius.circular(999),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 1),
                  child: Icon(
                    filled ? Icons.star_rounded : Icons.star_border_rounded,
                    size: 16,
                    color: _green,
                  ),
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}
