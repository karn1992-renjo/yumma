import 'package:flutter/material.dart';
import 'package:flutter_cashfree_pg_sdk/api/cferrorresponse/cferrorresponse.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpayment/cfwebcheckoutpayment.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfpaymentgateway/cfpaymentgatewayservice.dart';
import 'package:flutter_cashfree_pg_sdk/api/cfsession/cfsession.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfenums.dart';
import 'package:flutter_cashfree_pg_sdk/utils/cfexceptions.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../../config/api_constants.dart';
import '../../models/dining_booking.dart';
import '../../models/user.dart';
import '../../providers/auth_provider.dart';
import '../../services/api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../../utils/currency_utils.dart';
import '../../widgets/customer/account_chrome.dart';
import 'dining_confirmation_screen.dart';

class DiningBookingCheckoutScreen extends StatefulWidget {
  const DiningBookingCheckoutScreen({
    super.key,
    required this.booking,
    required this.restaurantName,
  });

  final DiningBooking booking;
  final String restaurantName;

  @override
  State<DiningBookingCheckoutScreen> createState() =>
      _DiningBookingCheckoutScreenState();
}

class _DiningBookingCheckoutScreenState
    extends State<DiningBookingCheckoutScreen> {
  final ApiService _api = ApiService();
  final Razorpay _razorpay = Razorpay();
  final CFPaymentGatewayService _cashfree = CFPaymentGatewayService();

  bool _isPaying = false;
  String? _pendingCashfreeOrderId;

  @override
  void initState() {
    super.initState();
    _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handleRazorpaySuccess);
    _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handleRazorpayError);
    _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (_) {});
    _cashfree.setCallback(_handleCashfreeSuccess, _handleCashfreeError);
  }

  @override
  void dispose() {
    _razorpay.clear();
    super.dispose();
  }

  Future<void> _payNow() async {
    if (_isPaying) return;

    final user = context.read<AuthProvider>().currentUser;
    final provider = _effectiveGatewayProvider(user);
    if (provider == null) {
      _showStatus('Online payment is not available right now');
      return;
    }

    setState(() => _isPaying = true);

    try {
      final response = await _api.post(
        ApiConstants.diningBookingCreatePayment(widget.booking.id),
        data: {'payment_method': provider},
      );

      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Unable to start payment');
      }

      final data = Map<String, dynamic>.from(response['data'] ?? {});

      if (provider == 'razorpay') {
        _razorpay.open({
          'key': data['key'],
          'amount': data['amount'],
          'currency': data['currency'] ?? 'INR',
          'name': 'FoodFlow Dining',
          'description': 'Dining table booking',
          'order_id': data['order_id'],
          'prefill': {
            'contact': user?.phone ?? '',
            'email': user?.email ?? '',
          },
          'theme': {'color': '#E23744'},
        });
        return;
      }

      if (provider == 'stripe') {
        await _processStripePayment(data);
        return;
      }

      if (provider == 'cashfree') {
        await _processCashfreePayment(data);
        return;
      }

      throw Exception('Unsupported payment gateway: $provider');
    } catch (e) {
      if (!mounted) return;
      setState(() => _isPaying = false);
      _showStatus(e.toString());
    }
  }

  Future<void> _processStripePayment(Map<String, dynamic> paymentData) async {
    try {
      final clientSecret = paymentData['client_secret']?.toString();
      final publishableKey = paymentData['publishable_key']?.toString();
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
          style: ThemeMode.system,
          merchantDisplayName: 'FoodFlow Dining',
          googlePay: const PaymentSheetGooglePay(merchantCountryCode: 'IN'),
          applePay: const PaymentSheetApplePay(merchantCountryCode: 'IN'),
        ),
      );

      await Stripe.instance.presentPaymentSheet();
      final paymentIntentId = clientSecret.split('_secret_')[0];
      await _verifyPayment({
        'payment_method': 'stripe',
        'payment_id': paymentIntentId,
        'stripe_payment_intent_id': paymentIntentId,
      });
    } on StripeException catch (e) {
      if (!mounted) return;
      setState(() => _isPaying = false);
      _showStatus(
        e.error.localizedMessage ?? e.error.message ?? 'Payment cancelled',
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isPaying = false);
      _showStatus('Payment failed: $e');
    }
  }

  Future<void> _processCashfreePayment(Map<String, dynamic> paymentData) async {
    final orderId = paymentData['order_id']?.toString();
    final paymentSessionId = paymentData['payment_session_id']?.toString();
    if (orderId == null ||
        orderId.isEmpty ||
        paymentSessionId == null ||
        paymentSessionId.isEmpty) {
      throw Exception('Cashfree payment session missing from server');
    }

    _pendingCashfreeOrderId = orderId;
    final environment =
        paymentData['environment']?.toString().toLowerCase() == 'sandbox'
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
    await _verifyPayment({
      'payment_method': 'razorpay',
      'payment_id': response.paymentId,
      'razorpay_order_id': response.orderId,
      'razorpay_signature': response.signature,
    });
  }

  void _handleRazorpayError(PaymentFailureResponse response) {
    if (!mounted) return;
    setState(() => _isPaying = false);
    _showStatus(response.message ?? 'Payment cancelled');
  }

  Future<void> _handleCashfreeSuccess(String orderId) async {
    await _verifyPayment({
      'payment_method': 'cashfree',
      'payment_id': _pendingCashfreeOrderId ?? orderId,
    });
  }

  void _handleCashfreeError(CFErrorResponse error, String orderId) {
    if (!mounted) return;
    setState(() => _isPaying = false);
    final message = error.getMessage();
    _showStatus(
      message == null || message.trim().isEmpty ? 'Payment cancelled' : message,
    );
  }

  Future<void> _verifyPayment(Map<String, dynamic> payload) async {
    try {
      final response = await _api.post(
        ApiConstants.diningBookingVerifyPayment(widget.booking.id),
        data: payload,
      );

      if (response['success'] != true) {
        throw Exception(response['message'] ?? 'Payment verification failed');
      }

      final booking = DiningBooking.fromJson(
        Map<String, dynamic>.from(response['data'] ?? {}),
      );

      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => DiningConfirmationScreen(booking: booking),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isPaying = false);
      _showStatus(e.toString());
    }
  }

  String? _effectiveGatewayProvider(User? user) {
    if (!(user?.isPaymentGatewayEnabled ?? true)) return null;
    final provider = user?.paymentGatewayProvider.toLowerCase();
    if (provider == null || provider.isEmpty) return 'razorpay';
    if (!{'razorpay', 'stripe', 'cashfree'}.contains(provider)) return null;
    return provider;
  }

  String _gatewayLabel(User? user) {
    return switch (_effectiveGatewayProvider(user)) {
      'stripe' => 'Stripe',
      'cashfree' => 'Cashfree',
      _ => 'Razorpay',
    };
  }

  String _gatewaySubtitle(User? user) {
    return switch (_effectiveGatewayProvider(user)) {
      'stripe' => 'Secure card payment powered by the admin gateway.',
      'cashfree' => 'UPI, cards and wallets powered by the admin gateway.',
      _ => 'UPI, cards and wallets powered by the admin gateway.',
    };
  }

  void _showStatus(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message.startsWith('Exception: ')
            ? message.substring('Exception: '.length)
            : message),
        backgroundColor: const Color(0xFFE23744),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().currentUser;
    final dateFormat = DateFormat('EEE, d MMM yyyy');
    final bookingTime = DateTime.now().copyWith(
      hour: widget.booking.bookingTime.hour,
      minute: widget.booking.bookingTime.minute,
    );

    final mediaQuery = MediaQuery.of(context);
    return MediaQuery(
      data: mediaQuery.copyWith(textScaler: const TextScaler.linear(1.08)),
      child: Scaffold(
        backgroundColor: accountCanvas,
        appBar: AppBar(
        title: const Text('Dining checkout'),
        backgroundColor: accountCanvas,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
      ),
      bottomNavigationBar: SafeArea(
        top: false,
        minimum: const EdgeInsets.fromLTRB(16, 10, 16, 14),
        child: SizedBox(
          height: 56,
          child: ElevatedButton(
            onPressed: _isPaying ? null : _payNow,
            style: ElevatedButton.styleFrom(
              backgroundColor: FoodFlowTheme.primaryColor,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(18),
              ),
            ),
            child: _isPaying
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      color: Colors.white,
                      strokeWidth: 2.4,
                    ),
                  )
                : Text(
                    'Pay ${formatCurrency(context, widget.booking.bookingCharge)}',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
          ),
        ),
      ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [FoodFlowTheme.primaryColor, FoodFlowTheme.orangeDark],
                ),
                borderRadius: BorderRadius.circular(28),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
                    decoration: BoxDecoration(
                      color: Colors.white.withOpacity(0.16),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: const Text(
                      'PAY TO CONFIRM',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.8,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    widget.restaurantName,
                    style: const TextStyle(
                      fontSize: 26,
                      fontWeight: FontWeight.w900,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Your table request is ready. Complete the cover-charge payment to lock it in.',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.92),
                      height: 1.45,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _buildCard(
              title: 'Booking summary',
              child: Column(
                children: [
                  _SummaryRow(
                    label: 'Booking reference',
                    value: widget.booking.bookingNumber,
                  ),
                  _SummaryRow(
                    label: 'Date',
                    value: dateFormat.format(widget.booking.bookingDate),
                  ),
                  _SummaryRow(
                    label: 'Time',
                    value: DateFormat('hh:mm a').format(bookingTime),
                  ),
                  _SummaryRow(
                    label: 'Guests',
                    value:
                        '${widget.booking.numberOfGuests} ${widget.booking.numberOfGuests == 1 ? 'person' : 'people'}',
                  ),
                  if ((widget.booking.celebrationType ?? '').isNotEmpty)
                    _SummaryRow(
                      label: 'Occasion',
                      value: widget.booking.celebrationType!,
                    ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _buildCard(
              title: 'Payment method',
              child: ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF1EE),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(
                    Icons.account_balance_wallet_outlined,
                    color: Color(0xFFE23744),
                  ),
                ),
                title: Text(
                  'Online payment via ${_gatewayLabel(user)}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(_gatewaySubtitle(user)),
              ),
            ),
            const SizedBox(height: 16),
            _buildCard(
              title: 'Charges',
              child: Column(
                children: [
                  _SummaryRow(
                    label: 'Cover charge',
                    value:
                        formatCurrency(context, widget.booking.bookingCharge),
                  ),
                  _SummaryRow(
                    label: 'To pay now',
                    value:
                        formatCurrency(context, widget.booking.bookingCharge),
                    isEmphasis: true,
                  ),
                ],
              ),
            ),
          ],
          ),
        ),
      ),
    );
  }

  Widget _buildCard({
    required String title,
    required Widget child,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.05),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: FoodFlowTheme.ink,
            ),
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.label,
    required this.value,
    this.isEmphasis = false,
  });

  final String label;
  final String value;
  final bool isEmphasis;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              color: Colors.grey.shade600,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: TextStyle(
                color: isEmphasis ? const Color(0xFFE23744) : FoodFlowTheme.ink,
                fontWeight: isEmphasis ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
