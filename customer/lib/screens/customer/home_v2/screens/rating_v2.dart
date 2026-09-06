import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../models/order.dart';
import '../../../../providers/order_provider.dart';
import '../theme/v2_theme.dart';
import '../widgets/v2_anim.dart';
import '../widgets/v2_glass.dart';
import '../widgets/v2_scaffold.dart';

const _labels = ['', 'Poor', 'Fair', 'Good', 'Very good', 'Excellent'];

const _foodReasons = [
  'Food was cold',
  'Portion was small',
  'Wrong item',
  'Quality issue',
  'Item missing',
];
const _deliveryReasons = [
  'Rider was late',
  'Rider was rude',
  'Order was damaged',
  'Wrong address',
];

/// Full-screen post-delivery rating flow (food + delivery + comment).
class RatingV2 extends StatefulWidget {
  const RatingV2({super.key, required this.order});

  final Order order;

  @override
  State<RatingV2> createState() => _RatingV2State();
}

class _RatingV2State extends State<RatingV2> {
  int _food = 0;
  int _delivery = 0;
  final Set<String> _foodTags = {};
  final Set<String> _deliveryTags = {};
  final TextEditingController _comment = TextEditingController();
  bool _submitting = false;

  bool get _canRateDriver =>
      !widget.order.isTakeaway && widget.order.driver != null;
  bool get _canSubmit =>
      _food > 0 && (!_canRateDriver || _delivery > 0) && !_submitting;

  @override
  void initState() {
    super.initState();
    _food = widget.order.restaurantRating ?? 0;
    _delivery = widget.order.driverRating ?? 0;
  }

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_canSubmit) return;
    setState(() => _submitting = true);
    final comment = _comment.text.trim();
    final restaurantFeedback = [
      if (_foodTags.isNotEmpty) _foodTags.join(', '),
      if (comment.isNotEmpty) comment,
    ].join('. ');
    final ok = await context.read<OrderProvider>().submitFeedback(
          orderId: widget.order.id,
          restaurantRating: _food,
          driverRating: _canRateDriver ? _delivery : null,
          itemRating: _food,
          restaurantFeedback:
              restaurantFeedback.isEmpty ? null : restaurantFeedback,
          driverFeedback:
              _deliveryTags.isEmpty ? null : _deliveryTags.join(', '),
        );
    if (!mounted) return;
    if (ok) {
      Navigator.of(context).pop(true);
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
            const SnackBar(content: Text('Thanks for the feedback!')));
    } else {
      setState(() => _submitting = false);
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
            const SnackBar(content: Text('Could not submit feedback.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return V2Scaffold(
      title: 'Rate your order',
      showBack: true,
      body: Builder(
        builder: (context) {
          final p = V2Theme.of(context);
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                  children: [
                    Text('From ${widget.order.restaurant?.name ?? 'the restaurant'}',
                        style: TextStyle(color: p.inkFaint, fontSize: 12.5)),
                    const SizedBox(height: 14),
                    _Block(
                      title: 'Food',
                      rating: _food,
                      onRate: (v) => setState(() => _food = v),
                      reasons: _food > 0 && _food <= 3 ? _foodReasons : const [],
                      selected: _foodTags,
                      onToggle: (r) => setState(() =>
                          _foodTags.contains(r)
                              ? _foodTags.remove(r)
                              : _foodTags.add(r)),
                    ),
                    if (_canRateDriver) ...[
                      const SizedBox(height: 14),
                      _Block(
                        title: 'Delivery',
                        rating: _delivery,
                        onRate: (v) => setState(() => _delivery = v),
                        reasons: _delivery > 0 && _delivery <= 3
                            ? _deliveryReasons
                            : const [],
                        selected: _deliveryTags,
                        onToggle: (r) => setState(() =>
                            _deliveryTags.contains(r)
                                ? _deliveryTags.remove(r)
                                : _deliveryTags.add(r)),
                      ),
                    ],
                    const SizedBox(height: 14),
                    GlassPanel(
                      radius: 16,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 14, vertical: 4),
                      child: TextField(
                        controller: _comment,
                        maxLines: 3,
                        minLines: 2,
                        keyboardAppearance: p.isDark
                            ? Brightness.dark
                            : Brightness.light,
                        style: TextStyle(color: p.ink, fontSize: 13),
                        cursorColor: p.accent,
                        decoration: InputDecoration(
                          isDense: true,
                          border: InputBorder.none,
                          contentPadding:
                              const EdgeInsets.symmetric(vertical: 4),
                          hintText: 'Tell us more (optional)',
                          hintStyle:
                              TextStyle(color: p.inkFaint, fontSize: 13),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: EdgeInsets.fromLTRB(16, 12, 16,
                    MediaQuery.of(context).padding.bottom + 14),
                decoration: BoxDecoration(
                  color: p.isDark ? const Color(0xF2121720) : Colors.white,
                  border: Border(top: BorderSide(color: p.glassBorder)),
                ),
                child: V2Tappable(
                  onTap: _canSubmit ? _submit : null,
                  child: Container(
                    height: 52,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: _canSubmit ? p.accent : p.inkFaint,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: _submitting
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white),
                          )
                        : const Text('Submit rating',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 15)),
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _Block extends StatelessWidget {
  const _Block({
    required this.title,
    required this.rating,
    required this.onRate,
    required this.reasons,
    required this.selected,
    required this.onToggle,
  });

  final String title;
  final int rating;
  final ValueChanged<int> onRate;
  final List<String> reasons;
  final Set<String> selected;
  final ValueChanged<String> onToggle;

  @override
  Widget build(BuildContext context) {
    final p = V2Theme.of(context);
    return GlassPanel(
      radius: 18,
      strong: true,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Text(title,
                  style: TextStyle(
                      color: p.ink,
                      fontSize: 15,
                      fontWeight: FontWeight.w900)),
              const Spacer(),
              if (rating > 0)
                Text(_labels[rating],
                    style: TextStyle(
                        color: p.accent,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w800)),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              for (var i = 1; i <= 5; i++)
                V2Tappable(
                  onTap: () => onRate(i),
                  child: Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: Icon(
                      i <= rating
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      size: 36,
                      color: i <= rating ? p.warning : p.inkFaint,
                    ),
                  ),
                ),
            ],
          ),
          if (reasons.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final r in reasons)
                  GlassChip(
                    label: r,
                    selected: selected.contains(r),
                    onTap: () => onToggle(r),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// Quick "rate your order" bottom sheet shown once after delivery. Opens the
/// full [RatingV2] screen for detailed feedback.
Future<void> showV2OrderFeedbackDialog(BuildContext context, Order order) async {
  final p = V2Palette.of(v2ModeNotifier.value);
  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: Colors.transparent,
    isScrollControlled: true,
    builder: (sheetContext) => V2Theme(
      palette: p,
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: GlassPanel(
            radius: 24,
            strong: true,
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 22),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: p.glassBorder,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
                const SizedBox(height: 16),
                const Text('🍽️', style: TextStyle(fontSize: 34)),
                const SizedBox(height: 10),
                Text('How was your order?',
                    style: TextStyle(
                        color: p.ink,
                        fontSize: 18,
                        fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(
                  'From ${order.restaurant?.name ?? 'the restaurant'}',
                  style: TextStyle(color: p.inkFaint, fontSize: 12.5),
                ),
                const SizedBox(height: 18),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    for (var i = 1; i <= 5; i++)
                      V2Tappable(
                        onTap: () {
                          Navigator.of(sheetContext).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => RatingV2(order: order),
                            ),
                          );
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 4),
                          child: Icon(Icons.star_outline_rounded,
                              size: 40, color: p.inkFaint),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    Expanded(
                      child: V2Tappable(
                        onTap: () => Navigator.of(sheetContext).pop(),
                        child: Container(
                          height: 48,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: p.glassTop,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: p.glassBorder),
                          ),
                          child: Text('Later',
                              style: TextStyle(
                                  color: p.inkSoft,
                                  fontWeight: FontWeight.w800)),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: V2Tappable(
                        onTap: () {
                          Navigator.of(sheetContext).pop();
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => RatingV2(order: order),
                            ),
                          );
                        },
                        child: Container(
                          height: 48,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: p.accent,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Text('Rate now',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900)),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
