import 'package:flutter/material.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_constants.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'app_branding_service.dart';

/// Starts a restaurant subscription's first (possibly trial-deferred)
/// charge via the chosen gateway's recurring-mandate checkout (Razorpay
/// Subscriptions SDK, or Cashfree Subscriptions' hosted authorization page).
/// Unlike the one-off ad-wallet top-up flow (AdWalletTopUpService), there is
/// no client-side "verify" call afterward -- the backend activates the
/// subscription from the gateway's own webhook once the mandate is
/// authorized/charged, which can land a moment after this screen's checkout
/// closes. [onAuthorized] fires once the payment step has been handed off
/// successfully (Razorpay: SDK reports success; Cashfree: the hosted page
/// was opened), before that webhook necessarily lands -- callers should
/// refresh and show a "setting up..." state rather than assuming the
/// subscription is already active.
class SubscriptionCheckoutService {
  SubscriptionCheckoutService({
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
      ApiConstants.restaurantSubscriptionSubscribe,
      data: {'plan_id': planId, 'payment_method': paymentMethod},
    );

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Unable to start subscription');
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
      'name': branding.displayName,
      'description': 'Subscription plan',
      'prefill': {
        'contact': user?.phone ?? '',
        'email': user?.email ?? '',
      },
      'theme': {'color': paymentColor},
    });
  }

  /// Cashfree Subscriptions has no native modal in the installed
  /// flutter_cashfree_pg_sdk version (only its one-off CFWebCheckoutPayment
  /// flow, already used for ad-wallet top-up) -- the mandate is authorized
  /// on a Cashfree-hosted page instead, opened here as an in-app browser tab
  /// via url_launcher. There is no in-app success callback for this path, so
  /// [onAuthorized] fires once the page has been handed off to the user;
  /// the real activation still only happens via the webhook.
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
      throw Exception('Unable to open the subscription payment page');
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
