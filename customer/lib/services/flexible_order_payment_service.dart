import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../config/api_constants.dart';
import '../config/app_config.dart';
import 'api_service.dart';

class FlexibleOrderPaymentService {
  FlexibleOrderPaymentService({ApiService? api}) : _api = api ?? ApiService() {
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _onRazorpaySuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _onRazorpayError);
    _cashfree.setCallback(_onCashfreeSuccess, _onCashfreeError);
  }

  final ApiService _api;
  final Razorpay _razorpay = Razorpay();
  final CFPaymentGatewayService _cashfree = CFPaymentGatewayService();
  Completer<_GatewayPaymentResult>? _completion;
  String? _pendingCashfreeOrderId;

  Future<bool> pay({
    required int orderId,
    required String gateway,
    bool cancelOrderOnFailure = true,
    String? customerName,
    String? customerEmail,
    String? customerPhone,
  }) async {
    _completion = Completer<_GatewayPaymentResult>();
    final response = await _api.post(
      ApiConstants.orderPay(orderId),
      data: {'gateway': gateway},
    );
    if (response['success'] != true) {
      _completion = null;
      throw StateError(
          response['message']?.toString() ?? 'Payment could not start.');
    }

    final data = Map<String, dynamic>.from(response['data'] ?? const {});
    final resolved = data['gateway']?.toString().toLowerCase() ?? gateway;
    var gatewaySucceeded = false;
    try {
      if (resolved == 'razorpay') {
        _razorpay.open(_razorpayOptions(
          data,
          customerName: customerName,
          customerEmail: customerEmail,
          customerPhone: customerPhone,
        ));
      } else if (resolved == 'stripe') {
        await _payStripe(data);
      } else if (resolved == 'cashfree') {
        _payCashfree(data);
      } else {
        throw StateError('Unsupported payment gateway: $resolved');
      }

      final result = await _completion!.future;
      if (!result.success) {
        if (cancelOrderOnFailure) {
          await _cancelPendingOrder(orderId);
        }
        return false;
      }

      gatewaySucceeded = true;
      return _verifyPayment(
        orderId: orderId,
        paymentMethod: resolved,
        data: result.data,
      );
    } catch (_) {
      if (!gatewaySucceeded && cancelOrderOnFailure) {
        await _cancelPendingOrder(orderId);
      }
      rethrow;
    } finally {
      _completion = null;
      _pendingCashfreeOrderId = null;
    }
  }

  Future<Map<String, dynamic>?> payForCheckout({
    required Map<String, dynamic> orderData,
    required String gateway,
  }) async {
    _completion = Completer<_GatewayPaymentResult>();
    final response = await _api.post(
      ApiConstants.createCheckoutPayment,
      data: {
        ...orderData,
        'payment_method': gateway,
      },
    );
    if (response['success'] != true) {
      _completion = null;
      throw StateError(
          response['message']?.toString() ?? 'Payment could not start.');
    }

    final data = Map<String, dynamic>.from(response['data'] ?? const {});
    final resolved = data['gateway']?.toString().toLowerCase() ??
        data['payment_method']?.toString().toLowerCase() ??
        gateway;
    try {
      if (resolved == 'razorpay') {
        _razorpay.open(_razorpayOptions(
          data,
          customerName: orderData['customer_name']?.toString(),
          customerEmail: orderData['customer_email']?.toString() ??
              orderData['email']?.toString(),
          customerPhone: orderData['customer_phone']?.toString() ??
              orderData['phone']?.toString() ??
              orderData['contact']?.toString(),
        ));
      } else if (resolved == 'stripe') {
        await _payStripe(data);
      } else if (resolved == 'cashfree') {
        _payCashfree(data);
      } else {
        throw StateError('Unsupported payment gateway: $resolved');
      }

      final result = await _completion!.future;
      if (!result.success) return null;

      return {
        'payment_method': resolved,
        ...result.data,
      };
    } finally {
      _completion = null;
      _pendingCashfreeOrderId = null;
    }
  }

  Future<Map<String, dynamic>?> paymentStatus(int orderId) async {
    final response = await _api.get(ApiConstants.orderPaymentStatus(orderId));
    if (response['success'] == true && response['data'] is Map) {
      return Map<String, dynamic>.from(response['data']);
    }
    return null;
  }

  Map<String, dynamic> _razorpayOptions(
    Map<String, dynamic> data, {
    String? customerName,
    String? customerEmail,
    String? customerPhone,
  }) {
    final options = <String, dynamic>{
      'key': data['key'],
      'amount': data['amount'] ?? data['amount_minor'],
      'currency': data['currency'] ?? 'INR',
      'name': AppConfig.appName,
      'description': 'Order payment',
      'order_id': data['order_id'],
    };

    final prefill = <String, dynamic>{};
    final gatewayPrefill = data['prefill'];
    if (gatewayPrefill is Map) {
      prefill.addAll(Map<String, dynamic>.from(gatewayPrefill));
    }

    void putIfPresent(String key, String? value) {
      final trimmed = value?.trim();
      if (trimmed != null && trimmed.isNotEmpty) {
        prefill[key] = trimmed;
      }
    }

    putIfPresent('name', customerName ?? data['customer_name']?.toString());
    putIfPresent(
      'email',
      customerEmail ??
          data['customer_email']?.toString() ??
          data['email']?.toString(),
    );
    final contact = _razorpayContact(
      customerPhone ??
          data['customer_phone']?.toString() ??
          data['phone']?.toString() ??
          data['contact']?.toString() ??
          prefill['contact']?.toString(),
    );
    if (contact != null) {
      prefill['contact'] = contact;
      final readonly = <String, dynamic>{};
      final gatewayReadonly = data['readonly'];
      if (gatewayReadonly is Map) {
        readonly.addAll(Map<String, dynamic>.from(gatewayReadonly));
      }
      readonly['contact'] = true;
      options['readonly'] = readonly;
    }

    if (prefill.isNotEmpty) {
      options['prefill'] = prefill;
    }
    return options;
  }

  String? _razorpayContact(String? value) {
    final raw = value?.trim();
    if (raw == null || raw.isEmpty) return null;
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 10) return null;
    return digits.substring(digits.length - 10);
  }

  Future<void> _payStripe(Map<String, dynamic> data) async {
    final secret = data['client_secret']?.toString();
    if (secret == null || secret.isEmpty) {
      throw StateError('Stripe payment session is missing.');
    }
    final key = data['publishable_key']?.toString();
    if (key != null && key.isNotEmpty) {
      Stripe.publishableKey = key;
      await Stripe.instance.applySettings();
    }
    await Stripe.instance.initPaymentSheet(
      paymentSheetParameters: SetupPaymentSheetParameters(
        paymentIntentClientSecret: secret,
        merchantDisplayName: AppConfig.appName,
        style: ThemeMode.system,
        googlePay: const PaymentSheetGooglePay(merchantCountryCode: 'IN'),
      ),
    );
    await Stripe.instance.presentPaymentSheet();
    final paymentIntentId = _paymentIntentIdFromClientSecret(secret);
    if (paymentIntentId == null || paymentIntentId.isEmpty) {
      _complete(_GatewayPaymentResult.failure());
      return;
    }
    _complete(_GatewayPaymentResult.success({
      'payment_id': paymentIntentId,
      'stripe_payment_intent_id': paymentIntentId,
    }));
  }

  void _payCashfree(Map<String, dynamic> data) {
    final gatewayOrderId = data['order_id']?.toString();
    final sessionId = data['payment_session_id']?.toString();
    if (gatewayOrderId == null ||
        gatewayOrderId.isEmpty ||
        sessionId == null ||
        sessionId.isEmpty) {
      throw StateError('Cashfree payment session is missing.');
    }
    _pendingCashfreeOrderId = gatewayOrderId;
    final environment =
        data['environment']?.toString().toLowerCase() == 'sandbox'
            ? CFEnvironment.SANDBOX
            : CFEnvironment.PRODUCTION;
    final session = CFSessionBuilder()
        .setEnvironment(environment)
        .setOrderId(gatewayOrderId)
        .setPaymentSessionId(sessionId)
        .build();
    _cashfree
        .doPayment(CFWebCheckoutPaymentBuilder().setSession(session).build());
  }

  void _onRazorpaySuccess(PaymentSuccessResponse response) {
    _complete(_GatewayPaymentResult.success({
      'payment_id': response.paymentId,
      'razorpay_order_id': response.orderId,
      'razorpay_signature': response.signature,
    }));
  }

  void _onRazorpayError(PaymentFailureResponse _) =>
      _complete(_GatewayPaymentResult.failure());

  void _onCashfreeSuccess(String orderId) {
    _complete(_GatewayPaymentResult.success({
      'payment_id': _pendingCashfreeOrderId ?? orderId,
    }));
  }

  void _onCashfreeError(CFErrorResponse _, String __) =>
      _complete(_GatewayPaymentResult.failure());

  void _complete(_GatewayPaymentResult value) {
    if (!(_completion?.isCompleted ?? true)) _completion!.complete(value);
  }

  Future<bool> _verifyPayment({
    required int orderId,
    required String paymentMethod,
    required Map<String, dynamic> data,
  }) async {
    final payload = <String, dynamic>{
      'order_id': orderId,
      'payment_method': paymentMethod,
      ...data,
    };

    final response = await _api.post(
      ApiConstants.verifyPayment,
      data: payload,
    );

    if (response['success'] == true) return true;
    throw StateError(
      response['message']?.toString() ?? 'Payment verification failed.',
    );
  }

  Future<void> _cancelPendingOrder(int orderId) async {
    try {
      await _api.post(
        ApiConstants.cancelPayment,
        data: {
          'order_id': orderId,
          'reason': 'Payment cancelled before confirmation',
        },
      );
    } catch (_) {
      // The order may already be closed or verified by webhook.
    }
  }

  String? _paymentIntentIdFromClientSecret(String clientSecret) {
    final separator = clientSecret.indexOf('_secret_');
    if (separator <= 0) return null;
    return clientSecret.substring(0, separator);
  }

  void dispose() {
    _razorpay.clear();
  }
}

class _GatewayPaymentResult {
  const _GatewayPaymentResult._({
    required this.success,
    required this.data,
  });

  factory _GatewayPaymentResult.success(Map<String, dynamic> data) {
    return _GatewayPaymentResult._(
      success: true,
      data: data,
    );
  }

  factory _GatewayPaymentResult.failure() {
    return const _GatewayPaymentResult._(
      success: false,
      data: <String, dynamic>{},
    );
  }

  final bool success;
  final Map<String, dynamic> data;
}
