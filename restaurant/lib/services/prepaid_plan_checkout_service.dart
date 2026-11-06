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

/// Buys (or upgrades) a pay-as-you-go plan such as the stories-only plan: one ordinary payment per period,
/// confirmed with the server straight after the payment SDK reports success. Nothing is charged again later.
class PrepaidPlanCheckoutService {
  PrepaidPlanCheckoutService({
    required this.onSuccess,
    required this.onFailure,
  }) {
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleRazorpaySuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});
    _cashfree.setCallback(_handleCashfreeSuccess, _handleCashfreeError);
  }

  final Future<void> Function() onSuccess;
  final void Function(String message) onFailure;
  final ApiService _api = ApiService();
  final Razorpay _razorpay = Razorpay();
  final CFPaymentGatewayService _cashfree = CFPaymentGatewayService();

  int? _pendingSubscriptionId;
  String? _pendingCashfreeOrderId;
  bool _isDisposed = false;

  void dispose() {
    _isDisposed = true;
    _razorpay.clear();
  }

  /// [upgrade] true moves the restaurant from its current plan to this higher one.
  Future<void> start({
    required int planId,
    required String paymentMethod,
    required User? user,
    bool upgrade = false,
  }) async {
    final response = await _api.post(
      upgrade
          ? ApiConstants.restaurantSubscriptionUpgrade
          : ApiConstants.restaurantSubscriptionSubscribe,
      data: {'plan_id': planId, 'payment_method': paymentMethod},
    );
    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Unable to start the payment');
    }

    final data = Map<String, dynamic>.from(response['data'] ?? {});
    final checkout = Map<String, dynamic>.from(data['checkout'] ?? {});
    _pendingSubscriptionId =
        int.tryParse('${(data['subscription'] as Map?)?['id'] ?? ''}');
    if (_pendingSubscriptionId == null) {
      throw Exception('Payment session missing from server');
    }

    final method =
        (checkout['payment_method'] ?? paymentMethod).toString().toLowerCase();
    if (method == 'razorpay') {
      final branding = await AppBrandingService.instance.loadBranding();
      final color = branding.primaryColorHex.trim().startsWith('#')
          ? branding.primaryColorHex.trim()
          : '#${branding.primaryColorHex.trim()}';
      _razorpay.open({
        'key': checkout['key'],
        'amount': checkout['amount'],
        'currency': checkout['currency'] ?? 'INR',
        'name': branding.displayName,
        'description': upgrade ? 'Plan upgrade' : 'Stories plan',
        'order_id': checkout['order_id'],
        'prefill': {'contact': user?.phone ?? '', 'email': user?.email ?? ''},
        'theme': {'color': color},
      });
      return;
    }

    if (method == 'cashfree') {
      final orderId = checkout['order_id']?.toString();
      final sessionId = checkout['payment_session_id']?.toString();
      if (orderId == null || orderId.isEmpty || sessionId == null || sessionId.isEmpty) {
        throw Exception('Cashfree payment session missing from server');
      }
      _pendingCashfreeOrderId = orderId;
      try {
        final session = CFSessionBuilder()
            .setEnvironment(checkout['environment']?.toString().toLowerCase() == 'sandbox'
                ? CFEnvironment.SANDBOX
                : CFEnvironment.PRODUCTION)
            .setOrderId(orderId)
            .setPaymentSessionId(sessionId)
            .build();
        _cashfree.doPayment(CFWebCheckoutPaymentBuilder().setSession(session).build());
      } on CFException catch (e) {
        throw Exception(e.message);
      }
      return;
    }

    throw Exception('Unsupported payment method: $method');
  }

  Future<void> _handleRazorpaySuccess(PaymentSuccessResponse response) => _verify({
        'payment_method': 'razorpay',
        'payment_id': response.paymentId,
        'razorpay_order_id': response.orderId,
        'razorpay_signature': response.signature,
      });

  Future<void> _handleCashfreeSuccess(String orderId) => _verify({
        'payment_method': 'cashfree',
        'payment_id': _pendingCashfreeOrderId ?? orderId,
      });

  void _handleError(PaymentFailureResponse response) =>
      onFailure(_clean(response.message, 'Payment cancelled'));

  void _handleCashfreeError(CFErrorResponse error, String orderId) =>
      onFailure(_clean(error.getMessage(), 'Payment cancelled'));

  Future<void> _verify(Map<String, dynamic> payload) async {
    final id = _pendingSubscriptionId;
    if (id == null) {
      onFailure('Payment session expired');
      return;
    }
    try {
      final response = await _api.post(
        ApiConstants.restaurantSubscriptionVerify,
        data: {'subscription_id': id, ...payload},
      );
      if (response['success'] == true) {
        if (!_isDisposed) await onSuccess();
        return;
      }
      onFailure(_clean(response['message']?.toString(), 'Payment verification failed'));
    } catch (e) {
      onFailure(_clean(e.toString(), 'Payment verification failed'));
    }
  }

  String _clean(String? message, String fallback) {
    final trimmed = message?.trim();
    if (trimmed == null || trimmed.isEmpty) return fallback;
    final lower = trimmed.toLowerCase();
    if (lower == 'null' || lower == 'undefined' || lower == 'exception: null' || lower == 'exception: undefined') {
      return fallback;
    }
    return trimmed.startsWith('Exception: ') ? trimmed.substring('Exception: '.length) : trimmed;
  }
}
