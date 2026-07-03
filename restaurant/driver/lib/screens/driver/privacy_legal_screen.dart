import 'package:flutter/material.dart';

import '../../config/api_constants.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';

class DriverPrivacyLegalScreen extends StatefulWidget {
  const DriverPrivacyLegalScreen({super.key});

  @override
  State<DriverPrivacyLegalScreen> createState() =>
      _DriverPrivacyLegalScreenState();
}

class _DriverPrivacyLegalScreenState extends State<DriverPrivacyLegalScreen> {
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
            : 'Driver access, earnings, conduct, cancellations and account usage are governed by active platform terms.'
      ),
      (
        'Privacy Policy',
        _content['privacy']?.toString().trim().isNotEmpty == true
            ? _content['privacy'].toString()
            : 'We use your profile, device, route, payout and order data to operate delivery workflows and safety checks.'
      ),
      (
        'Refund Policy',
        _content['refund']?.toString().trim().isNotEmpty == true
            ? _content['refund'].toString()
            : 'Customer refunds and settlement impacts follow the active admin-managed refund policy.'
      ),
      (
        'Legal Contact',
        'Contact: ${_content['contact_email']?.toString().trim().isNotEmpty == true ? _content['contact_email'] : 'support@yumma.in'}'
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
        itemCount: sections.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, index) {
          final section = sections[index];
          return Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
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
