import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';

class OffersScreen extends StatefulWidget {
  const OffersScreen({super.key});

  @override
  State<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends State<OffersScreen> {
  final ApiService _api = ApiService();

  List<Map<String, dynamic>> _offers = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadOffers();
  }

  Future<void> _loadOffers({bool forceRefresh = false}) async {
    setState(() => _loading = _offers.isEmpty);
    try {
      final response = await _api.get(
        ApiConstants.activeOffers,
        includeAuth: false,
        cachePolicy: ApiCachePolicy.discovery,
        cacheFirst: !forceRefresh,
        refreshCached: !forceRefresh,
        onCacheRefreshed: (_) {
          if (mounted) _loadOffers(forceRefresh: true);
        },
      );
      if (response['success'] == true && response['data'] is List) {
        _offers = (response['data'] as List)
            .whereType<Map>()
            .map((offer) => Map<String, dynamic>.from(offer))
            .toList(growable: false);
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _discountText(BuildContext context, Map<String, dynamic> offer) {
    final type = offer['discount_type']?.toString() ?? 'percentage';
    final rawValue = offer['discount_value'] ?? offer['value'] ?? 0;
    final value = rawValue is num
        ? rawValue.toDouble()
        : double.tryParse(rawValue.toString()) ?? 0;
    if (type == 'percentage') {
      return '${value.toStringAsFixed(value == value.roundToDouble() ? 0 : 1)}% OFF';
    }
    return '${formatCurrency(context, value)} OFF';
  }

  String _codeText(Map<String, dynamic> offer) {
    return (offer['code'] ??
            offer['coupon_code'] ??
            offer['promo_code'] ??
            offer['title'] ??
            'AUTO')
        .toString();
  }

  String _subtitleText(Map<String, dynamic> offer) {
    return (offer['description'] ??
            offer['subtitle'] ??
            offer['offer_text'] ??
            'Available on eligible restaurant orders')
        .toString();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: accountCanvas,
      body: SafeArea(
        bottom: false,
        child: RefreshIndicator(
          onRefresh: () => _loadOffers(forceRefresh: true),
          child: CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(12, 8, 12, 10),
                  child: Row(
                    children: [
                      _CircleIconButton(
                        icon: Icons.arrow_back_rounded,
                        onTap: () => Navigator.of(context).maybePop(),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Offers & Promos',
                              style: TextStyle(
                                color: FoodFlowTheme.ink,
                                fontSize: 20,
                                height: 1.05,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 4),
                            Text(
                              'Fresh deals from restaurants near you',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: FoodFlowTheme.inkSoft,
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      _CircleIconButton(
                        icon: Icons.refresh_rounded,
                        onTap: _loadOffers,
                      ),
                    ],
                  ),
                ),
              ),
              if (_loading)
                const SliverFillRemaining(
                  child: Center(child: CircularProgressIndicator()),
                )
              else if (_offers.isEmpty)
                SliverFillRemaining(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Lottie.asset(
                        'assets/animations/Coupons.json',
                        width: 240,
                        height: 205,
                        fit: BoxFit.contain,
                      ),
                      const Text(
                        'No offers right now',
                        style: TextStyle(
                          color: FoodFlowTheme.ink,
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 6),
                      const Text(
                        'Fresh deals will appear here soon.',
                        style: TextStyle(color: FoodFlowTheme.muted),
                      ),
                    ],
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(12, 4, 12, 120),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final offer = _offers[index];
                        return Padding(
                          padding: EdgeInsets.only(
                            bottom: index == _offers.length - 1 ? 0 : 12,
                          ),
                          child: _OfferCard(
                            discount: _discountText(context, offer),
                            code: _codeText(offer),
                            subtitle: _subtitleText(offer),
                          ),
                        );
                      },
                      childCount: _offers.length,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OfferCard extends StatelessWidget {
  final String discount;
  final String code;
  final String subtitle;

  const _OfferCard({
    required this.discount,
    required this.code,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: accountBorder),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: const Color(0xFFF0F5FF),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.local_offer_rounded,
              color: Color(0xFF4B76E5),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  discount,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: FoodFlowTheme.inkSoft,
                    fontSize: 12,
                    height: 1.3,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF5F7FC),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    'Code: $code',
                    style: const TextStyle(
                      color: FoodFlowTheme.inkSoft,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
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

class _CircleIconButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;

  const _CircleIconButton({
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: Border.all(color: accountBorder),
        ),
        child: Icon(icon, color: FoodFlowTheme.ink),
      ),
    );
  }
}
