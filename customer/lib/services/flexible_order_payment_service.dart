// ignore_for_file: deprecated_member_use

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfdropcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfupi.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfupipayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentcomponents/cfpaymentcomponent.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfupi/cfupiutils.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../config/api_constants.dart';
import '../config/app_config.dart';
import '../models/order.dart';
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

  Future<bool> pay({
    required int orderId,
    String? gateway,
    String? provider,
    bool cancelOrderOnFailure = true,
    String paymentMode = 'upi',
    String? cashfreeUpiAppId,
    String? customerName,
    String? customerEmail,
    String? customerPhone,
  }) async {
    final selectedGateway = (gateway ?? provider ?? '').trim().toLowerCase();
    if (selectedGateway.isEmpty) {
      throw StateError('Payment gateway is required.');
    }

    _completion = Completer<_GatewayPaymentResult>();
    _paymentTrace('orderPay.start orderId=$orderId selectedGateway=$selectedGateway mode=$paymentMode cashfreeUpiApp=${cashfreeUpiAppId ?? '-'}');
    final response = await _api.post(
      ApiConstants.orderPay(orderId),
      data: {
        'gateway': selectedGateway,
        'payment_gateway': selectedGateway,
        'payment_gateway_provider': selectedGateway,
        if (customerName?.trim().isNotEmpty == true)
          'customer_name': customerName!.trim(),
        if (customerEmail?.trim().isNotEmpty == true)
          'customer_email': customerEmail!.trim(),
        if (customerPhone?.trim().isNotEmpty == true)
          'customer_phone': customerPhone!.trim(),
      },
    );
    _paymentTrace('orderPay.response success=${response['success']} message=${response['message']} dataKeys=${_mapKeys(response['data'])}');
    _paymentTrace('checkoutPay.response success=${response['success']} message=${response['message']} dataKeys=${_mapKeys(response['data'])}');
    if (response['success'] != true) {
      throw StateError(
          response['message']?.toString() ?? 'Payment could not start.');
    }

    final data = Map<String, dynamic>.from(response['data'] ?? const {});
    final resolved =
        data['gateway']?.toString().toLowerCase() ?? selectedGateway;
    _paymentTrace('orderPay.resolved selected=$selectedGateway resolved=$resolved responsePaymentMethod=${data['payment_method']} orderId=${data['order_id']} sessionPresent=${_firstNonEmptyString(data, const ['payment_session_id', 'paymentSessionId', 'payment_session_token', 'paymentSessionToken', 'order_token']) != null}');
    var gatewaySucceeded = false;
    try {
      if (resolved == 'razorpay') {
        _openRazorpayPayment(
          data,
          paymentMode: paymentMode,
          customerName: customerName,
          customerEmail: customerEmail,
          customerPhone: customerPhone,
        );
      } else if (resolved == 'stripe') {
        await _payStripe(data, paymentMode: paymentMode);
      } else if (resolved == 'cashfree') {
        _payCashfree(
          data,
          paymentMode: paymentMode,
          cashfreeUpiAppId: cashfreeUpiAppId,
        );
      } else {
        throw StateError('Unsupported payment gateway: $resolved');
      }

      final result = await _completion!.future;
      if (!result.success) {
        if (await _waitForServerConfirmation(orderId)) {
          return true;
        }
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
      if (!gatewaySucceeded && await _waitForServerConfirmation(orderId)) {
        return true;
      }
      if (!gatewaySucceeded && cancelOrderOnFailure) {
        await _cancelPendingOrder(orderId);
      }
      rethrow;
    } finally {
      _completion = null;
    }
  }

  Future<List<CashfreeUpiApp>> cashfreeUpiApps() async {
    try {
      _paymentTrace('cashfreeUpiApps.start');
      final apps = await CFUPIUtils().getUPIApps();
      if (apps == null) {
        _paymentTrace('cashfreeUpiApps.raw null result from SDK');
        return const [];
      }

      final parsed = apps
          .map(CashfreeUpiApp.fromSdkValue)
          .where((app) => app.id.isNotEmpty && app.displayName.isNotEmpty)
          .toList(growable: false);
      final appSummary = parsed
          .map((app) => '${app.id}:${app.displayName}')
          .join('|');
      _paymentTrace('cashfreeUpiApps.rawType=${apps.runtimeType} rawCount=${apps.length} parsedCount=${parsed.length} apps=$appSummary');
      for (final app in parsed) {
        _paymentTrace('cashfreeUpiApps.app id=${app.id} name=${app.displayName} iconPresent=${app.icon.isNotEmpty}');
      }
      return parsed;
    } catch (error, stackTrace) {
      _paymentTrace('cashfreeUpiApps.error $error');
      debugPrint('$stackTrace');
      return const [];
    }
  }

  Future<Map<String, dynamic>?> paymentStatus(int orderId) async {
    final response = await _api.get(ApiConstants.orderPaymentStatus(orderId));
    if (response['success'] == true && response['data'] is Map) {
      return Map<String, dynamic>.from(response['data']);
    }
    return null;
  }

  Future<bool> _waitForServerConfirmation(int orderId) async {
    for (var attempt = 0; attempt < 5; attempt++) {
      if (attempt > 0) {
        await Future<void>.delayed(const Duration(milliseconds: 900));
      }

      final status = await paymentStatus(orderId);
      final paymentStatusValue =
          status?['payment_status']?.toString().toLowerCase();
      final activeAttempt = status?['active_attempt'];
      final activeAttemptStatus = activeAttempt is Map
          ? activeAttempt['status']?.toString().toLowerCase()
          : null;

      if (paymentStatusValue == 'success' || activeAttemptStatus == 'success') {
        return true;
      }
    }

    return false;
  }

  void _openRazorpayPayment(
    Map<String, dynamic> data, {
    String? paymentMode,
    String? customerName,
    String? customerEmail,
    String? customerPhone,
  }) {
    final key = _firstNonEmptyString(data, const ['key', 'razorpay_key']);
    final orderId = _firstNonEmptyString(data, const [
      'order_id',
      'razorpay_order_id',
      'gateway_reference',
    ]);
    final amount = data['amount'] ?? data['amount_minor'];
    final amountMinor = amount is num
        ? amount.round()
        : int.tryParse(amount?.toString().trim() ?? '');
    _paymentTrace('razorpay.open mode=$paymentMode orderId=$orderId amount=$amountMinor keyPresent=${key != null}');
    if (key == null ||
        orderId == null ||
        amountMinor == null ||
        amountMinor <= 0) {
      throw StateError(
        data['message']?.toString() ??
            'Razorpay payment order is missing required details. Please try again.',
      );
    }

    final options = <String, dynamic>{
      'key': key,
      'amount': amountMinor,
      'currency': _firstNonEmptyString(data, const ['currency']) ?? 'INR',
      'name': AppConfig.appName,
      'description': 'Order payment',
      'order_id': orderId,
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

    final method = _razorpayMethodForMode(paymentMode);
    if (method != null) {
      options['config'] = {
        'display': {
          'sequence': [method],
          'preferences': {
            'show_default_blocks': false,
          },
        },
      };
    }

    _razorpay.open(options);
  }

  String? _razorpayContact(String? value) {
    final raw = value?.trim();
    if (raw == null || raw.isEmpty) return null;
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    if (digits.length < 10) return null;
    return digits.substring(digits.length - 10);
  }

  String? _razorpayMethodForMode(String? paymentMode) {
    return switch (paymentMode) {
      'upi' => 'upi',
      'card' => 'card',
      'wallet' => 'wallet',
      'netbanking' => 'netbanking',
      'paylater' => 'paylater',
      'emi' => 'emi',
      _ => null,
    };
  }

  String _paymentModeTitle(String paymentMode) {
    return switch (paymentMode) {
      'card' => 'Credit / Debit Card',
      'wallet' => 'Online Wallets',
      'netbanking' => 'Net Banking',
      'paylater' => 'Pay Later',
      'emi' => 'EMI',
      'google_pay' => 'Google Pay',
      _ => 'UPI',
    };
  }

  Future<void> _payStripe(
    Map<String, dynamic> data, {
    String? paymentMode,
  }) async {
    final secret = data['client_secret']?.toString();
    if (secret == null || secret.isEmpty) {
      throw StateError('Stripe payment session is missing.');
    }
    final key = data['publishable_key']?.toString();
    if (key != null && key.isNotEmpty) {
      Stripe.publishableKey = key;
      await Stripe.instance.applySettings();
    }
    if (paymentMode != null &&
        paymentMode != 'card' &&
        paymentMode != 'google_pay') {
      throw StateError('Stripe supports card and Google Pay in this app.');
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

  void _payCashfree(
    Map<String, dynamic> data, {
    required String paymentMode,
    String? cashfreeUpiAppId,
  }) {
    final gatewayOrderId = _firstNonEmptyString(data, const [
      'order_id',
      'cf_order_id',
      'cashfree_order_id',
      'gateway_reference',
    ]);
    final sessionId = _firstNonEmptyString(data, const [
      'payment_session_id',
      'paymentSessionId',
      'payment_session_token',
      'paymentSessionToken',
      'order_token',
    ]);
    _paymentTrace('cashfree.open mode=$paymentMode orderId=$gatewayOrderId sessionPresent=${sessionId != null} selectedUpiApp=${cashfreeUpiAppId ?? '-'} environment=${data['environment']}');
    if (gatewayOrderId == null || sessionId == null) {
      throw StateError(
        data['message']?.toString() ??
            'Cashfree payment session token is missing. Please try again.',
      );
    }
    final environment = data['environment']?.toString() == 'sandbox'
        ? CFEnvironment.SANDBOX
        : CFEnvironment.PRODUCTION;
    final session = CFSessionBuilder()
        .setEnvironment(environment)
        .setOrderId(gatewayOrderId)
        .setPaymentSessionId(sessionId)
        .build();
    if (paymentMode == 'upi') {
      final selectedUpiApp = cashfreeUpiAppId?.trim() ?? '';
      final upiBuilder = CFUPIBuilder();
      if (selectedUpiApp.isNotEmpty) {
        _paymentTrace('cashfree.upi channel=INTENT selectedUpiApp=$selectedUpiApp');
        upiBuilder.setChannel(CFUPIChannel.INTENT).setUPIID(selectedUpiApp);
      } else {
        _paymentTrace('cashfree.upi channel=INTENT_WITH_UI selectedUpiApp=none');
        upiBuilder.setChannel(CFUPIChannel.INTENT_WITH_UI);
      }
      final payment = CFUPIPaymentBuilder()
          .setSession(session)
          .setUPI(upiBuilder.build())
          .build();

      _cashfree.doPayment(payment);
      return;
    }

    final component = _cashfreePaymentComponentForMode(paymentMode);
    if (component == null) {
      throw StateError('Selected Cashfree payment method is not available.');
    }

    final payment = CFDropCheckoutPaymentBuilder()
        .setSession(session)
        .setPaymentComponent(component)
        .build();
    _cashfree.doPayment(payment);
  }

  CFPaymentComponent? _cashfreePaymentComponentForMode(String paymentMode) {
    final mode = switch (paymentMode) {
      'card' => CFPaymentModes.CARD,
      'wallet' => CFPaymentModes.WALLET,
      'netbanking' => CFPaymentModes.NETBANKING,
      'paylater' => CFPaymentModes.PAYLATER,
      'emi' => CFPaymentModes.EMI,
      _ => null,
    };
    if (mode == null) return null;
    return CFPaymentComponentBuilder().setComponents([mode]).build();
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
      'payment_id': orderId,
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
      'gateway': paymentMethod,
      'payment_gateway': paymentMethod,
      'payment_gateway_provider': paymentMethod,
      ...data,
    };

    _paymentTrace('verifyPayment.request orderId=$orderId paymentMethod=$paymentMethod keys=${payload.keys.join(',')}');
    final response = await _api.post(
      ApiConstants.verifyPayment,
      data: payload,
    );

    _paymentTrace('verifyPayment.response success=${response['success']} message=${response['message']}');
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

extension FlexibleOrderPaymentCheckout on FlexibleOrderPaymentService {
  Future<Order?> payForCheckout({
    required Map<String, dynamic> orderData,
    required String gateway,
    String paymentMode = 'upi',
    String? cashfreeUpiAppId,
  }) async {
    _completion = Completer<_GatewayPaymentResult>();
    final selectedGateway = gateway.trim().toLowerCase();
    final payload = <String, dynamic>{
      ...orderData,
      'payment_method': selectedGateway,
      'gateway': selectedGateway,
      'payment_gateway': selectedGateway,
      'payment_gateway_provider': selectedGateway,
    };
    _paymentTrace(
      'checkoutPay.start gateway=$selectedGateway mode=$paymentMode '
      'cashfreeUpiApp=${cashfreeUpiAppId ?? '-'} '
      'orderPaymentMethod=${orderData['payment_method']} '
      'items=${orderData['items'] is List ? (orderData['items'] as List).length : '-'}',
    );
    final response = await _api.post(
      ApiConstants.createCheckoutPayment,
      data: payload,
    );
    _paymentTrace(
      'checkoutPay.response success=${response['success']} '
      'message=${response['message']} dataKeys=${_mapKeys(response['data'])}',
    );
    if (response['success'] != true) {
      throw StateError(
        response['message']?.toString() ?? 'Payment could not start.',
      );
    }

    final data = Map<String, dynamic>.from(response['data'] ?? const {});
    final token = _firstNonEmptyString(data, const [
      'checkout_payment_token',
      'checkoutPaymentToken',
      'payment_token',
    ]);
    if (token == null) {
      throw StateError(
        response['message']?.toString() ??
            data['message']?.toString() ??
            'Checkout payment token is missing. Please try again.',
      );
    }

    final resolved = data['gateway']?.toString().toLowerCase() ?? selectedGateway;
    final sessionPresent = _firstNonEmptyString(data, const [
          'payment_session_id',
          'paymentSessionId',
          'payment_session_token',
          'paymentSessionToken',
          'order_token',
        ]) !=
        null;
    _paymentTrace(
      'checkoutPay.resolved selected=$selectedGateway resolved=$resolved '
      'responsePaymentMethod=${data['payment_method']} tokenPresent=${token != null} '
      'orderId=${data['order_id']} sessionPresent=$sessionPresent',
    );    try {
      if (resolved == 'razorpay') {
        _openRazorpayPayment(
          data,
          paymentMode: paymentMode,
          customerName: orderData['customer_name']?.toString(),
          customerEmail: orderData['customer_email']?.toString() ??
              orderData['email']?.toString(),
          customerPhone: orderData['customer_phone']?.toString() ??
              orderData['phone']?.toString() ??
              orderData['contact']?.toString(),
        );
      } else if (resolved == 'stripe') {
        await _payStripe(data, paymentMode: paymentMode);
      } else if (resolved == 'cashfree') {
        _payCashfree(
          data,
          paymentMode: paymentMode,
          cashfreeUpiAppId: cashfreeUpiAppId,
        );
      } else {
        throw StateError('Unsupported payment gateway: $resolved');
      }

      final result = await _completion!.future;
      if (!result.success) {
        return null;
      }

      final verifyResponse = await _api.post(
        ApiConstants.verifyCheckoutPayment,
        data: {
          'checkout_payment_token': token,
          'payment_method': resolved,
          'gateway': resolved,
          'payment_gateway': resolved,
          'payment_gateway_provider': resolved,
          ...result.data,
        },
      );

      _paymentTrace('checkoutVerify.response success=${verifyResponse['success']} message=${verifyResponse['message']} dataKeys=${_mapKeys(verifyResponse['data'])}');
      if (verifyResponse['success'] == true) {
        final data = verifyResponse['data'];
        final orderData = data is Map ? data['order'] : null;
        if (orderData is Map<String, dynamic>) {
          return Order.fromJson(orderData);
        }
        if (orderData is Map) {
          return Order.fromJson(Map<String, dynamic>.from(orderData));
        }
      }

      throw StateError(
        verifyResponse['message']?.toString() ??
            'Payment verified, but order creation failed.',
      );
    } finally {
      _completion = null;
    }
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

class CashfreeUpiApp {
  const CashfreeUpiApp({
    required this.id,
    required this.displayName,
    this.icon = '',
  });

  factory CashfreeUpiApp.fromJson(Map<String, dynamic> json) {
    return CashfreeUpiApp(
      id: _firstNonEmptyString(json, const [
            'id',
            'packageName',
            'package_name',
            'applicationId',
            'appId',
          ]) ??
          '',
      displayName: _firstNonEmptyString(json, const [
            'displayName',
            'display_name',
            'name',
            'applicationName',
            'appName',
          ]) ??
          '',
      icon: _firstNonEmptyString(json, const [
            'icon',
            'base64Icon',
            'base64_icon',
            'iconBase64',
            'logo',
          ]) ??
          '',
    );
  }

  factory CashfreeUpiApp.fromSdkValue(dynamic value) {
    if (value is Map<String, dynamic>) {
      return CashfreeUpiApp.fromJson(value);
    }
    if (value is Map) {
      return CashfreeUpiApp.fromJson(Map<String, dynamic>.from(value));
    }

    final text = value.toString();
    final id = _sdkField(text, const [
      'id',
      'packageName',
      'package_name',
      'applicationId',
      'appId',
    ]);
    final name = _sdkField(text, const [
      'displayName',
      'display_name',
      'name',
      'applicationName',
      'appName',
    ]);
    final icon = _sdkField(text, const [
      'icon',
      'base64Icon',
      'base64_icon',
      'iconBase64',
      'logo',
    ]);

    return CashfreeUpiApp(
      id: id ?? '',
      displayName: name ?? id ?? '',
      icon: icon ?? '',
    );
  }

  final String id;
  final String displayName;
  final String icon;
}

String? _firstNonEmptyString(Map<dynamic, dynamic> values, List<String> keys) {
  for (final key in keys) {
    final value = values[key];
    if (value == null) continue;
    final text = value.toString().trim();
    if (text.isNotEmpty) return text;
  }
  return null;
}

String? _sdkField(String source, List<String> keys) {
  for (final key in keys) {
    final match = RegExp('$key[:=] ?([^,)}]+)').firstMatch(source);
    final value = match?.group(1)?.trim();
    if (value != null && value.isNotEmpty && value != 'null') {
      return value;
    }
  }
  return null;
}

void _paymentTrace(String message) {
  debugPrint('[PaymentTrace] $message');
}

String _mapKeys(dynamic value) {
  if (value is Map) return value.keys.join(',');
  return value?.runtimeType.toString() ?? 'null';
}