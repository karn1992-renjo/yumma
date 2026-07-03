import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfexceptions.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../config/api_constants.dart';
import '../models/user.dart';
import 'api_service.dart';

class WalletRechargePaymentService {
  WalletRechargePaymentService({
    required this.onSuccess,
    required this.onFailure,
  }) {
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleRazorpaySuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleRazorpayError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});
    _cashfree.setCallback(_handleCashfreeSuccess, _handleCashfreeError);
  }

  final Future<void> Function() onSuccess;
  final void Function(String message) onFailure;
  final ApiService _api = ApiService();
  final Razorpay _razorpay = Razorpay();
  final CFPaymentGatewayService _cashfree = CFPaymentGatewayService();

  int? _pendingRechargeId;
  String? _pendingCashfreeOrderId;
  String? _pendingRazorpayOrderId;
  bool _isDisposed = false;

  void dispose() {
    _isDisposed = true;
    _razorpay.clear();
  }

  Future<void> start({
    required double amount,
    required User? user,
  }) async {
    final provider = user?.paymentGatewayProvider ?? 'razorpay';

    final response = await _api.post(ApiConstants.walletTopUp, data: {
      'amount': amount,
      'payment_method': provider,
      'description': 'Wallet recharge',
    });

    if (response['success'] != true) {
      throw Exception(response['message'] ?? 'Unable to start wallet recharge');
    }

    final data = Map<String, dynamic>.from(response['data'] ?? {});
    final method =
        (data['payment_method'] ?? provider).toString().toLowerCase();
    _pendingRechargeId = int.tryParse('${data['wallet_recharge_id'] ?? ''}');

    if (_pendingRechargeId == null) {
      throw Exception('Wallet recharge session missing from server');
    }

    if (method == 'razorpay') {
      _pendingRazorpayOrderId = data['order_id']?.toString();
      _razorpay.open({
        'key': data['key'],
        'amount': data['amount'],
        'currency': data['currency'] ?? 'INR',
        'name': 'Yumma',
        'description': 'Wallet recharge',
        'order_id': data['order_id'],
        'prefill': {
          'contact': user?.phone ?? '',
          'email': user?.email ?? '',
        },
        'theme': {'color': '#FC8019'},
      });
      return;
    }

    if (method == 'stripe') {
      await _processStripePayment(data);
      return;
    }

    if (method == 'cashfree') {
      await _processCashfreePayment(data);
      return;
    }

    throw Exception('Unsupported payment method: $method');
  }

  Future<void> _processStripePayment(Map<String, dynamic> data) async {
    final clientSecret = data['client_secret']?.toString();
    final publishableKey = data['publishable_key']?.toString();
    if (clientSecret == null || clientSecret.isEmpty) {
      throw Exception('No Stripe client secret from server');
    }
    if (publishableKey != null && publishableKey.isNotEmpty) {
      Stripe.publishableKey = publishableKey;
      await Stripe.instance.applySettings();
    }

    await Stripe.instance.initPaymentSheet(
      paymentSheetParameters: SetupPaymentSheetParameters(
        paymentIntentClientSecret: clientSecret,
        merchantDisplayName: 'Yumma',
        style: ThemeMode.system,
        googlePay: const PaymentSheetGooglePay(merchantCountryCode: 'IN'),
        applePay: const PaymentSheetApplePay(merchantCountryCode: 'IN'),
      ),
    );

    try {
      await Stripe.instance.presentPaymentSheet();
    } on StripeException catch (e) {
      _clearPendingRecharge();
      if (!_isDisposed) {
        final message = e.error.localizedMessage ?? 'Payment cancelled';
        onFailure(_normalizeErrorMessage(message, fallback: 'Payment cancelled'));
      }
      return;
    } catch (e) {
      _clearPendingRecharge();
      if (!_isDisposed) {
        onFailure(_normalizeErrorMessage(e.toString(), fallback: 'Payment cancelled'));
      }
      return;
    }

    final paymentIntentId = clientSecret.split('_secret_')[0];
    await _verify({
      'payment_method': 'stripe',
      'payment_id': paymentIntentId,
      'stripe_payment_intent_id': paymentIntentId,
    });
  }

  Future<void> _processCashfreePayment(Map<String, dynamic> data) async {
    final orderId = data['order_id']?.toString();
    final paymentSessionId = data['payment_session_id']?.toString();
    if (orderId == null ||
        orderId.isEmpty ||
        paymentSessionId == null ||
        paymentSessionId.isEmpty) {
      throw Exception('Cashfree payment session missing from server');
    }

    _pendingCashfreeOrderId = orderId;
    final environment =
        data['environment']?.toString().toLowerCase() == 'sandbox'
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
    if (_isDisposed) return;
    final paymentId = response.paymentId;
    final orderId = response.orderId ?? _pendingRazorpayOrderId;
    final signature = response.signature;
    if (paymentId == null ||
        paymentId.isEmpty ||
        orderId == null ||
        orderId.isEmpty ||
        signature == null ||
        signature.isEmpty) {
      _clearPendingRecharge();
      if (!_isDisposed) {
        onFailure('Payment confirmation was incomplete. Please try again.');
      }
      return;
    }

    await _verify({
      'payment_method': 'razorpay',
      'payment_id': paymentId,
      'razorpay_order_id': orderId,
      'razorpay_signature': signature,
    });
  }

  void _handleRazorpayError(PaymentFailureResponse response) {
    _clearPendingRecharge();
    if (!_isDisposed) {
      onFailure(_normalizeErrorMessage(response.message, fallback: 'Payment cancelled or failed'));
    }
  }

  Future<void> _handleCashfreeSuccess(String orderId) async {
    if (_isDisposed) return;
    await _verify({
      'payment_method': 'cashfree',
      'payment_id': _pendingCashfreeOrderId ?? orderId,
    });
  }

  void _handleCashfreeError(CFErrorResponse error, String orderId) {
    _clearPendingRecharge();
    if (!_isDisposed) {
      onFailure(_normalizeErrorMessage(error.getMessage(), fallback: 'Cashfree payment failed'));
    }
  }

  String _normalizeErrorMessage(String? message, {required String fallback}) {
    final trimmed = message?.trim();
    if (trimmed == null || trimmed.isEmpty) return fallback;
    final normalized = trimmed.toLowerCase();
    if (normalized == 'null' || normalized == 'undefined') return fallback;
    return trimmed;
  }

  Future<void> _verify(Map<String, dynamic> payload) async {
    if (_isDisposed) return;
    final rechargeId = _pendingRechargeId;
    if (rechargeId == null) {
      if (!_isDisposed) {
        onFailure('Wallet recharge session expired');
      }
      return;
    }

    try {
      final response = await _api.post(ApiConstants.walletTopUpVerify, data: {
        'wallet_recharge_id': rechargeId,
        ...payload,
      });

      if (_isDisposed) return;
      if (response['success'] == true) {
        _clearPendingRecharge();
        if (!_isDisposed) await onSuccess();
        return;
      }

      _clearPendingRecharge();
      if (!_isDisposed) {
        onFailure(response['message'] ?? 'Wallet recharge verification failed');
      }
    } catch (e) {
      _clearPendingRecharge();
      if (!_isDisposed) {
        onFailure(_normalizeErrorMessage(e.toString(), fallback: 'Wallet recharge verification failed'));
      }
    }
  }

  void _clearPendingRecharge() {
    _pendingRechargeId = null;
    _pendingCashfreeOrderId = null;
    _pendingRazorpayOrderId = null;
  }
}
