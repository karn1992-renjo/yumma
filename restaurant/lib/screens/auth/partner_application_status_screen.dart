import 'package:flutter/material.dart';

import '../../services/partner_application_service.dart';
import '../../theme/foodflow_theme.dart';

class PartnerApplicationStatusScreen extends StatefulWidget {
  const PartnerApplicationStatusScreen({
    super.key,
    this.applicationNumber,
  });

  final String? applicationNumber;

  @override
  State<PartnerApplicationStatusScreen> createState() =>
      _PartnerApplicationStatusScreenState();
}

class _PartnerApplicationStatusScreenState
    extends State<PartnerApplicationStatusScreen> {
  static const _green = Color(0xFF22C55E);
  static const _ink = Color(0xFF111827);
  static const _muted = Color(0xFF6B7280);
  static const _canvas = Color(0xFFF8F9FB);

  Color get _brand => FoodFlowTheme.orange;
  Color get _brandSoft => FoodFlowTheme.orange.withOpacity(0.08);

  final TextEditingController _applicationController = TextEditingController();
  final PartnerApplicationService _service = PartnerApplicationService.instance;

  Map<String, dynamic>? _statusData;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _applicationController.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final saved =
        widget.applicationNumber ?? await _service.getSavedApplicationNumber();
    if (saved == null || saved.isEmpty) return;
    _applicationController.text = saved;
    await _refresh();
  }

  Future<void> _refresh() async {
    final applicationNumber = _applicationController.text.trim();
    if (applicationNumber.isEmpty) return;

    setState(() => _isLoading = true);
    try {
      final response = await _service.fetchStatus(applicationNumber);
      if (!mounted) return;
      setState(() => _statusData = Map<String, dynamic>.from(response['data']));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to fetch application status: $error')),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  int _currentStep(String status) {
    switch (status) {
      case 'approved':
        return 3;
      case 'rejected':
        return 2;
      default:
        return 1;
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = _statusData?['status']?.toString() ?? 'pending';
    final onboardingMeta = _statusData?['onboarding_meta'];

    return Scaffold(
      backgroundColor: FoodFlowTheme.orange.withOpacity(0.04),
      appBar: AppBar(
        backgroundColor: FoodFlowTheme.orange.withOpacity(0.04),
        elevation: 0,
        title: const Text(
          'Application Status',
          style: TextStyle(fontWeight: FontWeight.w900, color: _ink),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          _hero(status),
          const SizedBox(height: 18),
          _lookupCard(),
          const SizedBox(height: 18),
          if (_statusData != null) ...[
            _statusSummary(status),
            const SizedBox(height: 18),
            _timelineCard(status),
            const SizedBox(height: 18),
            _detailCard(
              title: 'Application snapshot',
              children: [
                _kv('Application number',
                    _statusData?['application_number']?.toString() ?? '-'),
                _kv('Business name',
                    _statusData?['business_name']?.toString() ?? '-'),
                _kv('Primary email', _statusData?['email']?.toString() ?? '-'),
                _kv('Mobile', _statusData?['phone']?.toString() ?? '-'),
                _kv(
                    'Delivery zone',
                    _statusData?['delivery_area']?['name']?.toString() ??
                        onboardingMeta?['zone_name']?.toString() ??
                        '-'),
                _kv('Menu summary',
                    onboardingMeta?['menu_summary']?.toString() ?? '-'),
              ],
            ),
            const SizedBox(height: 18),
            _detailCard(
              title: 'Verification board',
              children: [
                _statusPill(
                  'FSSAI & GST verification',
                  status == 'approved' ? 'Verified' : 'Under review',
                  status == 'approved' ? _green : _brand,
                ),
                _statusPill(
                  'Bank & payout check',
                  status == 'approved' ? 'Ready' : 'Queued',
                  status == 'approved' ? _green : _brand,
                ),
                _statusPill(
                  'Menu moderation',
                  status == 'rejected'
                      ? 'Needs correction'
                      : status == 'approved'
                          ? 'Cleared'
                          : 'Pending',
                  status == 'rejected'
                      ? FoodFlowTheme.danger
                      : status == 'approved'
                          ? _green
                          : _brand,
                ),
                _statusPill(
                  'Delivery zone review',
                  status == 'approved' ? 'Mapped' : 'Checking',
                  status == 'approved' ? _green : _brand,
                ),
              ],
            ),
            const SizedBox(height: 18),
            if ((_statusData?['admin_notes'] ?? '')
                .toString()
                .trim()
                .isNotEmpty)
              _detailCard(
                title:
                    status == 'rejected' ? 'Correction notes' : 'Admin notes',
                children: [
                  Text(
                    _statusData?['admin_notes']?.toString() ?? '',
                    style: const TextStyle(
                      color: _ink,
                      height: 1.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            if (status == 'approved') ...[
              const SizedBox(height: 18),
              _approvedCard(),
            ],
          ],
        ],
      ),
    );
  }

  Widget _hero(String status) {
    final approved = status == 'approved';
    final rejected = status == 'rejected';
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: approved
              ? const [Color(0xFF16A34A), Color(0xFF22C55E)]
              : rejected
                  ? const [Color(0xFFDC2626), Color(0xFFFB7185)]
                  : [FoodFlowTheme.orange, FoodFlowTheme.orangeDark],
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: (approved
                    ? _green
                    : rejected
                        ? FoodFlowTheme.danger
                        : _brand)
                .withOpacity(0.18),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.18),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(
                  approved
                      ? Icons.celebration_rounded
                      : rejected
                          ? Icons.error_outline_rounded
                          : Icons.hourglass_top_rounded,
                  color: Colors.white,
                  size: 25,
                ),
              ),
              const Spacer(),
              _floatingBadge(
                approved
                    ? 'Approved'
                    : rejected
                        ? 'Needs attention'
                        : 'Under review',
              ),
            ],
          ),
          const SizedBox(height: 20),
          Text(
            approved
                ? 'Congratulations! Your restaurant is now live'
                : rejected
                    ? 'Your application needs corrections'
                    : 'Your restaurant is under review',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w900,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 10),
          Text(
            approved
                ? 'Merchant setup is complete. You can now sign in with mobile OTP and start managing orders.'
                : rejected
                    ? 'Review the admin notes below, update the required details, and submit a corrected application.'
                    : 'We are validating documents, mapping delivery coverage, and checking your merchant profile before activation.',
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w600,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }

  Widget _lookupCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.035),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Track live approval',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: _ink,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Enter your application number to refresh verification, moderation, and approval updates.',
            style: TextStyle(
              color: _muted,
              fontWeight: FontWeight.w600,
              height: 1.45,
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _applicationController,
            decoration: InputDecoration(
              labelText: 'Application number',
              hintText: 'APPXXXXXX',
              prefixIcon: Icon(
                Icons.confirmation_number_outlined,
                color: _brand,
              ),
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide.none,
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: const BorderSide(color: FoodFlowTheme.line),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(14),
                borderSide: BorderSide(color: _brand, width: 1.4),
              ),
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: FoodFlowTheme.brandGradient,
                borderRadius: BorderRadius.circular(14),
              ),
              child: ElevatedButton(
                onPressed: _isLoading ? null : _refresh,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  shadowColor: Colors.transparent,
                  minimumSize: const Size.fromHeight(50),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
                child: Text(
                  _isLoading ? 'Refreshing...' : 'Refresh Status',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusSummary(String status) {
    final approved = status == 'approved';
    final rejected = status == 'rejected';
    final message = _statusData?['status_message']?.toString() ?? '-';
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _floatingBadge(
                approved
                    ? 'Approved'
                    : rejected
                        ? 'Rejected'
                        : 'Pending',
                dark: true,
              ),
              const Spacer(),
              Text(
                _statusData?['created_at']?.toString().split('T').first ?? '',
                style: const TextStyle(
                  color: _muted,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            message,
            style: const TextStyle(
              color: _ink,
              fontWeight: FontWeight.w800,
              fontSize: 18,
              height: 1.4,
            ),
          ),
        ],
      ),
    );
  }

  Widget _timelineCard(String status) {
    final steps = [
      ('Application submitted', true),
      ('Verification in progress', _currentStep(status) >= 1),
      ('Admin review', _currentStep(status) >= 2),
      ('Restaurant approved', _currentStep(status) >= 3),
    ];

    return _detailCard(
      title: 'Approval timeline',
      children: [
        for (var index = 0; index < steps.length; index++)
          Padding(
            padding:
                EdgeInsets.only(bottom: index == steps.length - 1 ? 0 : 16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Column(
                  children: [
                    Container(
                      width: 24,
                      height: 24,
                      decoration: BoxDecoration(
                        color:
                            steps[index].$2 ? _green : const Color(0xFFE5E7EB),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        steps[index].$2 ? Icons.check : Icons.more_horiz,
                        color: Colors.white,
                        size: 14,
                      ),
                    ),
                    if (index != steps.length - 1)
                      Container(
                        width: 2,
                        height: 34,
                        color: steps[index].$2
                            ? const Color(0xFFBBF7D0)
                            : const Color(0xFFE5E7EB),
                      ),
                  ],
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      steps[index].$1,
                      style: TextStyle(
                        color: steps[index].$2 ? _ink : _muted,
                        fontWeight:
                            steps[index].$2 ? FontWeight.w800 : FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }

  Widget _detailCard({
    required String title,
    required List<Widget> children,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: FoodFlowTheme.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: _ink,
              fontWeight: FontWeight.w900,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 16),
          ...children,
        ],
      ),
    );
  }

  Widget _approvedCard() {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _green.withOpacity(0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _green.withOpacity(0.20)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Ready to launch',
            style: TextStyle(
              color: _ink,
              fontWeight: FontWeight.w900,
              fontSize: 22,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Use mobile OTP sign in to enter the restaurant dashboard, upload your final menu, and start receiving orders.',
            style: TextStyle(
              color: _muted,
              fontWeight: FontWeight.w600,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => Navigator.pushNamedAndRemoveUntil(
                context,
                '/login',
                (_) => false,
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: _green,
                minimumSize: const Size.fromHeight(50),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                ),
              ),
              child: const Text(
                'Go To Dashboard',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _kv(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 128,
            child: Text(
              label,
              style: const TextStyle(
                color: _muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.isEmpty ? '-' : value,
              style: const TextStyle(
                color: _ink,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusPill(String title, String value, Color color) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: color.withOpacity(0.14)),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: _ink,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              value,
              style: TextStyle(
                color: color,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _floatingBadge(String label, {bool dark = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: dark ? _brandSoft : Colors.white.withOpacity(0.18),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: dark ? _brand : Colors.white,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
