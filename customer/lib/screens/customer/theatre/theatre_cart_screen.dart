import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../providers/auth_provider.dart';
import '../../../services/flexible_order_payment_service.dart';
import 'theatre_api_service.dart';
import 'theatre_cart_provider.dart';
import 'theatre_confirmation_screen.dart';
import 'theatre_models.dart';
import 'theatre_theme.dart';

class TheatreCartScreen extends StatefulWidget {
  const TheatreCartScreen({
    super.key,
    required this.cart,
  });

  /// Carries the whole ordering context, including how presence at the
  /// seat is proven: coordinates from the manual flow, or the signature
  /// from a scanned armrest QR. See [TheatreCartProvider.seatToken].
  final TheatreCartProvider cart;

  @override
  State<TheatreCartScreen> createState() => _TheatreCartScreenState();
}

class _TheatreCartScreenState extends State<TheatreCartScreen> {
  String _paymentMethod = 'cod';
  bool _placing = false;

  /// Show times are optional: a screen with nothing scheduled simply never
  /// offers the interval choice, which is how every theatre behaved before
  /// shows existed. So a failed or empty fetch is not an error here.
  List<TheatreShowOption> _shows = const [];
  TheatreShowOption? _show;
  String _timing = 'asap';
  final FlexibleOrderPaymentService _paymentService =
      FlexibleOrderPaymentService();

  @override
  void initState() {
    super.initState();
    widget.cart.addListener(_onCartChanged);
    _loadShows();
  }

  Future<void> _loadShows() async {
    final screenId = widget.cart.screen?.id;
    if (screenId == null) return;

    try {
      final shows = await TheatreApiService.instance.shows(screenId);
      if (!mounted) return;
      setState(() {
        _shows = shows;
        if (shows.length == 1) _show = shows.first;
      });
    } catch (_) {
      // Deliver-now only; nothing to tell the customer about.
    }
  }

  @override
  void dispose() {
    widget.cart.removeListener(_onCartChanged);
    _paymentService.dispose();
    super.dispose();
  }

  void _onCartChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _placeOrder() async {
    final cart = widget.cart;
    if (cart.isEmpty || _placing) return;

    setState(() => _placing = true);
    try {
      final result = _paymentMethod == 'online'
          ? await _placeOnlineOrder(cart)
          : await TheatreApiService.instance.checkout(
              theatreId: cart.theatre!.id,
              screenId: cart.screen!.id,
              seatLabel: cart.seatLabel!,
              lat: cart.lat,
              lng: cart.lng,
              seatToken: cart.seatToken,
              showId: _show?.id,
              deliveryTiming: _timing == 'interval' ? 'interval' : null,
              stalls: cart.toCheckoutStalls(),
              paymentMethod: _paymentMethod,
            );

      if (result == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              backgroundColor: BmsTheme.textDark,
              content: const Text(
                'Payment was not completed.',
                style: TextStyle(color: Colors.white),
              ),
            ),
          );
        }
        return;
      }

      cart.reset();

      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(
          builder: (_) => TheatreConfirmationScreen(result: result),
        ),
        (route) => route.isFirst,
      );
    } on TheatreCheckoutException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: BmsTheme.textDark,
          content: Text(e.message, style: const TextStyle(color: Colors.white)),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: BmsTheme.textDark,
          content:
              Text(e.toString(), style: const TextStyle(color: Colors.white)),
        ),
      );
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  Future<TheatreOrderGroupResult?> _placeOnlineOrder(
    TheatreCartProvider cart,
  ) async {
    final user = context.read<AuthProvider>().currentUser;
    final gateway = user?.paymentGatewayProvider ?? 'razorpay';

    final session = await TheatreApiService.instance.paymentSession(
      theatreId: cart.theatre!.id,
      screenId: cart.screen!.id,
      seatLabel: cart.seatLabel!,
      lat: cart.lat,
      lng: cart.lng,
      seatToken: cart.seatToken,
      showId: _show?.id,
      deliveryTiming: _timing == 'interval' ? 'interval' : null,
      stalls: cart.toCheckoutStalls(),
      gateway: gateway,
    );

    final proof = await _paymentService.payForTheatreCheckout(
      session: session,
      gateway: gateway,
      customerName: user?.name,
      customerEmail: user?.email,
      customerPhone: user?.phone,
    );

    if (proof == null) return null;

    return TheatreApiService.instance.checkout(
      theatreId: cart.theatre!.id,
      screenId: cart.screen!.id,
      seatLabel: cart.seatLabel!,
      lat: cart.lat,
      lng: cart.lng,
      seatToken: cart.seatToken,
      showId: _show?.id,
      deliveryTiming: _timing == 'interval' ? 'interval' : null,
      stalls: cart.toCheckoutStalls(),
      paymentMethod: gateway,
      paymentId: proof['payment_id'],
      razorpayOrderId: proof['razorpay_order_id'],
      razorpaySignature: proof['razorpay_signature'],
      stripePaymentIntentId: proof['stripe_payment_intent_id'],
    );
  }

  /// "Now" vs "at the interval". Only rendered when the screen actually has
  /// a schedule loaded — see [_loadShows].
  Widget _buildTimingCard() {
    final show = _show;
    final intervalUsable = show != null && show.canDeliverAtInterval;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'WHEN SHOULD WE BRING IT?',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: Color(0xFF6B7280),
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 12),

          // Back-to-back screenings overlap around a changeover, so with
          // more than one live show the customer has to say which they are
          // in before an interval time means anything.
          if (_shows.length > 1) ...[
            for (final option in _shows)
              _BmsPaymentTile(
                icon: Icons.movie_rounded,
                value: 'show-${option.id}',
                groupValue: 'show-${_show?.id}',
                title: option.movieTitle,
                subtitle: option.language ?? 'Now playing',
                onChanged: (_) => setState(() {
                  _show = option;
                  // A different show means a different interval, so a
                  // previously chosen interval no longer refers to anything.
                  _timing = 'asap';
                }),
              ),
            const SizedBox(height: 8),
          ],

          _BmsPaymentTile(
            icon: Icons.bolt_rounded,
            value: 'asap',
            groupValue: _timing,
            title: 'Now',
            subtitle: 'As soon as it is ready',
            onChanged: (v) => setState(() => _timing = v),
          ),
          if (intervalUsable)
            _BmsPaymentTile(
              icon: Icons.schedule_rounded,
              value: 'interval',
              groupValue: _timing,
              title: 'At the interval',
              subtitle: 'Made fresh and brought to you around ${show.intervalLabel}',
              onChanged: (v) => setState(() => _timing = v),
            )
          else if (show != null && show.intervalAt != null)
            const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Text(
                'The interval is too close to cook for now.',
                style: TextStyle(fontSize: 11.5, color: Color(0xFF9CA3AF)),
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = widget.cart;

    return Scaffold(
      backgroundColor: BmsTheme.bgLight,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: BmsTheme.textDark,
        elevation: 0.5,
        titleSpacing: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, size: 22),
          color: BmsTheme.textDark,
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Booking & Order Summary',
              style: TextStyle(
                fontSize: 15.5,
                fontWeight: FontWeight.w800,
                color: BmsTheme.textDark,
              ),
            ),
            SizedBox(height: 2),
            Text(
              'In-Seat Cinema Concessions',
              style: TextStyle(
                fontSize: 11.5,
                color: Color(0xFF6B7280),
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: cart.isEmpty
            ? Center(
                child: Container(
                  margin: const EdgeInsets.all(24),
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  child: const Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.shopping_bag_outlined,
                          size: 48, color: Color(0xFF9CA3AF)),
                      SizedBox(height: 12),
                      Text(
                        'Your cart is empty',
                        style: TextStyle(
                          color: Color(0xFF111827),
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      SizedBox(height: 6),
                      Text(
                        'Add popcorn, drinks or snacks from concession stalls to proceed.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: Color(0xFF6B7280),
                          fontSize: 13,
                        ),
                      ),
                    ],
                  ),
                ),
              )
            : ListView(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
                children: [
                  // BookMyShow Style Perforated Cinema Ticket Card
                  BmsTicketCard(
                    backgroundColor: Colors.white,
                    borderColor: const Color(0xFFE5E7EB),
                    notchColor: BmsTheme.bgLight,
                    header: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 36,
                                height: 36,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF1F3),
                                  borderRadius: BorderRadius.circular(8),
                                  border: Border.all(
                                    color: const Color(0xFFFFD4DC),
                                  ),
                                ),
                                child: const Center(
                                  child: Icon(
                                    Icons.theaters_rounded,
                                    color: BmsTheme.primary,
                                    size: 18,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      cart.theatre?.name ?? 'Cinema Hall',
                                      style: const TextStyle(
                                        color: Color(0xFF111827),
                                        fontSize: 15,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      '${cart.theatre?.city ?? ''} · Live In-Seat Delivery',
                                      style: const TextStyle(
                                        color: Color(0xFF6B7280),
                                        fontSize: 11.5,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          // Highlighted Audi & Seat Banner
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 8,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFFBEB),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(
                                color: const Color(0xFFFDE68A),
                              ),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.event_seat_rounded,
                                  color: Color(0xFFD97706),
                                  size: 18,
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  cart.screen?.name.toUpperCase() ?? 'AUDI',
                                  style: const TextStyle(
                                    color: Color(0xFFB45309),
                                    fontSize: 13,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Container(
                                  width: 1,
                                  height: 14,
                                  color: const Color(0xFFFCD34D),
                                ),
                                const SizedBox(width: 8),
                                Text(
                                  'SEAT ${cart.seatLabel ?? ''}',
                                  style: const TextStyle(
                                    color: Color(0xFF92400E),
                                    fontSize: 14,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    body: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          ...cart.stallRestaurantIds.map((restaurantId) {
                            final restaurant = cart.stallInfo(restaurantId)!;
                            final lines = cart.linesFor(restaurantId);
                            final stallTotal = lines.fold<double>(
                              0,
                              (s, l) => s + l.lineTotal,
                            );

                            return Container(
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF9FAFB),
                                borderRadius: BorderRadius.circular(10),
                                border:
                                    Border.all(color: const Color(0xFFE5E7EB)),
                              ),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      const Icon(
                                        Icons.storefront_rounded,
                                        size: 15,
                                        color: BmsTheme.primary,
                                      ),
                                      const SizedBox(width: 6),
                                      Expanded(
                                        child: Text(
                                          restaurant.name,
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w800,
                                            fontSize: 13,
                                            color: Color(0xFF111827),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  ...lines.map((line) => Padding(
                                        padding: const EdgeInsets.symmetric(
                                          vertical: 3,
                                        ),
                                        child: Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                '${line.quantity} × ${line.name}',
                                                style: const TextStyle(
                                                  color: Color(0xFF4B5563),
                                                  fontSize: 12.5,
                                                ),
                                              ),
                                            ),
                                            Text(
                                              '₹${line.lineTotal.toStringAsFixed(0)}',
                                              style: const TextStyle(
                                                color: Color(0xFF111827),
                                                fontWeight: FontWeight.w700,
                                                fontSize: 13,
                                              ),
                                            ),
                                          ],
                                        ),
                                      )),
                                  const Divider(
                                    color: Color(0xFFE5E7EB),
                                    height: 14,
                                  ),
                                  Row(
                                    mainAxisAlignment:
                                        MainAxisAlignment.spaceBetween,
                                    children: [
                                      const Text(
                                        'Stall Subtotal',
                                        style: TextStyle(
                                          color: Color(0xFF6B7280),
                                          fontSize: 11.5,
                                        ),
                                      ),
                                      Text(
                                        '₹${stallTotal.toStringAsFixed(0)}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          color: Color(0xFF111827),
                                          fontSize: 13,
                                        ),
                                      ),
                                    ],
                                  ),
                                ],
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),

                  // Bill Breakdown Card
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.04),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'BILL DETAILS',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF6B7280),
                            letterSpacing: 0.8,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'Items Total',
                              style: TextStyle(
                                color: Color(0xFF4B5563),
                                fontSize: 13,
                              ),
                            ),
                            Text(
                              '₹${cart.total.toStringAsFixed(0)}',
                              style: const TextStyle(
                                color: Color(0xFF111827),
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        const Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'In-Seat Runner Convenience',
                              style: TextStyle(
                                color: Color(0xFF4B5563),
                                fontSize: 13,
                              ),
                            ),
                            Text(
                              'FREE',
                              style: TextStyle(
                                color: Color(0xFF059669),
                                fontWeight: FontWeight.w800,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                        const Divider(
                          color: Color(0xFFF3F4F6),
                          height: 20,
                        ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text(
                              'To Pay',
                              style: TextStyle(
                                color: Color(0xFF111827),
                                fontWeight: FontWeight.w900,
                                fontSize: 15,
                              ),
                            ),
                            Text(
                              '₹${cart.total.toStringAsFixed(0)}',
                              style: const TextStyle(
                                color: BmsTheme.primary,
                                fontWeight: FontWeight.w900,
                                fontSize: 18,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        const Text(
                          'Taxes and any platform fee are calculated per stall at checkout.',
                          style: TextStyle(
                            fontSize: 11,
                            color: Color(0xFF9CA3AF),
                          ),
                        ),
                      ],
                    ),
                  ),

                  if (_shows.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    _buildTimingCard(),
                  ],

                  const SizedBox(height: 16),

                  // Payment Options Card
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: const Color(0xFFE5E7EB)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withOpacity(0.04),
                          blurRadius: 8,
                          offset: const Offset(0, 2),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'PAYMENT METHOD',
                          style: TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF6B7280),
                            letterSpacing: 0.8,
                          ),
                        ),
                        const SizedBox(height: 12),
                        if (context
                                .watch<AuthProvider>()
                                .currentUser
                                ?.isPaymentGatewayEnabled ??
                            false)
                          _BmsPaymentTile(
                            icon: Icons.credit_card_rounded,
                            title: 'Pay Online',
                            subtitle: 'UPI, Credit/Debit Cards, NetBanking',
                            value: 'online',
                            groupValue: _paymentMethod,
                            onChanged: (v) =>
                                setState(() => _paymentMethod = v),
                          ),
                        _BmsPaymentTile(
                          icon: Icons.account_balance_wallet_rounded,
                          title: 'In-App Wallet',
                          subtitle: 'Instant deduction from your Swaad balance',
                          value: 'wallet',
                          groupValue: _paymentMethod,
                          onChanged: (v) => setState(() => _paymentMethod = v),
                        ),
                        _BmsPaymentTile(
                          icon: Icons.handshake_rounded,
                          title: 'Cash on Delivery',
                          subtitle: 'Pay the runner directly at your seat',
                          value: 'cod',
                          groupValue: _paymentMethod,
                          onChanged: (v) => setState(() => _paymentMethod = v),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
      ),
      bottomNavigationBar: cart.isEmpty
          ? null
          : Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
              decoration: BoxDecoration(
                color: Colors.white,
                border: const Border(
                  top: BorderSide(color: Color(0xFFE5E7EB)),
                ),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.06),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: SafeArea(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: _placing ? null : _placeOrder,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: BmsTheme.primary,
                          foregroundColor: Colors.white,
                          disabledBackgroundColor: const Color(0xFFF3F4F6),
                          disabledForegroundColor: const Color(0xFF9CA3AF),
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 3,
                          shadowColor: BmsTheme.primary.withOpacity(0.35),
                        ),
                        child: _placing
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                  color: Colors.white,
                                ),
                              )
                            : Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(
                                    'Pay ₹${cart.total.toStringAsFixed(0)}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w900,
                                      fontSize: 15,
                                      letterSpacing: 0.3,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  const Icon(
                                    Icons.arrow_forward_rounded,
                                    size: 16,
                                  ),
                                ],
                              ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.lock_outline_rounded,
                          size: 12,
                          color: Color(0xFF9CA3AF),
                        ),
                        SizedBox(width: 4),
                        Text(
                          '100% Safe & Secure Cinema Checkout',
                          style: TextStyle(
                            fontSize: 10.5,
                            color: Color(0xFF9CA3AF),
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}

class _BmsPaymentTile extends StatelessWidget {
  const _BmsPaymentTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.value,
    required this.groupValue,
    required this.onChanged,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String value;
  final String groupValue;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final isSelected = value == groupValue;

    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: isSelected ? const Color(0xFFFFF1F3) : const Color(0xFFF9FAFB),
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => onChanged(value),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(
                color: isSelected
                    ? BmsTheme.primary
                    : const Color(0xFFE5E7EB),
                width: isSelected ? 1.5 : 1.0,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  icon,
                  color: isSelected ? BmsTheme.primary : const Color(0xFF6B7280),
                  size: 20,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 13.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF111827),
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 11,
                          color: Color(0xFF6B7280),
                        ),
                      ),
                    ],
                  ),
                ),
                Radio<String>(
                  value: value,
                  groupValue: groupValue,
                  onChanged: (v) => onChanged(v!),
                  activeColor: BmsTheme.primary,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
