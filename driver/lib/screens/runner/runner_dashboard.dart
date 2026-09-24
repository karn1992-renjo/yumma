import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';
import '../../services/runner_api_service.dart';
import '../../theme/foodflow_theme.dart';
import '../auth/login_screen.dart';

/// swaad-only: home screen for a movie-theatre runner. Deliberately a new,
/// self-contained screen rather than a branch bolted onto DriverDashboard --
/// that file is a ~2000-line, delivery-specific shell (GPS toggle, earnings,
/// gig booking UI) that assumes a road driver throughout; a runner's job is
/// simpler (browse a per-theatre queue, claim, hand off) and reuses only the
/// login/token/theming plumbing underneath, not that screen's widget tree.
class RunnerDashboard extends StatefulWidget {
  const RunnerDashboard({super.key});

  @override
  State<RunnerDashboard> createState() => _RunnerDashboardState();
}

class _RunnerDashboardState extends State<RunnerDashboard> {
  final _service = RunnerApiService.instance;
  late Future<Map<String, dynamic>> _future;
  bool _busyOrderId(int? id) => _busyId == id;
  int? _busyId;

  @override
  void initState() {
    super.initState();
    _future = _service.fetchQueue();
  }

  Future<void> _refresh() async {
    final future = _service.fetchQueue();
    setState(() => _future = future);
    await future;
  }

  Future<void> _claim(Map<String, dynamic> order) async {
    final id = (order['id'] as num).toInt();
    setState(() => _busyId = id);
    try {
      await _service.claim(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Claimed order #${order['order_number']}')),
      );
      await _refresh();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _advance(Map<String, dynamic> order, String nextStatus) async {
    final id = (order['id'] as num).toInt();
    setState(() => _busyId = id);
    try {
      await _service.advanceStatus(id, nextStatus);
      await _refresh();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _completeDelivery(Map<String, dynamic> order) async {
    final id = (order['id'] as num).toInt();
    final isCod = order['payment_method']?.toString() == 'cod' &&
        order['payment_status']?.toString() != 'success';
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _OtpDialog(isCod: isCod),
    );
    if (result == null) return;

    setState(() => _busyId = id);
    try {
      await _service.completeDelivery(
        orderId: id,
        otp: result['otp'] as String,
        paymentMode: isCod ? 'cash' : 'online',
        cashCollected: result['cashCollected'] as bool? ?? false,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Order #${order['order_number']} delivered.')),
      );
      await _refresh();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _logout() async {
    await context.read<AuthProvider>().logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: FoodFlowTheme.canvas,
      appBar: AppBar(
        title: const Text('Runner Queue'),
        backgroundColor: foodflow.surfaceColor,
        foregroundColor: FoodFlowTheme.ink,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded),
            onPressed: _logout,
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: FutureBuilder<Map<String, dynamic>>(
            future: _future,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return ListView(
                  children: [
                    const SizedBox(height: 80),
                    FoodFlowTheme.emptyState(
                      icon: Icons.error_outline_rounded,
                      title: 'Could not load the queue',
                      subtitle: snapshot.error.toString(),
                    ),
                  ],
                );
              }

              final claimable = snapshot.data?['claimable'] as List? ?? [];
              final mine = snapshot.data?['mine'] as List? ?? [];

              if (claimable.isEmpty && mine.isEmpty) {
                return ListView(
                  children: [
                    const SizedBox(height: 80),
                    FoodFlowTheme.emptyState(
                      icon: Icons.theaters_rounded,
                      title: 'No orders right now',
                      subtitle: 'Pull to refresh when a stall marks an order ready.',
                    ),
                  ],
                );
              }

              return ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (mine.isNotEmpty) ...[
                    FoodFlowTheme.sectionTitle('My Deliveries'),
                    ...mine.map((o) => _OrderCard(
                          order: Map<String, dynamic>.from(o as Map),
                          busy: _busyOrderId((o['id'] as num?)?.toInt()),
                          onAdvance: _advance,
                          onComplete: _completeDelivery,
                        )),
                  ],
                  if (claimable.isNotEmpty) ...[
                    FoodFlowTheme.sectionTitle('Ready to Claim'),
                    ...claimable.map((o) => _ClaimableCard(
                          order: Map<String, dynamic>.from(o as Map),
                          busy: _busyOrderId((o['id'] as num?)?.toInt()),
                          onClaim: _claim,
                        )),
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}

class _ClaimableCard extends StatelessWidget {
  const _ClaimableCard({
    required this.order,
    required this.busy,
    required this.onClaim,
  });

  final Map<String, dynamic> order;
  final bool busy;
  final void Function(Map<String, dynamic>) onClaim;

  @override
  Widget build(BuildContext context) {
    final restaurant = Map<String, dynamic>.from(order['restaurant'] as Map? ?? {});
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: FoodFlowTheme.softSurface(radius: 14),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  restaurant['name']?.toString() ?? '',
                  style: TextStyle(fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
                ),
                const SizedBox(height: 3),
                Row(
                  children: [
                    Icon(Icons.event_seat_rounded, size: 14, color: FoodFlowTheme.muted),
                    const SizedBox(width: 4),
                    Text(
                      'Seat ${order['seat_label'] ?? '-'}',
                      style: TextStyle(fontSize: 12, color: FoodFlowTheme.muted),
                    ),
                  ],
                ),
              ],
            ),
          ),
          ElevatedButton(
            onPressed: busy ? null : () => onClaim(order),
            style: ElevatedButton.styleFrom(
              backgroundColor: FoodFlowTheme.orange,
              foregroundColor: Colors.white,
            ),
            child: busy
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Text('Claim'),
          ),
        ],
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({
    required this.order,
    required this.busy,
    required this.onAdvance,
    required this.onComplete,
  });

  final Map<String, dynamic> order;
  final bool busy;
  final void Function(Map<String, dynamic>, String) onAdvance;
  final void Function(Map<String, dynamic>) onComplete;

  @override
  Widget build(BuildContext context) {
    final restaurant = Map<String, dynamic>.from(order['restaurant'] as Map? ?? {});
    final status = order['status']?.toString() ?? '';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: FoodFlowTheme.softSurface(radius: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  restaurant['name']?.toString() ?? '',
                  style: TextStyle(fontWeight: FontWeight.w800, color: FoodFlowTheme.ink),
                ),
              ),
              _StatusChip(status: status),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              Icon(Icons.event_seat_rounded, size: 14, color: FoodFlowTheme.muted),
              const SizedBox(width: 4),
              Text(
                'Seat ${order['seat_label'] ?? '-'} · #${order['order_number']}',
                style: TextStyle(fontSize: 12, color: FoodFlowTheme.muted),
              ),
            ],
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: _actionButton(),
          ),
        ],
      ),
    );
  }

  Widget _actionButton() {
    if (busy) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 10),
        child: Center(
          child: SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
      );
    }

    switch (order['status']?.toString()) {
      case 'ready_for_pickup':
        return ElevatedButton(
          onPressed: () => onAdvance(order, 'picked_up'),
          style: _buttonStyle(),
          child: const Text('Picked Up'),
        );
      case 'picked_up':
      case 'reached_pickup':
        return ElevatedButton(
          onPressed: () => onAdvance(order, 'on_the_way'),
          style: _buttonStyle(),
          child: const Text('Heading to Seat'),
        );
      case 'on_the_way':
        return ElevatedButton(
          onPressed: () => onComplete(order),
          style: _buttonStyle(color: FoodFlowTheme.success),
          child: const Text('Complete Delivery'),
        );
      default:
        return const SizedBox.shrink();
    }
  }

  ButtonStyle _buttonStyle({Color? color}) => ElevatedButton.styleFrom(
        backgroundColor: color ?? FoodFlowTheme.orange,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(vertical: 12),
      );
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  String get _label {
    switch (status) {
      case 'ready_for_pickup':
        return 'Ready';
      case 'picked_up':
        return 'Picked Up';
      case 'reached_pickup':
        return 'At Stall';
      case 'on_the_way':
        return 'Heading Over';
      case 'delivered':
        return 'Delivered';
      default:
        return status;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: FoodFlowTheme.orange.withOpacity(0.12),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        _label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: FoodFlowTheme.orange,
        ),
      ),
    );
  }
}

class _OtpDialog extends StatefulWidget {
  const _OtpDialog({required this.isCod});

  final bool isCod;

  @override
  State<_OtpDialog> createState() => _OtpDialogState();
}

class _OtpDialogState extends State<_OtpDialog> {
  final _controller = TextEditingController();
  bool _cashCollected = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Confirm Hand-off'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Ask the customer for the 4-digit code shown in their app.'),
          const SizedBox(height: 12),
          TextField(
            controller: _controller,
            keyboardType: TextInputType.number,
            maxLength: 4,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(
              labelText: 'OTP',
              counterText: '',
              border: OutlineInputBorder(),
            ),
          ),
          if (widget.isCod)
            CheckboxListTile(
              value: _cashCollected,
              onChanged: (v) => setState(() => _cashCollected = v ?? false),
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: const Text('Cash collected from customer'),
            ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: _controller.text.trim().length == 4 &&
                  (!widget.isCod || _cashCollected)
              ? () => Navigator.of(context).pop({
                    'otp': _controller.text.trim(),
                    'cashCollected': _cashCollected,
                  })
              : null,
          child: const Text('Confirm'),
        ),
      ],
    );
  }
}
