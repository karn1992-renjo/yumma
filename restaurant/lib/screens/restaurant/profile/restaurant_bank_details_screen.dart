// lib/screens/restaurant/profile/restaurant_bank_details_screen.dart
import 'dart:ui' show ImageFilter;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../services/api_service.dart';
import '../../../config/api_constants.dart';
import '../../../providers/auth_provider.dart';
import '../../../theme/foodflow_theme.dart';
import '../../../theme/aurora_theme.dart';
import '../../../widgets/aurora/aurora.dart';
import '../../../utils/payout_gateway_utils.dart';

class RestaurantBankDetailsScreen extends StatefulWidget {
  const RestaurantBankDetailsScreen({Key? key}) : super(key: key);

  @override
  State<RestaurantBankDetailsScreen> createState() =>
      _RestaurantBankDetailsScreenState();
}

class _RestaurantBankDetailsScreenState
    extends State<RestaurantBankDetailsScreen> {
  final ApiService _api = ApiService();
  final _formKey = GlobalKey<FormState>();
  bool _isSaving = false;

  late TextEditingController _bankNameController;
  late TextEditingController _accountHolderController;
  late TextEditingController _accountNumberController;
  late TextEditingController _confirmAccountNumberController;
  late TextEditingController _ifscController;
  late TextEditingController _upiIdController;
  late TextEditingController _accountIdController;
  PayoutGatewayProfile? _gatewayProfile;

  @override
  void initState() {
    super.initState();
    _bankNameController = TextEditingController();
    _accountHolderController = TextEditingController();
    _accountNumberController = TextEditingController();
    _confirmAccountNumberController = TextEditingController();
    _ifscController = TextEditingController();
    _upiIdController = TextEditingController();
    _accountIdController = TextEditingController();
    _loadBankDetails();
  }

  @override
  void dispose() {
    _bankNameController.dispose();
    _accountHolderController.dispose();
    _accountNumberController.dispose();
    _confirmAccountNumberController.dispose();
    _ifscController.dispose();
    _upiIdController.dispose();
    _accountIdController.dispose();
    super.dispose();
  }

  void _applyBankData(dynamic response) {
    if (response is! Map || response['success'] != true) return;
    final data = Map<String, dynamic>.from(response['data'] ?? {});
    if (!mounted) return;
    final authUser =
        Provider.of<AuthProvider>(context, listen: false).currentUser;
    final provider = data['payout_gateway_provider']?.toString() ??
        data['payment_gateway_provider']?.toString() ??
        authUser?.payoutGatewayProvider ??
        authUser?.paymentGatewayProvider;
    final countryCode =
        data['country_code']?.toString() ?? authUser?.countryCode;
    setState(() {
      _bankNameController.text = data['bank_name'] ?? '';
      _accountHolderController.text = data['account_holder_name'] ?? '';
      _accountNumberController.text = data['account_number'] ?? '';
      _confirmAccountNumberController.text = data['account_number'] ?? '';
      _ifscController.text = data['ifsc_code'] ?? '';
      _upiIdController.text = data['upi_id'] ?? '';
      _accountIdController.text =
          data['stripe_account_id'] ?? data['gateway_account_id'] ?? '';
      _gatewayProfile = resolvePayoutGatewayProfile(
        provider: provider,
        countryCode: countryCode,
      );
    });
  }

  Future<void> _loadBankDetails() async {
    try {
      final response = await _api.getWithCache(
        ApiConstants.restaurantSettings,
        onCache: _applyBankData,
      );
      _applyBankData(response);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error loading bank details: $e')),
      );
    }
  }

  Future<void> _saveBankDetails() async {
    if (!_formKey.currentState!.validate()) return;

    if (_accountNumberController.text !=
        _confirmAccountNumberController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Account numbers do not match'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() => _isSaving = true);

    try {
      final data = {
        'bank_name': _bankNameController.text.trim(),
        'account_holder_name': _accountHolderController.text.trim(),
        'account_number': _accountNumberController.text.trim(),
        'ifsc_code': _ifscController.text.trim(),
        'upi_id': _upiIdController.text.trim(),
        'stripe_account_id': _accountIdController.text.trim(),
        'gateway_account_id': _accountIdController.text.trim(),
      };

      final response =
          await _api.post(ApiConstants.restaurantSettings, data: data);

      if (response['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Bank details updated successfully'),
            backgroundColor: Colors.green,
          ),
        );
        Navigator.pop(context, true);
      } else {
        throw Exception(response['message'] ?? 'Failed to update bank details');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
      );
    }

    if (mounted) {
      setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final authUser = Provider.of<AuthProvider>(context, listen: false).currentUser;
    final profile = _gatewayProfile ??
        resolvePayoutGatewayProfile(
          provider: authUser?.paymentGatewayProvider,
          countryCode: authUser?.countryCode,
        );

    final topPad = MediaQuery.of(context).padding.top + 64;

    return Scaffold(
      backgroundColor: foodflow.canvas,
      extendBodyBehindAppBar: true,
      appBar: GlassAppBar(
        title: Text('Payout account',
            style: TextStyle(
                color: foodflow.ink,
                fontSize: 17,
                fontWeight: FontWeight.w900)),
      ),
      bottomNavigationBar: ClipRect(
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 16, sigmaY: 16),
          child: Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            decoration: BoxDecoration(
              color: foodflow.canvas.withOpacity(0.82),
              border: Border(top: BorderSide(color: foodflow.glassBorder)),
            ),
            child: SafeArea(
              top: false,
              child: SizedBox(
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: _isSaving ? null : _saveBankDetails,
                  icon: _isSaving
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white))
                      : const Icon(Icons.lock_outline_rounded),
                  label: Text(_isSaving ? 'Saving…' : 'Save payout details'),
                  style: FoodFlowTheme.zomatoPrimaryButton(),
                ),
              ),
            ),
          ),
        ),
      ),
      body: Stack(children: [
        ...AuroraTheme.auroraBlobs(),
        SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(16, topPad, 16, 24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: foodflow.brandGradient,
                    borderRadius: BorderRadius.circular(20),
                    boxShadow: [
                      BoxShadow(
                        color: foodflow.orange.withOpacity(0.24),
                        blurRadius: 20,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.verified_user_rounded,
                              color: Colors.white, size: 20),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Encrypted · used only for settlements',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.92),
                                fontSize: 12.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      _heroPill('Region  ${profile.countryCode}'),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Payouts are managed by the platform. Add the settlement account the platform should send your earnings to.',
                  style: TextStyle(color: foodflow.muted, fontSize: 12),
                ),
                const SizedBox(height: 20),
              // Account Holder Name (always shown)
              const Text(
                'Account Holder Name',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _accountHolderController,
                decoration: InputDecoration(
                  hintText: 'Enter account holder name',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                validator: (value) {
                  if (value?.isEmpty ?? true) {
                    return 'Please enter account holder name';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              // Bank Details Section (conditional based on profile)
              if (profile.showBankDetails) ...[
                const Text(
                  'Bank Name',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _bankNameController,
                  decoration: InputDecoration(
                    hintText: 'Enter settlement bank name',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: profile.bankDetailsRequired
                      ? (value) {
                          if (value?.isEmpty ?? true) {
                            return 'Please enter bank name';
                          }
                          return null;
                        }
                      : null,
                ),
                const SizedBox(height: 16),
                const Text(
                  'Account Number',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _accountNumberController,
                  decoration: InputDecoration(
                    hintText: 'Enter payout account number',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: profile.bankDetailsRequired
                      ? (value) {
                          if (value?.isEmpty ?? true) {
                            return 'Please enter account number';
                          }
                          return null;
                        }
                      : null,
                ),
                const SizedBox(height: 16),
                const Text(
                  'Confirm Account Number',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _confirmAccountNumberController,
                  decoration: InputDecoration(
                    hintText: 'Re-enter account number',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: profile.bankDetailsRequired
                      ? (value) {
                          if (value?.isEmpty ?? true) {
                            return 'Please confirm account number';
                          }
                          return null;
                        }
                      : null,
                ),
                const SizedBox(height: 16),
                Text(
                  profile.routingCodeLabel,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _ifscController,
                  decoration: InputDecoration(
                    hintText: profile.routingCodeHint,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  validator: profile.bankDetailsRequired
                      ? (value) {
                          if (value?.trim().isEmpty ?? true) {
                            return 'Please enter ${profile.routingCodeLabel.toLowerCase()}';
                          }
                          return null;
                        }
                      : null,
                ),
                const SizedBox(height: 16),
              ],
              // UPI Section (conditional based on profile)
              if (profile.supportsUpi) ...[
                const Text(
                  'UPI ID (Optional)',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 8),
                TextFormField(
                  controller: _upiIdController,
                  decoration: InputDecoration(
                    hintText: 'e.g., restaurant@upi',
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
              ],
              // Platform payout account reference
              Text(
                'Payout account ID${profile.requiresAccountId ? '' : ' (Optional)'}',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              TextFormField(
                controller: _accountIdController,
                decoration: InputDecoration(
                  hintText: 'Only if the platform gave you a payout reference',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                validator: (value) {
                  if (profile.requiresAccountId && (value?.trim().isEmpty ?? true)) {
                    return 'Please enter your payout account ID';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
            ],
          ),
        ),
        ),
      ]),
    );
  }

  Widget _heroPill(String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(
          color: Colors.white.withOpacity(0.16),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(text,
            style: const TextStyle(
                color: Colors.white,
                fontSize: 11,
                fontWeight: FontWeight.w800)),
      );
}
