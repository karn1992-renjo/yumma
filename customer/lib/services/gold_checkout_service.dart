import 'package:flutter/material.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_constants.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'app_branding_service.dart';

/// Starts a customer's Gold membership first (possibly trial-deferred)
/// charge via the chosen gateway's recurring-mandate checkout -- same shape
/// as the restaurant app's SubscriptionCheckoutService, reusing the same
/// backend gateway machinery (SubscriptionGatewayInterface) but against the
/// /gold/* endpoints instead of /restaurant/subscription/*. The backend
/// activates the membership from the gateway's own webhook once the mandate
/// is authorized/charged, not synchronously here.
class GoldCheckoutService {
  GoldCheckoutService({
    required this.onAuthorized,
    required this.onFailure,
  }) {
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleSuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});
  }

  final VoidCallback onAuthorized;
  final void Function(String message) onFailure;
  final ApiService _api = ApiService();
  final Razorpay _razorpay = Razorpay();

  void dispose() => _razorpay.clear();

  Future<void> subscribe({
    required int planId,
    required String paymentMethod,
    required User? user,
  }) async {
    final response = await _api.post(
      ApiConstants.goldSubscribe,
      data: {'plan_id': planId, 'payment_method': paymentMethod},
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Unable to start Gold membership');
    }

    final checkout = Map<String, dynamic>.from(
      (response['data'] as Map)['checkout'] ?? {},
    );

    if (paymentMethod == 'cashfree') {
      await _openCashfreeAuthorization(checkout);
      return;
    }

    await _openRazorpaySubscription(checkout, user);
  }

  Future<void> _openRazorpaySubscription(
    Map<String, dynamic> checkout,
    User? user,
  ) async {
    final subscriptionId = checkout['subscription_id']?.toString();
    if (subscriptionId == null || subscriptionId.isEmpty) {
      throw Exception('Subscription session missing from server');
    }

    final branding = await AppBrandingService.instance.loadBranding();
    final paymentColor = branding.primaryColorHex.trim().startsWith('#')
        ? branding.primaryColorHex.trim()
        : '#${branding.primaryColorHex.trim()}';

    _razorpay.open({
      'key': checkout['key'],
      'subscription_id': subscriptionId,
      'name': branding.appName,
      'description': 'Gold membership',
      'prefill': {
        'contact': user?.phone ?? '',
        'email': user?.email ?? '',
      },
      'theme': {'color': paymentColor},
    });
  }

  /// Same hosted-page fallback as the restaurant app's Cashfree subscription
  /// flow -- no native modal for Subscriptions in the installed SDK version.
  Future<void> _openCashfreeAuthorization(Map<String, dynamic> checkout) async {
    final link = checkout['authorization_link']?.toString();
    if (link == null || link.isEmpty) {
      throw Exception('Subscription authorization link missing from server');
    }

    final uri = Uri.tryParse(link);
    if (uri == null) {
      throw Exception('Subscription authorization link is invalid');
    }

    final opened = await launchUrl(uri, mode: LaunchMode.inAppBrowserView);
    if (!opened) {
      throw Exception('Unable to open the membership payment page');
    }

    onAuthorized();
  }

  void _handleSuccess(PaymentSuccessResponse response) {
    onAuthorized();
  }

  void _handleError(PaymentFailureResponse response) {
    onFailure(_normalizeErrorMessage(response.message, fallback: 'Payment cancelled'));
  }

  String _normalizeErrorMessage(String? message, {required String fallback}) {
    final trimmed = message?.trim();
    if (trimmed == null || trimmed.isEmpty) return fallback;

    final normalized = trimmed.toLowerCase();
    if (normalized == 'null' ||
        normalized == 'undefined' ||
        normalized == 'exception: null' ||
        normalized == 'exception: undefined') {
      return fallback;
    }

    return trimmed.startsWith('Exception: ')
        ? trimmed.substring('Exception: '.length)
        : trimmed;
  }
}
