import 'package:flutter/material.dart';

import '../../models/menu_item.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../common/network_image_loader.dart';

class MenuItemCard extends StatelessWidget {
  final MenuItem item;
  final int quantity;
  final bool isSaved;
  final VoidCallback? onTap;
  final VoidCallback? onShare;
  final VoidCallback? onSave;
  final ValueChanged<int>? onQuantityChanged;
  final bool orderingEnabled;
  final String? promotionTag;

  const MenuItemCard({
    super.key,
    required this.item,
    this.quantity = 0,
    this.isSaved = false,
    this.onTap,
    this.onShare,
    this.onSave,
    this.onQuantityChanged,
    this.orderingEnabled = true,
    this.promotionTag,
  });

  @override
  Widget build(BuildContext context) {
    final itemTags = item.displayTags.take(4).toList(growable: false);
    final highlightTag = item.totalOrders >= 20 ? 'Highly ordered' : null;
    final promoTag = promotionTag?.trim() ?? '';
    final compact = MediaQuery.sizeOf(context).width < 380;
    final imageWidth = compact ? 116.0 : 148.0;
    final imageHeight = compact ? 104.0 : 132.0;
    final itemPadding = compact
        ? const EdgeInsets.fromLTRB(12, 12, 12, 14)
        : const EdgeInsets.fromLTRB(16, 14, 16, 16);

    return InkWell(
      onTap: onTap,
      child: Container(
        padding: itemPadding,
        decoration: const BoxDecoration(
          color: Colors.white,
          border: Border(
            bottom: BorderSide(color: Color(0xFFF1F1F4)),
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _FoodTypeBadge(item: item),
                  SizedBox(height: compact ? 8 : 10),
                  Text(
                    item.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 14.1,
                      height: 1.2,
                      fontWeight: FontWeight.w700,
                      color: FoodFlowTheme.ink,
                    ),
                  ),
                  if (itemTags.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: itemTags
                          .map((tag) => _MenuTagBadge(label: tag))
                          .toList(),
                    ),
                  ],
                  if (highlightTag != null) ...[
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Container(
                          width: 32,
                          height: 8,
                          decoration: BoxDecoration(
                            color: const Color(0xFF0E924C),
                            borderRadius: BorderRadius.circular(999),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          highlightTag,
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: FoodFlowTheme.muted,
                          ),
                        ),
                      ],
                    ),
                  ],
                  SizedBox(height: compact ? 8 : 10),
                  Row(
                    children: [
                      Text(
                        formatCurrency(context, item.finalPrice),
                        style: const TextStyle(
                          fontSize: 12.3,
                          fontWeight: FontWeight.w600,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                      if (item.hasDiscount) ...[
                        const SizedBox(width: 6),
                        Text(
                          formatCurrency(context, item.price),
                          style: const TextStyle(
                            color: FoodFlowTheme.faint,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      ],
                    ],
                  ),
                  if (item.description?.trim().isNotEmpty == true) ...[
                    SizedBox(height: compact ? 8 : 10),
                    Text(
                      item.description!,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13,
                        height: 1.35,
                        color: FoodFlowTheme.inkSoft,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                  SizedBox(height: compact ? 10 : 14),
                  Row(
                    children: [
                      _ActionCircle(
                        icon: isSaved
                            ? Icons.bookmark_rounded
                            : Icons.bookmark_border_rounded,
                        onTap: onSave ?? onTap,
                      ),
                      const SizedBox(width: 10),
                      _ActionCircle(
                        icon: Icons.share_outlined,
                        onTap: onShare ?? onTap,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            SizedBox(width: compact ? 10 : 16),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Stack(
                  clipBehavior: Clip.none,
                  children: [
                    NetworkImageLoader(
                      imageUrl: item.imageUrl,
                      width: imageWidth,
                      height: imageHeight,
                      fit: BoxFit.cover,
                      borderRadius: BorderRadius.circular(compact ? 16 : 20),
                    ),
                    if (promoTag.isNotEmpty)
                      Positioned(
                        left: 8,
                        top: 8,
                        child: _PromotionTagBadge(label: promoTag),
                      ),
                    Positioned(
                      left: 20,
                      right: 20,
                      bottom: -15,
                      child: _AddControl(
                        quantity: quantity,
                        isAvailable: item.isAvailable && orderingEnabled,
                        unavailableLabel:
                            orderingEnabled ? 'UNAVAILABLE' : 'CLOSED',
                        isCustomisable: item.hasCustomizations,
                        onQuantityChanged: onQuantityChanged,
                        onTap: onTap,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                if (item.hasCustomizations)
                  const Text(
                    'customisable',
                    style: TextStyle(
                      fontSize: 12,
                      color: Color(0xFF9095A1),
                      fontWeight: FontWeight.w400,
                    ),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class MenuCustomizationSheet extends StatefulWidget {
  final MenuItem item;
  final ValueChanged<MenuCustomizationResult> onAdd;
  final bool isSaved;
  final VoidCallback? onSave;
  final VoidCallback? onShare;

  const MenuCustomizationSheet({
    super.key,
    required this.item,
    required this.onAdd,
    this.isSaved = false,
    this.onSave,
    this.onShare,
  });

  @override
  State<MenuCustomizationSheet> createState() => _MenuCustomizationSheetState();
}

class MenuCustomizationResult {
  final MenuOption? variant;
  final List<MenuOption> addOns;
  final int quantity;

  const MenuCustomizationResult({
    this.variant,
    this.addOns = const [],
    this.quantity = 1,
  });
}

class _MenuCustomizationSheetState extends State<MenuCustomizationSheet> {
  MenuOption? _selectedVariant;
  final Set<String> _selectedAddOns = <String>{};
  int _quantity = 1;

  @override
  void initState() {
    super.initState();
    if (widget.item.variants.isNotEmpty) {
      _selectedVariant = widget.item.variants.first;
    }
  }

  @override
  Widget build(BuildContext context) {
    final item = widget.item;
    final selectedAddOns = item.addOns
        .where((option) => _selectedAddOns.contains(option.name))
        .toList();
    final unitPrice = item.finalPrice +
        (_selectedVariant?.price ?? 0) +
        selectedAddOns.fold<double>(0, (sum, option) => sum + option.price);
    final total = unitPrice * _quantity;

    return SafeArea(
      top: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 54,
            height: 5,
            margin: const EdgeInsets.only(top: 10, bottom: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFD7DCE4),
              borderRadius: BorderRadius.circular(999),
            ),
          ),
          Flexible(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(28),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(24),
                          child: AspectRatio(
                            aspectRatio: 1.12,
                            child: NetworkImageLoader(
                              imageUrl: item.imageUrl,
                              width: double.infinity,
                              height: 280,
                              fit: BoxFit.cover,
                            ),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _FoodTypeBadge(item: item),
                              const SizedBox(height: 10),
                              Text(
                                item.name,
                                style: const TextStyle(
                                  fontSize: 15.8,
                                  fontWeight: FontWeight.w700,
                                  color: FoodFlowTheme.ink,
                                ),
                              ),
                              if (item.displayTags.isNotEmpty) ...[
                                const SizedBox(height: 10),
                                Wrap(
                                  spacing: 6,
                                  runSpacing: 6,
                                  children: item.displayTags
                                      .take(5)
                                      .map((tag) => _MenuTagBadge(label: tag))
                                      .toList(),
                                ),
                              ],
                              const SizedBox(height: 10),
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      item.description?.trim().isNotEmpty ==
                                              true
                                          ? item.description!
                                          : 'Handcrafted with careful preparation and delivered fresh.',
                                      style: const TextStyle(
                                        fontSize: 12.3,
                                        height: 1.35,
                                        color: FoodFlowTheme.inkSoft,
                                      ),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  _ActionCircle(
                                    icon: widget.isSaved
                                        ? Icons.bookmark_rounded
                                        : Icons.bookmark_border_rounded,
                                    onTap: widget.onSave ?? () {},
                                  ),
                                  const SizedBox(width: 10),
                                  _ActionCircle(
                                    icon: Icons.share_outlined,
                                    onTap: widget.onShare ?? () {},
                                  ),
                                ],
                              ),
                              const SizedBox(height: 12),
                              if (item.preparationTime != null)
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 7,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFF4F6FA),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Text(
                                    'Serves in ${item.preparationTime} mins',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w500,
                                      color: FoodFlowTheme.inkSoft,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (item.variants.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    _OptionSection(
                      title: 'Quantity',
                      subtitle: 'Required · Select any 1 option',
                      child: Column(
                        children: item.variants.map((option) {
                          final isSelected =
                              _selectedVariant?.name == option.name;
                          final optionTotal = item.finalPrice + option.price;
                          return _SelectionTile(
                            label: option.name,
                            trailing: formatCurrency(context, optionTotal),
                            selected: isSelected,
                            onTap: () {
                              setState(() => _selectedVariant = option);
                            },
                            selectionType: _SelectionType.radio,
                          );
                        }).toList(),
                      ),
                    ),
                  ],
                  if (item.addOns.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    _OptionSection(
                      title: 'Add Ons',
                      child: Column(
                        children: item.addOns.map((option) {
                          final isSelected =
                              _selectedAddOns.contains(option.name);
                          return _SelectionTile(
                            label: option.name,
                            trailing:
                                '+ ${formatCurrency(context, option.price)}',
                            selected: isSelected,
                            onTap: () {
                              setState(() {
                                if (isSelected) {
                                  _selectedAddOns.remove(option.name);
                                } else {
                                  _selectedAddOns.add(option.name);
                                }
                              });
                            },
                            selectionType: _SelectionType.checkbox,
                          );
                        }).toList(),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              boxShadow: [
                BoxShadow(
                  color: Color(0x14000000),
                  blurRadius: 18,
                  offset: Offset(0, -4),
                ),
              ],
            ),
            child: Row(
              children: [
                _FooterStepper(
                  quantity: _quantity,
                  onAdd: () => setState(() => _quantity++),
                  onRemove: () {
                    if (_quantity == 1) return;
                    setState(() => _quantity--);
                  },
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: SizedBox(
                    height: 56,
                    child: ElevatedButton(
                      onPressed: () {
                        widget.onAdd(
                          MenuCustomizationResult(
                            variant: _selectedVariant,
                            addOns: selectedAddOns,
                            quantity: _quantity,
                          ),
                        );
                        Navigator.pop(context);
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF0A9443),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      child: Text(
                        'Add item ${formatCurrency(context, total)}',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AddControl extends StatelessWidget {
  final int quantity;
  final bool isAvailable;
  final String unavailableLabel;
  final bool isCustomisable;
  final ValueChanged<int>? onQuantityChanged;
  final VoidCallback? onTap;

  const _AddControl({
    required this.quantity,
    required this.isAvailable,
    required this.unavailableLabel,
    required this.isCustomisable,
    this.onQuantityChanged,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    if (!isAvailable) {
      return Container(
        height: 44,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: const Color(0xFFF8F8FA),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFD8DCE4)),
        ),
        child: Text(
          unavailableLabel,
          style: const TextStyle(
            color: FoodFlowTheme.faint,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    if (quantity > 0 && !isCustomisable) {
      return Container(
        height: 44,
        decoration: BoxDecoration(
          color: const Color(0xFFEDFAF2),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFF83D4A5)),
        ),
        child: Row(
          children: [
            _StepperButton(
              icon: Icons.remove,
              onTap: () => onQuantityChanged?.call(quantity - 1),
            ),
            Expanded(
              child: Center(
                child: Text(
                  '$quantity',
                  style: const TextStyle(
                    color: Color(0xFF0A9443),
                    fontSize: 14.1,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
            _StepperButton(
              icon: Icons.add,
              onTap: () => onQuantityChanged?.call(quantity + 1),
            ),
          ],
        ),
      );
    }

    return Material(
      color: const Color(0xFFEDFAF2),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: isCustomisable ? onTap : () => onQuantityChanged?.call(1),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFF83D4A5)),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Text(
                'ADD',
                style: TextStyle(
                  color: Color(0xFF0A9443),
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(width: 8),
              Icon(
                isCustomisable ? Icons.arrow_forward_ios_rounded : Icons.add,
                size: isCustomisable ? 14 : 18,
                color: const Color(0xFF0A9443),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionCircle extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;

  const _ActionCircle({
    required this.icon,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: const Color(0xFFE8EAF0)),
        ),
        child: Icon(icon, color: const Color(0xFF7D8593), size: 20),
      ),
    );
  }
}

class _FoodTypeBadge extends StatelessWidget {
  final MenuItem item;

  const _FoodTypeBadge({required this.item});

  @override
  Widget build(BuildContext context) {
    final color = item.isEgg
        ? const Color(0xFFF39C12)
        : item.isNonVeg
            ? const Color(0xFFE24F4F)
            : const Color(0xFF0A9443);

    return Container(
      width: 16,
      height: 16,
      padding: const EdgeInsets.all(2.6),
      decoration: BoxDecoration(
        border: Border.all(color: color, width: 1.4),
        borderRadius: BorderRadius.circular(4),
      ),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(999),
        ),
      ),
    );
  }
}

class _MenuTagBadge extends StatelessWidget {
  final String label;

  const _MenuTagBadge({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: FoodFlowTheme.tagOrangeSoft,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: FoodFlowTheme.tagOrangeBorder),
      ),
      child: Text(
        label,
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: FoodFlowTheme.tagOrange,
        ),
      ),
    );
  }
}

class _PromotionTagBadge extends StatelessWidget {
  final String label;

  const _PromotionTagBadge({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 126),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: FoodFlowTheme.tagOrange,
        borderRadius: BorderRadius.circular(10),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.18),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.local_offer_rounded,
            color: Colors.white,
            size: 12,
          ),
          const SizedBox(width: 4),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _OptionSection extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget child;

  const _OptionSection({
    required this.title,
    this.subtitle,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 8),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 13.2,
              fontWeight: FontWeight.w700,
              color: FoodFlowTheme.ink,
            ),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 4),
            Text(
              subtitle!,
              style: const TextStyle(
                fontSize: 13,
                color: FoodFlowTheme.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}

enum _SelectionType { radio, checkbox }

class _SelectionTile extends StatelessWidget {
  final String label;
  final String trailing;
  final bool selected;
  final VoidCallback onTap;
  final _SelectionType selectionType;

  const _SelectionTile({
    required this.label,
    required this.trailing,
    required this.selected,
    required this.onTap,
    required this.selectionType,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  fontSize: 13.2,
                  fontWeight: FontWeight.w600,
                  color: FoodFlowTheme.ink,
                ),
              ),
            ),
            Text(
              trailing,
              style: const TextStyle(
                fontSize: 12.3,
                fontWeight: FontWeight.w600,
                color: FoodFlowTheme.ink,
              ),
            ),
            const SizedBox(width: 12),
            _SelectionIndicator(
              selected: selected,
              selectionType: selectionType,
            ),
          ],
        ),
      ),
    );
  }
}

class _SelectionIndicator extends StatelessWidget {
  final bool selected;
  final _SelectionType selectionType;

  const _SelectionIndicator({
    required this.selected,
    required this.selectionType,
  });

  @override
  Widget build(BuildContext context) {
    final borderRadius = selectionType == _SelectionType.radio
        ? BorderRadius.circular(999)
        : BorderRadius.circular(6);
    return Container(
      width: 28,
      height: 28,
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        border: Border.all(
          color: selected ? const Color(0xFF0A9443) : const Color(0xFFC6CBD5),
          width: 2,
        ),
        borderRadius: borderRadius,
      ),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: selected ? const Color(0xFF0A9443) : Colors.transparent,
          borderRadius: borderRadius,
        ),
      ),
    );
  }
}

class _StepperButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _StepperButton({
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        width: 42,
        height: 42,
        child: Icon(icon, color: const Color(0xFF0A9443), size: 18),
      ),
    );
  }
}

class _FooterStepper extends StatelessWidget {
  final int quantity;
  final VoidCallback onAdd;
  final VoidCallback onRemove;

  const _FooterStepper({
    required this.quantity,
    required this.onAdd,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 56,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFEDFAF2),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF83D4A5)),
      ),
      child: Row(
        children: [
          _StepperButton(icon: Icons.remove, onTap: onRemove),
          SizedBox(
            width: 28,
            child: Text(
              '$quantity',
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 15.8,
                fontWeight: FontWeight.w700,
                color: FoodFlowTheme.ink,
              ),
            ),
          ),
          _StepperButton(icon: Icons.add, onTap: onAdd),
        ],
      ),
    );
  }
}

/// A grid-style menu item card: full-width image on top with badges and the
/// add button overlaid directly on it, then text below with no card
/// background/border ("block") — matches the reference design used on the
/// restaurant details screen.
class RestaurantMenuGridCard extends StatelessWidget {
  final MenuItem item;
  final int quantity;
  final bool orderingEnabled;
  final VoidCallback? onTap;
  final ValueChanged<int>? onQuantityChanged;
  final String? promotionTag;

  const RestaurantMenuGridCard({
    super.key,
    required this.item,
    this.quantity = 0,
    this.orderingEnabled = true,
    this.onTap,
    this.onQuantityChanged,
    this.promotionTag,
  });

  @override
  Widget build(BuildContext context) {
    final isAvailable = item.isAvailable && orderingEnabled;
    final promo = promotionTag?.trim() ?? '';
    final isPopular = item.isBestseller ||
        item.isRecommended ||
        item.totalOrders >= 20;
    final rating = item.rating;
    final hasRating = rating != null && rating > 0;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(14),
            child: AspectRatio(
              aspectRatio: 1.5,
              child: Stack(
                clipBehavior: Clip.none,
                fit: StackFit.expand,
                children: [
                  if (!orderingEnabled)
                    ColorFiltered(
                      colorFilter: const ColorFilter.matrix(<double>[
                        0.2126, 0.7152, 0.0722, 0, 0,
                        0.2126, 0.7152, 0.0722, 0, 0,
                        0.2126, 0.7152, 0.0722, 0, 0,
                        0, 0, 0, 1, 0,
                      ]),
                      child: NetworkImageLoader(
                        imageUrl: item.imageUrl,
                        width: double.infinity,
                        height: double.infinity,
                        fit: BoxFit.cover,
                      ),
                    )
                  else
                    NetworkImageLoader(
                      imageUrl: item.imageUrl,
                      width: double.infinity,
                      height: double.infinity,
                      fit: BoxFit.cover,
                    ),
                  if (promo.isNotEmpty)
                    Positioned(
                      left: 8,
                      top: 8,
                      child: _PromotionTagBadge(label: promo),
                    )
                  else if (isPopular)
                    const Positioned(
                      left: 8,
                      top: 8,
                      child: _GridPopularBadge(),
                    ),
                  if (hasRating)
                    Positioned(
                      left: 8,
                      bottom: 8,
                      child: _GridRatingBadge(rating: rating),
                    ),
                  Positioned(
                    right: 8,
                    bottom: 8,
                    child: _GridAddButton(
                      quantity: quantity,
                      isAvailable: isAvailable,
                      unavailableLabel:
                          orderingEnabled ? 'Unavailable' : 'Closed',
                      isCustomisable: item.hasCustomizations,
                      onQuantityChanged: onQuantityChanged,
                      onTap: onTap,
                    ),
                  ),
                ],
              ),
            ),
          ),
          Padding(
              padding: const EdgeInsets.fromLTRB(2, 8, 2, 4),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Padding(
                        padding: const EdgeInsets.only(top: 3),
                        child: _FoodTypeBadge(item: item),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          item.name,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13.5,
                            height: 1.2,
                            fontWeight: FontWeight.w700,
                            color: FoodFlowTheme.ink,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.center,
                    children: [
                      Text(
                        formatCurrency(context, item.finalPrice),
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w800,
                          color: FoodFlowTheme.ink,
                        ),
                      ),
                      if (item.hasDiscount) ...[
                        const SizedBox(width: 6),
                        Text(
                          formatCurrency(context, item.price),
                          style: const TextStyle(
                            color: FoodFlowTheme.faint,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w500,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _GridPopularBadge extends StatelessWidget {
  const _GridPopularBadge();
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.14),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: const Text(
        'Popular',
        style: TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _GridRatingBadge extends StatelessWidget {
  const _GridRatingBadge({required this.rating});

  final double rating;
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(999),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.14),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.star_rounded, color: Colors.white, size: 13),
          const SizedBox(width: 3),
          Text(
            rating.toStringAsFixed(1),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _GridAddButton extends StatelessWidget {
  const _GridAddButton({
    required this.quantity,
    required this.isAvailable,
    required this.isCustomisable,
    this.unavailableLabel = 'Unavailable',
    this.onQuantityChanged,
    this.onTap,
  });

  final int quantity;
  final bool isAvailable;
  final bool isCustomisable;
  final String unavailableLabel;
  final ValueChanged<int>? onQuantityChanged;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    if (!isAvailable) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.black.withOpacity(0.55),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          unavailableLabel,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 10.5,
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }

    final isActive = quantity > 0 && !isCustomisable;

    return DecoratedBox(
      decoration: BoxDecoration(
        color: const Color(0xFF0A9443),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white, width: 3),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.2),
            blurRadius: 10,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: isActive
          ? SizedBox(
              height: 34,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  _GridStepperZone(
                    icon: Icons.remove_rounded,
                    onTap: () => onQuantityChanged?.call(quantity - 1),
                  ),
                  SizedBox(
                    width: 22,
                    child: Text(
                      '$quantity',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  _GridStepperZone(
                    icon: Icons.add_rounded,
                    onTap: () => onQuantityChanged?.call(quantity + 1),
                  ),
                ],
              ),
            )
          : InkWell(
              onTap: isCustomisable ? onTap : () => onQuantityChanged?.call(1),
              borderRadius: BorderRadius.circular(999),
              child: SizedBox(
                width: 34,
                height: 34,
                child: Icon(
                  isCustomisable
                      ? Icons.arrow_forward_ios_rounded
                      : Icons.add_rounded,
                  color: Colors.white,
                  size: isCustomisable ? 14 : 20,
                ),
              ),
            ),
    );
  }
}

class _GridStepperZone extends StatelessWidget {
  const _GridStepperZone({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: SizedBox(
        width: 25,
        height: 34,
        child: Icon(icon, color: Colors.white, size: 15),
      ),
    );
  }
}
