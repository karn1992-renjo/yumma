import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfexceptions.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../config/api_constants.dart';
import '../models/user.dart';
import 'api_service.dart';
import 'app_branding_service.dart';

/// Starts a customer's Gold membership period -- pay-as-you-go, same shape
/// as WalletRechargePaymentService (a one-time gateway order, confirmed
/// SYNCHRONOUSLY right here once the gateway SDK reports success), not a
/// recurring mandate. No webhook is involved anywhere in this flow: the
/// backend activates the membership the moment /gold/verify confirms the
/// payment, exactly like a regular checkout payment. A plan with a free
/// trial skips payment entirely -- the backend activates it immediately and
/// `onAuthorized` fires right away.
class GoldCheckoutService {
  GoldCheckoutService({
    required this.onAuthorized,
    required this.onFailure,
  }) {
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleRazorpaySuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleRazorpayError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});
    _cashfree.setCallback(_handleCashfreeSuccess, _handleCashfreeError);
  }

  final VoidCallback onAuthorized;
  final void Function(String message) onFailure;
  final ApiService _api = ApiService();
  final Razorpay _razorpay = Razorpay();
  final CFPaymentGatewayService _cashfree = CFPaymentGatewayService();

  int? _pendingSubscriptionId;
  String? _pendingPaymentMethod;
  String? _pendingCashfreeOrderId;
  bool _isDisposed = false;

  void dispose() {
    _isDisposed = true;
    _razorpay.clear();
  }

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

    final data = Map<String, dynamic>.from(response['data'] ?? {});
    final requiresPayment = data['requires_payment'] == true;
    final subscription = Map<String, dynamic>.from(data['subscription'] ?? {});
    _pendingSubscriptionId = int.tryParse('${subscription['id'] ?? ''}');

    if (!requiresPayment) {
      // Free trial -- already active on the backend, nothing to pay.
      onAuthorized();
      return;
    }

    if (_pendingSubscriptionId == null) {
      throw Exception('Membership session missing from server');
    }
    _pendingPaymentMethod = paymentMethod;

    final checkout = Map<String, dynamic>.from(data['checkout'] ?? {});
    final branding = await AppBrandingService.instance.loadBranding();
    final paymentColor = branding.primaryColorHex.trim().startsWith('#')
        ? branding.primaryColorHex.trim()
        : '#${branding.primaryColorHex.trim()}';

    if (paymentMethod == 'cashfree') {
      await _processCashfreePayment(checkout);
      return;
    }

    _razorpay.open({
      'key': checkout['key'],
      'amount': checkout['amount'],
      'currency': checkout['currency'] ?? 'INR',
      'name': branding.displayName,
      'description': 'Gold membership',
      'order_id': checkout['order_id'],
      'prefill': {
        'contact': user?.phone ?? '',
        'email': user?.email ?? '',
      },
      'theme': {'color': paymentColor},
    });
  }

  Future<void> _processCashfreePayment(Map<String, dynamic> checkout) async {
    final orderId = checkout['order_id']?.toString();
    final paymentSessionId = checkout['payment_session_id']?.toString();
    if (orderId == null ||
        orderId.isEmpty ||
        paymentSessionId == null ||
        paymentSessionId.isEmpty) {
      throw Exception('Cashfree payment session missing from server');
    }

    _pendingCashfreeOrderId = orderId;
    final environment = checkout['environment']?.toString().toLowerCase() == 'sandbox'
        ? CFEnvironment.SANDBOX
        : CFEnvironment.PRODUCTION;

    try {
      final session = CFSessionBuilder()
          .setEnvironment(environment)
          .setOrderId(orderId)
          .setPaymentSessionId(paymentSessionId)
          .build();
      final payment = CFWebCheckoutPaymentBuilder().setSession(session).build();
      _cashfree.doPayment(payment);
    } on CFException catch (e) {
      throw Exception(e.message);
    }
  }

  Future<void> _handleRazorpaySuccess(PaymentSuccessResponse response) async {
    await _verify({
      'payment_method': 'razorpay',
      'payment_id': response.paymentId,
      'razorpay_order_id': response.orderId,
      'razorpay_signature': response.signature,
    });
  }

  void _handleRazorpayError(PaymentFailureResponse response) {
    onFailure(_normalizeErrorMessage(response.message, fallback: 'Payment cancelled'));
  }

  Future<void> _handleCashfreeSuccess(String orderId) async {
    await _verify({
      'payment_method': 'cashfree',
      'payment_id': _pendingCashfreeOrderId ?? orderId,
    });
  }

  void _handleCashfreeError(CFErrorResponse error, String orderId) {
    onFailure(_normalizeErrorMessage(error.getMessage(), fallback: 'Payment cancelled'));
  }

  Future<void> _verify(Map<String, dynamic> payload) async {
    final subscriptionId = _pendingSubscriptionId;
    if (subscriptionId == null || _pendingPaymentMethod != payload['payment_method']) {
      onFailure('Membership checkout session expired');
      return;
    }

    try {
      final response = await _api.post(ApiConstants.goldVerify, data: {
        'subscription_id': subscriptionId,
        ...payload,
      });

      if (response['success'] == true) {
        if (!_isDisposed) onAuthorized();
        return;
      }

      onFailure(_normalizeErrorMessage(
        response['message']?.toString(),
        fallback: 'Membership payment verification failed',
      ));
    } catch (e) {
      onFailure(_normalizeErrorMessage(e.toString(), fallback: 'Membership payment verification failed'));
    }
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
