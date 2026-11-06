// lib/screens/restaurant/story_composer_screen.dart
import 'dart:io';

import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/stories/story_layout.dart';

/// What the restaurant chose for a story.
class StoryComposerResult {
  const StoryComposerResult({this.caption, required this.layout, this.menuItemId, this.ctaLabel});

  final String? caption;
  final String layout;
  final int? menuItemId;
  final String? ctaLabel;
}

/// Compose a story before posting: pick a layout, write a caption and (when the plan allows it) link a menu item
/// with a button. A live phone-shaped preview shows exactly what customers will see.
class StoryComposerScreen extends StatefulWidget {
  const StoryComposerScreen({
    super.key,
    required this.mediaType,
    required this.mediaPath,
    this.thumbnailPath,
    required this.canLinkMenu,
  });

  final String mediaType;
  final String mediaPath;
  final String? thumbnailPath;
  final bool canLinkMenu;

  @override
  State<StoryComposerScreen> createState() => _StoryComposerScreenState();
}

class _StoryComposerScreenState extends State<StoryComposerScreen> {
  final ApiService _api = ApiService();
  final TextEditingController _caption = TextEditingController();
  final TextEditingController _cta = TextEditingController(text: 'Order now');

  String _layout = 'spotlight';
  Map<String, dynamic>? _dish;
  List<Map<String, dynamic>> _dishes = [];
  bool _loadingDishes = false;

  @override
  void initState() {
    super.initState();
    if (widget.canLinkMenu) _loadDishes();
  }

  @override
  void dispose() {
    _caption.dispose();
    _cta.dispose();
    super.dispose();
  }

  Future<void> _loadDishes() async {
    setState(() => _loadingDishes = true);
    try {
      final response = await _api.get(ApiConstants.restaurantStoryMenuItems);
      if (response['success'] == true && mounted) {
        setState(() {
          _dishes = (response['data'] as List? ?? []).whereType<Map>().map((row) => Map<String, dynamic>.from(row)).toList();
        });
      }
    } catch (e) {
      debugPrint('Load story dishes error: $e');
    } finally {
      if (mounted) setState(() => _loadingDishes = false);
    }
  }

  Future<void> _chooseDish() async {
    if (_dishes.isEmpty && !_loadingDishes) await _loadDishes();
    if (!mounted) return;
    final picked = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: foodflow.surfaceColor,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(22))),
      builder: (sheetContext) => _DishPicker(dishes: _dishes),
    );
    if (picked != null && mounted) setState(() => _dish = picked);
  }

  Widget _media() {
    final thumb = widget.thumbnailPath;
    final path = widget.mediaType == 'video' && thumb != null ? thumb : widget.mediaPath;
    return Image.file(File(path), fit: BoxFit.cover);
  }

  void _post() {
    final caption = _caption.text.trim();
    Navigator.pop(
      context,
      StoryComposerResult(
        caption: caption.isEmpty ? null : caption,
        layout: _layout,
        menuItemId: _dish == null ? null : (_dish!['id'] as num).toInt(),
        ctaLabel: _dish == null ? null : (_cta.text.trim().isEmpty ? 'Order now' : _cta.text.trim()),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final previewHeight = MediaQuery.of(context).size.height * 0.5;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      appBar: AppBar(
        backgroundColor: foodflow.canvas,
        elevation: 0,
        foregroundColor: foodflow.ink,
        title: Text('New story', style: TextStyle(color: foodflow.ink, fontWeight: FontWeight.w900, fontSize: 17)),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: TextButton(
              onPressed: _post,
              style: TextButton.styleFrom(backgroundColor: kStoryAccent, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(horizontal: 18), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999))),
              child: const Text('Post', style: TextStyle(fontWeight: FontWeight.w900)),
            ),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 40),
        children: [
          Center(
            child: Container(
              height: previewHeight,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(26),
                boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.18), blurRadius: 24, offset: const Offset(0, 12))],
              ),
              child: AspectRatio(
                aspectRatio: 9 / 16,
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(26),
                  child: StoryLayoutView(
                    media: _media(),
                    layout: _layout,
                    caption: _caption.text,
                    menuItem: _dish,
                    ctaLabel: _cta.text,
                    compact: true,
                    topInset: 30,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 18),
          _label('Layout'),
          const SizedBox(height: 8),
          Row(
            children: [
              for (final l in kStoryLayouts)
                Expanded(
                  child: GestureDetector(
                    onTap: () => setState(() => _layout = l.key),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 160),
                      margin: const EdgeInsets.symmetric(horizontal: 4),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      decoration: BoxDecoration(
                        color: _layout == l.key ? kStoryAccent.withOpacity(0.12) : foodflow.surfaceColor,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: _layout == l.key ? kStoryAccent : foodflow.line, width: _layout == l.key ? 1.6 : 1),
                      ),
                      child: Column(children: [
                        Icon(l.icon, color: _layout == l.key ? kStoryAccent : foodflow.muted, size: 22),
                        const SizedBox(height: 4),
                        Text(l.label, style: TextStyle(color: _layout == l.key ? kStoryAccent : foodflow.ink, fontSize: 11.5, fontWeight: FontWeight.w800)),
                      ]),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 18),
          _label('Caption'),
          const SizedBox(height: 8),
          TextField(
            controller: _caption,
            maxLength: 150,
            maxLines: 2,
            onChanged: (_) => setState(() {}),
            decoration: _input('Say something about this story (optional)'),
          ),
          const SizedBox(height: 8),
          _label('Menu item & button'),
          const SizedBox(height: 8),
          if (!widget.canLinkMenu)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: foodflow.surfaceColor, borderRadius: BorderRadius.circular(16), border: Border.all(color: foodflow.line)),
              child: Row(children: [
                Icon(Icons.lock_outline_rounded, color: foodflow.muted),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Linking a dish with an order button is available on plans that include it. Upgrade your plan to turn it on.',
                    style: TextStyle(color: foodflow.muted, fontSize: 12.5, fontWeight: FontWeight.w600),
                  ),
                ),
              ]),
            )
          else ...[
            if (_dish == null)
              OutlinedButton.icon(
                onPressed: _loadingDishes ? null : _chooseDish,
                icon: _loadingDishes
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Icon(Icons.restaurant_menu_rounded),
                label: const Text('Link a menu item', style: TextStyle(fontWeight: FontWeight.w800)),
                style: OutlinedButton.styleFrom(
                  foregroundColor: kStoryAccent,
                  side: const BorderSide(color: kStoryAccent),
                  minimumSize: const Size.fromHeight(48),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              )
            else ...[
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: foodflow.surfaceColor, borderRadius: BorderRadius.circular(16), border: Border.all(color: foodflow.line)),
                child: Row(children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: SizedBox(
                      width: 48,
                      height: 48,
                      child: (_dish!['image_url'] ?? '').toString().isNotEmpty
                          ? Image.network(_dish!['image_url'].toString(), fit: BoxFit.cover)
                          : Container(color: foodflow.line, child: const Icon(Icons.restaurant_rounded)),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(_dish!['name'].toString(), maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: foodflow.ink, fontWeight: FontWeight.w800)),
                      Text(formatCurrencyValue(context, (_dish!['price'] as num?) ?? 0), style: TextStyle(color: foodflow.muted, fontSize: 12.5, fontWeight: FontWeight.w700)),
                    ]),
                  ),
                  TextButton(onPressed: _chooseDish, child: const Text('Change')),
                  IconButton(onPressed: () => setState(() => _dish = null), icon: const Icon(Icons.close_rounded)),
                ]),
              ),
              const SizedBox(height: 12),
              Text('Button text', style: TextStyle(color: foodflow.muted, fontSize: 12, fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 6,
                children: [
                  for (final option in kStoryCtaOptions)
                    ChoiceChip(
                      label: Text(option),
                      selected: _cta.text == option,
                      selectedColor: kStoryAccent.withOpacity(0.15),
                      labelStyle: TextStyle(color: _cta.text == option ? kStoryAccent : foodflow.ink, fontWeight: FontWeight.w800, fontSize: 12.5),
                      onSelected: (_) => setState(() => _cta.text = option),
                    ),
                ],
              ),
              const SizedBox(height: 8),
              TextField(
                controller: _cta,
                maxLength: 24,
                onChanged: (_) => setState(() {}),
                decoration: _input('Or type your own'),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _label(String text) => Text(text, style: TextStyle(color: foodflow.ink, fontSize: 14, fontWeight: FontWeight.w900));

  InputDecoration _input(String hint) => InputDecoration(
        hintText: hint,
        filled: true,
        fillColor: foodflow.surfaceColor,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: foodflow.line)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide(color: foodflow.line)),
      );
}

class _DishPicker extends StatefulWidget {
  const _DishPicker({required this.dishes});

  final List<Map<String, dynamic>> dishes;

  @override
  State<_DishPicker> createState() => _DishPickerState();
}

class _DishPickerState extends State<_DishPicker> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.dishes.where((d) => d['name'].toString().toLowerCase().contains(_query.toLowerCase())).toList();
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.7,
        child: Column(children: [
          const SizedBox(height: 10),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: foodflow.line, borderRadius: BorderRadius.circular(2))),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
            child: TextField(
              autofocus: false,
              onChanged: (v) => setState(() => _query = v),
              decoration: InputDecoration(
                hintText: 'Search your menu',
                prefixIcon: const Icon(Icons.search_rounded),
                filled: true,
                fillColor: foodflow.canvas,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
              ),
            ),
          ),
          Expanded(
            child: filtered.isEmpty
                ? Center(child: Text('No available dishes found.', style: TextStyle(color: foodflow.muted)))
                : ListView.builder(
                    itemCount: filtered.length,
                    itemBuilder: (context, index) {
                      final dish = filtered[index];
                      return ListTile(
                        leading: ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: SizedBox(
                            width: 44,
                            height: 44,
                            child: (dish['image_url'] ?? '').toString().isNotEmpty
                                ? Image.network(dish['image_url'].toString(), fit: BoxFit.cover)
                                : Container(color: foodflow.line, child: const Icon(Icons.restaurant_rounded)),
                          ),
                        ),
                        title: Text(dish['name'].toString(), style: const TextStyle(fontWeight: FontWeight.w800)),
                        subtitle: Text(formatCurrencyValue(context, (dish['price'] as num?) ?? 0)),
                        onTap: () => Navigator.pop(context, dish),
                      );
                    },
                  ),
          ),
        ]),
      ),
    );
  }
}
