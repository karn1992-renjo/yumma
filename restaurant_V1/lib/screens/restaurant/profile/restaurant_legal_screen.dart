import 'package:flutter/material.dart';

import '../../../config/api_constants.dart';
import '../../../config/app_config.dart';
import '../../../services/api_service.dart';
import '../../../theme/foodflow_theme.dart';

class RestaurantLegalScreen extends StatefulWidget {
  const RestaurantLegalScreen({super.key});

  @override
  State<RestaurantLegalScreen> createState() => _RestaurantLegalScreenState();
}

class _RestaurantLegalScreenState extends State<RestaurantLegalScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _content = {};

  @override
  void initState() {
    super.initState();
    _loadContent();
  }

  Future<void> _loadContent() async {
    try {
      final response = await _api.get(ApiConstants.legalContent);
      if (response['success'] == true && response['data'] is Map) {
        setState(() => _content = Map<String, dynamic>.from(response['data']));
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final sections = [
      (
        'Terms of Service',
        _content['terms']?.toString().trim().isNotEmpty == true
            ? _content['terms'].toString()
            : 'Restaurant onboarding, menu operations, payouts, cancellations and account usage are governed by platform terms.'
      ),
      (
        'Privacy Policy',
        _content['privacy']?.toString().trim().isNotEmpty == true
            ? _content['privacy'].toString()
            : 'foodflow privacy policy: ${AppConfig.privacyPolicyUrl}\n\nWe use customer, restaurant, staff, delivery, order, payout, device, support, and location data to operate foodflow services, process orders, manage partner operations, prevent fraud, provide support, and meet legal obligations.'
      ),
      (
        'Refund Policy',
        _content['refund']?.toString().trim().isNotEmpty == true
            ? _content['refund'].toString()
            : 'Customer refunds and settlement effects are governed by the active admin-managed refund policy.'
      ),
      (
        'Legal Contact',
        'Email: ${_content['contact_email']?.toString().trim().isNotEmpty == true ? _content['contact_email'] : AppConfig.supportEmail}\nPhone: ${AppConfig.supportPhone}'
      ),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFFFFBFA),
      appBar: AppBar(
        title: const Text('Privacy & Legal'),
        backgroundColor: Colors.white,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
      ),
      body: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: sections.length + 1,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          if (index == 0) {
            return InkWell(
              borderRadius: BorderRadius.circular(8),
              onTap: () => Navigator.pushNamed(
                context,
                '/restaurant/profile/account-deletion-policy',
              ),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFFF0DADB)),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.person_remove_outlined,
                        color: FoodFlowTheme.orange),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Account Deletion Policy',
                            style: TextStyle(
                              color: FoodFlowTheme.ink,
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          SizedBox(height: 8),
                          Text(
                            'How foodflow users and restaurant partners can request account deletion, and which data may be deleted or retained.',
                            style: TextStyle(
                              color: FoodFlowTheme.muted,
                              height: 1.45,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          SizedBox(height: 8),
                          Text(
                            AppConfig.accountDeletionPolicyUrl,
                            style: TextStyle(
                              color: FoodFlowTheme.orange,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right, color: FoodFlowTheme.faint),
                  ],
                ),
              ),
            );
          }

          final section = sections[index - 1];
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: const Color(0xFFF0DADB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  section.$1,
                  style: const TextStyle(
                    color: FoodFlowTheme.ink,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  section.$2,
                  style: const TextStyle(
                    color: FoodFlowTheme.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
