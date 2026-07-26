import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import '../../widgets/common/app_cached_image.dart';
import 'package:intl/intl.dart' show DateFormat;
import 'package:lottie/lottie.dart';
import 'package:path_provider/path_provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../config/api_constants.dart';
import '../../config/app_config.dart';
import '../../models/scratch_card.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/common/lucide_icon.dart';
import '../../widgets/customer/account_chrome.dart';
import '../../widgets/customer/scratch_card_art.dart';
import '../../widgets/customer/scratch_card_reveal_dialog.dart';

class OrderConfirmationScreen extends StatefulWidget {
  final int orderId;
  final String orderNumber;
  final String restaurantName;
  final String restaurantLogoUrl;
  final String paymentGatewayName;
  final String paymentGatewayLogoUrl;
  final double subtotal;
  final double discount;
  final double savedAmount;
  final double deliveryFee;
  final double platformFee;
  final double tax;
  final String taxLabel;
  final double total;
  final String? couponCode;
  final DateTime? scheduledTime;
  final List<ScratchCard> initialScratchCards;

  const OrderConfirmationScreen({
    super.key,
    required this.orderId,
    required this.orderNumber,
    required this.restaurantName,
    this.restaurantLogoUrl = '',
    this.paymentGatewayName = '',
    this.paymentGatewayLogoUrl = '',
    required this.subtotal,
    required this.discount,
    this.savedAmount = 0,
    required this.deliveryFee,
    required this.platformFee,
    required this.tax,
    this.taxLabel = 'Tax',
    required this.total,
    this.couponCode,
    this.scheduledTime,
    this.initialScratchCards = const [],
  });

  @override
  State<OrderConfirmationScreen> createState() =>
      _OrderConfirmationScreenState();
}

class _OrderConfirmationScreenState extends State<OrderConfirmationScreen>
    with SingleTickerProviderStateMixin {
  final ApiService _api = ApiService();
  final GlobalKey _shareCardKey = GlobalKey();
  late final AnimationController _animationController;
  late final Animation<double> _scaleAnimation;
  List<ScratchCard> _scratchCards = const [];
  bool _scratchPopupShown = false;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _scaleAnimation = CurvedAnimation(
      parent: _animationController,
      curve: Curves.elasticOut,
    );
    _animationController.forward();

    final initialCards = widget.initialScratchCards
        .where((card) => card.orderId == null || card.orderId == widget.orderId)
        .toList(growable: false);
    if (initialCards.isNotEmpty) {
      _scratchCards = initialCards;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _showScratchPopupIfNeeded(_scratchCards);
      });
      _loadScratchCards(showPopup: false);
    } else {
      _loadScratchCards();
    }
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
              child: Row(
                children: [
                  IconButton.filledTonal(
                    onPressed: _goToTrackOrder,
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: FoodFlowTheme.ink,
                    ),
                    icon: const AppIcon(AppIcons.delivery, size: 20),
                  ),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'Order confirmed',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(12, 2, 12, 20),
                children: [
                  _buildRestaurantCard(),
                  const SizedBox(height: 12),
                  _buildSuccessCard(),
                  if (_shareSavingsAmount > 0) ...[
                    const SizedBox(height: 12),
                    _buildSavingsShareCard(context),
                  ],
                  if (_scratchCards.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    _buildScratchCard(),
                  ],
                  const SizedBox(height: 12),
                  _buildBillCard(context),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _goToTrackOrder,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: FoodFlowTheme.primaryColor,
                        foregroundColor: Colors.white,
                        elevation: 2,
                        shadowColor:
                            FoodFlowTheme.primaryColor.withOpacity(0.22),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      icon: const AppIcon(AppIcons.delivery, size: 20),
                      label: const Text(
                        'Go to track order',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _loadScratchCards({
    bool showPopup = true,
    int attempt = 0,
  }) async {
    try {
      final response = await _api.get(ApiConstants.scratchCards);
      final rows = response['success'] == true && response['data'] is List
          ? response['data'] as List
          : const [];
      final cards = rows
          .whereType<Map>()
          .map((row) => ScratchCard.fromJson(Map<String, dynamic>.from(row)))
          .where((card) => card.orderId == widget.orderId)
          .toList(growable: false);
      if (!mounted) return;
      setState(() => _scratchCards = cards);
      if (cards.isEmpty && showPopup && attempt < 5) {
        await Future.delayed(Duration(milliseconds: 900 + (attempt * 350)));
        if (!mounted || _scratchPopupShown) return;
        await _loadScratchCards(showPopup: showPopup, attempt: attempt + 1);
        return;
      }
      if (showPopup) {
        _showScratchPopupIfNeeded(cards);
      }
    } catch (_) {}
  }

  Future<void> _showScratchPopupIfNeeded(List<ScratchCard> cards) async {
    if (_scratchPopupShown || cards.isEmpty) return;
    final index = cards.indexWhere((card) => !card.isRevealed);
    if (index < 0) return;

    _scratchPopupShown = true;
    await Future.delayed(const Duration(milliseconds: 650));
    if (!mounted) return;

    final updated = await showScratchCardRevealDialog(
      context,
      card: cards[index],
    );
    if (updated != null && mounted) {
      await _loadScratchCards(showPopup: false);
    }
  }

  void _goToTrackOrder() {
    Navigator.pushNamedAndRemoveUntil(
      context,
      '/order/track',
      (route) =>
          route.settings.name == '/home' ||
          route.settings.name == '/customer/home',
      arguments: widget.orderId,
    );
  }

  double get _shareSavingsAmount =>
      (widget.savedAmount > 0 ? widget.savedAmount : widget.discount)
          .clamp(0.0, double.infinity)
          .toDouble();

  String _appShareLink() {
    final domain = AppConfig.appsFlyerOneLinkDomain
        .replaceFirst(RegExp(r'^https?://'), '')
        .replaceFirst(RegExp(r'/$'), '')
        .trim();
    final pathSegments = AppConfig.appsFlyerOneLinkPath
        .split('/')
        .map((segment) => segment.trim())
        .where((segment) => segment.isNotEmpty)
        .toList(growable: true);
    final params = <String, String>{
      'pid': 'share',
      'c': 'order_savings_share',
      'deep_link_value': 'home',
      'screen': 'home',
    };

    if (domain.isNotEmpty) {
      return Uri.https(domain, pathSegments.join('/'), params).toString();
    }

    return AppConfig.apiBaseUrl.replaceFirst('/api', '');
  }

  Future<void> _shareSavings() async {
    final amount = _shareSavingsAmount;
    if (amount <= 0) return;

    final message =
        'Yeah! We have saved ${formatCurrency(context, amount)} from ${AppConfig.appName}. Download it now.\n${_appShareLink()}';

    try {
      final boundary = _shareCardKey.currentContext?.findRenderObject()
          as RenderRepaintBoundary?;
      if (boundary == null) {
        await Share.share(message, subject: AppConfig.appName);
        return;
      }

      final image = await boundary.toImage(pixelRatio: 3);
      final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
      final bytes = byteData?.buffer.asUint8List();
      if (bytes == null) {
        await Share.share(message, subject: AppConfig.appName);
        return;
      }

      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/yumma-savings-${widget.orderId}.png');
      await file.writeAsBytes(bytes, flush: true);
      await Share.shareXFiles(
        [XFile(file.path)],
        text: message,
        subject: AppConfig.appName,
      );
    } catch (_) {
      await Share.share(message, subject: AppConfig.appName);
    }
  }

  Widget _buildSavingsShareCard(BuildContext context) {
    final amount = _shareSavingsAmount;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: FoodFlowTheme.surface(radius: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          RepaintBoundary(
            key: _shareCardKey,
            child: Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    FoodFlowTheme.brandPrimary(context),
                    FoodFlowTheme.brandSecondary(context),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Row(
                children: [
                  Container(
                    width: 64,
                    height: 64,
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(22),
                    ),
                    child: Image.asset(
                      'assets/images/offer-icon.png',
                      fit: BoxFit.contain,
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'You saved ${formatCurrency(context, amount)}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 19,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          'Yeah! We have saved from ${AppConfig.appName}. Download it now.',
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.9),
                            fontSize: 12,
                            height: 1.25,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: _shareSavings,
            style: OutlinedButton.styleFrom(
              foregroundColor: FoodFlowTheme.brandPrimary(context),
              side: BorderSide(
                color: FoodFlowTheme.brandPrimary(context).withOpacity(0.24),
              ),
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            icon: const Icon(Icons.ios_share_rounded, size: 18),
            label: const Text(
              'Share savings',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRestaurantCard() {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: FoodFlowTheme.surface(radius: 30),
      child: Row(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: BoxDecoration(
              color: const Color(0xFFF7EFE7),
              borderRadius: BorderRadius.circular(24),
            ),
            clipBehavior: Clip.antiAlias,
            child: widget.restaurantLogoUrl.isNotEmpty
                ? AppCachedImage(
                    imageUrl: widget.restaurantLogoUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _restaurantIcon(),
                  )
                : _restaurantIcon(),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.restaurantName.isNotEmpty
                      ? widget.restaurantName
                      : 'Selected Restaurant',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: FoodFlowTheme.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Order #${widget.orderNumber}',
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    fontSize: 12,
                    fontWeight: FontWeight.w400,
                  ),
                ),
                if (widget.couponCode?.isNotEmpty == true) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 10,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE9F8EE),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      'Promo ${widget.couponCode}',
                      style: const TextStyle(
                        color: Color(0xFF189B50),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSuccessCard() {
    final primary = Theme.of(context).colorScheme.primary;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: FoodFlowTheme.surface(radius: 24),
      child: Column(
        children: [
          ScaleTransition(
            scale: _scaleAnimation,
            child: DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: primary.withOpacity(0.18),
                    blurRadius: 24,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: SizedBox(
                width: 154,
                height: 154,
                child: Lottie.asset(
                  'assets/animations/success.json',
                  fit: BoxFit.contain,
                  repeat: false,
                  animate: true,
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Order placed successfully',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Your restaurant has received the order and will start preparing it soon.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w400,
              color: FoodFlowTheme.inkSoft,
            ),
          ),
          if (widget.scheduledTime != null) ...[
            const SizedBox(height: 14),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFEFF7FF),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFD7E9FF)),
              ),
              child: Row(
                children: [
                  const AppIcon(
                    AppIcons.schedule,
                    color: Color(0xFF2F68D8),
                    size: 18,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Scheduled for ${DateFormat('EEE, d MMM - hh:mm a').format(widget.scheduledTime!)}',
                      style: const TextStyle(
                        color: FoodFlowTheme.ink,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (widget.paymentGatewayName.isNotEmpty) ...[
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF7F1),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFFFE0C8)),
              ),
              child: Row(
                children: [
                  _buildGatewayLogo(),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Payment gateway',
                          style: TextStyle(
                            color: FoodFlowTheme.muted,
                            fontSize: 11,
                            fontWeight: FontWeight.w400,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          widget.paymentGatewayName,
                          style: const TextStyle(
                            color: FoodFlowTheme.ink,
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildScratchCard() {
    final card = _scratchCards.first;
    final revealed = card.isRevealed;
    final locked = card.isRevealLocked;
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      decoration: FoodFlowTheme.surface(radius: 24),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      revealed
                          ? ScratchRewardText.title(context, card)
                          : 'You unlocked a scratch card!',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                        color: FoodFlowTheme.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      revealed
                          ? ScratchRewardText.subtitle(card)
                          : locked
                              ? (card.revealLockedReason ??
                                  'Claim this reward after order delivery')
                              : 'Scratch now and win exciting rewards',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: FoodFlowTheme.muted,
                        height: 1.25,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              const ScratchGiftBox(size: 62),
            ],
          ),
          const SizedBox(height: 12),
          ScratchCardFace(
            card: card,
            height: 124,
            compact: true,
            showOrderTag: false,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: ElevatedButton(
                  onPressed: () async {
                    if (revealed || locked) {
                      Navigator.pushNamed(
                        context,
                        '/scratch-cards',
                        arguments: widget.orderId,
                      ).then((_) => _loadScratchCards(showPopup: false));
                      return;
                    }

                    final updated = await showScratchCardRevealDialog(
                      context,
                      card: card,
                    );
                    if (updated != null && mounted) {
                      await _loadScratchCards(showPopup: false);
                    }
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: FoodFlowTheme.tagOrange,
                    foregroundColor: Colors.white,
                    elevation: 8,
                    shadowColor: FoodFlowTheme.tagOrange.withOpacity(0.24),
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                  child: Text(
                    revealed
                        ? 'View Reward'
                        : (locked ? 'After Delivery' : 'Scratch Now'),
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              TextButton(
                onPressed: () {
                  Navigator.pushNamed(
                    context,
                    '/scratch-cards',
                    arguments: widget.orderId,
                  ).then((_) => _loadScratchCards(showPopup: false));
                },
                style: TextButton.styleFrom(
                  foregroundColor: FoodFlowTheme.tagOrange,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 12,
                  ),
                ),
                child: const Text(
                  'All cards',
                  style: TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBillCard(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: FoodFlowTheme.surface(radius: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Bill details',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 14),
          _buildAmountRow(context, 'Item total', widget.subtotal),
          _buildAmountRow(context, 'Delivery fee', widget.deliveryFee),
          _buildAmountRow(context, 'Platform fee', widget.platformFee),
          _buildAmountRow(context, widget.taxLabel, widget.tax),
          if (widget.discount > 0)
            _buildAmountRow(
              context,
              'Coupon discount',
              -widget.discount,
              isSavings: true,
            ),
          const Divider(height: 28),
          _buildAmountRow(context, 'Total paid', widget.total, isTotal: true),
        ],
      ),
    );
  }

  Widget _buildAmountRow(
    BuildContext context,
    String title,
    double amount, {
    bool isTotal = false,
    bool isSavings = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                fontSize: isTotal ? 15 : 14,
                fontWeight: isTotal ? FontWeight.w800 : FontWeight.w400,
                color: isTotal ? FoodFlowTheme.ink : FoodFlowTheme.inkSoft,
              ),
            ),
          ),
          Text(
            '${isSavings ? '-' : ''}${formatCurrency(context, amount.abs())}',
            style: TextStyle(
              fontSize: isTotal ? 16 : 14,
              fontWeight: isTotal ? FontWeight.w900 : FontWeight.w700,
              color: isSavings ? FoodFlowTheme.success : FoodFlowTheme.ink,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGatewayLogo() {
    if (widget.paymentGatewayLogoUrl.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(12),
        child: AppCachedImage(
          imageUrl: widget.paymentGatewayLogoUrl,
          width: 44,
          height: 44,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => _gatewayFallback(),
        ),
      );
    }

    return _gatewayFallback();
  }

  Widget _gatewayFallback() {
    final primary = Theme.of(context).colorScheme.primary;
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: const Color(0xFFFFEBDC),
        borderRadius: BorderRadius.circular(12),
      ),
      alignment: Alignment.center,
      child: AppIcon(
        AppIcons.wallet,
        color: primary,
      ),
    );
  }

  Widget _restaurantIcon() {
    final primary = Theme.of(context).colorScheme.primary;
    return Container(
      color: const Color(0xFFFFF3E8),
      alignment: Alignment.center,
      child: AppIcon(
        AppIcons.storefront,
        color: primary,
      ),
    );
  }
}
