import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../models/order.dart';
import '../../providers/order_provider.dart';

const _accent = Color(0xFFFF6B00);
const _text = Color(0xFF111827);
const _muted = Color(0xFF6B7280);
const _green = Color(0xFF16A34A);
const _lightGreen = Color(0xFFEAF8EA);

Future<bool> showOrderFeedbackDialog(
  BuildContext context,
  Order order,
) async {
  final existingPrefs = await SharedPreferences.getInstance();
  if (existingPrefs.getInt('dismissed_feedback_order_id') == order.id) {
    return false;
  }

  var restaurantRating = 5;
  var driverRating = 5;
  var itemRating = 5;
  var serviceRating = 5;
  var submitting = false;
  var submitted = false;
  final restaurantFeedback = TextEditingController();
  final driverFeedback = TextEditingController();
  final itemFeedback = TextEditingController();
  final serviceFeedback = TextEditingController();
  final canRateDriver = !order.isTakeaway && order.driver != null;

  await showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, setDialogState) {
        Future<void> submit() async {
          if (submitting) return;
          setDialogState(() => submitting = true);
          final success = await context.read<OrderProvider>().submitFeedback(
                orderId: order.id,
                restaurantRating: restaurantRating,
                driverRating: canRateDriver ? driverRating : null,
                itemRating: itemRating,
                serviceRating: serviceRating,
                restaurantFeedback: restaurantFeedback.text,
                driverFeedback: canRateDriver ? driverFeedback.text : null,
                itemFeedback: itemFeedback.text,
                serviceFeedback: serviceFeedback.text,
              );

          if (!dialogContext.mounted) return;
          if (success) {
            submitted = true;
            final prefs = await SharedPreferences.getInstance();
            await prefs.setInt('dismissed_feedback_order_id', order.id);
            if (dialogContext.mounted) Navigator.of(dialogContext).pop();
            return;
          }

          setDialogState(() => submitting = false);
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Could not submit feedback.')),
          );
        }

        return AlertDialog(
          backgroundColor: Colors.white,
          surfaceTintColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(28),
          ),
          icon: Container(
            width: 58,
            height: 58,
            decoration: const BoxDecoration(
              color: _lightGreen,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.check_circle_rounded,
              color: _green,
              size: 36,
            ),
          ),
          title: const Text(
            'Order completed!',
            style: TextStyle(color: _text, fontWeight: FontWeight.w900),
          ),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'How was your order from ${order.restaurant?.name ?? 'the restaurant'}?',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: _muted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 18),
                _RatingSection(
                  title: 'Items',
                  rating: itemRating,
                  controller: itemFeedback,
                  hint: 'How was the food?',
                  onChanged: (value) =>
                      setDialogState(() => itemRating = value),
                ),
                _RatingSection(
                  title: 'Restaurant',
                  rating: restaurantRating,
                  controller: restaurantFeedback,
                  hint: 'Packaging, freshness and restaurant experience',
                  onChanged: (value) =>
                      setDialogState(() => restaurantRating = value),
                ),
                if (canRateDriver)
                  _RatingSection(
                    title: 'Delivery partner · ${order.driver!.name}',
                    rating: driverRating,
                    controller: driverFeedback,
                    hint: 'Delivery experience and behaviour',
                    onChanged: (value) =>
                        setDialogState(() => driverRating = value),
                  ),
                _RatingSection(
                  title: 'Overall service',
                  rating: serviceRating,
                  controller: serviceFeedback,
                  hint: 'Tell us about the overall experience',
                  onChanged: (value) =>
                      setDialogState(() => serviceRating = value),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: submitting
                  ? null
                  : () async {
                      final prefs = await SharedPreferences.getInstance();
                      await prefs.setInt(
                        'dismissed_feedback_order_id',
                        order.id,
                      );
                      if (dialogContext.mounted) {
                        Navigator.of(dialogContext).pop();
                      }
                    },
              child: const Text('Later', style: TextStyle(color: _muted)),
            ),
            ElevatedButton(
              onPressed: submitting ? null : submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: _accent,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: submitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Submit rating'),
            ),
          ],
        );
      },
    ),
  );

  // showDialog completes as soon as the route is popped, while its reverse
  // transition (and the TextFields) can remain mounted for a few frames.
  // Keep their controllers alive until that transition has finished.
  await Future<void>.delayed(const Duration(milliseconds: 350));
  restaurantFeedback.dispose();
  driverFeedback.dispose();
  itemFeedback.dispose();
  serviceFeedback.dispose();
  return submitted;
}

class _RatingSection extends StatelessWidget {
  const _RatingSection({
    required this.title,
    required this.rating,
    required this.controller,
    required this.hint,
    required this.onChanged,
  });

  final String title;
  final int rating;
  final TextEditingController controller;
  final String hint;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: _text,
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
          Row(
            children: List.generate(5, (index) {
              final value = index + 1;
              return IconButton(
                visualDensity: VisualDensity.compact,
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints.tightFor(
                  width: 36,
                  height: 38,
                ),
                onPressed: () => onChanged(value),
                icon: Icon(
                  value <= rating
                      ? Icons.star_rounded
                      : Icons.star_border_rounded,
                  color: _green,
                  size: 30,
                ),
              );
            }),
          ),
          TextField(
            controller: controller,
            maxLines: 2,
            decoration: InputDecoration(
              hintText: hint,
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
    );
  }
}
