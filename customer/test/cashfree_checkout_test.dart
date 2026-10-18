import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:food_delivery_customer/config/api_constants.dart';
import 'package:food_delivery_customer/services/api_service.dart';
import 'package:food_delivery_customer/services/flexible_order_payment_service.dart';

class _CheckoutApi extends Fake implements ApiService {
  final verifications = <Map<String, dynamic>>[];
  bool verified = true;

  @override
  Future<dynamic> post(String endpoint,
      {dynamic data,
      Map<String, dynamic>? queryParams,
      bool includeAuth = true,
      bool coalesce = false,
      Duration reuseFor = Duration.zero}) async {
    if (endpoint == ApiConstants.createCheckoutPayment) {
      return {
        'success': true,
        'data': {
          'gateway': 'cashfree',
          'order_id': 'cf_test_order',
          'payment_session_id': 'session_test',
          'checkout_payment_token': 'checkout_test',
          'environment': 'sandbox',
        },
      };
    }
    if (endpoint == ApiConstants.verifyCheckoutPayment) {
      verifications.add(Map<String, dynamic>.from(data as Map));
      return {
        'success': verified,
        'message': 'Payment not confirmed',
        'data': {
          'order': {'id': 42},
        },
      };
    }
    throw StateError('Unexpected endpoint: $endpoint');
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('flutter_cashfree_pg_sdk');
  final messenger =
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
  late _CheckoutApi api;
  late FlexibleOrderPaymentService service;
  late Completer<MethodCall> launched;
  late Completer<String> nativeResult;

  setUp(() {
    debugDefaultTargetPlatformOverride = TargetPlatform.android;
    api = _CheckoutApi();
    launched = Completer<MethodCall>();
    nativeResult = Completer<String>();
    messenger.setMockMethodCallHandler(channel, (call) async {
      if (call.method == 'response') return null;
      launched.complete(call);
      return nativeResult.future;
    });
    messenger.setMockMethodCallHandler(
        const MethodChannel('razorpay_flutter'), (_) async => null);
    service = FlexibleOrderPaymentService(api: api);
  });

  tearDown(() {
    service.dispose();
    CFPaymentGatewayService.verifyPayment = null;
    CFPaymentGatewayService.onError = null;
    messenger.setMockMethodCallHandler(channel, null);
    messenger.setMockMethodCallHandler(
        const MethodChannel('razorpay_flutter'), null);
    debugDefaultTargetPlatformOverride = null;
  });

  void returnForVerification() => nativeResult.complete(jsonEncode({
        'status': 'success',
        'data': {'order_id': 'cf_test_order'},
      }));

  // Note: unlike Swado's customer app, this app's _payCashfree does not
  // target a specific installed UPI app (always drop-checkout via
  // doPayment) -- that's a pre-existing, separate feature gap, not part of
  // this fix, so this test only covers the generic UPI wait-for-SDK path.
  test('Android UPI checkout waits for SDK and server', () async {
    var completed = false;
    final checkout = service.payForCheckout(
      orderData: const {},
      gateway: 'cashfree',
    ).then((order) {
      completed = true;
      return order;
    });
    final call = await launched.future;
    expect(call.method, 'doPayment');
    expect(call.arguments['session']['order_id'], 'cf_test_order');
    expect(completed, isFalse);
    expect(api.verifications, isEmpty);

    returnForVerification();
    expect((await checkout)?.id, 42);
    expect(api.verifications.single['payment_id'], 'cf_test_order');
    expect(
        api.verifications.single['checkout_payment_token'], 'checkout_test');
  });

  test(
      'UPI cancellation still asks the server to verify, and returns the '
      'order if the bank actually confirmed the payment', () async {
    // A UPI intent hand-off frequently reaches the SDK as an error/cancel
    // even when the bank later confirms the payment -- the fix is to never
    // trust that callback alone and always ask the server (which re-checks
    // Cashfree's own order status), exactly like the web checkout does.
    final checkout = service.payForCheckout(
      orderData: const {},
      gateway: 'cashfree',
    );
    await launched.future;
    nativeResult.complete(jsonEncode({
      'status': 'failed',
      'data': {
        'order_id': 'cf_test_order',
        'status': 'FAILED',
        'message': 'Payment cancelled',
        'code': 'payment_cancelled',
        'type': 'request_failed',
      },
    }));
    expect((await checkout)?.id, 42);
    expect(api.verifications.single['payment_id'], 'cf_test_order');
  });

  test(
      'UPI cancellation throws when the server also confirms the payment '
      'never completed', () async {
    api.verified = false;
    final checkout = service.payForCheckout(
      orderData: const {},
      gateway: 'cashfree',
    );
    final assertion = expectLater(checkout, throwsStateError);
    await launched.future;
    nativeResult.complete(jsonEncode({
      'status': 'failed',
      'data': {
        'order_id': 'cf_test_order',
        'status': 'FAILED',
        'message': 'Payment cancelled',
        'code': 'payment_cancelled',
        'type': 'request_failed',
      },
    }));
    await assertion;
    expect(api.verifications, hasLength(1));
  });

  test('SDK verification callback alone cannot mark checkout paid', () async {
    api.verified = false;
    final checkout = service.payForCheckout(
      orderData: const {},
      gateway: 'cashfree',
    );
    final assertion = expectLater(checkout, throwsStateError);
    await launched.future;
    returnForVerification();
    await assertion;
    expect(api.verifications, hasLength(1));
  });

  for (final platform in [TargetPlatform.android, TargetPlatform.iOS]) {
    test('$platform card checkout keeps the existing SDK flow', () async {
      debugDefaultTargetPlatformOverride = platform;
      final checkout = service.payForCheckout(
        orderData: const {},
        gateway: 'cashfree',
        paymentMode: 'card',
      );
      expect((await launched.future).method, 'doPayment');
      returnForVerification();
      expect((await checkout)?.id, 42);
    });
  }

  test('iOS UPI keeps the existing SDK flow', () async {
    debugDefaultTargetPlatformOverride = TargetPlatform.iOS;
    final checkout = service.payForCheckout(
      orderData: const {},
      gateway: 'cashfree',
    );
    expect((await launched.future).method, 'doPayment');
    returnForVerification();
    expect((await checkout)?.id, 42);
  });
}
