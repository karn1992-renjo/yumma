import 'package:flutter/material.dart';

import '../../services/partner_application_service.dart';

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
  static const Color _text = Color(0xFF111827);
  static const Color _subtext = Color(0xFF6B7280);
  static const Color _line = Color(0xFFE5E7EB);

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
    final saved = widget.applicationNumber ??
        await _service.getSavedApplicationNumber();
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
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to fetch application status: $e')),
      );
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final status = (_statusData?['status']?.toString() ?? 'pending').toLowerCase();
    final isApproved = status == 'approved';
    final isRejected = status == 'rejected';
    final color = isApproved
        ? Colors.green
        : isRejected
            ? Colors.red
            : Theme.of(context).colorScheme.primary;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(title: const Text('Application Status')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: _line),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x12000000),
                  blurRadius: 18,
                  offset: Offset(0, 12),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isApproved
                      ? 'Driver account approved'
                      : isRejected
                          ? 'Application rejected'
                          : 'Application under review',
                  style: const TextStyle(
                    color: _text,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  isApproved
                      ? 'Your delivery partner account is ready. You can now sign in with mobile OTP.'
                      : isRejected
                          ? 'Please review the admin notes below and submit a corrected application if needed.'
                          : 'Your submitted documents and profile details are waiting for admin approval.',
                  style: const TextStyle(
                    color: _subtext,
                    height: 1.45,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    status.toUpperCase(),
                    style: TextStyle(
                      color: color,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: _line),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Track your application',
                  style: TextStyle(
                    color: _text,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _applicationController,
                  decoration: InputDecoration(
                    labelText: 'Application Number',
                    hintText: 'APPXXXXXX',
                    prefixIcon: const Icon(Icons.confirmation_number_outlined),
                    suffixIcon: IconButton(
                      onPressed: _isLoading ? null : _refresh,
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                    filled: true,
                    fillColor: Colors.white,
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: const BorderSide(color: _line),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(18),
                      borderSide: BorderSide(color: color, width: 1.4),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _refresh,
                    child: Text(_isLoading ? 'Checking...' : 'Check Status'),
                  ),
                ),
              ],
            ),
          ),
          if (_statusData != null) ...[
            const SizedBox(height: 18),
            _detailCard(
              title: 'Current status',
              children: [
                _detailRow('Application', _statusData?['application_number'] ?? '-'),
                _detailRow('Partner type', _statusData?['partner_type'] ?? '-'),
                _detailRow('Name', _statusData?['full_name'] ?? '-'),
                _detailRow('Phone', _statusData?['phone'] ?? '-'),
                _detailRow('Email', _statusData?['email'] ?? '-'),
              ],
            ),
            const SizedBox(height: 18),
            _detailCard(
              title: 'Review notes',
              children: [
                Text(
                  _statusData?['status_message']?.toString() ??
                      'Application in progress',
                  style: const TextStyle(
                    color: _text,
                    fontWeight: FontWeight.w600,
                    height: 1.45,
                  ),
                ),
                if ((_statusData?['admin_notes']?.toString() ?? '').isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF7E8),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Text(
                      _statusData?['admin_notes']?.toString() ?? '',
                      style: const TextStyle(
                        color: _text,
                        fontWeight: FontWeight.w500,
                        height: 1.45,
                      ),
                    ),
                  ),
                ],
              ],
            ),
            if (isApproved) ...[
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pushNamedAndRemoveUntil(
                    context,
                    '/login',
                    (_) => false,
                  ),
                  child: const Text('Go To Login'),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _detailCard({
    required String title,
    required List<Widget> children,
  }) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: _text,
              fontSize: 18,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }

  Widget _detailRow(String label, dynamic value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 112,
            child: Text(
              label,
              style: const TextStyle(
                color: _subtext,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value?.toString() ?? '-',
              style: const TextStyle(
                color: _text,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
